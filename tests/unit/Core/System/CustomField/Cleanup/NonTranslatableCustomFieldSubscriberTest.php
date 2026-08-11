<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomField\Cleanup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Cleanup\CleanupNonTranslatableCustomFieldMessage;
use Shopware\Core\System\CustomField\Cleanup\NonTranslatableCustomFieldSubscriber;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Shopware\Core\System\CustomField\CustomFieldEvents;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NonTranslatableCustomFieldSubscriber::class)]
class NonTranslatableCustomFieldSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [
                EntityWriteEvent::class => 'detectChangeset',
                CustomFieldEvents::CUSTOM_FIELD_WRITTEN_EVENT => 'onCustomFieldWritten',
            ],
            NonTranslatableCustomFieldSubscriber::getSubscribedEvents()
        );
    }

    public function testChangesetIsRequestedForTranslatableUpdates(): void
    {
        $command = $this->createUpdateCommand(['translatable' => 0]);

        $this->createSubscriber()->detectChangeset(
            EntityWriteEvent::create(WriteContext::createFromContext(Context::createDefaultContext()), [$command])
        );

        static::assertTrue($command->requiresChangeSet());
    }

    public function testChangesetIsNotRequestedForUnrelatedUpdates(): void
    {
        $command = $this->createUpdateCommand(['active' => 0]);

        $this->createSubscriber()->detectChangeset(
            EntityWriteEvent::create(WriteContext::createFromContext(Context::createDefaultContext()), [$command])
        );

        static::assertFalse($command->requiresChangeSet());
    }

    public function testCleanupIsDispatchedWhenFieldBecomesNonTranslatable(): void
    {
        $bus = new CollectingMessageBus();

        $this->createSubscriber($bus)->onCustomFieldWritten(
            $this->createWrittenEvent(new ChangeSet(
                ['name' => 'shared_flag', 'translatable' => 1],
                ['translatable' => 0],
                false
            ))
        );

        $messages = $bus->getMessages();
        static::assertCount(1, $messages);

        $message = $messages[0]->getMessage();
        static::assertInstanceOf(CleanupNonTranslatableCustomFieldMessage::class, $message);
        static::assertSame('shared_flag', $message->customFieldName);
    }

    public function testCleanupIsNotDispatchedWhenTheValueDidNotChange(): void
    {
        $bus = new CollectingMessageBus();

        // an app that repeats its manifest value must not trigger a cleanup
        $this->createSubscriber($bus)->onCustomFieldWritten(
            $this->createWrittenEvent(new ChangeSet(
                ['name' => 'shared_flag', 'translatable' => 0],
                ['translatable' => 0],
                false
            ))
        );

        static::assertSame([], $bus->getMessages());
    }

    public function testCleanupIsNotDispatchedWhenFieldBecomesTranslatable(): void
    {
        $bus = new CollectingMessageBus();

        $this->createSubscriber($bus)->onCustomFieldWritten(
            $this->createWrittenEvent(new ChangeSet(
                ['name' => 'shared_flag', 'translatable' => 0],
                ['translatable' => 1],
                false
            ))
        );

        static::assertSame([], $bus->getMessages());
    }

    public function testCleanupIsNotDispatchedWithoutChangeset(): void
    {
        $bus = new CollectingMessageBus();

        $this->createSubscriber($bus)->onCustomFieldWritten($this->createWrittenEvent(null));

        static::assertSame([], $bus->getMessages());
    }

    private function createSubscriber(?CollectingMessageBus $bus = null): NonTranslatableCustomFieldSubscriber
    {
        return new NonTranslatableCustomFieldSubscriber($bus ?? new CollectingMessageBus());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createUpdateCommand(array $payload): UpdateCommand
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [CustomFieldDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );

        $primaryKey = ['id' => Uuid::randomBytes()];

        return new UpdateCommand(
            $registry->getByEntityName(CustomFieldDefinition::ENTITY_NAME),
            $payload,
            $primaryKey,
            new EntityExistence(CustomFieldDefinition::ENTITY_NAME, $primaryKey, true, false, false, []),
            '/0'
        );
    }

    private function createWrittenEvent(?ChangeSet $changeSet): EntityWrittenEvent
    {
        $id = Uuid::randomHex();

        return new EntityWrittenEvent(
            CustomFieldDefinition::ENTITY_NAME,
            [
                new EntityWriteResult(
                    $id,
                    ['id' => $id],
                    CustomFieldDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_UPDATE,
                    null,
                    $changeSet
                ),
            ],
            Context::createDefaultContext()
        );
    }
}
