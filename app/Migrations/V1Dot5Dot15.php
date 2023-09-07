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

class V1Dot5Dot15 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("Update import_feed set file_data_action = 'delete_not_found' where file_data_action='delete'");
    }

    public function down(): void
    {
        $this->getPDO()->exec("Update import_feed set file_data_action = 'delete' where file_data_action='delete_found' or file_data_action='delete_not_found'");
    }
}
