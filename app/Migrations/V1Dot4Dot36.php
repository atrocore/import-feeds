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

use Treo\Core\Migration\Base;

class V1Dot4Dot36 extends Base
{
    public function up(): void
    {
        $this->execute("ALTER TABLE `import_feed` ADD sheet INT DEFAULT 0 COLLATE utf8mb4_unicode_ci");

    }

    public function down(): void
    {
        $this->execute("ALTER TABLE `import_feed` DROP sheet");
    }

    protected function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
