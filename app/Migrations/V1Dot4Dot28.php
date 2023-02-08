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

namespace Import\Migrations;

use Espo\Core\Exceptions\BadRequest;
use Treo\Core\Migration\Base;

class V1Dot4Dot28 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("ALTER TABLE import_feed ADD source_fields LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT '(DC2Type:jsonArray)'");
        $feeds = $this->getPDO()->query("SELECT * FROM import_feed WHERE deleted=0")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($feeds as $feed) {
            $data = @json_decode($feed['data'], true);
            if (is_array($data) && !empty($data['feedFields']['allColumns'])) {
                $sourceFields = $this->getPDO()->quote(json_encode($data['feedFields']['allColumns']));
                $this->getPDO()->exec("UPDATE import_feed SET source_fields=$sourceFields WHERE id='{$feed['id']}'");
            }
        }
    }

    public function down(): void
    {
        throw new BadRequest('Downgrade is prohibited.');
    }
}
