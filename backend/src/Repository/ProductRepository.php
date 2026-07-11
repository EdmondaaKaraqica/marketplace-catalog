<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function getProducts(
        int $page, 
        int $limit,
        ?float $minPrice,
        ?float $maxPrice,
        bool $inStockOnly
    ): array {

        $offset = ($page - 1) * $limit;
   
        $itemsQb = $this->createQueryBuilder('p')
            ->select('p.id AS id', 'p.title AS title', 'c.name AS category', 'p.sku AS sku', 'p.price AS price', 'p.stock AS stock')
            ->join('p.category', 'c')
            ->orderBy('p.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        $this->applyFilters($itemsQb, $minPrice, $maxPrice, $inStockOnly);

        $items = $itemsQb->getQuery()->getArrayResult();
        foreach ($items as &$item) {
            $item['stock'] = (int) $item['stock']; // COUNT/ints come back as strings
        }
        unset($item);

        // total with the SAME filters (no join needed, filters only touch p)
        $countQb = $this->createQueryBuilder('p')->select('COUNT(p.id)');
        $this->applyFilters($countQb, $minPrice, $maxPrice, $inStockOnly);
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        return ['items' => $items, 'total' => $total];
    }

    private function applyFilters(QueryBuilder $qb, ?float $minPrice, ?float $maxPrice, bool $inStockOnly): void
    {
        if (null !== $minPrice) {
            $qb->andWhere('p.price >= :minPrice')->setParameter('minPrice', $minPrice);
        }
        if (null !== $maxPrice) {
            $qb->andWhere('p.price <= :maxPrice')->setParameter('maxPrice', $maxPrice);
        }
        if ($inStockOnly) {
            $qb->andWhere('p.stock > 0'); // exclude out-of-stock
        }
    }
}
