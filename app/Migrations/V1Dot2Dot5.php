<?php
/*
 * Import Feeds
 * Free Extension
 * Copyright (c) AtroCore UG (haftungsbeschränkt).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Import\Migrations;

class V1Dot2Dot5 extends \Treo\Core\Migration\Base
{
    public function up(): void
    {
        $em = (new \Treo\Core\Application())->getContainer()->get('entityManager');
        foreach ($em->getRepository('ImportFeed')->find() as $feed) {
            try {
                $feed->setFeedField('fieldDelimiterForRelation', '|');
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
