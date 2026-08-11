<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField\DataAbstractionLayer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\System\CustomField\CustomFieldService;

/**
 * @internal
 */
#[Package('framework')]
readonly class NonTranslatableCustomFieldRerouter
{
    private const LANGUAGE_STORAGE_NAME = 'language_id';

    public function __construct(private CustomFieldService $customFieldService)
    {
    }

    /**
     * @param array<string, mixed> $translations
     *
     * @return array<int|string, mixed>
     */
    public function rerouteTranslations(EntityDefinition $translationDefinition, array $translations): array
    {
        $properties = $this->getCustomFieldsProperties($translationDefinition);
        $languageProperty = $this->getLanguageProperty($translationDefinition);

        if ($properties === [] || $languageProperty === null || !$this->customFieldService->hasNonTranslatableCustomFields()) {
            return $translations;
        }

        $shared = [];

        foreach ($translations as $languageId => $translation) {
            if (!\is_array($translation)) {
                continue;
            }

            foreach ($properties as $property) {
                [$translation, $sharedValues] = $this->extractSharedValues($translation, $property);

                // later values win, mirroring the order the payload would have been written in
                $shared[$property] = [...$shared[$property] ?? [], ...$sharedValues];
            }

            $translations[$languageId] = $translation;
        }

        $shared = array_filter($shared);

        if ($shared !== []) {
            $systemTranslation = $translations[Defaults::LANGUAGE_SYSTEM] ?? [];
            $systemTranslation = \is_array($systemTranslation) ? $systemTranslation : [];

            foreach ($shared as $property => $sharedValues) {
                $target = $systemTranslation[$property] ?? [];
                $systemTranslation[$property] = [...\is_array($target) ? $target : [], ...$sharedValues];
            }

            $systemTranslation[$languageProperty] = Defaults::LANGUAGE_SYSTEM;
            $translations[Defaults::LANGUAGE_SYSTEM] = $systemTranslation;
        }

        return $this->dropEmptyTranslations($translations, $languageProperty, [$languageProperty]);
    }

    /**
     * @param array<int|string, mixed> $rawData
     *
     * @return array<int|string, mixed>
     */
    public function rerouteTranslationRows(EntityDefinition $translationDefinition, array $rawData, string $contextLanguageId): array
    {
        $properties = $this->getCustomFieldsProperties($translationDefinition);
        $languageProperty = $this->getLanguageProperty($translationDefinition);

        if ($properties === [] || $languageProperty === null || !$this->customFieldService->hasNonTranslatableCustomFields()) {
            return $rawData;
        }

        $identifierProperties = $this->getIdentifierProperties($translationDefinition, $languageProperty);

        /** @var array<string, array{identifier: array<string, mixed>, values: array<string, array<string, mixed>>}> $sharedRows */
        $sharedRows = [];

        foreach ($rawData as $index => $row) {
            if (!\is_array($row)) {
                continue;
            }

            $identifier = [];
            foreach ($identifierProperties as $identifierProperty) {
                if (!isset($row[$identifierProperty])) {
                    // the row cannot be related to a single entity, leave it untouched
                    continue 2;
                }

                $identifier[$identifierProperty] = $row[$identifierProperty];
            }

            $group = Hasher::hash(serialize($identifier), 'md5');

            foreach ($properties as $property) {
                [$row, $sharedValues] = $this->extractSharedValues($row, $property);

                if ($sharedValues === []) {
                    continue;
                }

                $sharedRows[$group]['identifier'] = $identifier;
                $sharedRows[$group]['values'][$property] = [...$sharedRows[$group]['values'][$property] ?? [], ...$sharedValues];
            }

            $rawData[$index] = $row;
        }

        foreach ($sharedRows as $sharedRow) {
            $rawData = $this->mergeIntoSystemLanguageRow(
                $rawData,
                $sharedRow['identifier'],
                $sharedRow['values'],
                $languageProperty,
                $contextLanguageId
            );
        }

        return $this->dropEmptyTranslations($rawData, $languageProperty, [$languageProperty, ...$identifierProperties]);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function extractSharedValues(array $payload, string $property): array
    {
        $customFields = $payload[$property] ?? null;

        if (!\is_array($customFields)) {
            return [$payload, []];
        }

        $shared = [];
        foreach ($customFields as $customFieldName => $value) {
            if ($this->customFieldService->isTranslatable((string) $customFieldName)) {
                continue;
            }

            $shared[$customFieldName] = $value;
            unset($customFields[$customFieldName]);
        }

        if ($shared === []) {
            return [$payload, []];
        }

        if ($customFields === []) {
            unset($payload[$property]);
        } else {
            $payload[$property] = $customFields;
        }

        return [$payload, $shared];
    }

    /**
     * @param array<int|string, mixed> $rawData
     * @param array<string, mixed> $identifier
     * @param array<string, array<string, mixed>> $values
     *
     * @return array<int|string, mixed>
     */
    private function mergeIntoSystemLanguageRow(
        array $rawData,
        array $identifier,
        array $values,
        string $languageProperty,
        string $contextLanguageId
    ): array {
        foreach ($rawData as $index => $row) {
            if (!\is_array($row)) {
                continue;
            }

            if (($row[$languageProperty] ?? $contextLanguageId) !== Defaults::LANGUAGE_SYSTEM) {
                continue;
            }

            foreach ($identifier as $property => $value) {
                if (($row[$property] ?? null) !== $value) {
                    continue 2;
                }
            }

            foreach ($values as $property => $customFields) {
                $target = $row[$property] ?? [];
                $row[$property] = [...\is_array($target) ? $target : [], ...$customFields];
            }

            $row[$languageProperty] = Defaults::LANGUAGE_SYSTEM;
            $rawData[$index] = $row;

            return $rawData;
        }

        $rawData[] = [...$identifier, ...$values, $languageProperty => Defaults::LANGUAGE_SYSTEM];

        return $rawData;
    }

    /**
     * @param array<int|string, mixed> $translations
     * @param list<string> $structuralProperties
     *
     * @return array<int|string, mixed>
     */
    private function dropEmptyTranslations(array $translations, string $languageProperty, array $structuralProperties): array
    {
        foreach ($translations as $key => $translation) {
            if ($key === Defaults::LANGUAGE_SYSTEM || !\is_array($translation)) {
                continue;
            }

            if (($translation[$languageProperty] ?? null) === Defaults::LANGUAGE_SYSTEM) {
                continue;
            }

            $payload = $translation;
            foreach ($structuralProperties as $structuralProperty) {
                unset($payload[$structuralProperty]);
            }

            if ($payload === []) {
                unset($translations[$key]);
            }
        }

        return $translations;
    }

    /**
     * @return list<string>
     */
    private function getCustomFieldsProperties(EntityDefinition $definition): array
    {
        $properties = [];
        foreach ($definition->getFields() as $field) {
            if ($field instanceof CustomFields) {
                $properties[] = $field->getPropertyName();
            }
        }

        return $properties;
    }

    private function getLanguageProperty(EntityDefinition $definition): ?string
    {
        return $definition->getFields()->getByStorageName(self::LANGUAGE_STORAGE_NAME)?->getPropertyName();
    }

    /**
     * @return list<string>
     */
    private function getIdentifierProperties(EntityDefinition $definition, string $languageProperty): array
    {
        $properties = [];
        foreach ($definition->getPrimaryKeys() as $field) {
            if ($field->getPropertyName() === $languageProperty) {
                continue;
            }

            $properties[] = $field->getPropertyName();
        }

        return $properties;
    }
}
