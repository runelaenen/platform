<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomField\DataAbstractionLayer\_fixtures;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Language\LanguageDefinition;

/**
 * Mirrors the shape of an entity translation definition without pulling in a concrete one.
 *
 * @internal
 */
class TranslationDefinitionFixture extends EntityDefinition
{
    final public const ENTITY_NAME = 'fixture_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('fixture_id', 'fixtureId', FixtureDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            new StringField('name', 'name'),
            new CustomFields(),
        ]);
    }
}
