<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CategoryController extends AbstractController
{
    #[Route('/api/categories', name: 'api_categories_list', methods: ['GET'])]
    public function list(Request $request, CategoryRepository $categories): JsonResponse
    {
        // read ?page= and ?limit= from the URL, with sane defaults and bounds
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 20)));

        $result = $categories->getCategories($page, $limit);

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