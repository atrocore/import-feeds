<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Import\FieldConverters;

use Espo\ORM\Entity;

class ExtensibleMultiEnum extends LinkMultiple
{
    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        $config['replaceArray'] = true;
        parent::convert($inputRow, $config, $row);
    }

    public function prepareValue(\stdClass $restore, Entity $entity, array $item): void
    {
        $fieldName = $this->getFieldName($item);
        $restore->$fieldName = $entity->get($fieldName);
    }

    protected function convertItem(array $config, array $column, array $row): ?string
    {
        $input = new \stdClass();
        $this
            ->getService('ImportConfiguratorItem')
            ->getFieldConverter('extensibleEnum')
            ->convert($input, array_merge($config, $column, ['default' => null]), $row);

        $key = $config['name'];
        if (property_exists($input, $key) && $input->$key !== null) {
            return $input->$key;
        }

        return null;
    }

    protected function getFieldName(array $config): string
    {
        return $config['name'];
    }

    protected function getForeignEntityName(array $config): string
    {
        return 'ExtensibleEnumOption';
    }
}
