<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1786434961AddTranslatableToCustomField;
use Shopware\Core\System\CustomField\CustomFieldDefinition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1786434961AddTranslatableToCustomField::class)]
class Migration1786434961AddTranslatableToCustomFieldTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrationAddsColumnDefaultingToTranslatableAndIsIdempotent(): void
    {
        $this->rollback();

        static::assertFalse($this->columnExists());

        $migration = new Migration1786434961AddTranslatableToCustomField();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->columnExists());

        $column = $this->connection
            ->createSchemaManager()
            ->introspectTableByUnquotedName(CustomFieldDefinition::ENTITY_NAME)
            ->getColumn('translatable');

        static::assertTrue($column->getNotnull());
        static::assertSame('1', (string) $column->getDefault());

        // existing custom fields keep their per-language behaviour
        $customFieldCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `custom_field`');
        static::assertSame(
            $customFieldCount,
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `custom_field` WHERE `translatable` = 1')
        );
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1786434961,
            (new Migration1786434961AddTranslatableToCustomField())->getCreationTimestamp()
        );
    }

    private function columnExists(): bool
    {
        return TableHelper::columnExists($this->connection, CustomFieldDefinition::ENTITY_NAME, 'translatable');
    }

    private function rollback(): void
    {
        if (!$this->columnExists()) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `custom_field` DROP COLUMN `translatable`');
    }
}
