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
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Templates\Controllers\Base;

class ImportJob extends Base
{
    public function actionGenerateConvertedFile($params, \stdClass $data, $request): array
    {
        if (!$request->isPost() || !property_exists($data, 'id')) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->generateConvertedFile((string)$data->id);
    }

    public function actionGetImportJobsViaScope($params, $data, $request): array
    {
        if (!$request->isGet() || empty($request->get('scope'))) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            return [];
        }

        return $this->getRecordService()->getImportJobsViaScope((string)$request->get('scope'));
    }

    /**
     * @inheritDoc
     */
    public function actionListLinked($params, $data, $request)
    {
        if ($params['link'] == 'importJobLogs') {
            $where = $request->get('where');
            $where[] = [
                'type'      => 'in',
                'attribute' => 'type',
                'value'     => ['error']
            ];
            $request->setQuery('where', $where);
        }

        return parent::actionListLinked($params, $data, $request);
    }

    /**
     * @inheritDoc
     *
     * @throws NotFound
     */
    public function actionCreate($params, $data, $request)
    {
        throw new NotFound();
    }

    /**
     * @inheritDoc
     *
     * @throws NotFound
     */
    public function actionMassUpdate($params, $data, $request)
    {
        throw new NotFound();
    }

    /**
     * @inheritDoc
     *
     * @throws NotFound
     */
    public function actionCreateLink($params, $data, $request)
    {
        throw new NotFound();
    }
}
