<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField\Cleanup;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Shopware\Core\System\CustomField\CustomFieldEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class NonTranslatableCustomFieldSubscriber implements EventSubscriberInterface
{
    public function __construct(private MessageBusInterface $messageBus)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteEvent::class => 'detectChangeset',
            CustomFieldEvents::CUSTOM_FIELD_WRITTEN_EVENT => 'onCustomFieldWritten',
        ];
    }

    public function detectChangeset(EntityWriteEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if (!$command instanceof UpdateCommand) {
                continue;
            }

            if ($command->getEntityName() !== CustomFieldDefinition::ENTITY_NAME) {
                continue;
            }

            if (!\array_key_exists('translatable', $command->getPayload())) {
                continue;
            }

            $command->requestChangeSet();
        }
    }

    public function onCustomFieldWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $result) {
            $changeSet = $result->getChangeSet();

            if ($changeSet === null) {
                continue;
            }

            if (!$changeSet->hasChanged('translatable') || (bool) $changeSet->getAfter('translatable') !== false) {
                continue;
            }

            $name = $changeSet->getBefore('name');

            if (!\is_string($name) || $name === '') {
                continue;
            }

            $this->messageBus->dispatch(new CleanupNonTranslatableCustomFieldMessage($name, $event->getContext()));
        }
    }
}
