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

class V1Dot3Dot61 extends Base
{
    public function up(): void
    {
        $this->execute("ALTER TABLE `import_feed` RENAME COLUMN ignore_already_proceeded TO proceed_already_proceeded");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE `import_feed` RENAME COLUMN proceed_already_proceeded TO ignore_already_proceeded");
    }

    protected function execute(string $query)
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
