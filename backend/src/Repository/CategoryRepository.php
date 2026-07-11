<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
 * Returns one page of categories, each with its product count.
 *
 * @return array{items: array<int, array{id:int, name:string, productCount:int}>, total:int}
 */
public function getCategories(int $page, int $limit): array
{
    $offset = ($page - 1) * $limit;

    // items for this page: id, name, and how many products point at each category
    $items = $this->createQueryBuilder('c')
        ->select('c.id AS id', 'c.name AS name', 'COUNT(p.id) AS productCount')
        ->leftJoin('c.products', 'p')   // LEFT JOIN so categories with 0 products still show
        ->groupBy('c.id')                // count is per category
        ->orderBy('c.id', 'ASC')
        ->setFirstResult($offset)        // skip previous pages
        ->setMaxResults($limit)          // page size
        ->getQuery()
        ->getArrayResult();

    // COUNT() comes back as a string; make it an int
    foreach ($items as &$item) {
        $item['productCount'] = (int) $item['productCount'];
    }
    unset($item);

    // total number of categories (for the page count)
    $total = (int) $this->createQueryBuilder('c')
        ->select('COUNT(c.id)')
        ->getQuery()
        ->getSingleScalarResult();

    return ['items' => $items, 'total' => $total];
}
}
