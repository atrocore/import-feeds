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

class FloatValue extends Varchar
{
    public static function prepareFloatValue(string $value): float
    {
        return (float)str_replace(',', '.', preg_replace("/[^0-9]\.,/", "", $value));
    }

    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        $default = empty($config['default']) ? null : $config['default'];
        if ($config['default'] === '0' || $config['default'] === 0) {
            $default = 0;
        }

        if (isset($config['column'][0]) && isset($row[$config['column'][0]])) {
            $value = $row[$config['column'][0]];
            $this->ignoreAttribute($value, $config);
            if (strtolower((string)$value) === strtolower((string)$config['emptyValue']) || $value === '') {
                $value = $default;
            }
            if (strtolower((string)$value) === strtolower((string)$config['nullValue'])) {
                $value = null;
            }
        } else {
            $value = $default;
        }

        if ($value !== null) {
            $value = self::prepareFloatValue((string)$value);
        }

        $inputRow->{$config['name']} = $value;
    }
}