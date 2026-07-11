<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\Request;

final class ProductController extends AbstractController
{
        #[Route('/api/products', name: 'api_products_list', methods: ['GET'])]
        public function list(Request $request, ProductRepository $products): JsonResponse
        {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(100, (int) $request->query->get('limit', 20)));

            $minPrice = $request->query->has('minPrice') ? (float) $request->query->get('minPrice') : null;
            $maxPrice = $request->query->has('maxPrice') ? (float) $request->query->get('maxPrice') : null;
            $inStockOnly = $request->query->getBoolean('inStock'); // ?inStock=1 hides out-of-stock

            $result = $products->getProducts($page, $limit, $minPrice, $maxPrice, $inStockOnly);

            return new JsonResponse([
                    'items' => $result['items'],
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $result['total'],
                        'pages' => (int) ceil($result['total'] / $limit),
                    ],
                ]);
        }
}
