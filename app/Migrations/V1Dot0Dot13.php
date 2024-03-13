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

/**
 * Class V1Dot0Dot13
 */
class V1Dot0Dot13 extends Base
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $this->execute("DELETE FROM import_feed WHERE 1");
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        $this->up();
    }

    /**
     * @param string $sql
     */
    protected function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}
