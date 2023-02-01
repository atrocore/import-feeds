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

        $this->getPDO()->exec("ALTER TABLE import_feed CHANGE proceed_already_proceeded proceed_already_proceeded VARCHAR(255) DEFAULT 'mistake' COLLATE `utf8mb4_unicode_ci`");
        $this->getPDO()->exec("UPDATE import_feed SET proceed_already_proceeded='mistake' WHERE proceed_already_proceeded='0'");

        foreach ($ids as $id) {
            $this->getPDO()->exec("UPDATE import_feed SET proceed_already_proceeded='repeat' WHERE id='$id'");
        }

        $this->getPDO()->exec("ALTER TABLE import_feed RENAME COLUMN proceed_already_proceeded TO repeat_processing");

        $this->getPDO()->exec("DELETE FROM import_job_log WHERE deleted=1");

        $this->getPDO()->exec("UPDATE import_job_log SET entity_name='' WHERE entity_name IS NULL");
        $this->getPDO()->exec("UPDATE import_job_log SET entity_id='' WHERE entity_id IS NULL");
        $this->getPDO()->exec("UPDATE import_job_log SET import_job_log.row_number=0 WHERE import_job_log.row_number IS NULL");

        $query
            = "SELECT import_job_id, type, entity_name, entity_id, import_job_log.row_number FROM import_job_log GROUP BY import_job_id, type, entity_name, entity_id, import_job_log.row_number HAVING count(*) > 1";

        while (!empty($v = $this->getPDO()->query($query)->fetch(\PDO::FETCH_ASSOC))) {
            $id = $this->getPDO()->query(
                "SELECT id FROM import_job_log  WHERE import_job_id='{$v['import_job_id']}' AND type='{$v['type']}' AND entity_name='{$v['entity_name']}' AND entity_id='{$v['entity_id']}' AND import_job_log.row_number={$v['row_number']}"
            )->fetch(\PDO::FETCH_COLUMN);
            $this->getPDO()->exec("DELETE FROM import_job_log WHERE id='$id'");
        }

        $this->getPDO()->exec("ALTER TABLE import_job_log CHANGE type type VARCHAR(10) DEFAULT 'create' COLLATE `utf8mb4_unicode_ci`");
        $this->getPDO()->exec("ALTER TABLE import_job_log CHANGE entity_name entity_name VARCHAR(100) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->getPDO()->exec("ALTER TABLE import_job_log CHANGE entity_id entity_id VARCHAR(30) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");

        $this->getPDO()->exec("ALTER TABLE import_job ADD converted_file_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");

        $this->getPDO()->exec(
            "CREATE UNIQUE INDEX UNIQ_58FA52B877D1F4B181257D5D8CDE572916EFC72DC6B6CC86EB3B4E33 ON import_job_log (import_job_id, entity_id, type, entity_name, `row_number`, deleted)"
        );
    }

    public function down(): void
    {
        throw new BadRequest('Downgrade is prohibited.');
    }
}
