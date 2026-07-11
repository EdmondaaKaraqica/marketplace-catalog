<?php

namespace App\Command;

use App\Service\CatalogImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:catalog:import',
    description: 'Import the product catalog from the marketplace XML feed',
)]
class CatalogImportCommand extends Command
{
    public function __construct(
        private readonly CatalogImporter $importer,
        #[Autowire('%env(MARKETPLACE_FEED_URL)%')]
        private readonly string $defaultFeedUrl,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'feed',
            InputArgument::OPTIONAL,
            'URL or local path to the XML feed (defaults to MARKETPLACE_FEED_URL)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $source = $input->getArgument('feed') ?: $this->defaultFeedUrl;

        $io->title('Catalog import');
        $io->text(sprintf('Source: <info>%s</info>', $source));

        $io->progressStart();

        try {
            $result = $this->importer->import($source, static fn () => $io->progressAdvance());
        } catch (\Throwable $e) {
            $io->newLine(2);
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->progressFinish();

        $io->success('Import complete.');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Products created', $result->productsCreated],
                ['Products updated', $result->productsUpdated],
                ['Products deleted', $result->productsDeleted],
                ['Categories created', $result->categoriesCreated],
                ['Categories deleted', $result->categoriesDeleted],
            ],
        );

        return Command::SUCCESS;
    }
}
