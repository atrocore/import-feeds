<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Import\Migrations;

use Atro\Core\Migration\Base;

class V1Dot6Dot0 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE import_job ADD parent_id VARCHAR(24) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_IMPORT_JOB_PARENT_ID ON import_job (parent_id, deleted)");
        $this->exec("ALTER TABLE import_job ADD created_count INT DEFAULT NULL");
        $this->exec("ALTER TABLE import_job ADD updated_count INT DEFAULT NULL");
        $this->exec("ALTER TABLE import_job ADD deleted_count INT DEFAULT NULL");
        $this->exec("ALTER TABLE import_job ADD skipped_count INT DEFAULT NULL");
        $this->exec("ALTER TABLE import_job ADD errors_count INT DEFAULT NULL");
    }

    public function down(): void
    {
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}