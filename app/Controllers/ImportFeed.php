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

namespace Import\Controllers;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\FilePathBuilder;
use Espo\Core\Utils\Log;

class ImportFeed extends \Espo\Core\Templates\Controllers\Base
{
    public function actionParseFileColumns($params, $data, $request): array
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->parseFileColumns($data);
    }

    public function actionGetFileSheets($params, $data, $request): array
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }
        return $this->getRecordService()->getFileSheets($data);
    }

    public function actionRunImport($params, $data, $request): bool
    {
        // checking request
        if (!$request->isPost() || !property_exists($data, 'importFeedId')) {
            throw new BadRequest();
        }

        // checking rules
        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        $attachmentId = property_exists($data, 'attachmentId') ? (string)$data->attachmentId : '';

        return $this->getRecordService()->runImport((string)$data->importFeedId, $attachmentId);
    }

    public function actionCreateFromExport($params, $data, $request)
    {
        if (!$request->isPost() || !property_exists($data, 'exportFeedId')) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'create')) {
            throw new Forbidden();
        }

        $exportFeed = $this->getEntityManager()->getEntity('ExportFeed', $data->exportFeedId);

        if (empty($exportFeed)) {
            throw new NotFound();
        }

        $sourceFields = [];
        foreach ($exportFeed->configuratorItems as $exportConfiguratorItem) {
            $sourceFields[] = $exportConfiguratorItem->column;
        }

        $attachment = new \stdClass();
        $attachment->name = $exportFeed->name . '(From Export)';
        $attachment->description = $exportFeed->description;
//        $attachment->code = $exportFeed->code;
        $attachment->isActive = $exportFeed->isActive;
        $attachment->type = 'simple';
        $attachment->fileDataAction = 'update';
        $attachment->sourceFields = $sourceFields;
        $importFeed = $this->getRecordService()->createEntity($attachment);

        foreach ($exportFeed->configuratorItems as $configuratorItem) {
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

            if ($configuratorItem->name === 'id') {
                $attachment->entityIdentifier = true;
            }

            $this->getRecordService("ImportConfiguratorItem")->createEntity($attachment);
        }

        return ["id" => $importFeed->id];
    }

    public function actionEasyCatalog($params, $data, $request)
    {
        if (!$request->isPost() || !property_exists($data, 'code') || !property_exists($data, 'json')) {
            throw new BadRequest();
        }

        $importFeed = $this->getEntityManager()->getRepository('ImportFeed')->where(['code' => $data->code])->findOne();
        if (empty($importFeed)) {
            throw new NotFound();
        }

        $repository = $this->getEntityManager()->getRepository('Attachment');
        $attachment = $repository->get();
        $attachment->set('name', 'easy-catalog.json');
        $attachment->set('role', 'Import');
        $attachment->set('storage', 'UploadDir');
        $attachment->set('type', 'application/json');
        $attachment->set('storageFilePath', $this->createPath());
        $fileName = $repository->getFilePath($attachment);

        $this->createDir($fileName);
        file_put_contents($fileName, json_encode($data->json));
        $attachment->set('size', \filesize($fileName));

        $this->getEntityManager()->saveEntity($attachment);

        $this->getRecordService()->runImport($importFeed->id, $attachment->id);
        return true;
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

    protected function createPath(): string
    {
        return $this->getContainer()->get('filePathBuilder')->createPath(FilePathBuilder::UPLOAD);
    }
}
