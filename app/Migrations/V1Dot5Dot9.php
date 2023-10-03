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

class V1Dot5Dot9 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE import_feed ADD max_per_job INT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("ALTER TABLE import_job ADD uploaded_file_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
    }

    public function down(): void
    {
        $this->exec("ALTER TABLE import_feed DROP max_per_job");
        $this->exec("ALTER TABLE import_job DROP uploaded_file_id");
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
