<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Import\Migrations;

use Atro\Core\Migration\Base;

class V1Dot5Dot63 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE import_job ADD parent_id VARCHAR(24) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_IMPORT_JOB_PARENT_ID ON import_job (parent_id, deleted)");
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