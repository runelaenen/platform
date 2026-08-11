<?php
declare(strict_types=1);

namespace Shopware\Core\System\CustomField\Cleanup;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class CleanupNonTranslatableCustomFieldMessage implements AsyncMessageInterface
{
    public function __construct(
        public string $customFieldName,
        public Context $context,
    ) {
    }
}
