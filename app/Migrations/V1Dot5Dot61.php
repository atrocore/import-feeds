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

namespace Import\Migrations;

use Atro\Core\Migration\Base;

class V1Dot5Dot61 extends Base
{
    public function up(): void
    {
       $this->getConnection()
            ->createQueryBuilder()
            ->update('import_configurator_item')
            ->set('name',':newName')
            ->where("import_feed_id IN (SELECT id FROM import_feed WHERE  JSON_CONTAINS(data, :entity, '$.feedFields.entity') = 1 )")
            ->andWhere('name=:name')
            ->setParameter('newName','extensibleEnums')
            ->setParameter('name','extensibleEnum')
            ->setParameter('entity','"ExtensibleEnumOption"')
           ->executeQuery();


    }

    public function down(): void
    {
        $this->getConnection()
            ->createQueryBuilder()
            ->update('import_configurator_item')
            ->set('name',':newName')
            ->where("import_feed_id IN (SELECT id FROM import_feed WHERE  JSON_CONTAINS(data, :entity, '$.feedFields.entity') = 1 )")
            ->andWhere('name=:name')
            ->setParameter('newName','extensibleEnum')
            ->setParameter('name','extensibleEnums')
            ->setParameter('entity','"ExtensibleEnumOption"')
            ->executeQuery();
    }
}