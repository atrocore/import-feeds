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
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;

class Unit extends FloatValue
{
    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        $parsedDefault = $this->parseDefault($config);

        $value = $parsedDefault[0];
        $unit = $parsedDefault[1];

        $isSingleColumn = !isset($config['column'][1]);

        if ($isSingleColumn) {
            if (!empty($config['column'][0]) && isset($row[$config['column'][0]])) {
                $cell = $row[$config['column'][0]];
                $this->ignoreAttribute($cell, $config);

                if (
                    strtolower((string)$cell) === strtolower((string)$config['emptyValue'])
                    || $cell === ''
                    || strtolower((string)$cell) === strtolower((string)$config['nullValue'])
                ) {
                    $value = null;
                    $unit = null;
                } else {
                    $parts = explode(' ', preg_replace('!\s+!', ' ', trim($cell)));
                    if (count($parts) > 2) {
                        throw new BadRequest($this->translate('incorrectUnitValue', 'exceptions', 'ImportFeed'));
                    }

                    try {
                        $value = $this->prepareFloatValue((string)$parts[0], $config);
                    } catch (BadRequest $e) {
                        throw new BadRequest(sprintf($this->translate('unexpectedFieldType', 'exceptions', 'ImportFeed'), 'unit'));
                    }

                    if (isset($parts[1])) {
                        $unit = $parts[1];
                    }
                }
            }
        } else {
            if (!empty($config['column'][0]) && isset($row[$config['column'][0]])) {
                $cellValue = trim($row[$config['column'][0]]);
                $this->ignoreAttribute($cellValue, $config);

                if (
                    strtolower((string)$cellValue) === strtolower((string)$config['emptyValue'])
                    || $cellValue === ''
                    || strtolower((string)$cellValue) === strtolower((string)$config['nullValue'])
                ) {
                    $value = null;
                    $unit = null;
                } else {
                    try {
                        $value = $this->prepareFloatValue((string)$cellValue, $config);
                    } catch (BadRequest $e) {
                        throw new BadRequest(sprintf($this->translate('unexpectedFieldType', 'exceptions', 'ImportFeed'), 'unit'));
                    }
                }
            }

            if (!empty($config['column'][1]) && isset($row[$config['column'][1]])) {
                $cellUnit = trim($row[$config['column'][1]]);
                $this->ignoreAttribute($cellUnit, $config);

                if (
                    strtolower((string)$cellUnit) === strtolower((string)$config['emptyValue'])
                    || $cellUnit === ''
                    || strtolower((string)$cellUnit) === strtolower((string)$config['nullValue'])
                ) {
                    $unit = $parsedDefault[1];
                } else {
                    $unit = $cellUnit;
                }
            }
        }

        if ($value !== null && !$this->validateUnit($unit, $config['entity'], $config)) {
            if (isset($config['attributeId'])) {
                $message = sprintf($this->translate('incorrectAttributeUnit', 'exceptions', 'ImportFeed'), $unit, $this->getAttribute((string)$config['attributeId'])->get('name'));
            } else {
                $message = sprintf($this->translate('incorrectUnit', 'exceptions', 'ImportFeed'), $unit, $config['name']);
            }
            throw new BadRequest($message);
        }

        $inputRow->{$config['name']} = $value;
        if (isset($config['attributeId'])) {
            $inputRow->data = (object)['unit' => $unit];
        } else {
            $inputRow->{$config['name'] . 'Unit'} = $unit;
        }
    }

    /**
     * @inheritDoc
     */
    public function prepareValue(\stdClass $restore, Entity $entity, array $item): void
    {
        if (isset($item['attributeId'])) {
            $restore->data = $entity->get('data');
        } else {
            $restore->{$item['name'] . 'Unit'} = $entity->get($item['name'] . 'Unit');
        }

        parent::prepareValue($restore, $entity, $item);
    }

    public function prepareFindExistEntityWhere(array &$where, array $configuration, array $row): void
    {
        $inputRow = new \stdClass();
        $this->convert($inputRow, $configuration, $row);

        $where[$configuration['name']] = $inputRow->{$configuration['name']};
        $where["{$configuration['name']}Unit"] = $inputRow->{"{$configuration['name']}Unit"};
    }

    public function prepareForSaveConfiguratorDefaultField(Entity $entity): void
    {
        $old = !$entity->isNew() ? Json::decode($entity->getFetched('default'), true) : ['value' => 0, 'unit' => ''];
        $unitData = [
            'value' => $entity->has('default') && strpos((string)$entity->get('default'), '{') === false ? $entity->get('default') : $old['value'],
            'unit'  => $entity->has('defaultUnit') ? $entity->get('defaultUnit') : $old['unit']
        ];

        $entity->set('default', Json::encode($unitData));
    }

    public function prepareForOutputConfiguratorDefaultField(Entity $entity): void
    {
        $unitData = Json::decode($entity->get('default'), true);
        $entity->set('default', $unitData['value']);
        $entity->set('defaultUnit', $unitData['unit']);
    }

    protected function validateUnit(?string $unit, ?string $entityType, array $config): bool
    {
        if ($unit === null) {
            throw new BadRequest(sprintf($this->translate('noSpecified', 'exceptions', 'ImportFeed'), 'unit'));
        }

        if ($entityType === null) {
            throw new BadRequest(sprintf($this->translate('noSpecified', 'exceptions', 'ImportFeed'), 'entity'));
        }

        // prepare result
        $result = false;

        // prepare exist measuring units list
        $units = $this->getConfig()->get('unitsOfMeasure', []);

        // prepare measure
        $measure = $this->getMeasure($entityType, $config);

        // check for exist unit
        foreach ($units as $name => $data) {
            if (in_array($unit, $data->unitList) && $name == $measure) {
                $result = true;
                break;
            }
        }

        return $result;
    }

    protected function getMeasure(string $entityType, array $config): string
    {
        if (isset($config['attributeId'])) {
            return $this->getAttribute((string)$config['attributeId'])->get('typeValue')[0];
        }

        return (string)$this->getMetadata()->get(['entityDefs', $entityType, 'fields', $config['name'], 'measure']);
    }

    protected function parseDefault(array $configuration): array
    {
        $value = null;
        $unit = null;

        if (!empty($configuration['default'])) {
            $default = Json::decode($configuration['default'], true);

            if (!empty($default['value']) || $default['value'] === '0' || $default['value'] === 0) {
                try {
                    $value = $this->prepareFloatValue((string)$default['value'], $configuration);
                } catch (BadRequest $e) {
                    throw new BadRequest(sprintf($this->translate('unexpectedFieldType', 'exceptions', 'ImportFeed'), 'unit'));
                }
            }

            if (!empty($default['unit'])) {
                $unit = (string)$default['unit'];
            } else {
                $measure = $this->getMeasure($configuration['entity'], $configuration);
                $units = $this->getConfig()->get('unitsOfMeasure', []);
                if (property_exists($units, $measure) && property_exists($units->{$measure}, 'unitList') && isset($units->{$measure}->unitList[0])) {
                    $unit = $units->{$measure}->unitList[0];
                }
            }
        }

        return [$value, $unit];
    }

    protected function getAttribute(string $attributeId): Entity
    {
        $attribute = $this->getEntityManager()->getEntity('Attribute', $attributeId);
        if (empty($attribute)) {
            throw new BadRequest("Attribute with ID '$attributeId' does not exist.");
        }

        return $attribute;
    }
}