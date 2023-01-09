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

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Templates\Controllers\Base;

class ImportConfiguratorItem extends Base
{
    public function actionList($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function getActionListKanban($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionListLinked($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionMassUpdate($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionMassDelete($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionCreateLink($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionRemoveLink($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionFollow($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionUnfollow($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionMerge($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function postActionGetDuplicateAttributes($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function postActionMassFollow($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function postActionMassUnfollow($params, $data, $request)
    {
        throw new Forbidden();
    }
}
