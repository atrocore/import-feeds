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

class V1Dot5Dot11 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("ALTER TABLE import_feed ADD code VARCHAR(255) DEFAULT NULL UNIQUE COLLATE `utf8mb4_unicode_ci`");
        $this->getPDO()->exec("CREATE UNIQUE INDEX UNIQ_CE6CAA2B77153098EB3B4E33 ON import_feed (code, deleted)");
    }

    public function down(): void
    {
        $this->getPDO()->exec("DROP INDEX code ON import_feed");
        $this->getPDO()->exec("DROP INDEX UNIQ_CE6CAA2B77153098EB3B4E33 ON import_feed");
        $this->getPDO()->exec("ALTER TABLE import_feed DROP code");
    }
}
