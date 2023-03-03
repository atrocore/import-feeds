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

namespace Import\FieldConverters;

class Text extends Wysiwyg
{
    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        parent::convert($inputRow, $config, $row);

        if (!property_exists($inputRow, $config['name']) || $inputRow->{$config['name']} === null) {
            return;
        }

        $value = (string)$inputRow->{$config['name']};

        $this->ignoreAttribute($value, $config);

//        $value = str_replace(['<br>', '<br/>', '<br />', '\n'], ["\n", "\n", "\n", "\n"], $value);
//        $inputRow->{$config['name']} = trim(html_entity_decode(strip_tags($value)));

        $inputRow->{$config['name']} = $value;
    }
}
