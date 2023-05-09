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

use Espo\Core\Utils\Json;
use Espo\ORM\Entity;

class RangeFloatValue extends FloatValue
{
    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        $default = $this->parseDefault((string)$config['default']);

        if (isset($config['column'][0]) && isset($row[$config['column'][0]])) {
            $value = $row[$config['column'][0]];
            $this->ignoreAttribute($value, $config);
            if (strtolower((string)$value) === strtolower((string)$config['emptyValue']) || $value === '') {
                $valueFrom = $default['from'];
                $valueTo = $default['to'];
                $value = null;
            }
            if (strtolower((string)$value) === strtolower((string)$config['nullValue'])) {
                $value = null;
            }
        } else {
            $valueFrom = $default['from'];
            $valueTo = $default['to'];
        }

        if (isset($value)) {
            $valueFrom = null;
            $valueTo = null;
            foreach (RangeInteger::SEPARATORS as $separator) {
                $parts = explode($separator, (string)$value);
                if (count($parts) === 2) {
                    $valueFrom = $this->prepareRangeValue(trim($parts[0]), $config);
                    $valueTo = $this->prepareRangeValue(trim($parts[1]), $config);
                    break;
                }
            }
        }

        if (isset($valueFrom)) {
            $inputRow->{$config['name'] . 'From'} = $valueFrom;
        }

        if (isset($valueTo)) {
            $inputRow->{$config['name'] . 'To'} = $valueTo;
        }
    }

    public function prepareForSaveConfiguratorDefaultField(Entity $entity): void
    {
        $data = $this->parseDefault((string)$entity->getFetched('default'));

        if ($entity->get('defaultFrom') !== null) {
            $data['from'] = (float)$entity->get('defaultFrom');
        }

        if ($entity->get('defaultTo') !== null) {
            $data['to'] = (float)$entity->get('defaultTo');
        }

        $entity->set('default', Json::encode($data));
    }

    public function prepareForOutputConfiguratorDefaultField(Entity $entity): void
    {
        $data = Json::decode($entity->get('default'), true);

        $entity->set('defaultFrom', $data['from']);
        $entity->set('defaultTo', $data['to']);
    }

    protected function parseDefault(string $default): array
    {
        $default = @json_decode($default, true);

        return [
            'from' => $default['from'] ?? null,
            'to'   => $default['to'] ?? null,
        ];
    }

    public function prepareRangeValue(string $value, array $config): ?float
    {
        if (
            strtolower($value) === strtolower((string)$config['emptyValue'])
            && $value !== ''
            && strtolower($value) !== strtolower((string)$config['nullValue'])
        ) {
            return $this->prepareFloatValue($value, $config);
        }

        return null;
    }
}