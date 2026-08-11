<?php
declare(strict_types=1);

namespace Shopware\Core\System\CustomField\Cleanup;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler]
final class CleanupNonTranslatableCustomFieldHandler
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly Connection $connection,
        private readonly DefinitionInstanceRegistry $registry,
        private readonly EntityIndexerRegistry $indexerRegistry,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function __invoke(CleanupNonTranslatableCustomFieldMessage $message): void
    {
        $this->cleanup($message->customFieldName, $message->context);
    }

    public function cleanup(string $customFieldName, Context $context): void
    {
        if (!preg_match(CustomFieldService::CUSTOM_FIELD_NAME_PATTERN, $customFieldName)) {
            // the name is part of the JSON path, and only validated names can be stored
            return;
        }

        foreach ($this->registry->getDefinitions() as $definition) {
            if (!$definition instanceof EntityTranslationDefinition) {
                continue;
            }

            foreach ($this->getCustomFieldsStorageNames($definition) as $storageName) {
                $this->cleanupDefinition($definition, $storageName, $customFieldName, $context);
            }
        }
    }

    private function cleanupDefinition(
        EntityTranslationDefinition $definition,
        string $storageName,
        string $customFieldName,
        Context $context
    ): void {
        $table = $definition->getEntityName();
        $primaryKeys = $this->getPrimaryKeyStorageNames($definition);

        if ($primaryKeys === []) {
            return;
        }

        $selection = implode(', ', array_map(static fn (string $column): string => '`' . $column . '`', $primaryKeys));
        $path = '$."' . $customFieldName . '"';

        while ($this->isStillNonTranslatable($customFieldName)) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT ' . $selection . ' FROM `' . $table . '`
                        WHERE `language_id` != :systemLanguage
                          AND JSON_CONTAINS_PATH(`' . $storageName . '`, \'one\', :path)
                        LIMIT :limit',
                [
                    'systemLanguage' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                    'path' => $path,
                    'limit' => self::BATCH_SIZE,
                ],
                [
                    'limit' => ParameterType::INTEGER,
                ]
            );

            if ($rows === []) {
                return;
            }

            $affected = 0;
            foreach ($rows as $row) {
                $affected += (int) $this->connection->executeStatement(
                    'UPDATE `' . $table . '`
                     SET `' . $storageName . '` = JSON_REMOVE(`' . $storageName . '`, :path)
                     WHERE ' . $this->buildWhere($primaryKeys),
                    [...$row, 'path' => $path]
                );
            }

            if ($affected === 0) {
                // nothing could be removed, stop instead of selecting the same rows forever
                return;
            }

            $this->invalidate($definition, $rows, $context);
        }
    }

    private function isStillNonTranslatable(string $customFieldName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM `custom_field` WHERE `name` = :name AND `translatable` = 0',
            ['name' => $customFieldName]
        );
    }

    /**
     * @param list<string> $primaryKeys
     */
    private function buildWhere(array $primaryKeys): string
    {
        return implode(' AND ', array_map(
            static fn (string $column): string => '`' . $column . '` = :' . $column,
            $primaryKeys
        ));
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function invalidate(EntityTranslationDefinition $definition, array $rows, Context $context): void
    {
        $parentDefinition = $definition->getParentDefinition();
        $foreignKey = $parentDefinition->getEntityName() . '_id';

        $ids = [];
        foreach ($rows as $row) {
            $id = $row[$foreignKey] ?? null;

            if (\is_string($id)) {
                $ids[] = Uuid::fromBytesToHex($id);
            }
        }

        if ($ids === []) {
            return;
        }

        $results = array_map(
            static fn (string $id): EntityWriteResult => new EntityWriteResult(
                $id,
                ['id' => $id],
                $parentDefinition->getEntityName(),
                EntityWriteResult::OPERATION_UPDATE
            ),
            array_values(array_unique($ids))
        );

        $event = EntityWrittenContainerEvent::createWithWrittenEvents(
            [$parentDefinition->getEntityName() => $results],
            $context,
            []
        );

        $this->eventDispatcher->dispatch($event);
        $this->indexerRegistry->refresh($event);
    }

    /**
     * @return list<string>
     */
    private function getCustomFieldsStorageNames(EntityDefinition $definition): array
    {
        $storageNames = [];
        foreach ($definition->getFields() as $field) {
            if ($field instanceof CustomFields) {
                $storageNames[] = $field->getStorageName();
            }
        }

        return $storageNames;
    }

    /**
     * @return list<string>
     */
    private function getPrimaryKeyStorageNames(EntityDefinition $definition): array
    {
        $storageNames = [];
        foreach ($definition->getPrimaryKeys() as $field) {
            if (!$field instanceof StorageAware) {
                continue;
            }

            $storageNames[] = $field->getStorageName();
        }

        return $storageNames;
    }
}
