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

class V1Dot3Dot27 extends Base
{
    public function up(): void
    {
        $this->execute("ALTER TABLE `import_feed` ADD connection_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->execute("CREATE INDEX IDX_CONNECTION_ID ON `import_feed` (connection_id)");
    }

    public function down(): void
    {
        $this->execute("DROP INDEX IDX_CONNECTION_ID ON `import_feed`");
        $this->execute("ALTER TABLE `import_feed` DROP connection_id");
    }

    protected function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
