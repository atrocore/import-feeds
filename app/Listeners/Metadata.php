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

namespace Import\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;

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
            'filterDisabled' => true,
            'readOnly' => true,
            'optionsIds' => $data['entityDefs']['ImportJob']['fields']['state']['optionsIds'],
            'options' => $data['entityDefs']['ImportJob']['fields']['state']['options'],
            'optionColors' => $data['entityDefs']['ImportJob']['fields']['state']['optionColors']
        ];

        $data['clientDefs']['Action']['dynamicLogic']['fields']['payload']['visible']['conditionGroup'][0]['type'] = 'in';
        $data['clientDefs']['Action']['dynamicLogic']['fields']['payload']['visible']['conditionGroup'][0]['attribute'] = 'type';
        $data['clientDefs']['Action']['dynamicLogic']['fields']['payload']['visible']['conditionGroup'][0]['value'][] = 'import';

        $event->setArgument('data', $data);
    }
}
