<?php

namespace App\Domain\Collections\Controllers;

use App\Infrastructure\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class CollectionController extends ApiController
{
    public function index(): JsonResponse
    {
        // Mock data for DDD demonstration
        $collections = [
            ['id' => 1, 'name' => 'Users', 'icon' => 'Users'],
            ['id' => 2, 'name' => 'Products', 'icon' => 'Database'],
            ['id' => 3, 'name' => 'Orders', 'icon' => 'Database'],
            ['id' => 4, 'name' => 'Blog Posts', 'icon' => 'Database'],
        ];

        return $this->successResponse($collections, 'Collections retrieved successfully');
    }
}
