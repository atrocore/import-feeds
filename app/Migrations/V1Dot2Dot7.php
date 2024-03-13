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

class V1Dot2Dot7 extends \Atro\Core\Migration\Base
{
    public function up(): void
    {
        $em = (new \Atro\Core\Application())->getContainer()->get('entityManager');
        foreach ($em->getRepository('ImportFeed')->where(['type' => 'simple'])->find() as $feed) {
            try {
                $feed->setFeedField('format', 'CSV');
                $em->saveEntity($feed);
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    public function down(): void
    {
    }
}
