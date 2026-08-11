<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField\Cleanup;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('framework')]
#[AsCommand(
    name: 'custom-field:cleanup-non-translatable',
    description: 'Removes per language values of custom fields that are not translatable',
)]
class CleanupNonTranslatableCustomFieldCommand extends Command
{
    public function __construct(
        private readonly CleanupNonTranslatableCustomFieldHandler $handler,
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'name',
            InputArgument::OPTIONAL,
            'Name of the custom field to clean up. All non-translatable custom fields are cleaned up when omitted.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $names = \is_string($name) ? [$name] : $this->getNonTranslatableCustomFieldNames();

        if ($names === []) {
            $io->success('No non-translatable custom fields found.');

            return self::SUCCESS;
        }

        $context = Context::createCLIContext();

        foreach ($names as $customFieldName) {
            $io->text(\sprintf('Cleaning up "%s"', $customFieldName));

            $this->handler->cleanup($customFieldName, $context);
        }

        $io->success(\sprintf('Cleaned up %d custom field(s).', \count($names)));

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function getNonTranslatableCustomFieldNames(): array
    {
        /** @var list<string> $names */
        $names = $this->connection->fetchFirstColumn('SELECT `name` FROM `custom_field` WHERE `translatable` = 0');

        return $names;
    }
}
