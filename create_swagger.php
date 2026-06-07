<?php
$content = <<<'PHP'
<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="Minizon API",
 *     version="1.0.0",
 *     description="Documentation API Minizon"
 * )
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Serveur local"
 * )
 */
class SwaggerController extends Controller
{
}
PHP;

file_put_contents('app/Http/Controllers/SwaggerController.php', $content);
echo "Fichier cree avec succes!\n";
echo file_get_contents('app/Http/Controllers/SwaggerController.php');