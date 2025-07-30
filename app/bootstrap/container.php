<?php
declare(strict_types=1);

use FlujosDimension\Core\Application;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$app = new Application();
return $app->getContainer();
