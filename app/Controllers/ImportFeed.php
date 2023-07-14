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
        if (!$this->getMetadata()->isModuleInstalled('Export')) {
            throw new Forbidden();
        }

        if (!$request->isPost() || !property_exists($data, 'exportFeedId')) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'create')) {
            throw new Forbidden();
        }

        $importFeed = $this->getRecordService()->createFromExportFeed($data->exportFeedId);

        return ["id" => $importFeed->id];
    }

    public function actionEasyCatalogVerifyCode($params, $data, $request)
    {
        if (!$request->isGet() || empty($request->get("code"))) {
            throw new BadRequest();
        }
        return $this->getRecordService()->verifyCodeEasyCatalog($request->get('code'));
    }

    public function actionEasyCatalog($params, $data, $request)
    {
        if (!$request->isPost() || !property_exists($data, 'code') || !property_exists($data, 'json')) {
            throw new BadRequest();
        }

        $this->getRecordService()->importFromEasyCatalog($data);
        return true;
    }


}
