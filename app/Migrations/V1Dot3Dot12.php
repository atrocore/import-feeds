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

class V1Dot3Dot12 extends Base
{
    public function up(): void
    {
        $this->execute("CREATE TABLE `import_feed_assigned_account` (`id` INT AUTO_INCREMENT NOT NULL UNIQUE COLLATE utf8mb4_unicode_ci, `account_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `import_feed_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, INDEX `IDX_F4AD304E9B6B5FBA` (account_id), INDEX `IDX_F4AD304E42B515EF` (import_feed_id), UNIQUE INDEX `UNIQ_F4AD304E9B6B5FBA42B515EF` (account_id, import_feed_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->execute("DROP INDEX id ON `import_feed_assigned_account`");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE `import_feed_assigned_account`");
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
