<?php
require_once '../vendor/autoload.php';

use Slim\Factory\AppFactory;
use App\Middleware\CorsMiddleware;

// Create Slim app
$app = AppFactory::create();

// Add middleware
$app->add(new CorsMiddleware());
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Load routes
require_once '../config/routes.php';

$app->run();