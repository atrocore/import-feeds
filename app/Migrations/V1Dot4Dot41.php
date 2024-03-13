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
