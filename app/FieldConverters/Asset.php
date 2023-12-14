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

class Asset extends Link
{
    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        $config['relEntityName'] = 'Asset';

        if (isset($config['attributeId'])) {
            $config['importBy'] = ['url'];
        }

        if ($config['importBy'] === ['url']) {
            $config['createIfNotExist'] = true;
        }

        parent::convert($inputRow, $config, $row);

        $key = $config['name'] . 'Id';

        if (property_exists($inputRow, $key)) {
            $asset = $this->getEntityManager()->getEntity('Asset', $inputRow->$key);
            unset($inputRow->$key);
            if (!empty($asset) && !empty($asset->get('fileId'))) {
                $inputRow->$key = $asset->get('fileId');
            }
        }
    }

    public function prepareValue(\stdClass $restore, Entity $entity, array $item): void
    {
        $value = null;

        if (!empty($foreign = $entity->get($item['name']))) {
            $value = is_string($foreign) ? $foreign : $foreign->get('id');
        }

        $restore->{$item['name'] . 'Id'} = $value;
    }

    public function prepareFindExistEntityWhere(array &$where, array $configuration, array $row): void
    {
    }

    public function prepareForSaveConfiguratorDefaultField(Entity $entity): void
    {
        if ($entity->has('defaultId')) {
            $entity->set('default', empty($entity->get('defaultId')) ? null : $entity->get('defaultId'));
        }
    }

    public function prepareForOutputConfiguratorDefaultField(Entity $entity): void
    {
        $entity->set('defaultId', null);
        $entity->set('defaultName', null);
        $entity->set('defaultPathsData', null);
        if (!empty($entity->get('default'))) {
            $entity->set('defaultId', $entity->get('default'));
            $relEntity = $this->getEntityManager()->getEntity('Attachment', $entity->get('defaultId'));
            $entity->set('defaultName', empty($relEntity) ? $entity->get('defaultId') : $relEntity->get('name'));
            $entity->set('defaultPathsData', $this->getEntityManager()->getRepository('Attachment')->getAttachmentPathsData($relEntity));
        }
    }
}
