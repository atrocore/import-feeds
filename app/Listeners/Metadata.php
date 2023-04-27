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

namespace Import\Listeners;

use Espo\Core\EventManager\Event;
use Espo\Listeners\AbstractListener;

class Metadata extends AbstractListener
{
    public function modify(Event $event)
    {
        $data = $event->getArgument('data');

        if (empty($data['scopes']['Channel'])) {
            unset($data['entityDefs']['ImportConfiguratorItem']['fields']['channel']);
            unset($data['entityDefs']['ImportConfiguratorItem']['links']['channel']);
        }

        if (empty($data['scopes']['Attribute'])) {
            unset($data['entityDefs']['ImportConfiguratorItem']['fields']['attribute']);
            unset($data['entityDefs']['ImportConfiguratorItem']['links']['attribute']);
        }

        foreach ($data['entityDefs'] as $scope => $scopeData) {
            if (empty($scopeData['fields'])) {
                continue;
            }

            $data['entityDefs'][$scope]['fields']['filterCreateImportJob'] = [
                'type'                      => 'enum',
                'notStorable'               => true,
                'view'                      => 'import:views/fields/filter-import-job',
                'scope'                     => $scope,
                'layoutDetailDisabled'      => true,
                'layoutDetailSmallDisabled' => true,
                'layoutListDisabled'        => true,
                'layoutListSmallDisabled'   => true,
                'layoutMassUpdateDisabled'  => true,
                'exportDisabled'            => true,
                'importDisabled'            => true,
                'textFilterDisabled'        => true,
                'emHidden'                  => true,
            ];

            $data['entityDefs'][$scope]['fields']['filterUpdateImportJob'] = [
                'type'                      => 'enum',
                'notStorable'               => true,
                'view'                      => 'import:views/fields/filter-import-job',
                'scope'                     => $scope,
                'layoutDetailDisabled'      => true,
                'layoutDetailSmallDisabled' => true,
                'layoutListDisabled'        => true,
                'layoutListSmallDisabled'   => true,
                'layoutMassUpdateDisabled'  => true,
                'exportDisabled'            => true,
                'importDisabled'            => true,
                'textFilterDisabled'        => true,
                'emHidden'                  => true,
            ];
        }

        if (!empty($data['clientDefs']['ImportFeed']['relationshipPanels']['configuratorItems'])) {
            $data['clientDefs']['ImportFeed']['relationshipPanels']['configuratorItems']['dragDrop']['maxSize'] = $this->getConfig()->get('recordsPerPageSmall', 20);
        }

        $data['entityDefs']['ImportFeed']['fields']['lastStatus'] = [
            'type' => 'enum',
            'notStorable' => true,
            'readOnly' => true,
            'optionsIds' => $data['entityDefs']['ImportJob']['fields']['state']['optionsIds'],
            'options' => $data['entityDefs']['ImportJob']['fields']['state']['options'],
            'optionColors' => $data['entityDefs']['ImportJob']['fields']['state']['optionColors']
        ];

        $event->setArgument('data', $data);
    }
}
