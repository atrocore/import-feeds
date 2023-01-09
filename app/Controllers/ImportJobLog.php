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

use Espo\Core\Exceptions\NotFound;
use Espo\Core\Templates\Controllers\Base;

class ImportJobLog extends Base
{
    /**
     * @inheritDoc
     */
    public function actionList($params, $data, $request)
    {
        // prepare request
        $where = $request->get('where');
        $where[] = [
            'type'      => 'in',
            'attribute' => 'type',
            'value'     => ['error']
        ];
        $request->setQuery('where', $where);

        return parent::actionList($params, $data, $request);
    }

    /**
     * @param array  $params
     * @param array  $data
     * @param object $request
     *
     * @throws NotFound
     */
    public function actionCreate($params, $data, $request)
    {
        throw new NotFound();
    }

    /**
     * @param array  $params
     * @param array  $data
     * @param object $request
     *
     * @throws NotFound
     */
    public function actionUpdate($params, $data, $request)
    {
        throw new NotFound();
    }

    /**
     * @param array  $params
     * @param array  $data
     * @param object $request
     *
     * @throws NotFound
     */
    public function actionDelete($params, $data, $request)
    {
        throw new NotFound();
    }

    /**
     * @param array  $params
     * @param array  $data
     * @param object $request
     *
     * @throws NotFound
     */
    public function actionMassUpdate($params, $data, $request)
    {
        throw new NotFound();
    }

    /**
     * @param array  $params
     * @param array  $data
     * @param object $request
     *
     * @throws NotFound
     */
    public function actionMassDelete($params, $data, $request)
    {
        throw new NotFound();
    }

    /**
     * @param array  $params
     * @param array  $data
     * @param object $request
     *
     * @throws NotFound
     */
    public function actionCreateLink($params, $data, $request)
    {
        throw new NotFound();
    }

    /**
     * @param array  $params
     * @param array  $data
     * @param object $request
     *
     * @throws NotFound
     */
    public function actionRemoveLink($params, $data, $request)
    {
        throw new NotFound();
    }
}
