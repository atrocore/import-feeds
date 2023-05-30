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

use Espo\Core\Exceptions\BadRequest;

class RangeFloat extends FloatValue
{
    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        $isValid = false;
        $default = empty($config['default']) ? null : (float)$config['default'];
        if ($config['default'] === '0' || $config['default'] === 0) {
            $default = 0;
        }

        if (isset($config['column'][0]) && isset($row[$config['column'][0]])) {
            $value = $row[$config['column'][0]];
            $this->ignoreAttribute($value, $config);
            if (strtolower((string)$value) === strtolower((string)$config['emptyValue']) || $value === '') {
                $value = $default;
                $isValid = true;
            }
            if (strtolower((string)$value) === strtolower((string)$config['nullValue'])) {
                $value = null;
            }
        } else {
            $value = $default;
            $isValid = true;
        }

        if ($value !== null && !$isValid) {
            if ($config['customField'] == 'unit') {
                $inputRow->{$config['name'] . 'UnitId'} = (string)$value;
            } else {
                if (!empty($config['regex'])) {
                    if (preg_match_all((string)$value, $config['regex'], $matches) > 0) {
                        $value = $matches[0];
                    }
                }
                $final = $this->prepareFloatValue((string)$value, $config);
                $inputRow->{$config['name'] . ($config['customField'] == 'valueFrom' ? 'From' : 'To')} = $final;
            }
        }
    }
}