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

use Treo\Core\Migration\Base;

class V1Dot4Dot41 extends Base
{
    public function up(): void
    {
        $this->execute("CREATE INDEX IDX_TYPE ON import_job_log (type, deleted)");
        $this->execute("CREATE INDEX IDX_ENTITY_NAME ON import_job_log (entity_name, deleted)");
        $this->execute("CREATE INDEX IDX_ENTITY_ID ON import_job_log (entity_id, deleted)");
        $this->execute("CREATE INDEX IDX_ROW_NUMBER ON import_job_log (`row_number`, deleted)");
        $this->execute("CREATE INDEX IDX_CREATED_AT ON import_job_log (created_at, deleted)");
        $this->execute("CREATE INDEX IDX_MODIFIED_AT ON import_job_log (modified_at, deleted)");

        $this->execute("CREATE INDEX IDX_ENTITY_NAME ON import_job (entity_name, deleted)");
        $this->execute("CREATE INDEX IDX_STATE ON import_job (state, deleted)");
        $this->execute("CREATE INDEX IDX_START ON import_job (start, deleted)");
        $this->execute("CREATE INDEX IDX_END ON import_job (end, deleted)");
        $this->execute("CREATE INDEX IDX_CREATED_AT ON import_job (created_at, deleted)");
        $this->execute("CREATE INDEX IDX_MODIFIED_AT ON import_job (modified_at, deleted)");
    }

    public function down(): void
    {
    }

    protected function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
