<?php
require 'vendor/autoload.php';
$openapi = \OpenApi\Generator::scan([__DIR__ . '/app']);
echo $openapi->toJson();