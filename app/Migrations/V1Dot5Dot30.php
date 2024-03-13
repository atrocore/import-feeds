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

class V1Dot5Dot30 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE import_job ADD sort_order DOUBLE PRECISION DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");

        $res = $this->getConnection()->createQueryBuilder()
            ->select('i.*')
            ->from('import_job', 'i')
            ->orderBy('i.created_at', 'ASC')
            ->fetchAllAssociative();

        foreach ($res as $k => $v) {
            $this->getConnection()->createQueryBuilder()
                ->update('import_job', 'i')
                ->set('i.sort_order', $k)
                ->where('i.id = :id')
                ->setParameter('id', $v['id'])
                ->executeQuery();
        }
    }

    public function down(): void
    {
        $this->exec("ALTER TABLE import_job DROP sort_order");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
