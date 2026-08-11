<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationDefinition;
use Shopware\Core\System\CustomField\Api\CustomFieldSetActionController;
use Shopware\Core\System\CustomField\Cleanup\CleanupNonTranslatableCustomFieldCommand;
use Shopware\Core\System\CustomField\Cleanup\CleanupNonTranslatableCustomFieldHandler;
use Shopware\Core\System\CustomField\Cleanup\NonTranslatableCustomFieldSubscriber;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\System\CustomField\CustomFieldSetPersister;
use Shopware\Core\System\CustomField\DataAbstractionLayer\NonTranslatableCustomFieldRerouter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Messenger\MessageBusInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CustomFieldDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CustomFieldSetDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CustomFieldSetRelationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CustomFieldSetActionController::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(CustomFieldService::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(NonTranslatableCustomFieldRerouter::class)
        ->args([
            service(CustomFieldService::class),
        ]);

    $services->set(NonTranslatableCustomFieldSubscriber::class)
        ->args([
            service(MessageBusInterface::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CleanupNonTranslatableCustomFieldHandler::class)
        ->args([
            service(Connection::class),
            service(DefinitionInstanceRegistry::class),
            service(EntityIndexerRegistry::class),
            service('event_dispatcher'),
        ])
        ->tag('messenger.message_handler');

    $services->set(CleanupNonTranslatableCustomFieldCommand::class)
        ->args([
            service(CleanupNonTranslatableCustomFieldHandler::class),
            service(Connection::class),
        ])
        ->tag('console.command');

    $services->set(CustomFieldSetPersister::class)
        ->args([
            service('custom_field_set.repository'),
            service(Connection::class),
            service('custom_field_set_relation.repository'),
            service('custom_field.repository'),
        ]);
};
