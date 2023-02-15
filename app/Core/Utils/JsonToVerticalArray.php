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

namespace Import\Core\Utils;

class JsonToVerticalArray
{
    private static ?array $importPayload;

    public static function mutate(string $json, ?array $importPayload = null): array
    {
        self::$importPayload = $importPayload;

        $array = @json_decode($json, true);
        if (empty($array)) {
            return [];
        }

        $horizontalArray = [];
        self::toHorizontalArray($array, '', $horizontalArray);

        $data = [];
        self::toVerticalArray($horizontalArray, $data);

        while (strpos(json_encode($data), 'collection{') !== false) {
            $newData = [];
            foreach ($data as $row) {
                self::toVerticalArray($row, $newData);
            }
            $data = $newData;
        }

        $keys = [];
        foreach ($data as $row) {
            $keys = array_merge($keys, array_keys($row));
        }
        $keys = array_unique($keys);

        $result = [];
        foreach ($data as $v) {
            $row = [];
            foreach ($keys as $key) {
                $row[$key] = isset($v[$key]) ? $v[$key] : null;
            }
            $result[] = $row;
        }

        return $result;
    }

    protected static function concatKeys(string $k1, $k2): string
    {
        $keys = [];
        if ($k1 !== '') {
            $keys[] = $k1;
        }
        if (is_int($k2)) {
            $keys[] = 'collection{' . $k2 . '}';
        } elseif ($k2 !== '') {
            $keys[] = $k2;
        }

        return implode('.', $keys);
    }

    protected static function toHorizontalArray(array $value, $key, &$result): void
    {
        $nullValue = null;
        $emptyValue = '';

        /**
         * Prepare NULL value and EMPTY value
         */
        $importPayload = self::$importPayload;
        if (!empty($importPayload) && is_array($importPayload)) {
            if (isset($importPayload['data']['configuration'][0]['nullValue'])) {
                $nullValue = $importPayload['data']['configuration'][0]['nullValue'];
            }
            if (isset($importPayload['data']['configuration'][0]['emptyValue'])) {
                $emptyValue = $importPayload['data']['configuration'][0]['emptyValue'];
            }
        }

        foreach ($value as $k => $v) {
            if (is_array($v)) {
                self::toHorizontalArray($v, self::concatKeys($key, $k), $result);
            } else {
                $value = $v;
                if ($value === null) {
                    $value = $nullValue;
                }
                if ($value === '') {
                    $value = $emptyValue;
                }
                $result[self::concatKeys($key, $k)] = $value;
            }
        }
    }

    protected static function toVerticalArray(array $array, &$data): void
    {
        $run = true;
        $i = 0;
        while ($run) {
            $run = false;
            $row = [];
            foreach ($array as $name => $value) {
                $nameParts = [];
                $checkParts = true;
                foreach (explode('.', $name) as $part) {
                    $nameParts[] = $part;
                    if ($checkParts && strpos($part, 'collection{') !== false) {
                        preg_match_all("/^collection\{([0-9]*)\}$/", $part, $matches);
                        $num = (int)$matches[1][0];
                        $checkParts = false;
                        if ($i === $num) {
                            array_pop($nameParts);
                        } elseif ($num > $i) {
                            $run = true;
                            continue 2;
                        }
                    }
                }

                $preparedName = implode(".", $nameParts);
                $row[$preparedName] = $value;
            }
            $data[] = $row;
            $i++;
        }
    }
}
