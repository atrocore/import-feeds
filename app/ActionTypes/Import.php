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

namespace Import\ActionTypes;

use Atro\Core\Container;
use Atro\ActionTypes\TypeInterface;
use Atro\Core\EventManager\Event;
use Espo\Core\ServiceFactory;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class Import implements TypeInterface
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function executeViaWorkflow(array $workflowData, Event $event): bool
    {
        $action = $this->getEntityManager()->getEntity('Action', $workflowData['id']);
        $input = new \stdClass();
        $input->entityType = $event->getArgument('entity')->getEntityType();
        $input->entityId = $event->getArgument('entity')->get('id');

        if (!empty($workflow['_relationData'])) {
            $input->_relationData = $workflow['_relationData'];
        }

        return $this->executeNow($action, $input);
    }

    public function executeNow(Entity $action, \stdClass $input): bool
    {
        $payload = empty($action->get('payload')) ? '' : (string)$action->get('payload');

        if (property_exists($input, 'entityId') && property_exists($input, 'entityType')) {
            $entity = $this->getEntityManager()->getRepository($input->entityType)->get($input->entityId);
            $payload = $this->container->get('twig')->renderTemplate($payload, ['entity' => $entity]);
        }

        if (!empty($input->_relationData)) {
            $payload = @json_decode($payload, true);
            $payload['relation'] = [
                'action'       => $input->_relationData['action'],
                'relationName' => $input->_relationData['relationName'],
                'foreignId'    => $input->_relationData['foreignId']
            ];
            $payload = json_encode($payload);
        }

        /** @var \Import\Services\ImportFeed $service */
        $service = $this->getServiceFactory()->create('ImportFeed');

        $importFeed = $service->getEntity($action->get('importFeedId'));
        if (empty($importFeed) || empty($importFeed->get('isActive'))) {
            return false;
        }

        $attachmentId = $importFeed->get('fileId');

        if (!empty($payload)) {
            $attachmentService = $this->getServiceFactory()->create('Attachment');
            $attachment = $attachmentService->createEntity(
                (object)[
                    'contents'    => $payload,
                    'name'        => 'import_' . md5(uniqid()) . '.json',
                    'type'        => 'application/json',
                    'role'        => 'Attachment',
                    'relatedType' => 'ImportFeed',
                    'field'       => 'importFile'
                ]
            );
            if (empty($attachment)) {
                return false;
            }
            $attachmentId = $attachment->get('id');
        }

        $service->runImport($importFeed->get('id'), $attachmentId);

        return true;
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getServiceFactory(): ServiceFactory
    {
        return $this->container->get('serviceFactory');
    }
}