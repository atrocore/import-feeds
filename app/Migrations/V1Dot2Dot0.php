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

class V1Dot2Dot0 extends V1Dot0Dot13
{
    public function up(): void
    {
        $this->execute("DELETE FROM `import_feed` WHERE 1");
        $this->execute("DELETE FROM `scheduled_job` WHERE `job`='ImportScheduledJob'");
        $this->execute("DELETE FROM `job` WHERE `name`='ImportScheduledJob'");
        $this->execute("CREATE TABLE `import_configurator_item` (`id` VARCHAR(24) NOT NULL COLLATE utf8mb4_unicode_ci, `name` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, `created_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `import_feed_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, INDEX `IDX_IMPORT_FEED_ID` (import_feed_id), INDEX `IDX_NAME` (name, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->execute("ALTER TABLE `import_configurator_item` ADD `column` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->execute("ALTER TABLE `import_configurator_item` ADD `default` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->execute("ALTER TABLE `import_configurator_item` ADD import_by MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->execute("ALTER TABLE `import_configurator_item` ADD create_if_not_exist TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci");
        $this->execute("ALTER TABLE `import_configurator_item` ADD type VARCHAR(255) DEFAULT 'field' COLLATE utf8mb4_unicode_ci, ADD scope VARCHAR(255) DEFAULT 'Global' COLLATE utf8mb4_unicode_ci, ADD locale VARCHAR(255) DEFAULT 'main' COLLATE utf8mb4_unicode_ci, ADD attribute_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD channel_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->execute("CREATE INDEX IDX_ATTRIBUTE_ID ON `import_configurator_item` (attribute_id)");
        $this->execute("CREATE INDEX IDX_CHANNEL_ID ON `import_configurator_item` (channel_id)");
        $this->execute("ALTER TABLE `import_configurator_item` ADD entity_identifier TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        $this->execute("DELETE FROM `import_feed` WHERE 1");
        $this->execute("DROP TABLE `import_configurator_item`");
    }
}
