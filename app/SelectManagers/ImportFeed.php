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

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Util;

class ImportFeed extends \Espo\Core\SelectManagers\Base
{
    protected function boolFilterOnlyImportFailed24Hours(array &$result): void
    {
        $result['whereClause'][] = [
            'id' => $this->getImportFeedFilteredIds(1)
        ];
    }

    protected function boolFilterOnlyImportFailed7Days(array &$result): void
    {
        $result['whereClause'][] = [
            'id' => $this->getImportFeedFilteredIds(7)
        ];
    }

    protected function boolFilterOnlyImportFailed28Days(array &$result): void
    {
        $result['whereClause'][] = [
            'id' => $this->getImportFeedFilteredIds(28)
        ];
    }

    protected function getImportFeedFilteredIds(int $interval): array
    {
        $query = "SELECT imp.id
            FROM `import_feed` imp
            JOIN import_job imj ON imj.import_feed_id = imp.id
            WHERE imj.state = 'Failed'
            AND imj.start >= DATE_SUB(NOW(), INTERVAL $interval DAY)";

        return array_column(
            $this->getEntityManager()->getPDO()->query($query)->fetchAll(\PDO::FETCH_ASSOC),
            'id'
        );
    }
}
