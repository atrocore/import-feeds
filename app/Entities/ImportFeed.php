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

namespace Import\Entities;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Entities\Base;
use Espo\Core\Utils\Json;

class ImportFeed extends Base
{
    protected $entityType = "ImportFeed";

    public function setFeedField(string $name, $value): void
    {
        $data = [];
        if (!empty($this->get('data'))) {
            $data = Json::decode(Json::encode($this->get('data')), true);
        }

        $data['feedFields'][$name] = $value;

        $this->set('data', $data);
    }

    public function getFeedField(string $name)
    {
        $data = $this->getFeedFields();

        if (!isset($data[$name])) {
            return null;
        }

        return $data[$name];
    }

    public function getFeedFields(): array
    {
        if (!empty($data = $this->get('data'))) {
            $data = Json::decode(Json::encode($data), true);
            if (!empty($data['feedFields']) && is_array($data['feedFields'])) {
                return $data['feedFields'];
            }
        }

        return [];
    }

    public function getDelimiter(): string
    {
        if (empty($this->getFeedField('fileFieldDelimiter'))) {
            return ';';
        }

        return (string)$this->getFeedField('fileFieldDelimiter');
    }

    public function getEnclosure(): string
    {
        if (empty($this->getFeedField('fileTextQualifier'))) {
            return '"';
        }

        return $this->getFeedField('fileTextQualifier') == 'singleQuote' ? "'" : '"';
    }

    public function isFileHeaderRow(): bool
    {
        return !empty($this->getFeedField('isFileHeaderRow'));
    }

    public function getConfiguratorData(): array
    {
        $result = [];

        if (empty($configuratorItems = $this->get('configuratorItems')) || count($configuratorItems) === 0) {
            $language = $this->getEntityManager()->getRepository('ImportFeed')->getLanguage();
            throw new BadRequest($language->translate('configuratorEmpty', 'exceptions', 'ImportFeed'));
        }

        $result['entity'] = $this->getFeedField('entity');
        $result['idField'] = [];
        $result['priority'] = empty($this->get('priority')) ? 'Normal' : (string)$this->get('priority');
        $result['excludedNodes'] = $this->getFeedField('excludedNodes');
        $result['keptStringNodes'] = $this->getFeedField('keptStringNodes');
        $result['delimiter'] = $this->getFeedField('delimiter');
        $result['configuration'] = [];

        foreach ($configuratorItems as $item) {
            if (!empty($item->get('entityIdentifier'))) {
                $result['idField'][] = $item->get('name');
            }

            $result['configuration'][] = [
                'name'                      => $item->get('name'),
                'column'                    => $item->get('column'),
                'createIfNotExist'          => !empty($item->get('createIfNotExist')),
                'replaceArray'              => !empty($item->get('replaceArray')),
                'foreignColumn'             => $item->get('foreignColumn'),
                'foreignImportBy'           => $item->get('foreignImportBy'),
                'default'                   => $item->get('default'),
                'importBy'                  => $item->get('importBy'),
                'type'                      => $item->get('type'),
                'attributeId'               => $item->get('attributeId'),
                'scope'                     => $item->get('scope'),
                'channelId'                 => $item->get('channelId'),
                'locale'                    => $item->get('locale'),
                'entity'                    => $result['entity'],
                'delimiter'                 => $result['delimiter'],
                'emptyValue'                => $this->getFeedField('emptyValue'),
                'nullValue'                 => $this->getFeedField('nullValue'),
                'decimalMark'               => $this->getFeedField('decimalMark'),
                'thousandSeparator'         => $this->getFeedField('thousandSeparator'),
                'markForNotLinkedAttribute' => $this->getFeedField('markForNotLinkedAttribute'),
                'fieldDelimiterForRelation' => $this->getFeedField('fieldDelimiterForRelation'),
                'attributeValue'            => $item->get('attributeValue')
            ];
        }

        return $result;
    }
}
