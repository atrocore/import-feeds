<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Import\FieldConverters;

use Espo\Core\Services\Base;
use Espo\ORM\Entity;
use Espo\Core\Container;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;
use Import\Exceptions\IgnoreAttribute;
use Import\Services\ImportConfiguratorItem;

class Wysiwyg
{
    protected Container $container;
    protected ImportConfiguratorItem $configuratorItem;

    protected array $services = [];

    public function __construct(Container $container, ImportConfiguratorItem $configuratorItem)
    {
        $this->container = $container;
        $this->configuratorItem = $configuratorItem;
    }

    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        $default = empty($config['default']) ? '' : $config['default'];
        $emptyValue = empty($config['emptyValue']) ? '' : (string)$config['emptyValue'];
        $nullValue = empty($config['nullValue']) ? 'Null' : (string)$config['nullValue'];

        if (isset($config['column'][0]) && isset($row[$config['column'][0]])) {
            $value = $row[$config['column'][0]];
            $this->ignoreAttribute($value, $config);
            if (strtolower((string)$value) === strtolower($emptyValue) || $value === '') {
                $value = $default;
            }
            if (strtolower((string)$value) === strtolower($nullValue)) {
                $value = null;
            }
        } else {
            $value = $default;
        }

        if ($value === null) {
            return;
        }

        $inputRow->{$config['name']} = (string)$value;
    }

    public function prepareValue(\stdClass $restore, Entity $entity, array $item): void
    {
        $restore->{$item['name']} = $entity->get($item['name']);
    }

    public function prepareFindExistEntityWhere(array &$where, array $configuration, array $row): void
    {
        $inputRow = new \stdClass();
        $this->convert($inputRow, $configuration, $row);

        $where[$configuration['name']] = $inputRow->{$configuration['name']};
    }

    public function prepareForSaveConfiguratorDefaultField(Entity $entity): void
    {
    }

    public function prepareForOutputConfiguratorDefaultField(Entity $entity): void
    {
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function translate(string $label, string $category = 'labels', string $scope = 'Global'): string
    {
        return $this->container->get('language')->translate($label, $category, $scope);
    }

    protected function getService(string $name): Base
    {
        if (!isset($this->services[$name])) {
            $this->services[$name] = $this->container->get('serviceFactory')->create($name);
        }

        return $this->services[$name];
    }

    protected function ignoreAttribute($value, array $config): void
    {
        if (!isset($config['attributeId'])) {
            return;
        }

        if ($value === $config['markForNoRelation']) {
            throw new IgnoreAttribute();
        }
    }
}
