<?php
declare(strict_types=1);

use FlujosDimension\Core\Application;

// bootstrap the application so services like the cache are registered

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$app = new Application();

// Ensure cache service is available via Application
$app->service('cache');

return $app->getContainer();
