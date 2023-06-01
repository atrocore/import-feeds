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

use Treo\Core\Migration\Base;

class V1Dot5Dot0 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("ALTER TABLE import_configurator_item ADD custom_field VARCHAR(255) DEFAULT 'value' COLLATE `utf8mb4_unicode_ci`");
        $this->getPDO()->exec("ALTER TABLE import_configurator_item ADD regex VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->getPDO()->exec("ALTER TABLE import_configurator_item ADD measure_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
    }

    public function down(): void
    {
        $this->getPDO()->exec("ALTER TABLE import_configurator_item DROP custom_field");
        $this->getPDO()->exec("ALTER TABLE import_configurator_item DROP regex");
        $this->getPDO()->exec("ALTER TABLE import_configurator_item DROP measure_id");
    }
}
