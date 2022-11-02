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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Import\Services;

use Espo\Core\EventManager\Event;
use Espo\Core\EventManager\Manager;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\FilePathBuilder;
use Espo\Core\Services\Base;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Util;
use Espo\Entities\Attachment;
use Espo\ORM\Entity;
use Espo\Services\QueueManagerBase;
use Import\Entities\ImportFeed;
use Import\Exceptions\IgnoreAttribute;
use Treo\Core\Exceptions\NotModified;

class ImportTypeSimple extends QueueManagerBase
{
    private array $services = [];
    private array $restore = [];
    private array $updatedPav = [];
    private array $deletedPav = [];
    private int $iterations = 0;
    private array $channels = [];
    private array $products = [];

    public function prepareJobData(ImportFeed $feed, string $attachmentId): array
    {
        if (empty($attachmentId) || empty($file = $this->getEntityManager()->getEntity('Attachment', $attachmentId))) {
            throw new NotFound($this->translate('noSuchFile', 'exceptions', 'ImportFeed'));
        }

        $result = [
            "name"                    => $feed->get('name'),
            "offset"                  => $feed->isFileHeaderRow() ? 1 : 0,
            "limit"                   => \PHP_INT_MAX,
            "fileFormat"              => $feed->getFeedField('format'),
            "delimiter"               => $feed->getDelimiter(),
            "enclosure"               => $feed->getEnclosure(),
            "isFileHeaderRow"         => $feed->isFileHeaderRow(),
            "adapter"                 => $feed->getFeedField('adapter'),
            "action"                  => $feed->get('fileDataAction'),
            "attachmentId"            => $attachmentId,
            "data"                    => $feed->getConfiguratorData(),
            "proceedAlreadyProceeded" => !empty($feed->get("proceedAlreadyProceeded")) ? 1 : 0
        ];

        return $this
            ->getEventManager()
            ->dispatch(new Event(['result' => $result, 'importFeed' => $feed, 'attachment' => $file]), 'prepareJobData')
            ->getArgument('result');
    }

    public function run(array $data = []): bool
    {
        $importJob = $this->getEntityManager()->getEntity('ImportJob', $data['data']['importJobId']);
        if (empty($importJob)) {
            throw new BadRequest('No such ImportJob.');
        }

        $scope = $data['data']['entity'];

        $ids = [];

        $updatedIds = [];

        // prepare file row
        $fileRow = empty($data['offset']) ? 0 : (int)$data['offset'];

        $hasAttachment = !empty($data['attachmentId']);

        // create imported file
        if (!$hasAttachment) {
            $importedFileName = str_replace(' ', '_', strtolower($data['name'])) . '_' . time() . '.csv';
            $importedFilePath = $this->getContainer()->get('filePathBuilder')->createPath(FilePathBuilder::UPLOAD);
            $importedFileFullPath = $this->getConfig()->get('filesPath', 'upload/files/') . $importedFilePath;
            Util::createDir($importedFileFullPath);
            $importedFile = fopen($importedFileFullPath . '/' . $importedFileName, 'w');
        }

        while (!empty($inputData = $this->getInputData($data))) {
            foreach ($inputData as $row) {
                // push to imported file
                if (!$hasAttachment) {
                    if (empty($firstRow)) {
                        $firstRow = true;
                        fputcsv($importedFile, array_keys($row), ';');
                        $fileRow++;
                    }
                    fputcsv($importedFile, array_values($row), ';');
                }

                // increment file row number
                $fileRow++;

                try {
                    $entity = $this->findExistEntity($this->getService($scope)->getEntityType(), $data['data'], $row);
                    $id = null;

                    if (!empty($entity)) {
                        $id = $entity->get('id');

                        if (self::isDeleteAction($data['action'])) {
                            $ids[] = $id;
                        }

                        if (empty($data['proceedAlreadyProceeded']) && in_array($id, $updatedIds)) {
                            throw new BadRequest($this->translate('alreadyProceeded', 'exceptions', 'ImportFeed'));
                        }
                    }
                } catch (\Throwable $e) {
                    $this->log($scope, $importJob->get('id'), 'error', (string)$fileRow, $e->getMessage());
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

                $event = $this->getEventManager()->dispatch(new Event(['row' => $row, 'jobData' => $data, 'skip' => false]), 'prepareImportRow');
                if (!empty($event->getArgument('skip'))) {
                    continue 1;
                }

                $row = $event->getArgument('row');

                if (!$this->getEntityManager()->getPDO()->inTransaction()) {
                    $this->getEntityManager()->getPDO()->beginTransaction();
                }

                try {
                    $input = new \stdClass();
                    $input->_importJobData = $data;
                    $input->_importInputDataRow = $row;

                    $restore = new \stdClass();

                    $attributes = [];
                    foreach ($data['data']['configuration'] as $item) {
                        if ($item['type'] == 'Attribute') {
                            $attributes[] = ['item' => $item, 'row' => $row];
                            continue 1;
                        }

                        $type = $this->getMetadata()->get(['entityDefs', $item['entity'], 'fields', $item['name'], 'type'], 'varchar');
                        $this->getService('ImportConfiguratorItem')->getFieldConverter($type)->convert($input, $item, $row);
                        if (!empty($entity)) {
                            $this->getService('ImportConfiguratorItem')->getFieldConverter($type)->prepareValue($restore, $entity, $item);
                        }
                    }

                    if (empty($id)) {
                        $updatedEntity = $this->getService($scope)->createEntity($input);

                        if (self::isDeleteAction($data['action'])) {
                            $ids[] = $updatedEntity->get('id');
                        }

                        $this->importAttributes($attributes, $updatedEntity);
                        $this->saveRestoreRow('created', $scope, $updatedEntity->get('id'));
                    } else {
                        $notModified = true;
                        try {
                            $updatedEntity = $this->getService($scope)->updateEntity($id, $input);
                            $updatedIds[] = $id;
                            $this->saveRestoreRow('updated', $scope, [$id => $restore]);
                            $notModified = false;
                        } catch (NotModified $e) {
                            $updatedIds[] = $id;
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
                        if ($this->getService($scope)->deleteEntity($record->get('id'))) {
                            $this->log($scope, $importJob->get('id'), 'delete', null, $record->get('id'));
                        }
                    } catch (\Throwable $e) {
                        // ignore all
                    }
                }
            }
        }

        // save imported file
        if (!$hasAttachment) {
            fclose($importedFile);
            $attachmentRepository = $this->getEntityManager()->getRepository('Attachment');
            $attachment = $attachmentRepository->get();
            $attachment->set('name', $importedFileName);
            $attachment->set('role', 'Import');
            $attachment->set('relatedType', 'ImportJob');
            $attachment->set('relatedId', $importJob->get('id'));
            $attachment->set('storage', 'UploadDir');
            $attachment->set('storageFilePath', $importedFilePath);
            $attachment->set('type', 'text/csv');
            $attachment->set('size', \filesize($attachmentRepository->getFilePath($attachment)));
            $this->getEntityManager()->saveEntity($attachment);

            $importJob->set('attachmentId', $attachment->get('id'));
            $this->getEntityManager()->saveEntity($importJob);
        }

        return true;
    }

    public function log(string $entityName, string $importJobId, string $type, ?string $row, string $data): Entity
    {
        $log = $this->getEntityManager()->getEntity('ImportJobLog');
        $log->set('name', $row);
        $log->set('entityName', $entityName);
        $log->set('importJobId', $importJobId);
        $log->set('type', $type);

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

    protected function getInputData(array $data): array
    {
        if ($this->iterations > 0) {
            return [];
        }

        $attachment = $this->getEntityManager()->getEntity('Attachment', $data['attachmentId']);
        if (empty($attachment)) {
            throw new BadRequest('No such Attachment.');
        }

        $fileParser = $this->getService('ImportFeed')->getFileParser($data['fileFormat']);

        $fileData = $fileParser->getFileData($attachment, $data['delimiter'], $data['enclosure'], $data['offset'], $data['limit']);
        if (empty($fileData)) {
            throw new BadRequest('File is empty.');
        }

        $result = [];

        if (in_array($data['fileFormat'], ['JSON', 'XML'])) {
            $result = $fileData;
        } else {
            $allColumns = $fileParser->getFileColumns($attachment, $data['delimiter'], $data['enclosure'], $data['isFileHeaderRow']);
            foreach ($fileData as $line => $fileLine) {
                foreach ($fileLine as $k => $v) {
                    $result[$line][$allColumns[$k]] = $v;
                }
            }
        }

        /**
         * Validation
         */
        if (!empty($result)) {
            foreach ($data['data']['configuration'] as $item) {
                $columns = $item['column'];
                if (empty($columns) || !is_array($columns)) {
                    continue 1;
                }
                foreach ($columns as $column) {
                    if (!in_array($column, array_keys($result[0]))) {
                        throw new BadRequest($this->translate('theFileDoesNotMatchTheTemplate', 'exceptions', 'ImportFeed'));
                    }
                }
            }
        }

        $this->iterations++;

        return $result;
    }

    protected function findExistEntity(string $entityType, array $configuration, array $row): ?Entity
    {
        $where = [];
        foreach ($configuration['configuration'] as $item) {
            if (in_array($item['name'], $configuration['idField'])) {
                $fields[] = $this->translate($item['name'], 'fields', $entityType);
                $this
                    ->getService('ImportConfiguratorItem')
                    ->getFieldConverter($this->getMetadata()->get(['entityDefs', $entityType, 'fields', $item['name'], 'type'], 'varchar'))
                    ->prepareFindExistEntityWhere($where, $item, $row);
            }
        }

        if (empty($where)) {
            return null;
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
        if ($entity->getEntityType() === 'Product') {
            $product = $entity;
        } elseif ($entity->getEntityType() === 'ProductAttributeValue') {
            $product = $this->getProductViaId((string)$entity->get('productId'));
            if (empty($product)) {
                return true;
            }
        } else {
            return true;
        }

        $result = false;
        foreach ($attributes as $attribute) {
            if ($this->importAttribute($product, $attribute)) {
                $result = true;
            }
        }

        $product->set('hasInconsistentAttributes', true);
        $this->getEntityManager()->getRepository($product->getEntityType())->updateInconsistentAttributes($product);

        return $result;
    }

    protected function getProductViaId(string $productId): ?Entity
    {
        if (!isset($this->products[$productId])) {
            $this->products[$productId] = $this->getEntityManager()->getEntity('Product', $productId);
        }

        return $this->products[$productId];
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

        $attribute = $this->getEntityManager()->getEntity('Attribute', $conf['attributeId']);
        if (empty($attribute)) {
            throw new BadRequest("No such Attribute '{$conf['attributeId']}'.");
        }
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
                throw new BadRequest($this->translate('unlinkAndLinkInOneRow', 'exceptions', 'ImportFeed'));
            }

            $this->deletedPav[] = implode('_', $pavWhere);

            if (property_exists($inputRow, 'id')) {
                $this->saveRestoreRow('deleted', $entityType, $pav->toArray());
                $service->deleteEntity($inputRow->id);
                return true;
            } else {
                return false;
            }
        }

        if (in_array(implode('_', $pavWhere), $this->deletedPav)) {
            throw new BadRequest($this->translate('unlinkAndLinkInOneRow', 'exceptions', 'ImportFeed'));
        }

        $this->updatedPav[] = implode('_', $pavWhere);

        if (!property_exists($inputRow, 'id')) {
            foreach ($pavWhere as $name => $value) {
                $inputRow->$name = $value;
            }

            if (property_exists($inputRow, 'channelId')) {
                $inputRow->channelName = $this->getChannel($inputRow->channelId)->get('name');
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

    protected function isFileValid(Entity $feed, Attachment $file): bool
    {
        $feedFile = $feed->get('file');
        if (empty($feedFile)) {
            throw new BadRequest($this->translate('noFeedFileFound', 'exceptions', 'ImportFeed'));
        }

        // prepare settings
        $delimiter = $feed->getDelimiter();
        $enclosure = $feed->getEnclosure();
        $isFileHeaderRow = $feed->isFileHeaderRow();

        $fileParser = $this->getService('ImportFeed')->getFileParser($feed->getFeedField('format'));

        $fileColumns = $fileParser->getFileColumns($file, $delimiter, $enclosure, $isFileHeaderRow);

        foreach ($feed->get('configuratorItems') as $item) {
            $columns = $item->get('column');
            if (empty($columns) || !is_array($columns)) {
                continue 1;
            }
            foreach ($columns as $column) {
                if (!in_array($column, $fileColumns)) {
                    return false;
                }
            }
        }

        return true;
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

    protected function getChannel(string $channelId): Entity
    {
        if (!isset($this->channels[$channelId])) {
            $this->channels[$channelId] = $this->getEntityManager()->getEntity('Channel', $channelId);
            if (empty($this->channels[$channelId])) {
                throw new BadRequest("No such channel '$channelId'.");
            }
        }

        return $this->channels[$channelId];
    }

    protected function getEventManager(): Manager
    {
        return $this->getContainer()->get('eventManager');
    }
}
