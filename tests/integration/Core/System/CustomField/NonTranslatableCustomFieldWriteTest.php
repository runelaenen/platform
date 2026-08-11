<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\CustomField;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationCollection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
#[Package('framework')]
class NonTranslatableCustomFieldWriteTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const SHARED_FIELD = 'shared_flag';

    private const TRANSLATED_FIELD = 'local_note';

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    /**
     * @var EntityRepository<CategoryTranslationCollection>
     */
    private EntityRepository $categoryTranslationRepository;

    private Connection $connection;

    private string $deLanguageId;

    protected function setUp(): void
    {
        $this->categoryRepository = static::getContainer()->get('category.repository');
        $this->categoryTranslationRepository = static::getContainer()->get('category_translation.repository');
        $this->connection = static::getContainer()->get(Connection::class);
        $this->deLanguageId = $this->getDeDeLanguageId();

        $this->createCustomFields();
    }

    public function testWriteInForeignLanguageIsStoredInSystemLanguage(): void
    {
        $id = $this->createCategory();

        $this->categoryRepository->update([
            ['id' => $id, 'customFields' => [self::SHARED_FIELD => true]],
        ], $this->createContext($this->deLanguageId));

        static::assertSame([self::SHARED_FIELD => true], $this->fetchCustomFields($id, Defaults::LANGUAGE_SYSTEM));
        static::assertNull($this->fetchCustomFields($id, $this->deLanguageId));

        // the shared value is readable in every language
        foreach ([Defaults::LANGUAGE_SYSTEM, $this->deLanguageId] as $languageId) {
            $category = $this->categoryRepository->search(
                new Criteria([$id]),
                $this->createContext($languageId)
            )->getEntities()->first();

            static::assertNotNull($category);
            static::assertTrue($category->getTranslation('customFields')[self::SHARED_FIELD] ?? null);
        }
    }

    public function testExplicitTranslationsPayloadIsRerouted(): void
    {
        $id = $this->createCategory();

        $this->categoryRepository->update([
            [
                'id' => $id,
                'translations' => [
                    $this->deLanguageId => ['customFields' => [self::SHARED_FIELD => 'from-de']],
                ],
            ],
        ], Context::createDefaultContext());

        static::assertSame([self::SHARED_FIELD => 'from-de'], $this->fetchCustomFields($id, Defaults::LANGUAGE_SYSTEM));
        static::assertNull($this->fetchCustomFields($id, $this->deLanguageId));
    }

    public function testTranslatableCustomFieldsKeepTheirLanguage(): void
    {
        $id = $this->createCategory();

        $this->categoryRepository->update([
            [
                'id' => $id,
                'customFields' => [
                    self::SHARED_FIELD => 'shared',
                    self::TRANSLATED_FIELD => 'german note',
                ],
            ],
        ], $this->createContext($this->deLanguageId));

        static::assertSame([self::SHARED_FIELD => 'shared'], $this->fetchCustomFields($id, Defaults::LANGUAGE_SYSTEM));
        static::assertSame([self::TRANSLATED_FIELD => 'german note'], $this->fetchCustomFields($id, $this->deLanguageId));
    }

    public function testLastTranslationInPayloadOrderWins(): void
    {
        $id = $this->createCategory();

        $this->categoryRepository->update([
            [
                'id' => $id,
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => [self::SHARED_FIELD => 'first']],
                    $this->deLanguageId => ['customFields' => [self::SHARED_FIELD => 'last']],
                ],
            ],
        ], Context::createDefaultContext());

        static::assertSame([self::SHARED_FIELD => 'last'], $this->fetchCustomFields($id, Defaults::LANGUAGE_SYSTEM));
    }

    public function testNullValueClearsTheSharedValue(): void
    {
        $id = $this->createCategory();

        $this->categoryRepository->update([
            ['id' => $id, 'customFields' => [self::SHARED_FIELD => 'value']],
        ], Context::createDefaultContext());

        $this->categoryRepository->update([
            ['id' => $id, 'customFields' => [self::SHARED_FIELD => null]],
        ], $this->createContext($this->deLanguageId));

        static::assertSame([self::SHARED_FIELD => null], $this->fetchCustomFields($id, Defaults::LANGUAGE_SYSTEM));
    }

    public function testDirectTranslationWriteIsRerouted(): void
    {
        $id = $this->createCategory();

        $this->categoryTranslationRepository->upsert([
            [
                'categoryId' => $id,
                'languageId' => $this->deLanguageId,
                'customFields' => [self::SHARED_FIELD => 'direct'],
            ],
        ], Context::createDefaultContext());

        static::assertSame([self::SHARED_FIELD => 'direct'], $this->fetchCustomFields($id, Defaults::LANGUAGE_SYSTEM));
        static::assertNull($this->fetchCustomFields($id, $this->deLanguageId));
    }

    public function testWriteToDeactivatedCustomFieldIsStillRerouted(): void
    {
        $customFieldRepository = static::getContainer()->get('custom_field.repository');
        $customField = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `custom_field` WHERE `name` = :name',
            ['name' => self::SHARED_FIELD]
        );

        $customFieldRepository->update([
            ['id' => $customField, 'active' => false],
        ], Context::createDefaultContext());

        static::getContainer()->get(CustomFieldService::class)->reset();

        $id = $this->createCategory();

        $this->categoryRepository->update([
            ['id' => $id, 'customFields' => [self::SHARED_FIELD => 'inactive']],
        ], $this->createContext($this->deLanguageId));

        static::assertSame([self::SHARED_FIELD => 'inactive'], $this->fetchCustomFields($id, Defaults::LANGUAGE_SYSTEM));
        static::assertNull($this->fetchCustomFields($id, $this->deLanguageId));
    }

    private function createCustomFields(): void
    {
        static::getContainer()->get('custom_field_set.repository')->create([
            [
                'name' => 'non_translatable_test_set',
                'config' => ['label' => ['en-GB' => 'Test set']],
                'relations' => [['entityName' => 'category']],
                'customFields' => [
                    [
                        'name' => self::SHARED_FIELD,
                        'type' => CustomFieldTypes::TEXT,
                        'translatable' => false,
                    ],
                    [
                        'name' => self::TRANSLATED_FIELD,
                        'type' => CustomFieldTypes::TEXT,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        static::getContainer()->get(CustomFieldService::class)->reset();
    }

    private function createCategory(): string
    {
        $id = Uuid::randomHex();

        $this->categoryRepository->create([
            ['id' => $id, 'name' => 'Test category'],
        ], Context::createDefaultContext());

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchCustomFields(string $categoryId, string $languageId): ?array
    {
        $customFields = $this->connection->fetchOne(
            'SELECT `custom_fields` FROM `category_translation` WHERE `category_id` = :id AND `language_id` = :language',
            ['id' => Uuid::fromHexToBytes($categoryId), 'language' => Uuid::fromHexToBytes($languageId)]
        );

        if (!\is_string($customFields)) {
            return null;
        }

        return json_decode($customFields, true, 512, \JSON_THROW_ON_ERROR);
    }

    private function createContext(string $languageId): Context
    {
        return new Context(
            Context::createDefaultContext()->getSource(),
            [],
            Defaults::CURRENCY,
            array_values(array_unique([$languageId, Defaults::LANGUAGE_SYSTEM]))
        );
    }
}
