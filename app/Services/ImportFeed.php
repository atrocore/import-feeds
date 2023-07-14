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
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\FilePathBuilder;
use Espo\Core\Templates\Services\Base;
use Espo\ORM\Entity;
use Import\Entities\ImportFeed as ImportFeedEntity;
use Import\Entities\ImportJob;

class ImportFeed extends Base
{
    protected $mandatorySelectAttributeList = ['sourceFields', 'sheet', 'data'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        foreach ($entity->getFeedFields() as $name => $value) {
            $entity->set($name, $value);
        }

        $latestJob = $this->getEntityManager()
            ->getRepository('ImportJob')
            ->where([
                'importFeedId' => $entity->id
            ])
            ->order('start', 'DESC')
            ->limit(1, 0)
            ->findOne();
        if (!empty($latestJob)) {
            $entity->set('lastStatus', $latestJob->get('state'));
            $entity->set('lastTime', $latestJob->get('start'));
        }
    }

    public function parseFileColumns(\stdClass $payload): array
    {
        if (!property_exists($payload, 'attachmentId')) {
            throw new BadRequest($this->exception("noSuchFile"));
        }

        $attachment = $this->getEntityManager()->getEntity('Attachment', $payload->attachmentId);
        if (empty($attachment)) {
            throw new BadRequest($this->exception("noSuchFile"));
        }

        if (property_exists($payload, 'format')) {
            $method = "validate{$payload->format}File";
            if (method_exists($this, $method)) {
                $this->$method($attachment->get('id'));
            }
        }

        $maxSize = 1024 * 1024 * 2; // 2 MB

        if ($attachment->get('size') > $maxSize) {
            $name = str_replace("{{fileName}}", $attachment->get('name'), $this->translate('parseFile'));
            $id = $this
                ->getInjection('queueManager')
                ->createQueueItem($name, 'BackgroundFileParser', ['payload' => $payload]);
            return [
                'jobId' => $id
            ];
        }

        return $this->getFileColumns($payload);
    }

    public function getFileSheets(\stdClass $payload): array
    {
        if (!property_exists($payload, 'format') || $payload->format !== 'Excel') {
            return [];
        }

        if (!property_exists($payload, 'attachmentId')) {
            throw new BadRequest($this->exception("noSuchFile"));
        }

        $attachment = $this->getEntityManager()->getEntity('Attachment', $payload->attachmentId);
        if (empty($attachment)) {
            throw new BadRequest($this->exception("noSuchFile"));
        }

        return $this->getFileParser($payload->format)->getFileSheetsNames($attachment);
    }

    public function getFileColumns(\stdClass $payload): array
    {
        if (!property_exists($payload, 'attachmentId')) {
            throw new BadRequest($this->exception("noSuchFile"));
        }

        $attachment = $this->getEntityManager()->getEntity('Attachment', $payload->attachmentId);
        if (empty($attachment)) {
            throw new BadRequest($this->exception("noSuchFile"));
        }

        if (!property_exists($payload, 'format') || empty($payload->format)) {
            throw new BadRequest('Format is required.');
        }

        $method = "validate{$payload->format}File";
        if (method_exists($this, $method)) {
            $this->$method($attachment->get('id'));
        }

        $parser = $this->getFileParser($payload->format);

        if ($parser instanceof CsvFileParser) {
            $delimiter = (property_exists($payload, 'delimiter') && !empty($payload->delimiter)) ? $payload->delimiter : ';';
            $enclosure = (property_exists($payload, 'enclosure') && $payload->enclosure == 'singleQuote') ? "'" : '"';
            $isFileHeaderRow = (property_exists($payload, 'isHeaderRow') && is_null($payload->isHeaderRow)) ? true : !empty($payload->isHeaderRow);
            $sheet = intval($payload->sheet);

            return $parser->getFileColumns($attachment, $delimiter, $enclosure, $isFileHeaderRow, null, $sheet);
        }

        if ($parser instanceof JsonFileParser) {
            $excludedNodes = (property_exists($payload, 'excludedNodes') && !empty($payload->excludedNodes)) ? $payload->excludedNodes : [];
            $keptStringNodes = (property_exists($payload, 'keptStringNodes') && !empty($payload->keptStringNodes)) ? $payload->keptStringNodes : [];

            return $parser->getFileColumns($attachment, $excludedNodes, $keptStringNodes);
        }

        return [];
    }

    public function validateXMLFile(string $attachmentId): void
    {
        $attachment = $this->getEntityManager()->getEntity('Attachment', $attachmentId);
        if (empty($attachment)) {
            throw new BadRequest($this->exception("noSuchFile"));
        }

        $contents = file_get_contents($attachment->getFilePath());

        $data = \simplexml_load_string($contents);
        if (empty($data)) {
            throw new BadRequest($this->getInjection('language')->translate('xmlExpected', 'exceptions', 'ImportFeed'));
        }
    }

    public function validateJSONFile(string $attachmentId): void
    {
        $attachment = $this->getEntityManager()->getEntity('Attachment', $attachmentId);
        if (empty($attachment)) {
            throw new BadRequest($this->exception("noSuchFile"));
        }

        $contents = file_get_contents($attachment->getFilePath());

        if (is_string($contents)) {
            $data = @json_decode($contents, true);
        }

        if (empty($data)) {
            throw new BadRequest($this->getInjection('language')->translate('jsonExpected', 'exceptions', 'ImportFeed'));
        }
    }

    public function validateCSVFile(string $attachmentId): void
    {
        $attachment = $this->getEntityManager()->getEntity('Attachment', $attachmentId);
        if (empty($attachment)) {
            throw new BadRequest($this->exception("noSuchFile"));
        }

        $csvTypes = [
            "text/csv",
            "text/plain",
            "text/x-csv",
            "application/vnd.ms-excel",
            "text/x-csv",
            "application/csv",
            "application/x-csv",
            "text/comma-separated-values",
            "text/x-comma-separated-values",
            "text/tab-separated-values"
        ];

        if (!in_array($attachment->get('type'), $csvTypes)) {
            throw new BadRequest($this->getInjection('language')->translate('csvExpected', 'exceptions', 'ImportFeed'));
        }

        $contents = file_get_contents($attachment->getFilePath());
        if (!preg_match('//u', $contents)) {
            throw new BadRequest($this->getInjection('language')->translate('utf8Expected', 'exceptions', 'ImportFeed'));
        }
    }

    public function validateExcelFile(string $attachmentId): void
    {
        $attachment = $this->getEntityManager()->getEntity('Attachment', $attachmentId);
        if (empty($attachment)) {
            throw new BadRequest($this->exception("noSuchFile"));
        }

        $excelTypes = [
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "application/vnd.ms-excel",
        ];

        if (!in_array($attachment->get('type'), $excelTypes)) {
            throw new BadRequest($this->getInjection('language')->translate('excelExpected', 'exceptions', 'ImportFeed'));
        }
    }

    public function runImport(string $importFeedId, string $attachmentId, \stdClass $payload = null): bool
    {
        $event = $this
            ->getInjection('eventManager')
            ->dispatch('ImportFeedService', 'beforeRunImport', new Event(['importFeedId' => $importFeedId, 'attachmentId' => $attachmentId, 'payload' => $payload]));

        $importFeedId = $event->getArgument('importFeedId');
        $attachmentId = $event->getArgument('attachmentId');
        $payload = $event->getArgument('payload');

        $feed = $this->getImportFeed($importFeedId);

        // firstly, validate feed
        $this->getRepository()->validateFeed($feed);

        $serviceName = $this->getImportTypeService($feed);
        $service = $this->getServiceFactory()->create($serviceName);

        if (method_exists($service, 'runImport')) {
            return $service->runImport($feed, $attachmentId, $payload);
        }

        $this->pushJobs($feed, !empty($attachmentId) ? $attachmentId : $feed->get('fileId'), $payload);

        $this
            ->getInjection('eventManager')
            ->dispatch('ImportFeedService', 'afterImportJobsCreations', new Event(['importFeedId' => $importFeedId]));

        return true;
    }

    public function pushJobs(ImportFeedEntity $importFeed, string $attachmentId, \stdClass $payload = null): void
    {
        $serviceName = $this->getImportTypeService($importFeed);
        $service = $this->getServiceFactory()->create($serviceName);
        $attachmentRepo = $this->getEntityManager()->getRepository('Attachment');

        $maxPerJob = (int)$importFeed->get('maxPerJob');
        $fileFormat = $importFeed->getFeedField('format');

        if ($maxPerJob > 0 && in_array($fileFormat, ['CSV', 'Excel'])) {
            $isFileHeaderRow = !empty($importFeed->getFeedField('isFileHeaderRow'));
            $fileParser = $this->getFileParser($fileFormat);
            $attachment = $this->getEntityManager()->getEntity('Attachment', $attachmentId);
            $sheet = $fileFormat === 'CSV' ? null : $importFeed->get('sheet');

            $offset = 0;

            $header = [];
            if ($isFileHeaderRow) {
                $header = $fileParser->getFileData($attachment, $importFeed->getDelimiter(), $importFeed->getEnclosure(), 0, 1, $sheet);
                $offset = 1;
            }

            $partNumber = 1;
            while (!empty($fileData = $fileParser->getFileData($attachment, $importFeed->getDelimiter(), $importFeed->getEnclosure(), $offset, $maxPerJob, $sheet))) {
                $part = array_merge($header, $fileData);

                $fileExt = $fileFormat === 'CSV' ? 'csv' : 'xlsx';

                $jobAttachment = $attachmentRepo->get();
                $jobAttachment->set('name', date('Y-m-d H:i:s') . ' (' . $partNumber . ')' . '.' . $fileExt);
                $jobAttachment->set('role', 'Attachment');
                $jobAttachment->set('relatedType', 'ImportFeed');
                $jobAttachment->set('relatedId', $importFeed->get('id'));
                $jobAttachment->set('storage', 'UploadDir');
                $jobAttachment->set('storageFilePath', $this->getInjection('filePathBuilder')->createPath(FilePathBuilder::UPLOAD));

                $fileName = $attachmentRepo->getFilePath($jobAttachment);
                $fileParser->createFile($fileName, $part, ['delimiter' => $importFeed->getDelimiter(), 'enclosure' => $importFeed->getEnclosure()]);

                $jobAttachment->set('md5', \md5_file($fileName));
                $jobAttachment->set('type', \mime_content_type($fileName));
                $jobAttachment->set('size', \filesize($fileName));
                $this->getEntityManager()->saveEntity($jobAttachment);

                $data = $service->prepareJobData($importFeed, $jobAttachment->get('id'));
                $data['sheet'] = 0;
                $data['data']['importJobId'] = $this
                    ->createImportJob($importFeed, $importFeed->getFeedField('entity'), $attachmentId, $payload, $jobAttachment->get('id'))
                    ->get('id');
                $this->push($this->getName($importFeed) . ' (' . $partNumber . ')', $serviceName, $data);

                $offset = $offset + $maxPerJob;
                $partNumber++;
            }
        } else {
            $data = $service->prepareJobData($importFeed, $attachmentId);
            $data['data']['importJobId'] = $this->createImportJob($importFeed, $importFeed->getFeedField('entity'), $attachmentId, $payload)->get('id');
            $this->push($this->getName($importFeed), $serviceName, $data);
        }
    }

    public function findLinkedEntities($id, $link, $params)
    {
        if ($link === 'configuratorItems') {
            if (!empty($feed = $this->getRepository()->get($id))) {
                if (!empty($this->getMetadata()->get(['scopes', 'Attribute']))) {
                    $this->getRepository()->removeInvalidConfiguratorItems($feed);
                }
                $sourceFields = empty($feed->get('sourceFields')) ? [] : $feed->get('sourceFields');
                $this->removeItemsBySourceFields($feed, $sourceFields);
            }
        }

        return parent::findLinkedEntities($id, $link, $params);
    }

    public function removeItemsBySourceFields(Entity $importFeed, array $sourceFields): void
    {
        $items = $importFeed->get('configuratorItems');
        if (!empty($items) && count($items) > 0) {
            foreach ($items as $item) {
                if (!empty($columns = $item->get('column'))) {
                    foreach ($columns as $column) {
                        if (!in_array($column, $sourceFields)) {
                            $this->getEntityManager()->removeEntity($item);
                            continue 2;
                        }
                    }
                }
            }
        }
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public function exception(string $key): string
    {
        return $this->getInjection('language')->translate($key, 'exceptions', 'ImportFeed');
    }

    /**
     * @inheritdoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('queueManager');
        $this->addDependency('filePathBuilder');
    }

    protected function duplicateConfiguratorItems(Entity $entity, Entity $duplicatingEntity): void
    {
        if (empty($items = $duplicatingEntity->get('configuratorItems')) || count($items) === 0) {
            return;
        }

        $service = $this->getServiceFactory()->create('ImportConfiguratorItem');

        foreach ($items as $item) {
            $data = $item->toArray();
            unset($data['id']);
            $data['importFeedId'] = $entity->get('id');
            $newItem = $this->getEntityManager()->getEntity('ImportConfiguratorItem');
            $newItem->set($data);
            $service->prepareDuplicateEntityForSave($entity, $newItem);
            $this->getEntityManager()->saveEntity($newItem);
        }
    }

    protected function duplicateImportHttpHeaders(Entity $entity, Entity $duplicatingEntity): void
    {
        $headers = $duplicatingEntity->get('importHttpHeaders');

        if (empty($headers) || count($headers) === 0) {
            return;
        }

        foreach ($headers as $header) {
            $data = $header->toArray();
            unset($data['id']);
            $data['importFeedId'] = $entity->get('id');

            $newHeader = $this->getEntityManager()->getEntity('ImportHttpHeader');
            $newHeader->set($data);
            $this->getEntityManager()->saveEntity($newHeader);
        }
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function translate(string $key): string
    {
        return $this->getInjection('language')->translate($key, 'labels', 'ImportFeed');
    }

    public function push(string $name, string $serviceName, array $data = []): bool
    {
        $priority = empty($data['data']['priority']) ? 'Normal' : (string)$data['data']['priority'];

        return $this->getInjection('queueManager')->push($name, $serviceName, $data, $priority);
    }

    /**
     * @param string $importFeedId
     *
     * @return ImportFeedEntity
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    protected function getImportFeed(string $importFeedId): ImportFeedEntity
    {
        $feed = $this->getEntityManager()->getEntity('ImportFeed', $importFeedId);
        if (empty($feed)) {
            throw new NotFound($this->exception("No such ImportFeed"));
        }

        // checking rules
        if (!$this->getAcl()->check($feed, 'read')) {
            throw new Forbidden();
        }

        // is feed active ?
        if (!$feed->get('isActive')) {
            throw new BadRequest($this->exception("importFeedIsInactive"));
        }

        return $feed;
    }

    /**
     * @param string $format
     *
     * @return CsvFileParser|ExcelFileParser
     * @throws BadRequest
     */
    public function getFileParser(string $format)
    {
        if ($format === 'CSV') {
            return $this->getInjection('serviceFactory')->create('CsvFileParser');
        }

        if ($format === 'Excel') {
            return $this->getInjection('serviceFactory')->create('ExcelFileParser');
        }

        if ($format === 'JSON') {
            return $this->getInjection('serviceFactory')->create('JsonFileParser');
        }

        if ($format === 'XML') {
            return $this->getInjection('serviceFactory')->create('XmlFileParser');
        }

        throw new BadRequest("No such file parser type '$format'.");
    }

    /**
     * @param ImportFeedEntity $feed
     *
     * @return string
     */
    public function getName(ImportFeedEntity $feed): string
    {
        return $this->translate("Import") . ": <strong>{$feed->get("name")}</strong>";
    }

    /**
     * @param ImportFeedEntity $feed
     *
     * @return string
     */
    protected function getImportTypeService(ImportFeedEntity $feed): string
    {
        return 'ImportType' . ucfirst($feed->get('type'));
    }

    public function createImportJob(ImportFeedEntity $feed, string $entityType, string $uploadedFileId, \stdClass $payload = null, string $attachmentId = null): ImportJob
    {
        $entity = $this->getEntityManager()->getEntity('ImportJob');
        $entity->set('name', date('Y-m-d H:i:s'));
        $entity->set('importFeedId', $feed->get('id'));
        $entity->set('entityName', $entityType);
        $entity->set('uploadedFileId', $uploadedFileId);
        $entity->set('attachmentId', empty($attachmentId) ? $uploadedFileId : $attachmentId);
        $entity->set('payload', $payload);

        $this->getEntityManager()->saveEntity($entity);

        return $entity;
    }

    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        parent::beforeUpdateEntity($entity, $data);

        foreach ($entity->getFeedFields() as $name => $value) {
            if (!$entity->has($name)) {
                $entity->set($name, $value);
            }
        }
    }

    protected function getFieldsThatConflict(Entity $entity, \stdClass $data): array
    {
        return [];
    }

    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        return true;
    }

    public function createFromExportFeed($exportFeedId)
    {
        $exportFeed = $this->getEntityManager()->getEntity('ExportFeed', $exportFeedId);

        if (empty($exportFeed)) {
            throw new NotFound();
        }

        $sourceFields = [];
        foreach ($exportFeed->configuratorItems as $configuratorItem) {
            if ($configuratorItem->type === 'Fixed value') {
                continue;
            }
            $sourceFields[] = $configuratorItem->column;
        }
        if (empty($sourceFields)) {
            $sourceFields = ['ID'];
        }

        $attachment = new \stdClass();
        $attachment->name = $exportFeed->get('name') . '(From Export)';
        $attachment->description = $exportFeed->get('description');
        $attachment->code = $exportFeed->code;
        $attachment->isActive = $exportFeed->get('isActive');
        $attachment->type = 'simple';
        $attachment->fileDataAction = 'update';
        $format = $exportFeed->get('fileType') === 'xlsx' ? 'Excel' : strtoupper($exportFeed->get('fileType'));
        $attachment->format = $format;
        $attachment->sourceFields = $sourceFields;
        $attachment->entity = $exportFeed->getFeedField('entity');
        $importFeed = $this->createEntity($attachment);

        foreach ($exportFeed->configuratorItems as $configuratorItem) {
            if ($configuratorItem->type === 'Fixed value') {
                continue;
            }

            $attachment = new \stdClass();
            $attachment->importFeedId = $importFeed->id;
            $attachment->name = $configuratorItem->name;
            $attachment->column = [$configuratorItem->column];
            $attachment->type = $configuratorItem->type;
            $attachment->scope = $configuratorItem->scope;
            $attachment->locale = $configuratorItem->locale;
            $attachment->attributeId = $configuratorItem->attributeId;
            $attachment->channelId = $configuratorItem->channelId;
            $attachment->sortOrder = $configuratorItem->sortOrder;
            $attachment->importBy = $configuratorItem->exportBy;
            if (!empty($configuratorItem->attributeValue)) {
                $attachment->attributeValue = $configuratorItem->attributeValue;
            }

            if ($configuratorItem->name === 'id') {
                $attachment->entityIdentifier = true;
            }

            $this->getRecordService("ImportConfiguratorItem")->createEntity($attachment);
        }

        return $importFeed;
    }

    public function verifyCodeEasyCatalog(string $code)
    {
        $importFeed = $this->getRepository()->where(['code' => $code])->findOne();
        if (empty($importFeed)) {
            return 'Import Feed code is invalid';
        }

        $hasIdColumn = false;
        foreach ($importFeed->configuratorItems as $configuratorItem) {
            if ($configuratorItem->get('name') == 'id' && !empty($configuratorItem->get('column')) && $configuratorItem->get('column')[0] == "ID") {
                $hasIdColumn = true;
                break;
            }
        }

        if (!$hasIdColumn) {
            return 'This import feed has no ID column';
        }

        return 'Import feed is correctly configured';
    }


    public function importFromEasyCatalog(\stdClass $data)
    {
        $importFeed = $this->getRepository()->where(['code' => $data->code])->findOne();
        if (empty($importFeed)) {
            throw new NotFound();
        }

        $repository = $this->getEntityManager()->getRepository('Attachment');
        $attachment = $repository->get();
        $attachment->set('name', 'easy-catalog.json');
        $attachment->set('type', 'application/json');
        $attachment->set('role', 'Import');
        $attachment->set('relatedType', 'ImportFeed');
        $attachment->set('relatedId', $importFeed->get('id'));
        $attachment->set('storage', 'UploadDir');
        $attachment->set('storageFilePath', $this->getInjection('filePathBuilder')->createPath(FilePathBuilder::UPLOAD));
        $fileName = $repository->getFilePath($attachment);

        $this->createDir($fileName);
        file_put_contents($fileName, json_encode($data->json));
        $attachment->set('size', \filesize($fileName));

        $this->getEntityManager()->saveEntity($attachment);

        $this->runImport($importFeed->id, $attachment->id);
    }

    protected function createDir(string $fileName): void
    {
        $parts = explode('/', $fileName);
        array_pop($parts);
        $dir = implode('/', $parts);

        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
            sleep(1);
        }
    }

}
