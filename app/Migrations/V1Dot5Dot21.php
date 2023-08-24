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

use Espo\Core\Exceptions\BadRequest;
use Treo\Core\Migration\Base;

class V1Dot5Dot21 extends Base
{
    public function up(): void
    {
        $feeds = $this->getPDO()->query("SELECT * FROM import_feed WHERE deleted=0")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($feeds as $feed) {
            $data = @json_decode($feed['data'], true);
            if (is_array($data) && isset($data['feedFields']['markForNotLinkedAttribute'])) {
                $data['feedFields']['markForNoRelation'] = $data['feedFields']['markForNotLinkedAttribute'];
                unset($data['feedFields']['markForNotLinkedAttribute']);
                $newData = $this->getPDO()->quote(json_encode($data));
                $this->getPDO()->exec("UPDATE import_feed SET data=$newData WHERE id='{$feed['id']}'");
            }
        }
    }

    public function down(): void
    {
        throw new BadRequest('Downgrade is prohibited.');
    }
}
