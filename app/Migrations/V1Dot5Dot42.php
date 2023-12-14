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

class V1Dot5Dot42 extends Base
{
    public function up(): void
    {
        $this->getConfig()->set('importJobsMaxDays', 21);
        $this->getConfig()->save();
    }

    public function down(): void
    {
    }
}
