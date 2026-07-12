<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Category;
use App\Entity\Product;
use App\Service\CatalogImporter;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the catalog import (the core business logic).
 *
 * Runs against a throwaway in-memory SQLite database so it needs no external
 * services and can run anywhere. Each test starts from an empty schema.
 */
final class CatalogImporterTest extends TestCase
{
    private EntityManagerInterface $em;

    /** @var string[] temp feed files to clean up */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            [__DIR__ . '/../../src/Entity'],
            true,
        );
        $connection = DriverManager::getConnection(
            ['driver' => 'pdo_sqlite', 'memory' => true],
            $config,
        );
        $this->em = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($this->em);
        $schemaTool->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
    }

    public function testImportCreatesProductsAndTheCategoryTree(): void
    {
        $feed = $this->feed([
            ['UltraBook', 'Electronics | Computers | Laptops', '10001', '1299.00', '12'],
            ['Tower', 'Electronics | Computers | Desktops', '10002', '899.50', '7'],
        ]);

        $result = (new CatalogImporter($this->em))->import($feed);

        self::assertSame(2, $result->productsCreated);
        self::assertSame(0, $result->productsUpdated);
        // Electronics, Electronics|Computers, ...|Laptops, ...|Desktops
        self::assertSame(4, $result->categoriesCreated);
        self::assertSame(2, $this->countProducts());
        self::assertSame(4, $this->countCategories());
    }

    public function testReimportingTheSameFeedIsIdempotent(): void
    {
        $rows = [['UltraBook', 'Electronics | Computers | Laptops', '10001', '1299.00', '12']];
        $importer = new CatalogImporter($this->em);

        $importer->import($this->feed($rows));
        $second = $importer->import($this->feed($rows));

        self::assertSame(0, $second->productsCreated);
        self::assertSame(1, $second->productsUpdated);
        self::assertSame(0, $second->productsDeleted);
        self::assertSame(0, $second->categoriesCreated);
        self::assertSame(1, $this->countProducts());
    }

    public function testUpdatesExistingAndDeletesRowsMissingFromTheFeed(): void
    {
        $importer = new CatalogImporter($this->em);

        $importer->import($this->feed([
            ['UltraBook', 'Electronics | Computers | Laptops', '10001', '1299.00', '12'],
            ['Table', 'Home | Furniture | Tables', '10008', '749.00', '3'],
        ]));

        // 10001 price changed; 10008 removed entirely
        $result = $importer->import($this->feed([
            ['UltraBook', 'Electronics | Computers | Laptops', '10001', '1199.00', '12'],
        ]));

        self::assertSame(0, $result->productsCreated);
        self::assertSame(1, $result->productsUpdated);
        self::assertSame(1, $result->productsDeleted);
        // the now-empty "Home | Furniture | Tables" branch is removed
        self::assertSame(3, $result->categoriesDeleted);

        $updated = $this->findProductBySku('10001');
        self::assertNotNull($updated);
        self::assertSame(1199.0, (float) $updated->getPrice()); // compare numeric value (SQLite drops trailing zeros; Postgres keeps '1199.00')
        self::assertNull($this->findProductBySku('10008'));
    }

    public function testSkipsRowsWithoutSku(): void
    {
        $feed = $this->feed([
            ['Valid', 'A | B', '1', '1.00', '1'],
            ['Broken (no sku)', 'A | B', '', '1.00', '1'],
        ]);

        $result = (new CatalogImporter($this->em))->import($feed);

        self::assertSame(1, $result->productsCreated);
        self::assertSame(1, $this->countProducts());
    }

    public function testTrimsWhitespaceWhenBuildingCategoryPaths(): void
    {
        $feed = $this->feed([
            ['Pot', '  Home |  Decor  | Planters ', '10009', '39.95', '60'],
        ]);

        (new CatalogImporter($this->em))->import($feed);

        $paths = $this->categoryPaths();
        self::assertContains('Home', $paths);
        self::assertContains('Home | Decor | Planters', $paths);
    }

    private function countProducts(): int
    {
        return (int) $this->em
            ->createQuery('SELECT COUNT(p.id) FROM ' . Product::class . ' p')
            ->getSingleScalarResult();
    }

    private function countCategories(): int
    {
        return (int) $this->em
            ->createQuery('SELECT COUNT(c.id) FROM ' . Category::class . ' c')
            ->getSingleScalarResult();
    }

    private function findProductBySku(string $sku): ?Product
    {
        return $this->em
            ->createQuery('SELECT p FROM ' . Product::class . ' p WHERE p.sku = :sku')
            ->setParameter('sku', $sku)
            ->getOneOrNullResult();
    }

    /**
     * @return string[]
     */
    private function categoryPaths(): array
    {
        return $this->em
            ->createQuery('SELECT c.path FROM ' . Category::class . ' c')
            ->getSingleColumnResult();
    }

    /**
     * Builds an XML feed file from rows of [title, category, sku, price, stock].
     *
     * @param array<int, array{0:string,1:string,2:string,3:string,4:string}> $rows
     */
    private function feed(array $rows): string
    {
        $items = '';
        foreach ($rows as [$title, $category, $sku, $price, $stock]) {
            $items .= sprintf(
                "<product><title>%s</title><description>d</description>"
                . "<category>%s</category><sku>%s</sku><price>%s</price><stock>%s</stock></product>\n",
                $title,
                $category,
                $sku,
                $price,
                $stock,
            );
        }

        return $this->tempFile("<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<products>\n{$items}</products>\n");
    }

    private function tempFile(string $xml): string
    {
        $file = tempnam(sys_get_temp_dir(), 'feed_') . '.xml';
        file_put_contents($file, $xml);
        $this->tempFiles[] = $file;

        return $file;
    }
}
