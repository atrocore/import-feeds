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
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Templates\Services\Base;
use Espo\ORM\Entity;
use Import\Entities\ImportFeed as ImportFeedEntity;
use Import\Entities\ImportJob;

class ImportFeed extends Base
{
    protected $mandatorySelectAttributeList = ['allColumns'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        foreach ($entity->getFeedFields() as $name => $value) {
            $entity->set($name, $value);
        }
    }

    /**
     * @param string $attachmentId
     * @param object $request
     *
     * @return array
     * @throws BadRequest
     */
    public function getFileColumns(string $attachmentId, $request): array
    {
        $attachment = $this->getEntityManager()->getEntity('Attachment', $attachmentId);
        if (empty($attachment)) {
            throw new BadRequest($this->exception("noSuchFile"));
        }

        $method = "validate{$request->get('format')}File";
        if (method_exists($this, $method)) {
            $this->$method($attachmentId);
        }

        // prepare settings
        $delimiter = (!empty($request->get('delimiter'))) ? $request->get('delimiter') : ';';
        $enclosure = ($request->get('enclosure') == 'singleQuote') ? "'" : '"';
        $isFileHeaderRow = (is_null($request->get('isHeaderRow'))) ? true : !empty($request->get('isHeaderRow'));

        return $this->getFileParser($request->get('format'))->getFileColumns($attachment, $delimiter, $enclosure, $isFileHeaderRow);
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

        $data = @json_decode($contents, true);
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

        $maxSize = 1000 * 1000 * 2;

        if ($attachment->get('size') > $maxSize) {
            throw new BadRequest($this->getInjection('language')->translate('excelFileTooBig', 'exceptions', 'ImportFeed'));
        }
    }

    public function runImport(string $importFeedId, string $attachmentId): string
    {
        $feed = $this->getImportFeed($importFeedId);

        // firstly, validate feed
        $this->getRepository()->validateFeed($feed);

        $serviceName = $this->getImportTypeService($feed);

        $service = $this->getServiceFactory()->create($serviceName);
        if (method_exists($service, 'runImport')) {
            return $service->runImport($feed, $attachmentId);
        }

        $data = $service->prepareJobData($feed, $attachmentId);
        $data['data']['importJobId'] = $this->createImportJob($feed, $feed->getFeedField('entity'), $attachmentId)->get('id');

        $this->push($this->getName($feed), $serviceName, $data);

        return $data['data']['importJobId'];
    }

    public function findLinkedEntities($id, $link, $params)
    {
        if ($link === 'configuratorItems') {
            if (!empty($feed = $this->getRepository()->get($id))) {
                if (!empty($this->getMetadata()->get(['scopes', 'Attribute']))) {
                    $this->getRepository()->removeInvalidConfiguratorItems($feed);
                }
                $allColumns = empty($feed->getFeedField('allColumns')) ? [] : $feed->getFeedField('allColumns');
                $this->removeItemsByAllColumns($feed, $allColumns);
            }
        }

        return parent::findLinkedEntities($id, $link, $params);
    }

    public function removeItemsByAllColumns(Entity $importFeed, array $allColumns): void
    {
        $items = $importFeed->get('configuratorItems');
        if (!empty($items) && count($items) > 0) {
            foreach ($items as $item) {
                if (!empty($columns = $item->get('column'))) {
                    foreach ($columns as $column) {
                        if (!in_array($column, $allColumns)) {
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
    }

    protected function duplicateConfiguratorItems(Entity $entity, Entity $duplicatingEntity): void
    {
        if (empty($items = $duplicatingEntity->get('configuratorItems')) || count($items) === 0) {
            return;
        }

        foreach ($items as $item) {
            $data = $item->toArray();
            unset($data['id']);
            $data['importFeedId'] = $entity->get('id');

            $newItem = $this->getEntityManager()->getEntity('ImportConfiguratorItem');
            $newItem->set($data);
            $this->getEntityManager()->saveEntity($newItem);
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

    /**
     * @param string $name
     * @param string $serviceName
     * @param array  $data
     *
     * @return bool
     */
    public function push(string $name, string $serviceName, array $data = []): bool
    {
        return $this->getInjection('queueManager')->push($name, $serviceName, $data);
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
        return "ImportType" . ucfirst($feed->get('type'));
    }

    /**
     * @param ImportFeedEntity $feed
     * @param string           $entityType
     * @param string           $attachmentId
     *
     * @return ImportJob
     */
    public function createImportJob(ImportFeedEntity $feed, string $entityType, string $attachmentId): ImportJob
    {
        $entity = $this->getEntityManager()->getEntity('ImportJob');
        $entity->set('name', date('Y-m-d H:i:s'));
        $entity->set('importFeedId', $feed->get('id'));
        $entity->set('entityName', $entityType);
        if (!empty($attachmentId)) {
            $entity->set('attachmentId', $attachmentId);
        }

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
}
