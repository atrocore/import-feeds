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

/**
 * Migration class for version 1.3.49
 */
class V1Dot3Dot49 extends Base
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $this->execute("ALTER TABLE `import_configurator_item` ADD foreign_column MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD foreign_import_by MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci");
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        $this->execute("ALTER TABLE `import_configurator_item` DROP foreign_column, DROP foreign_import_by");
    }

    /**
     * @param string $sql
     *
     * @return void
     */
    protected function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
