<?php

namespace App\Service;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Imports a marketplace XML feed into the database.
 */
class CatalogImporter
{
    private const BATCH_SIZE = 500;

    private const PATH_SEPARATOR = ' | ';

    /** @var array<string,int> existing sku => id (read-only during a run) */
    private array $productIdBySku = [];
    /** @var array<string,int> path => id, grows as new categories are created */
    private array $categoryIdByPath = [];
    /** @var array<string,int> snapshot of category paths that existed before the run */
    private array $existingCategoryPaths = [];
    /** @var array<string,true> skus seen in the feed this run */
    private array $seenSkus = [];
    /** @var array<string,true> category paths seen in the feed this run */
    private array $seenCategoryPaths = [];

    private int $created = 0;
    private int $updated = 0;
    private int $categoriesCreated = 0;
    private int $processed = 0;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @param callable|null $onProduct Called once per processed product (for progress display).
     */
    public function import(string $source, ?callable $onProduct = null): ImportResult
    {
        $reader = @\XMLReader::open($source);
        if (!$reader instanceof \XMLReader) {
            throw new \RuntimeException(sprintf('Unable to open feed source: %s', $source));
        }

        // Preload natural-key -> id maps so we never SELECT per row.
        $this->productIdBySku = $this->loadKeyMap(Product::class, 'sku');
        $this->categoryIdByPath = $this->loadKeyMap(Category::class, 'path');
        $this->existingCategoryPaths = $this->categoryIdByPath; // snapshot before new categories are added

        $this->seenSkus = [];
        $this->seenCategoryPaths = [];
        $this->created = 0;
        $this->updated = 0;
        $this->categoriesCreated = 0;
        $this->processed = 0;

        try {
            while ($reader->read()) {
                // Skip everything that is not a <product> opening tag.
                if (\XMLReader::ELEMENT !== $reader->nodeType || 'product' !== $reader->localName) {
                    continue;
                }

                $node = new \SimpleXMLElement($reader->readOuterXml());
                if (!$this->importProduct($node)) {
                    continue; // skip malformed rows
                }

                if (null !== $onProduct) {
                    $onProduct();
                }

                if (0 === ++$this->processed % self::BATCH_SIZE) {
                    $this->em->flush();
                    $this->em->clear();
                }
            }

            $this->em->flush();
            $this->em->clear();
        } finally {
            $reader->close();
        }

        return new ImportResult(
            productsCreated: $this->created,
            productsUpdated: $this->updated,
            productsDeleted: $this->deleteProductsMissingFromFeed(),
            categoriesCreated: $this->categoriesCreated,
            categoriesDeleted: $this->deleteCategoriesMissingFromFeed(),
        );
    }

    /**
     * Creates or updates one product from a feed node.
     *
     * @return bool false when the row is malformed (empty SKU) and was skipped
     */
    private function importProduct(\SimpleXMLElement $node): bool
    {
        $sku = trim((string) $node->sku);
        if ('' === $sku) {
            return false; // skip malformed entries without a natural key
        }

        $leafCategoryId = $this->resolveCategoryPath(
            (string) $node->category,
            $this->categoryIdByPath,
            $this->seenCategoryPaths,
            $this->categoriesCreated,
        );

        $product = $this->findOrNewProduct($sku);
        $product->setTitle(trim((string) $node->title));
        $product->setDescription($this->nullableText($node->description));
        $product->setPrice(trim((string) $node->price));
        $product->setStock((int) $node->stock);
        $product->setCategory($this->em->getReference(Category::class, $leafCategoryId));

        $this->em->persist($product);
        $this->seenSkus[$sku] = true;

        return true;
    }

    /**
     * Returns the existing product for this SKU (to update) or a fresh one (to create),
     * and bumps the created/updated counters accordingly.
     */
    private function findOrNewProduct(string $sku): Product
    {
        if (isset($this->productIdBySku[$sku])) {
            ++$this->updated;

            return $this->em->getReference(Product::class, $this->productIdBySku[$sku]);
        }

        ++$this->created;

        $product = new Product();
        $product->setSku($sku);

        return $product;
    }

    private function deleteProductsMissingFromFeed(): int
    {
        $idsToDelete = [];
        foreach ($this->productIdBySku as $sku => $id) {
            if (!isset($this->seenSkus[$sku])) {
                $idsToDelete[] = $id;
            }
        }

        return $this->deleteByIds(Product::class, $idsToDelete);
    }

    private function deleteCategoriesMissingFromFeed(): int
    {
        $idsByPath = [];
        foreach ($this->existingCategoryPaths as $path => $id) {
            if (!isset($this->seenCategoryPaths[$path])) {
                $idsByPath[$path] = $id;
            }
        }

        // Delete deepest paths first so child rows go before their parents (parent_id FK).
        uksort(
            $idsByPath,
            static fn (string $a, string $b): int => substr_count($b, '|') <=> substr_count($a, '|'),
        );

        return $this->deleteByIds(Category::class, array_values($idsByPath));
    }

    /**
     * Ensures every level of a "Top | Mid | Leaf" path exists, creating missing
     * levels, and returns the id of the leaf category.
     *
     * @param array<string,int>  $idByPath cache of path => id (mutated)
     * @param array<string,true> $seen     paths touched this run (mutated)
     */
    private function resolveCategoryPath(string $raw, array &$idByPath, array &$seen, int &$createdCount): int
    {
        // "Electronics | Computers | Laptops" -> ['Electronics', 'Computers', 'Laptops']
        $levels = array_values(array_filter(
            array_map('trim', explode('|', $raw)),
            static fn (string $name): bool => '' !== $name,
        ));

        $parentId = null; // id of the level above (null = top level)
        $path = '';        // full path built up so far

        foreach ($levels as $index => $name) {
            $path = 0 === $index ? $name : $path.self::PATH_SEPARATOR.$name;
            $seen[$path] = true;

            if (isset($idByPath[$path])) {
                $parentId = $idByPath[$path];
                continue;
            }

            $category = new Category();
            $category->setName($name);
            $category->setPath($path);
            if (null !== $parentId) {
                $category->setParent($this->em->getReference(Category::class, $parentId));
            }

            $this->em->persist($category);
            $this->em->flush(); // categories are few (~1k); flush to obtain the id

            $parentId = $category->getId();
            $idByPath[$path] = $parentId;
            ++$createdCount;
        }

        if (null === $parentId) {
            throw new \RuntimeException(sprintf('Product has an empty category: "%s"', $raw));
        }

        return $parentId;
    }

    /**
     * Loads a lightweight lookup table of "natural key -> id" for one entity.
     *
     * @param class-string $entityClass
     *
     * @return array<string,int> naturalKey => id
     */
    private function loadKeyMap(string $entityClass, string $keyField): array
    {
        $rows = $this->em
            ->createQuery(sprintf('SELECT e.%s AS k, e.id AS id FROM %s e', $keyField, $entityClass))
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['k']] = (int) $row['id'];
        }

        return $map;
    }

    /**
     * @param class-string $entityClass
     * @param int[]        $ids
     */
    private function deleteByIds(string $entityClass, array $ids): int
    {
        if ([] === $ids) {
            return 0;
        }

        $deleted = 0;
        foreach (array_chunk($ids, 1000) as $chunk) {
            $deleted += (int) $this->em
                ->createQuery(sprintf('DELETE FROM %s e WHERE e.id IN (:ids)', $entityClass))
                ->setParameter('ids', $chunk)
                ->execute();
        }

        return $deleted;
    }

    private function nullableText(\SimpleXMLElement $node): ?string
    {
        $text = trim((string) $node);

        return '' === $text ? null : $text;
    }
}
