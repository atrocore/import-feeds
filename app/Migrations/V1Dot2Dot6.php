<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
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
