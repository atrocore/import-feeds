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

class V1Dot4Dot26 extends Base
{
    public function up(): void
    {
        $ids = $this->getPDO()->query('SELECT id FROM import_feed WHERE proceed_already_proceeded=1')->fetchAll(\PDO::FETCH_COLUMN);

        $this->execute("ALTER TABLE import_feed CHANGE proceed_already_proceeded proceed_already_proceeded VARCHAR(255) DEFAULT 'mistake' COLLATE `utf8mb4_unicode_ci`");
        $this->getPDO()->exec("UPDATE import_feed SET proceed_already_proceeded='mistake' WHERE proceed_already_proceeded='0'");

        foreach ($ids as $id) {
            $this->getPDO()->exec("UPDATE import_feed SET proceed_already_proceeded='repeat' WHERE id='$id'");
        }
    }

    public function down(): void
    {
        throw new BadRequest('Downgrade is prohibited.');
    }

    protected function execute(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
