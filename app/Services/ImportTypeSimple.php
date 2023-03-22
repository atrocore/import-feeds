<?php
/*
 * Import Feeds
 * Free Extension
 * Copyright (c) AtroCore UG (haftungsbeschränkt).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Import\Services;

use Espo\Core\EventManager\Event;
use Espo\Core\EventManager\Manager;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Services\Base;
use Espo\Core\Utils\Metadata;
use Espo\ORM\Entity;
use Espo\Services\QueueManagerBase;
use Import\Entities\ImportFeed;
use Import\Exceptions\IgnoreAttribute;
use Treo\Core\Exceptions\NotModified;

class ImportTypeSimple extends QueueManagerBase
{
    public const CSV_LIMIT = 500;

    private array $restore = [];
    private array $updatedPav = [];
    private array $deletedPav = [];
    private bool $lastIteration = false;

    protected array $fileParsers = [];
    protected array $entities = [];
    protected array $services = [];

    public function prepareJobData(ImportFeed $feed, string $attachmentId): array
    {
        if (empty($attachmentId) || empty($file = $this->getEntityById('Attachment', $attachmentId))) {
            throw new NotFound($this->translate('noSuchFile', 'exceptions', 'ImportFeed'));
        }

        $result = [
            "name"             => $feed->get('name'),
            "offset"           => $feed->isFileHeaderRow() ? 1 : 0,
            "limit"            => \PHP_INT_MAX,
            "fileFormat"       => $feed->getFeedField('format'),
            "delimiter"        => $feed->getDelimiter(),
            "enclosure"        => $feed->getEnclosure(),
            "isFileHeaderRow"  => $feed->isFileHeaderRow(),
            "adapter"          => $feed->getFeedField('adapter'),
            "action"           => $feed->get('fileDataAction'),
            "attachmentId"     => $attachmentId,
            "data"             => $feed->getConfiguratorData(),
            "repeatProcessing" => $feed->get("repeatProcessing"),
            "sheet"            => $feed->get("sheet"),
        ];

        return $this
            ->getEventManager()
            ->dispatch(new Event(['result' => $result, 'importFeed' => $feed, 'attachment' => $file]), 'prepareJobData')
            ->getArgument('result');
    }

    public function run(array $data = []): bool
    {
        $importJob = $this->getEntityById('ImportJob', $data['data']['importJobId']);

        $GLOBALS['importJobId'] = $importJob->get('id');
        $GLOBALS['skipAssignmentNotifications'] = true;
        $GLOBALS['skipHooks'] = true;

        $scope = $data['data']['entity'];
        $entityService = $this->getService($scope);

        $ids = [];

        $processedIds = [];

        // prepare file row
        $fileRow = empty($data['offset']) ? 0 : (int)$data['offset'];

        while (!empty($inputData = $this->getInputData($data))) {
            while (!empty($inputData)) {
                $row = array_shift($inputData);

                // increment file row number
                $fileRow++;

                try {
                    // prepare where for finding existed entity
                    $where = $this->prepareWhere($entityService->getEntityType(), $data['data'], $row);

                    $id = null;
                    if (!empty($entity = $this->findExistEntity($entityService->getEntityType(), $data['data'], $where))) {
                        $id = $entity->get('id');
                        if (self::isDeleteAction($data['action'])) {
                            $ids[] = $id;
                        }
                    }

                    /**
                     * Check if such row is already processed
                     */
                    if (!empty($id) && in_array($id, $processedIds)) {
                        switch ($data['repeatProcessing']) {
                            case 'repeat':
                                // clear memory
                                $processedIds = [];
                                break;
                            case 'skip':
                                continue 2;
                                break;
                            default:
                                throw new BadRequest($this->translate('alreadyProceeded', 'exceptions', 'ImportFeed'));
                        }
                    }
                } catch (\Throwable $e) {
                    $this->log($scope, $importJob->get('id'), 'error', (string)$fileRow, $e->getMessage());
                    if ($this->getConfig()->get('tracingImportErrors')) {
                        $GLOBALS['log']->error("Import Job '{$importJob->get('id')}' Failed. Message: '{$e->getMessage()}'. Trace: '{$e->getTraceAsString()}'.");
                    }

                    continue 1;
                }

                if (in_array($data['action'], ['create', 'create_delete']) && !empty($entity)) {
                    continue 1;
                }

                if (in_array($data['action'], ['update', 'update_delete']) && empty($entity)) {
                    continue 1;
                }

                if ($data['action'] == 'delete') {
                    continue 1;
                }

                if (!$this->getEntityManager()->getPDO()->inTransaction()) {
                    $this->getEntityManager()->getPDO()->beginTransaction();
                }

                try {
                    $input = new \stdClass();
                    $input->_importJobData = $data;
                    $input->_importInputDataRow = $row;

                    $restore = new \stdClass();

                    $this->sortConfigurator($data);

                    $attributes = [];
                    foreach ($data['data']['configuration'] as $item) {
                        if ($item['type'] == 'Attribute') {
                            $attributes[] = ['item' => $item, 'row' => $row];
                            continue 1;
                        }

                        $type = $this->getMetadata()->get(['entityDefs', $item['entity'], 'fields', $item['name'], 'type'], 'varchar');
                        if ($item['entity'] === 'ProductAttributeValue' && $item['name'] === 'value') {
                            if (property_exists($input, 'attributeType')) {
                                $type = $input->attributeType;
                            } elseif (!empty($entity) && !empty($attribute = $this->getEntityById('Attribute', $entity->get('attributeId')))) {
                                $type = $attribute->get('type');
                            }
                        }

                        try {
                            $this->getService('ImportConfiguratorItem')->getFieldConverter($type)->convert($input, $item, $row);
                        } catch (BadRequest $e) {
                            $message = '';
                            if (array_key_exists('column', $item)) {
                                $message = $this->translate('convertValidationPrefix', 'exceptions', 'ImportFeed');
                                $values = [];
                                foreach ($item['column'] as $column) {
                                    $values[] = array_key_exists($column, $row) ? $row[$column] : '';
                                }
                                $message = str_replace(['{{value}}', '{{column}}'], [implode(', ', $values), implode(', ', $item['column'])], $message);
                            }
                            throw new BadRequest($message . lcfirst($e->getMessage()));
                        }
                        if (!empty($entity)) {
                            $this->getService('ImportConfiguratorItem')->getFieldConverter($type)->prepareValue($restore, $entity, $item);
                        }
                    }

                    if (empty($id)) {
                        $updatedEntity = $entityService->createEntity($input);
                        $processedIds[] = $updatedEntity->get('id');

                        if (self::isDeleteAction($data['action'])) {
                            $ids[] = $updatedEntity->get('id');
                        }

                        $this->importAttributes($attributes, $updatedEntity);
                        $this->saveRestoreRow('created', $scope, $updatedEntity->get('id'));
                    } else {
                        $notModified = true;
                        try {
                            $updatedEntity = $entityService->updateEntity($id, $input);
                            $processedIds[] = $updatedEntity->get('id');
                            $this->saveRestoreRow('updated', $scope, [$id => $restore]);
                            $notModified = false;
                        } catch (NotModified $e) {
                        }

                        if ($this->importAttributes($attributes, $entity)) {
                            $notModified = false;
                            $updatedEntity = $entity;
                        }

                        if ($notModified) {
                            throw new NotModified();
                        }
                    }

                    if ($this->getEntityManager()->getPDO()->inTransaction()) {
                        $this->getEntityManager()->getPDO()->commit();
                    }
                } catch (\Throwable $e) {
                    if ($this->getEntityManager()->getPDO()->inTransaction()) {
                        $this->getEntityManager()->getPDO()->rollBack();
                    }

                    $message = empty($e->getMessage()) ? $this->getCodeMessage($e->getCode()) : $e->getMessage();

                    if (!$e instanceof NotModified) {
                        $this->log($scope, $importJob->get('id'), 'error', (string)$fileRow, $message);
                    }

                    continue 1;
                }

                $action = empty($id) ? 'create' : 'update';
                $this->log($scope, $importJob->get('id'), $action, (string)$fileRow, $updatedEntity->get('id'));
            }
        }

        if (self::isDeleteAction($data['action'])) {
            $toDeleteRecords = $this
                ->getEntityManager()
                ->getRepository($scope)
                ->select(['id'])
                ->where(['id!=' => $ids])
                ->find();

            if (!empty($toDeleteRecords) && count($toDeleteRecords) > 0) {
                foreach ($toDeleteRecords as $record) {
                    try {
                        if ($entityService->deleteEntity($record->get('id'))) {
                            $this->log($scope, $importJob->get('id'), 'delete', null, $record->get('id'));
                        }
                    } catch (\Throwable $e) {
                        // ignore all
                    }
                }
            }
        }

        return true;
    }

    public function log(string $entityName, string $importJobId, string $type, ?string $row, string $data): Entity
    {
        $log = $this->getEntityManager()->getEntity('ImportJobLog');
        $log->set('name', $row);
        $log->set('entityName', $entityName);
        $log->set('entityId', '');
        $log->set('importJobId', $importJobId);
        $log->set('type', $type);
        $log->set('rowNumber', 0);

        switch ($type) {
            case 'create':
            case 'update':
                $log->set('rowNumber', (int)$row);
                $log->set('entityId', $data);
                $log->set('restoreData', $this->restore);
                break;
            case 'delete':
                $log->set('entityId', $data);
                break;
            case 'error':
                $log->set('rowNumber', (int)$row);
                $log->set('message', $data);
                break;
        }

        try {
            $this->getEntityManager()->saveEntity($log);
        } catch (\Throwable $e) {
            // ignore
        }

        $this->restore = [];

        return $log;
    }

    protected static function isDeleteAction(string $action): bool
    {
        return in_array($action, ['delete', 'create_delete', 'update_delete', 'create_update_delete']);
    }

    public function getInputData(array &$data): array
    {
        if ($this->lastIteration) {
            return [];
        }

        /** @var \Espo\Entities\Attachment $attachment */
        $attachment = $this->getEntityById('Attachment', $data['attachmentId']);

        $fileParser = $this->getFileParser($data['fileFormat']);
        $fileParser->setImportPayload($data);

        // for getting header row
        $includedHeaderRow = $data['offset'] === 1 && !empty($data['isFileHeaderRow']);
        if ($includedHeaderRow) {
            $data['offset'] = 0;
        }

        switch ($data['fileFormat']) {
            case 'CSV':
                $fileData = $fileParser->getFileData($attachment, $data['delimiter'], $data['enclosure'], null, self::CSV_LIMIT);
                break;
            case 'Excel':
                $sheet = empty($data['sheet']) ? 0 : (int)$data['sheet'];
                $fileData = $fileParser->getFileData($attachment, $data['delimiter'], $data['enclosure'], $data['offset'], PHP_INT_MAX, $sheet);
                $this->lastIteration = true;
                break;
            case 'JSON':
            case 'XML':
                $fileData = $fileParser->getFileData($attachment);
                $this->lastIteration = true;
                break;
        }

        if (empty($fileData)) {
            return [];
        }

        /**
         * Prepare table data
         */
        if (in_array($data['fileFormat'], ['CSV', 'Excel'])) {
            if (empty($data['sourceFields'])) {
                $data['sourceFields'] = $fileParser->getFileColumns($attachment, $data['delimiter'], $data['enclosure'], $data['isFileHeaderRow'], $fileData);
                if ($includedHeaderRow) {
                    array_shift($fileData);
                }
            }

            $newFileData = [];
            foreach ($fileData as $line => $fileLine) {
                foreach ($fileLine as $k => $v) {
                    $newFileData[$line][$data['sourceFields'][$k]] = $v;
                }
            }
            $fileData = $newFileData;
            unset($newFileData);
        }

        /**
         * Prepare import rows
         */
        $prepared = [];
        while (count($fileData) > 0) {
            $row = array_shift($fileData);
            $event = $this->getEventManager()->dispatch(new Event(['row' => $row, 'jobData' => $data, 'skip' => false]), 'prepareImportRow');
            if (!empty($event->getArgument('skip'))) {
                continue 1;
            }
            $prepared[] = $event->getArgument('row');
        }

        /**
         * Validation
         */
        if (!empty($prepared)) {
            foreach ($data['data']['configuration'] as $item) {
                if (!in_array($item['name'], $data['data']['idField'])) {
                    continue;
                }
                $columns = $item['column'];
                if (empty($columns) || !is_array($columns)) {
                    continue 1;
                }
                foreach ($columns as $column) {
                    if (!in_array($column, array_keys($prepared[0]))) {
                        throw new BadRequest(sprintf($this->translate('missingSourceFieldAsIdentifiers', 'exceptions', 'ImportFeed'), $column));
                    }
                }
            }
        }

        return $prepared;
    }

    protected function prepareWhere(string $entityType, array $configuration, array $row): array
    {
        $where = [];
        foreach ($configuration['configuration'] as $item) {
            if (in_array($item['name'], $configuration['idField'])) {
                $this
                    ->getService('ImportConfiguratorItem')
                    ->getFieldConverter($this->getMetadata()->get(['entityDefs', $entityType, 'fields', $item['name'], 'type'], 'varchar'))
                    ->prepareFindExistEntityWhere($where, $item, $row);
            }
        }

        /**
         * Hack for product attribute scoping
         */
        if (!empty($where['scope']) && $where['scope'] === 'Global' && array_key_exists('channelId', $where)) {
            unset($where['channelId']);
        }

        return $where;
    }

    protected function findExistEntity(string $entityType, array $configuration, array $where): ?Entity
    {
        if (empty($where)) {
            return null;
        }

        $fields = [];
        foreach ($configuration['configuration'] as $item) {
            if (in_array($item['name'], $configuration['idField'])) {
                $fields[] = $this->translate($item['name'], 'fields', $entityType);
            }
        }

        if ($this->getEntityManager()->getRepository($entityType)->where($where)->count() > 1) {
            throw new BadRequest(sprintf($this->translate('moreThanOneFound', 'exceptions', 'ImportFeed'), implode(', ', $fields)));
        }

        return $this->getEntityManager()->getRepository($entityType)->where($where)->findOne();
    }

    protected function saveRestoreRow(string $action, string $entityType, $data): void
    {
        $this->restore[] = [
            'action' => $action,
            'entity' => $entityType,
            'data'   => $data
        ];
    }

    protected function getCodeMessage(int $code): string
    {
        if ($code == 304) {
            return $this->translate('nothingToUpdate', 'exceptions', 'ImportFeed');
        }

        if ($code == 403) {
            return $this->translate('permissionDenied', 'exceptions', 'ImportFeed');
        }

        return 'HTTP Code: ' . $code;
    }

    protected function importAttributes(array $attributes, Entity $entity): bool
    {
        if ($entity->getEntityType() !== 'Product') {
            return false;
        }

        $this->updatedPav = [];
        $this->deletedPav = [];

        $result = false;
        foreach ($attributes as $attribute) {
            if ($this->importAttribute($entity, $attribute)) {
                $result = true;
            }
        }

        /**
         * @todo deprecated. Kept for backward compatibility
         */
        if (method_exists($this->getEntityManager()->getRepository('Product'), 'updateInconsistentAttributes')) {
            $entity->set('hasInconsistentAttributes', true);
            $this->getEntityManager()->getRepository('Product')->updateInconsistentAttributes($entity);
        }

        return $result;
    }

    protected function importAttribute(Entity $product, array $data): bool
    {
        $entityType = 'ProductAttributeValue';

        /** @var \Pim\Services\ProductAttributeValue $service */
        $service = $this->getService($entityType);

        $inputRow = new \stdClass();
        $restoreRow = new \stdClass();

        $conf = $data['item'];
        $row = $data['row'];

        $attribute = $this->getEntityById('Attribute', $conf['attributeId']);

        $conf['attribute'] = $attribute;
        $conf['name'] = 'value';

        $pavWhere = [
            'productId'   => $product->get('id'),
            'attributeId' => $conf['attributeId'],
            'scope'       => $conf['scope'],
            'language'    => $conf['locale'],
        ];

        if ($conf['scope'] === 'Channel') {
            $pavWhere['channelId'] = $conf['channelId'];
        }

        $converter = $this->getService('ImportConfiguratorItem')->getFieldConverter($attribute->get('type'));

        $pav = $this->getEntityManager()->getRepository($entityType)->where($pavWhere)->findOne();
        if (!empty($pav)) {
            $inputRow->id = $pav->get('id');
            $converter->prepareValue($restoreRow, $pav, $conf);
        }

        try {
            $converter->convert($inputRow, $conf, $row);
        } catch (IgnoreAttribute $e) {
            if (in_array(implode('_', $pavWhere), $this->updatedPav)) {
                throw new BadRequest(sprintf($this->translate('unlinkAndLinkInOneRow', 'exceptions', 'ImportFeed'), implode(', ', $conf['column'])));
            }

            $this->deletedPav[] = implode('_', $pavWhere);

            if (property_exists($inputRow, 'id')) {
                $this->saveRestoreRow('deleted', $entityType, $pav->toArray());
                $service->deleteEntity($inputRow->id);
                return true;
            } else {
                return false;
            }
        } catch (BadRequest $e) {
            $message = '';
            if (array_key_exists('column', $conf)) {
                $message = $this->translate('convertValidationPrefix', 'exceptions', 'ImportFeed');
                $values = [];
                foreach ($conf['column'] as $column) {
                    $values[] = array_key_exists($column, $row) ? $row[$column] : '';
                }
                $message = str_replace(['{{value}}', '{{column}}'], [implode(', ', $values), implode(', ', $conf['column'])], $message);
            }
            throw new BadRequest($message . lcfirst($e->getMessage()));
        }

        if (in_array(implode('_', $pavWhere), $this->deletedPav)) {
            throw new BadRequest(sprintf($this->translate('unlinkAndLinkInOneRow', 'exceptions', 'ImportFeed'), implode(', ', $conf['column'])));
        }

        $this->updatedPav[] = implode('_', $pavWhere);

        if (!property_exists($inputRow, 'id')) {
            foreach ($pavWhere as $name => $value) {
                $inputRow->$name = $value;
            }

            if (property_exists($inputRow, 'channelId')) {
                $channel = $this->getEntityManager()->getEntity('Channel', $inputRow->channelId);
                if (empty($channel)) {
                    throw new BadRequest("No such channel '$inputRow->channelId'.");
                }
                $inputRow->channelName = $channel->get('name');
            }

            $pavEntity = $service->createEntity($inputRow);
            $this->saveRestoreRow('created', $entityType, $pavEntity->get('id'));
        } else {
            $id = $inputRow->id;
            unset($inputRow->id);

            try {
                $service->updateEntity($id, $inputRow);
                $this->saveRestoreRow('updated', $entityType, [$id => $restoreRow]);
            } catch (NotModified $e) {
                return false;
            }
        }

        return true;
    }

    protected function sortConfigurator(array &$data): void
    {
        if (empty($data['data']['entity']) || empty($data['data']['configuration'])) {
            return;
        }

        if ($data['data']['entity'] === 'ProductAttributeValue') {
            foreach ($data['data']['configuration'] as $k => $item) {
                if (!empty($item['name']) && $item['name'] === 'value') {
                    $valueItem = $item;
                    unset($data['data']['configuration'][$k]);
                }
            }
            if (isset($valueItem)) {
                $data['data']['configuration'] = array_merge(array_values($data['data']['configuration']), [$valueItem]);
            }
        }
    }

    protected function getService(string $name): Base
    {
        if (!isset($this->services[$name])) {
            $this->services[$name] = $this->getContainer()->get('serviceFactory')->create($name);
        }

        return $this->services[$name];
    }

    protected function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }

    protected function getEventManager(): Manager
    {
        return $this->getContainer()->get('eventManager');
    }

    protected function getFileParser(string $format): AbstractFileParser
    {
        if (!isset($this->fileParsers[$format])) {
            $this->fileParsers[$format] = $this->getService('ImportFeed')->getFileParser($format);
        }

        return $this->fileParsers[$format];
    }

    public function getEntityById(string $scope, string $id): Entity
    {
        if (!isset($this->entities[$scope][$id])) {
            $entity = $this->getEntityManager()->getEntity($scope, $id);
            if (empty($entity)) {
                throw new BadRequest("No such $scope '$id'.");
            }
            $this->entities[$scope][$id] = $entity;
        }

        return $this->entities[$scope][$id];
    }
}
