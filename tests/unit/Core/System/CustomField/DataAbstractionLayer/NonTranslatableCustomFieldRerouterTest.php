<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomField\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\System\CustomField\DataAbstractionLayer\NonTranslatableCustomFieldRerouter;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Doctrine\FakeConnection;
use Shopware\Tests\Unit\Core\System\CustomField\DataAbstractionLayer\_fixtures\FixtureDefinition;
use Shopware\Tests\Unit\Core\System\CustomField\DataAbstractionLayer\_fixtures\TranslationDefinitionFixture;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NonTranslatableCustomFieldRerouter::class)]
class NonTranslatableCustomFieldRerouterTest extends TestCase
{
    private const GERMAN = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const FRENCH = '0f8e7d6c5b4a39281706f5e4d3c2b1a0';

    private EntityDefinition $definition;

    protected function setUp(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [TranslationDefinitionFixture::class, FixtureDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );

        $this->definition = $registry->getByEntityName(TranslationDefinitionFixture::ENTITY_NAME);
    }

    public function testTranslationsAreUntouchedWithoutNonTranslatableCustomFields(): void
    {
        $translations = [
            self::GERMAN => ['customFields' => ['shared' => true], 'languageId' => self::GERMAN],
        ];

        static::assertSame($translations, $this->createRerouter([])->rerouteTranslations($this->definition, $translations));
    }

    public function testSharedValueIsMovedIntoTheSystemLanguage(): void
    {
        $translations = [
            self::GERMAN => ['customFields' => ['shared' => true], 'languageId' => self::GERMAN],
        ];

        $result = $this->createRerouter(['shared'])->rerouteTranslations($this->definition, $translations);

        static::assertSame([
            Defaults::LANGUAGE_SYSTEM => [
                'customFields' => ['shared' => true],
                'languageId' => Defaults::LANGUAGE_SYSTEM,
            ],
        ], $result);
    }

    public function testTranslatableCustomFieldsStayInTheirLanguage(): void
    {
        $translations = [
            self::GERMAN => [
                'customFields' => ['shared' => true, 'per_language' => 'de'],
                'languageId' => self::GERMAN,
            ],
        ];

        $result = $this->createRerouter(['shared'])->rerouteTranslations($this->definition, $translations);

        static::assertSame([
            self::GERMAN => [
                'customFields' => ['per_language' => 'de'],
                'languageId' => self::GERMAN,
            ],
            Defaults::LANGUAGE_SYSTEM => [
                'customFields' => ['shared' => true],
                'languageId' => Defaults::LANGUAGE_SYSTEM,
            ],
        ], $result);
    }

    public function testOtherTranslatedValuesKeepTheirTranslation(): void
    {
        $translations = [
            self::GERMAN => [
                'name' => 'Deutscher Name',
                'customFields' => ['shared' => true],
                'languageId' => self::GERMAN,
            ],
        ];

        $result = $this->createRerouter(['shared'])->rerouteTranslations($this->definition, $translations);

        static::assertSame([
            self::GERMAN => ['name' => 'Deutscher Name', 'languageId' => self::GERMAN],
            Defaults::LANGUAGE_SYSTEM => [
                'customFields' => ['shared' => true],
                'languageId' => Defaults::LANGUAGE_SYSTEM,
            ],
        ], $result);
    }

    public function testLastLanguageInPayloadOrderWins(): void
    {
        $translations = [
            self::GERMAN => ['customFields' => ['shared' => 'de'], 'languageId' => self::GERMAN],
            self::FRENCH => ['customFields' => ['shared' => 'fr'], 'languageId' => self::FRENCH],
        ];

        $result = $this->createRerouter(['shared'])->rerouteTranslations($this->definition, $translations);

        static::assertSame(['shared' => 'fr'], $result[Defaults::LANGUAGE_SYSTEM]['customFields']);
    }

    public function testSharedValueOverwritesAnExistingSystemLanguageValue(): void
    {
        $translations = [
            Defaults::LANGUAGE_SYSTEM => ['customFields' => ['shared' => 'system'], 'languageId' => Defaults::LANGUAGE_SYSTEM],
            self::FRENCH => ['customFields' => ['shared' => 'fr'], 'languageId' => self::FRENCH],
        ];

        $result = $this->createRerouter(['shared'])->rerouteTranslations($this->definition, $translations);

        static::assertSame(['shared' => 'fr'], $result[Defaults::LANGUAGE_SYSTEM]['customFields']);
        static::assertArrayNotHasKey(self::FRENCH, $result);
    }

    public function testNullValueIsRerouted(): void
    {
        $translations = [
            self::FRENCH => ['customFields' => ['shared' => null], 'languageId' => self::FRENCH],
        ];

        $result = $this->createRerouter(['shared'])->rerouteTranslations($this->definition, $translations);

        static::assertSame([
            Defaults::LANGUAGE_SYSTEM => [
                'customFields' => ['shared' => null],
                'languageId' => Defaults::LANGUAGE_SYSTEM,
            ],
        ], $result);
    }

    public function testWholeObjectClearIsNotRerouted(): void
    {
        $translations = [
            self::FRENCH => ['customFields' => null, 'languageId' => self::FRENCH],
        ];

        $result = $this->createRerouter(['shared'])->rerouteTranslations($this->definition, $translations);

        static::assertSame($translations, $result);
    }

    public function testSystemLanguageTranslationIsCreatedWhenMissing(): void
    {
        $translations = [
            self::FRENCH => [
                'name' => 'Nom',
                'customFields' => ['shared' => 1],
                'languageId' => self::FRENCH,
            ],
        ];

        $result = $this->createRerouter(['shared'])->rerouteTranslations($this->definition, $translations);

        static::assertArrayHasKey(Defaults::LANGUAGE_SYSTEM, $result);
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $result[Defaults::LANGUAGE_SYSTEM]['languageId']);
        static::assertSame(['shared' => 1], $result[Defaults::LANGUAGE_SYSTEM]['customFields']);
        static::assertSame(['name' => 'Nom', 'languageId' => self::FRENCH], $result[self::FRENCH]);
    }

    public function testTranslationRowIsRerouted(): void
    {
        $fixtureId = Uuid::randomHex();

        $rawData = [
            ['fixtureId' => $fixtureId, 'languageId' => self::FRENCH, 'customFields' => ['shared' => 'fr']],
        ];

        $result = $this->createRerouter(['shared'])->rerouteTranslationRows($this->definition, $rawData, self::GERMAN);

        static::assertSame([
            1 => [
                'fixtureId' => $fixtureId,
                'customFields' => ['shared' => 'fr'],
                'languageId' => Defaults::LANGUAGE_SYSTEM,
            ],
        ], $result);
    }

    public function testTranslationRowKeepsTranslatableValues(): void
    {
        $fixtureId = Uuid::randomHex();

        $rawData = [
            [
                'fixtureId' => $fixtureId,
                'languageId' => self::FRENCH,
                'name' => 'Nom',
                'customFields' => ['shared' => 'fr', 'per_language' => 'fr'],
            ],
        ];

        $result = $this->createRerouter(['shared'])->rerouteTranslationRows($this->definition, $rawData, self::GERMAN);

        static::assertSame([
            0 => [
                'fixtureId' => $fixtureId,
                'languageId' => self::FRENCH,
                'name' => 'Nom',
                'customFields' => ['per_language' => 'fr'],
            ],
            1 => [
                'fixtureId' => $fixtureId,
                'customFields' => ['shared' => 'fr'],
                'languageId' => Defaults::LANGUAGE_SYSTEM,
            ],
        ], $result);
    }

    public function testTranslationRowIsMergedIntoAnExistingSystemLanguageRow(): void
    {
        $fixtureId = Uuid::randomHex();

        $rawData = [
            ['fixtureId' => $fixtureId, 'languageId' => Defaults::LANGUAGE_SYSTEM, 'name' => 'Name'],
            ['fixtureId' => $fixtureId, 'languageId' => self::FRENCH, 'customFields' => ['shared' => 'fr']],
        ];

        $result = $this->createRerouter(['shared'])->rerouteTranslationRows($this->definition, $rawData, self::GERMAN);

        static::assertSame([
            0 => [
                'fixtureId' => $fixtureId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'name' => 'Name',
                'customFields' => ['shared' => 'fr'],
            ],
        ], $result);
    }

    public function testTranslationRowsOfDifferentEntitiesAreNotMerged(): void
    {
        $first = Uuid::randomHex();
        $second = Uuid::randomHex();

        $rawData = [
            ['fixtureId' => $first, 'languageId' => self::FRENCH, 'customFields' => ['shared' => 'first']],
            ['fixtureId' => $second, 'languageId' => self::FRENCH, 'customFields' => ['shared' => 'second']],
        ];

        $result = $this->createRerouter(['shared'])->rerouteTranslationRows($this->definition, $rawData, self::GERMAN);

        static::assertCount(2, $result);
        static::assertSame(['shared' => 'first'], $result[2]['customFields']);
        static::assertSame($first, $result[2]['fixtureId']);
        static::assertSame(['shared' => 'second'], $result[3]['customFields']);
        static::assertSame($second, $result[3]['fixtureId']);
    }

    public function testTranslationRowWithoutLanguageUsesTheContextLanguage(): void
    {
        $fixtureId = Uuid::randomHex();

        $rawData = [
            ['fixtureId' => $fixtureId, 'customFields' => ['shared' => 'context']],
        ];

        $result = $this->createRerouter(['shared'])->rerouteTranslationRows($this->definition, $rawData, self::GERMAN);

        static::assertSame([
            1 => [
                'fixtureId' => $fixtureId,
                'customFields' => ['shared' => 'context'],
                'languageId' => Defaults::LANGUAGE_SYSTEM,
            ],
        ], $result);
    }

    public function testTranslationRowsAreUntouchedWithoutNonTranslatableCustomFields(): void
    {
        $rawData = [
            ['fixtureId' => Uuid::randomHex(), 'languageId' => self::FRENCH, 'customFields' => ['shared' => 'fr']],
        ];

        static::assertSame($rawData, $this->createRerouter([])->rerouteTranslationRows($this->definition, $rawData, self::GERMAN));
    }

    /**
     * @param list<string> $nonTranslatableCustomFields
     */
    private function createRerouter(array $nonTranslatableCustomFields): NonTranslatableCustomFieldRerouter
    {
        $rows = array_map(static fn (string $name): array => [$name], $nonTranslatableCustomFields);

        return new NonTranslatableCustomFieldRerouter(new CustomFieldService(new FakeConnection($rows)));
    }
}
