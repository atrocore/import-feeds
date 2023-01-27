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

/**
 * Class Currency
 */
class Currency extends FloatValue
{
    /**
     * @inheritDoc
     */
    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        $parsedDefault = $this->parseDefault($config);

        $value = $parsedDefault[0];
        $currency = $parsedDefault[1];

        $isSingleColumn = !isset($config['column'][1]);

        if ($isSingleColumn) {
            if (!empty($config['column'][0]) && isset($row[$config['column'][0]])) {
                $cell = trim((string)$row[$config['column'][0]]);
                $this->ignoreAttribute($cell, $config);

                if (strtolower($cell) === strtolower((string)$config['emptyValue']) || $cell === '' || strtolower($cell) === strtolower((string)$config['nullValue'])) {
                    $value = null;
                    $currency = null;
                } else {
                    $parts = explode(' ', preg_replace('!\s+!', ' ', $cell));
                    if (count($parts) > 2) {
                        throw new BadRequest($this->translate('incorrectCurrencyValue', 'exceptions', 'ImportFeed'));
                    }

                    try {
                        $value = $this->prepareFloatValue((string)$parts[0], $config);
                    } catch (BadRequest $e) {
                        throw new BadRequest(sprintf($this->translate('unexpectedFieldType', 'exceptions', 'ImportFeed'), $parts[0], 'currency'));
                    }

                    if (isset($parts[1])) {
                        $currency = $parts[1];
                    }
                }
            }
        } else {
            if (!empty($config['column'][0]) && isset($row[$config['column'][0]])) {
                $cellValue = trim($row[$config['column'][0]]);
                $this->ignoreAttribute($cellValue, $config);

                if (strtolower((string)$cellValue) === strtolower((string)$config['emptyValue']) || $cellValue === '' || strtolower((string)$cellValue) === strtolower((string)$config['nullValue'])) {
                    $value = null;
                    $currency = null;
                } else {
                    try {
                        $value = $this->prepareFloatValue((string)$cellValue, $config);
                    } catch (BadRequest $e) {
                        throw new BadRequest(sprintf($this->translate('unexpectedFieldType', 'exceptions', 'ImportFeed'), $cellValue, 'currency'));
                    }
                }
            }

            if (!empty($config['column'][1]) && isset($row[$config['column'][1]])) {
                $cellCurrency = trim($row[$config['column'][1]]);
                $this->ignoreAttribute($cellCurrency, $config);
                if (strtolower((string)$cellCurrency) === strtolower((string)$config['emptyValue']) || $cellCurrency === '' || strtolower((string)$cellCurrency) === strtolower((string)$config['nullValue'])) {
                    $value = null;
                    $currency = null;
                } else {
                    $currency = $cellCurrency;
                }
            }
        }

        if ($value !== null && !in_array($currency, $this->getConfig()->get('currencyList', []))) {
            if (isset($config['attributeId'])) {
                $attribute = $this->getEntityManager()->getEntity('Attribute', $config['attributeId']);
                $fieldValue = empty($attribute) ? '-' : $attribute->get('name');
                $message = sprintf($this->translate('incorrectAttributeCurrency', 'exceptions', 'ImportFeed'), $currency, $fieldValue);
            } else {
                $message = sprintf($this->translate('incorrectCurrency', 'exceptions', 'ImportFeed'), $currency, $config['name']);
            }
            throw new BadRequest($message);
        }

        $inputRow->{$config['name']} = $value;
        $inputRow->{$config['name'] . 'Currency'} = $currency;

        if (isset($config['attributeId'])) {
            $inputRow->data = (object)['currency' => $currency];
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
            $restore->{$item['name'] . 'Currency'} = $entity->get($item['name'] . 'Currency');
        }

        parent::prepareValue($restore, $entity, $item);
    }

    public function prepareFindExistEntityWhere(array &$where, array $configuration, array $row): void
    {
        $inputRow = new \stdClass();
        $this->convert($inputRow, $configuration, $row);

        if ($configuration['entity'] !== 'ProductPrice') {
            $where[$configuration['name']] = $inputRow->{$configuration['name']};
        }

        $where["{$configuration['name']}Currency"] = $inputRow->{"{$configuration['name']}Currency"};
    }

    public function prepareForSaveConfiguratorDefaultField(Entity $entity): void
    {
        $old = !$entity->isNew() ? Json::decode($entity->getFetched('default'), true) : ['value' => 0, 'currency' => 'EUR'];
        $currencyData = [
            'value'    => $entity->has('default') && strpos((string)$entity->get('default'), '{') === false ? $entity->get('default') : $old['value'],
            'currency' => $entity->has('defaultCurrency') ? $entity->get('defaultCurrency') : $old['currency']
        ];

        if (empty($currencyData['currency'])) {
            throw new BadRequest('Default currency is required.');
        }

        $entity->set('default', Json::encode($currencyData));
    }

    public function prepareForOutputConfiguratorDefaultField(Entity $entity): void
    {
        $currencyData = Json::decode($entity->get('default'), true);
        $entity->set('default', $currencyData['value']);
        $entity->set('defaultCurrency', $currencyData['currency']);
    }

    protected function parseDefault(array $configuration): array
    {
        $value = null;
        $currency = null;

        if (!empty($configuration['default'])) {
            $default = Json::decode($configuration['default'], true);

            if (!empty($default['value']) || $default['value'] === '0' || $default['value'] === 0) {
                try {
                    $value = $this->prepareFloatValue((string)$default['value'], $configuration);
                } catch (BadRequest $e) {
                    throw new BadRequest(sprintf($this->translate('unexpectedFieldType', 'exceptions', 'ImportFeed'), $value, 'currency'));
                }
            }

            if (!empty($default['currency'])) {
                $currency = (string)$default['currency'];
            }
        }

        return [$value, $currency];
    }
}