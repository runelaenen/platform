<?php
declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\System\CustomField\CustomFieldDefinition;

/**
 * @internal
 */
#[Package('framework')]
class Migration1786434961AddTranslatableToCustomField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1786434961;
    }

    public function update(Connection $connection): void
    {
        if ($this->columnExists($connection, CustomFieldDefinition::ENTITY_NAME, 'translatable')) {
            return;
        }

        $this->addColumn($connection, CustomFieldDefinition::ENTITY_NAME, 'translatable', 'TINYINT(1)', false, '1');
    }
}
