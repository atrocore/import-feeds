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

declare(strict_types=1);

namespace Import\Migrations;

use Atro\Core\Migration\Base;

class V1Dot5Dot30 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE import_job ADD position INT AUTO_INCREMENT NOT NULL UNIQUE COLLATE `utf8mb4_unicode_ci`");
        $this->exec("ALTER TABLE import_job ADD sort_order DOUBLE PRECISION DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE UNIQUE INDEX UNIQ_6FB54078462CE4F5 ON import_job (position)");
        $this->exec("DROP INDEX position ON import_job");

        $this->exec("ALTER TABLE import_job ADD parent_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_PARENT_ID ON import_job (parent_id)");
        $this->exec("CREATE INDEX IDX_PARENT_ID_DELETED ON import_job (parent_id, deleted)");

        $res = $this->getSchema()->getConnection()->createQueryBuilder()
            ->select('i.*')
            ->from('import_job', 'i')
            ->orderBy('i.created_at', 'ASC')
            ->fetchAllAssociative();

        foreach ($res as $k => $v) {
            $this->getSchema()->getConnection()->createQueryBuilder()
                ->update('import_job', 'i')
                ->set('i.sort_order', $k)
                ->where('i.id = :id')
                ->setParameter('id', $v['id'])
                ->executeQuery();
        }
    }

    public function down(): void
    {
        $this->exec("DROP INDEX IDX_PARENT_ID_DELETED ON import_job");
        $this->exec("DROP INDEX IDX_PARENT_ID ON import_job");
        $this->exec("ALTER TABLE import_job DROP parent_id");
        $this->exec("ALTER TABLE import_job DROP sort_order");
        $this->exec("ALTER TABLE import_job DROP position");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
