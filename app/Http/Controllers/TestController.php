<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

class TestController extends Controller
{
    #[OA\Get(
        path: '/api/test',
        summary: 'Route de test',
        responses: [
            new OA\Response(response: 200, description: 'Succès')
        ]
    )]
    public function index()
    {
        return response()->json(['message' => 'API Minizon fonctionne !']);
    }
}