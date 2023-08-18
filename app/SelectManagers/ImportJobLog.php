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

namespace Import\SelectManagers;

use Espo\Core\SelectManagers\Base;

class ImportJobLog extends Base
{
    protected function access(&$result)
    {
        $importJobs = $this->getEntityManager()->getRepository('ImportJob')
            ->select(['id'])
            ->find($this->createSelectManager('ImportJob')->getSelectParams([], true, true));

        $result['whereClause'][] = ['OR' => ['importJobId' => array_column($importJobs->toArray(), 'id')]];
    }
}