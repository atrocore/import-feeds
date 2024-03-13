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

declare(strict_types=1);

namespace Import\Migrations;

use Atro\Core\Migration\Base;

class V1Dot5Dot31 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE import_job ADD queue_item_id VARCHAR(24) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_IMPORT_JOB_QUEUE_ITEM_ID ON import_job (queue_item_id)");
        $this->exec("CREATE INDEX IDX_IMPORT_JOB_QUEUE_ITEM_ID_DELETED ON import_job (queue_item_id, deleted)");
    }

    public function down(): void
    {
        $this->exec("DROP INDEX IDX_IMPORT_JOB_QUEUE_ITEM_ID ON import_job");
        $this->exec("DROP INDEX IDX_IMPORT_JOB_QUEUE_ITEM_ID_DELETED ON import_job");
        $this->exec("ALTER TABLE import_job DROP queue_item_id");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
