<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Import\Services;

use Atro\Core\Exceptions\NotModified;
use Atro\Core\EventManager\Event;
use Atro\DTO\QueueItemDTO;
use Espo\Core\EventManager\Manager;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Metadata;
use Espo\ORM\Entity;
use Espo\Services\QueueManagerBase;
use Espo\Core\Services\Base;
use Import\Entities\ImportFeed;
use Import\Exceptions\DeleteProductAttributeValue;
use Import\FieldConverters\Link;

class ImportTypeSimple extends QueueManagerBase
{
    public const MEMORY_KEYS = 'loaded_exists_entities_keys';
    public const MEMORY_WHERE_KEYS = 'loaded_exists_entities_by_where_keys';
    private array $restore = [];
    private bool $lastIteration = false;

    public function prepareJobData(ImportFeed $feed, string $attachmentId): array
    {
        if (empty($attachmentId) || empty($file = $this->getEntityById('Attachment', $attachmentId))) {
            $attachmentId = $feed->get('fileId');
            if (!empty($attachmentId)) {
                $file = $this->getEntityById('Attachment', $attachmentId);
            }
        }

        if (empty($file)) {
            throw new BadRequest($this->translate('noSuchFile', 'exceptions', 'ImportFeed'));
        }

        $result = [
            "name"             => $feed->get('name'),
            "offset"           => $feed->isFileHeaderRow() ? 1 : 0,
            "limit"            => $this->getConfig()->get('importLimit', 5000),
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

        $this->getMemoryStorage()->set('importJobId', $importJob->get('id'));
        $this->getMemoryStorage()->set('skipAssignmentNotifications', true);
        $this->getMemoryStorage()->set('skipHooks', true);

        $scope = $data['data']['entity'];
        $entityService = $this->getService($scope);

        // set configurator item position
        foreach ($data['data']['configuration'] as $k => $v) {
            $data['data']['configuration'][$k]['pos'] = $k;
        }

        $ids = [];

        $processedIds = [];

        // prepare file row
        $fileRow = empty($data['offset']) ? 0 : (int)$data['offset'];

        while (!empty($inputData = $this->getInputData($data))) {
            $this->getMemoryStorage()->set('importRowsPart', $inputData);
            while (!empty($inputData)) {
                $row = array_shift($inputData);

                // increment file row number
                $fileRow++;

                try {
                    $where = $this->prepareWhere($entityService->getEntityType(), $data['data'], $row);

                    $id = null;
                    $entity = $this->findExistEntity($entityService->getEntityType(), $data['data'], $where);
                    if (!empty($entity)) {
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
                                $this->log($scope, $importJob->get('id'), 'skip', (string)$fileRow, null);
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
                    $this->log($scope, $importJob->get('id'), 'skip', (string)$fileRow, null);
                    continue 1;
                }

                if (in_array($data['action'], ['delete_found', 'update', 'update_delete']) && empty($entity)) {
                    $this->log($scope, $importJob->get('id'), 'skip', (string)$fileRow, null);
                    continue 1;
                }

                if ($data['action'] == 'delete_not_found') {
                    $this->log($scope, $importJob->get('id'), 'skip', (string)$fileRow, null);
                    continue 1;
                }

                $action = $data['action'];

                if (!$this->getEntityManager()->getPDO()->inTransaction()) {
                    $this->getEntityManager()->getPDO()->beginTransaction();
                }

                try {
                    $input = new \stdClass();
                    $input->_importJobData = $data;
                    $input->_importInputDataRow = $row;

                    $restore = new \stdClass();

                    $this->sortConfigurator($data);

                    foreach ($data['data']['configuration'] as $item) {
                        // skip if for attribute
                        if ($item['type'] === 'Attribute') {
                            continue 1;
                        }

                        if ($item['entity'] === 'ProductAttributeValue' && in_array($item['name'], ['value', 'valueFrom', 'valueTo', 'valueUnitId'])) {
                            $item = json_decode(json_encode($item), true);
                            // if there is attributeId in input data (We have to put it in configurator item)
                            if (property_exists($input, 'attributeId')) {
                                $item['attributeId'] = $input->attributeId;
                            }
                        }

                        $type = $this->prepareFieldType($item, $input, $entity ?? null);

                        try {
                            $this->getService('ImportConfiguratorItem')->getFieldConverter($type)->convert($input, $item, $row);
                        } catch (DeleteProductAttributeValue $e) {
                            $action = 'delete_found';
                            break;
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
                        if ($action == 'delete_found') {
                            $logAction = 'delete';
                        } else {
                            $logAction = 'create';
                            $id = $entityService->createEntity($input)->get('id');
                            $processedIds[] = $id;
                            if (self::isDeleteAction($action)) {
                                $ids[] = $id;
                            }
                            $this->saveRestoreRow('created', $scope, $id);
                        }
                    } elseif ($action === 'delete_found') {
                        $logAction = 'delete';
                        $entityService->deleteEntity($id);
                        $processedIds[] = $id;
                    } else {
                        $logAction = 'update';
                        $notModified = true;
                        try {
                            $entityService->updateEntity($id, $input);
                            $processedIds[] = $id;
                            $this->saveRestoreRow('updated', $scope, [$id => $restore]);
                            $notModified = false;
                        } catch (NotModified $e) {
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
                    } else {
                        $this->log($scope, $importJob->get('id'), 'skip', (string)$fileRow, null);
                    }

                    $this->afterRowProceed($entityService->getEntityType(), $where, $id);

                    continue;
                }

                if (!empty($id)) {
                    $this->log($scope, $importJob->get('id'), $logAction, (string)$fileRow, $id);
                } else {
                    $this->log($scope, $importJob->get('id'), 'skip', (string)$fileRow, null);
                }

                $this->afterRowProceed($entityService->getEntityType(), $where, $id);
            }
            $this->clearMemoryOfLoadedEntities();
        }

        // create jobs for importing ProductAttributeValues
        $this->createImportPavJobs($data);

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

    public function afterRowProceed(string $entityType, array $where, ?string $id): void
    {
        if (!empty($id)) {
            $keys = $this->getMemoryStorage()->get(self::MEMORY_KEYS) ?? [];
            $key = $this->createMemoryKey($entityType, $id);
            $keys[] = $key;
            $this->getMemoryStorage()->set(self::MEMORY_KEYS, $keys);

            $whereKeys = $this->getMemoryStorage()->get(self::MEMORY_WHERE_KEYS) ?? [];
            $whereKey = $this->createWhereKey(array_keys($where), $this->getMemoryStorage()->get($key));
            if (empty($whereKeys[$whereKey]) || !in_array($key, $whereKeys[$whereKey])) {
                $whereKeys[$whereKey][] = $key;
            }
            $this->getMemoryStorage()->set(self::MEMORY_WHERE_KEYS, $whereKeys);
        }
    }

    public function loadExistsEntities(string $entityType, array $configuration, array $where): void
    {
        $keys = $this->getMemoryStorage()->get(self::MEMORY_KEYS) ?? [];
        if (!empty($keys)) {
            return;
        }

        $rows = $this->getMemoryStorage()->get('importRowsPart');

        $collectionWhere = [];
        foreach ($rows as $row) {
            try {
                $whereRow = $this->prepareWhere($entityType, $configuration, $row);
            } catch (\Throwable $e) {
                continue;
            }
            foreach ($whereRow as $f => $v) {
                if (!is_array($where[$f]) || !in_array($v, $where[$f])) {
                    $collectionWhere[$f][] = $v;
                }
            }
        }

        if (empty($collectionWhere)) {
            throw new \Error('Where is empty');
        }

        $existsEntities = $this->getEntityManager()->getRepository($entityType)
            ->where($collectionWhere)
            ->find();

        $whereKeys = $this->getMemoryStorage()->get(self::MEMORY_WHERE_KEYS) ?? [];

        foreach ($existsEntities as $existsEntity) {
            $key = $this->createMemoryKey($existsEntity->getEntityType(), $existsEntity->get('id'));
            $this->getMemoryStorage()->set($key, $existsEntity);
            $keys[] = $key;

            $whereKey = $this->createWhereKey(array_keys($where), $existsEntity);
            if (empty($whereKeys[$whereKey]) || !in_array($key, $whereKeys[$whereKey])) {
                $whereKeys[$whereKey][] = $key;
            }
        }

        $this->getMemoryStorage()->set(self::MEMORY_KEYS, $keys);
        $this->getMemoryStorage()->set(self::MEMORY_WHERE_KEYS, $whereKeys);
    }

    public function createWhereKey(array $fields, Entity $entity): string
    {
        sort($fields);

        $whereKey = [];
        foreach ($fields as $field) {
            $whereKey[$field] = $entity->get($field);
        }

        return json_encode($whereKey);
    }

    public function clearMemoryOfLoadedEntities(): void
    {
        foreach ($this->getMemoryStorage()->get(self::MEMORY_KEYS) ?? [] as $key) {
            $this->getMemoryStorage()->delete($key);
        }
        $this->getMemoryStorage()->delete(self::MEMORY_KEYS);
        $this->getMemoryStorage()->delete(self::MEMORY_WHERE_KEYS);

        foreach ($this->getMemoryStorage()->get(Link::MEMORY_FOREIGN_KEYS) ?? [] as $entities) {
            foreach ($entities as $keys) {
                foreach ($keys as $key) {
                    $this->getMemoryStorage()->delete($key);
                }
            }
        }
        $this->getMemoryStorage()->delete(Link::MEMORY_FOREIGN_KEYS);
        $this->getMemoryStorage()->delete(Link::MEMORY_WHERE_FOREIGN_KEYS);
    }

    public function createMemoryKey(string $entityType, string $entityId): string
    {
        return $this->getEntityManager()->getRepository($entityType)->getCacheKey($entityId);
    }

    public function log(string $entityName, string $importJobId, string $type, ?string $row, ?string $data): Entity
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
            case 'skip':
                $log->set('rowNumber', (int)$row);
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
        return in_array($action, ['delete_not_found', 'create_delete', 'update_delete', 'create_update_delete']);
    }

    public function getInputData(array &$data): array
    {
        if ($this->lastIteration) {
            return [];
        }

        /** @var \Espo\Entities\Attachment $attachment */
        $attachment = $this->getEntityById('Attachment', $data['attachmentId']);

        $fileParser = $this->getFileParser($data['fileFormat']);
        $fileParser->setData($data);

        // for getting header row
        $includedHeaderRow = $data['offset'] === 1 && !empty($data['isFileHeaderRow']);
        if ($includedHeaderRow) {
            $data['offset'] = 0;
        }

        switch ($data['fileFormat']) {
            case 'CSV':
            case 'Excel':
                $fileData = $fileParser->getFileData($attachment, $data['offset'], $data['limit']);
                $data['offset'] = $data['offset'] + $data['limit'];
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
                $fileParser->setData(array_merge($data, ['fileData' => $fileData]));
                $data['sourceFields'] = $fileParser->getFileColumns($attachment);
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
        foreach ($configuration['configuration'] as $k => $item) {
            if (in_array($item['name'], $configuration['idField'])) {
                $type = $this->getMetadata()->get(['entityDefs', $entityType, 'fields', $item['name'], 'type'], 'varchar');
                $this
                    ->getService('ImportConfiguratorItem')
                    ->getFieldConverter($type)
                    ->prepareFindExistEntityWhere($where, $item, $row);
            }
        }

        return $where;
    }

    protected function findExistEntity(string $entityType, array $configuration, array $where): ?Entity
    {
        if (empty($where)) {
            return null;
        }

        $this->loadExistsEntities($entityType, $configuration, $where);

        $whereKeys = $this->getMemoryStorage()->get(self::MEMORY_WHERE_KEYS) ?? [];

        ksort($where);
        $jsonWhere = json_encode($where);

        if (isset($whereKeys[$jsonWhere])) {
            if (isset($whereKeys[$jsonWhere][1])) {
                $fields = [];
                foreach ($configuration['configuration'] as $item) {
                    if (in_array($item['name'], $configuration['idField'])) {
                        $fields[] = $this->translate($item['name'], 'fields', $entityType);
                    }
                }
                throw new BadRequest(sprintf($this->translate('moreThanOneFound', 'exceptions', 'ImportFeed'), implode(', ', $fields)));
            }

            return $this->getMemoryStorage()->get($whereKeys[$jsonWhere][0]);
        }

        return null;
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

    protected function createImportPavJobs(array $productImportData): void
    {
        if (empty($productImportData['data']['entity']) || $productImportData['data']['entity'] !== 'Product') {
            return;
        }

        $commonFields = [
            'delimiter',
            'emptyValue',
            'nullValue',
            'decimalMark',
            'thousandSeparator',
            'markForNoRelation',
            'fieldDelimiterForRelation'
        ];

        /**
         * Prepare Product configurator item
         */
        foreach ($productImportData['data']['configuration'] as $item) {
            if ($item['type'] !== 'Attribute' && in_array($item['name'], $productImportData['data']['idField'])) {
                $product['type'] = 'Field';
                $product['name'] = 'product';
                $product['default'] = null;
                $product['entity'] = 'ProductAttributeValue';
                $product['column'][] = $item['column'][0];
                $product['importBy'][] = $item['name'];
                foreach ($commonFields as $commonField) {
                    $product[$commonField] = $item[$commonField];
                }
            }
        }

        if (empty($product)) {
            return;
        }

        $importJob = $this->getEntityById('ImportJob', $productImportData['data']['importJobId']);
        if (empty($importJob)) {
            return;
        }

        $qmJob = $this->getEntityManager()->getRepository('ImportJob')->getQmJob($importJob);
        if (empty($qmJob)) {
            return;
        }

        /** @var \Import\Entities\ImportFeed $importFeed */
        $importFeed = $this->getEntityById('ImportFeed', $importJob->get('importFeedId'));
        if (empty($importFeed)) {
            return;
        }

        /** @var \Import\Services\ImportFeed $importService */
        $importService = $this->getService('ImportFeed');

        foreach ($productImportData['data']['configuration'] as $item) {
            if ($item['type'] === 'Attribute') {
                $attribute = $this->getEntityById('Attribute', $item['attributeId']);
                if (empty($attribute)) {
                    continue;
                }

                $common = ['entity' => 'ProductAttributeValue'];
                foreach ($commonFields as $commonField) {
                    $common[$commonField] = $item[$commonField];
                }

                $configurator = [$product];
                $configurator[] = array_merge($common, [
                    'type'     => 'Field',
                    'name'     => 'attribute',
                    'column'   => [],
                    'importBy' => [],
                    'default'  => $attribute->get('id')
                ]);
                $configurator[] = array_merge($common, [
                    'type'    => 'Field',
                    'name'    => 'language',
                    'column'  => [],
                    'default' => $item['locale']
                ]);
                $configurator[] = array_merge($common, [
                        'type'    => 'Field',
                        'name'    => 'scope',
                        'column'  => [],
                        'default' => $item['scope']
                    ]
                );
                if ($item['scope'] === 'Channel') {
                    $configurator[] = array_merge($common, [
                            'type'    => 'Field',
                            'name'    => 'channelId',
                            'column'  => [],
                            'default' => $item['channelId']
                        ]
                    );
                }
                $configurator[] = array_merge($common, [
                    'type'             => 'Field',
                    'name'             => $item['attributeValue'] ?? 'value',
                    'column'           => $item['column'],
                    'default'          => $item['default'],
                    'createIfNotExist' => $item['createIfNotExist'],
                    'foreignColumn'    => $item['foreignColumn'],
                    'foreignImportBy'  => $item['foreignImportBy'],
                    'importBy'         => $item['importBy'],
                    'attributeId'      => $attribute->get('id'),
                    'attributeType'    => $attribute->get('type')
                ]);

                $pavData = $productImportData;
                $pavData['offset'] = $importFeed->isFileHeaderRow() ? 1 : 0;
                $pavData['action'] = 'create_update';
                $pavData['data']['entity'] = 'ProductAttributeValue';
                $pavData['data']['idField'] = [
                    "language",
                    "scope",
                    "product",
                    "attribute"
                ];

                if (isset($pavData['sourceFields'])) {
                    unset($pavData['sourceFields']);
                }

                $pavData['data']['configuration'] = $configurator;

                $payload = new \stdClass();
                $payload->parentJobId = $importJob->get('id');

                $pavJob = $importService->createImportJob($importFeed, 'ProductAttributeValue', $pavData['attachmentId'], $payload);

                $pavData['data']['importJobId'] = $pavJob->get('id');

                $dto = new QueueItemDTO($importService->getName($importFeed), 'ImportTypeSimple', $pavData);
                $dto->setParentId($qmJob->get('id'));

                $importService->push($dto);
            }
        }
    }

    protected function sortConfigurator(array &$data): void
    {
        if (empty($data['data']['entity']) || empty($data['data']['configuration'])) {
            return;
        }

        if ($data['data']['entity'] === 'ProductAttributeValue') {
            // sort items by ASC
            usort($data['data']['configuration'], function ($a, $b) {
                if ($a['name'] == $b['name']) {
                    return 0;
                }
                return ($a['name'] < $b['name']) ? -1 : 1;
            });
        }
    }

    protected function getService(string $name): Base
    {
        $key = "service_{$name}";

        if (!$this->getMemoryStorage()->has($key)) {
            $this->getMemoryStorage()->set($key, $this->getContainer()->get('serviceFactory')->create($name));
        }

        return $this->getMemoryStorage()->get($key);
    }

    protected function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }

    protected function getEventManager(): Manager
    {
        return $this->getContainer()->get('eventManager');
    }

    protected function getFileParser(string $format): \Import\FileParsers\FileParserInterface
    {
        return $this->getContainer()->get(ImportFeed::getFileParserClass($format));
    }

    public function getEntityById(string $scope, string $id): Entity
    {
        $entity = $this->getEntityManager()->getEntity($scope, $id);
        if (empty($entity)) {
            throw new BadRequest("No such $scope '$id'.");
        }

        return $entity;
    }

    protected function prepareFieldType(array $item, \stdClass $input, ?Entity $entity): string
    {
        $fieldName = $item['name'];
        $type = $this->getMetadata()->get(['entityDefs', $item['entity'], 'fields', $fieldName, 'type'], 'varchar');

        if ($item['entity'] === 'ProductAttributeValue') {
            if (in_array($fieldName, ['value', 'valueFrom', 'valueTo'])) {
                if (isset($item['attributeType'])) {
                    $type = $item['attributeType'];
                } elseif (property_exists($input, 'attributeType')) {
                    $type = $input->attributeType;
                } elseif (!empty($entity) && !empty($attribute = $this->getEntityById('Attribute', $entity->get('attributeId')))) {
                    $type = $attribute->get('type');
                }

                if (in_array($fieldName, ['valueFrom', 'valueTo'])) {
                    if ($type === 'rangeInt') {
                        $type = 'int';
                    } elseif ($type === 'rangeFloat') {
                        $type = 'float';
                    } else {
                        $type = 'varchar';
                    }
                }
            }

            if ($fieldName === 'valueUnitId') {
                $type = 'unit';
            }
        }

        return $type;
    }
}
