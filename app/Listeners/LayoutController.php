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

use Espo\Core\Utils\Json;
use Atro\Core\EventManager\Event;

class LayoutController extends \Atro\Listeners\AbstractListener
{
    public function afterActionRead(Event $event): void
    {
        $scope = $event->getArgument('params')['scope'];

        $name = $event->getArgument('params')['name'];

        $method = 'modify' . $scope . ucfirst($name);

        if (method_exists($this, $method)) {
            $this->{$method}($event);
        }
    }

    protected function modifyScheduledJobDetail(Event $event): void
    {
        $result = Json::decode($event->getArgument('result'), true);

        $newRows = [];
        foreach ($result[0]['rows'] as $row) {
            $newRows[] = $row;
            if ($row[0]['name'] === 'job') {
                $newRows[] = [['name' => 'importFeed'], false];
                $newRows[] = [['name' => 'importFeeds'], false];
                if(!$this->checkIfFieldExists('maximumHoursToLookBack', $result[0]['rows'])){
                    $newRows[] = [['name' => 'maximumHoursToLookBack'], false];
                }
            }
        }

        $result[0]['rows'] = $newRows;

        $event->setArgument('result', Json::encode($result));
    }

    public function checkIfFieldExists(string $fieldName, array $array): bool
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if ($this->checkIfFieldExists($fieldName, $value)) {
                    return true;
                }
            } else if ($key === 'name' && $value === $fieldName) {
                return true;
            }
        }
        return false;
    }
}
