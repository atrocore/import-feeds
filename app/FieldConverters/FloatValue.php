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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Import\FieldConverters;

use Espo\Core\Exceptions\BadRequest;

class FloatValue extends Varchar
{
    public function prepareFloatValue(string $value, array $config): float
    {
        $thousandSeparator = $config['thousandSeparator'];
        $decimalMark = $config['decimalMark'];

        $decimals = 0;
        $parts = explode($decimalMark, $value);
        if (count($parts) > 1) {
            $decimals = strlen(array_pop($parts));
        }

        $floatValue = round((float)str_replace($decimalMark, '.', str_replace($thousandSeparator, '', $value)), 13);
        $checkValueStrict = number_format($floatValue, $decimals, $decimalMark, $thousandSeparator);
        $checkValueUnStrict = number_format($floatValue, $decimals, $decimalMark, '');

        if (!in_array($value, [$checkValueStrict, $checkValueUnStrict])) {
            throw new BadRequest(sprintf($this->translate('unexpectedFieldType', 'exceptions', 'ImportFeed'), $value, 'float'));
        }

        return $floatValue;
    }

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
            $value = $this->prepareFloatValue((string)$value, $config);
        }

        $inputRow->{$config['name']} = $value;
    }
}