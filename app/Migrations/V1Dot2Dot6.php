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

class V1Dot2Dot6 extends \Treo\Core\Migration\Base
{
    public function up(): void
    {
        $this->execute("RENAME TABLE `import_result` TO `import_job`");
        $this->execute("RENAME TABLE `import_result_log` TO `import_job_log`");
        $this->execute("DROP INDEX IDX_IMPORT_RESULT_ID ON `import_job_log`");
        $this->execute("ALTER TABLE `import_job_log` CHANGE `import_result_id` `import_job_id` VARCHAR(24)");
        $this->execute("CREATE INDEX IDX_IMPORT_JOB_ID ON `import_job_log` (import_job_id)");
    }

    public function down(): void
    {
        $this->execute("RENAME TABLE `import_job` TO `import_result`");
        $this->execute("RENAME TABLE `import_job_log` TO `import_result_log`");
        $this->execute("DROP INDEX IDX_IMPORT_JOB_ID ON `import_result_log`");
        $this->execute("ALTER TABLE `import_result_log` CHANGE `import_job_id` `import_result_id` VARCHAR(24)");
        $this->execute("CREATE INDEX IDX_IMPORT_RESULT_ID ON `import_result_log` (import_result_id)");
    }

    protected function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}
