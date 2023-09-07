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

use Treo\Core\Migration\Base;

class V1Dot4Dot48 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("ALTER TABLE import_job ADD trial INT DEFAULT 0 COLLATE `utf8mb4_unicode_ci`");
    }

    public function down(): void
    {
        $this->getPDO()->exec("ALTER TABLE import_job DROP trial");
    }
}
