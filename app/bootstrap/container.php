<?php
declare(strict_types=1);

use FlujosDimension\Core\Container;
use FlujosDimension\Core\Config;
use FlujosDimension\Core\Database;
use FlujosDimension\Infrastructure\Http\HttpClient;
use FlujosDimension\Repositories\CallRepository;
use FlujosDimension\Services\{RingoverService, OpenAIService, PipedriveService, AnalyticsService};

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

/* ---------- contenedor ---------- */
$container = new Container();

/* ---------- config & PDO ---------- */
$cfg = Config::getInstance();
$container->bind(Config::class, fn () => $cfg);
$container->bind(PDO::class, fn () => Database::getInstance());


/* ---------- HTTP ---------- */
$container->singleton(HttpClient::class, fn () =>
    new HttpClient(['headers' => ['User-Agent' => 'FlujosDimensionBot/1.0']])
);
$container->alias(HttpClient::class, 'httpClient');

/* ---------- Repos ---------- */
$container->singleton(CallRepository::class,
    fn ($c) => new CallRepository($c->resolve(PDO::class))
);
$container->alias(CallRepository::class, 'callRepository');

/* ---------- Integraciones ---------- */
$container->singleton(RingoverService::class,
    fn ($c) => new RingoverService($c)
);
$container->singleton(OpenAIService::class,
    fn ($c) => new OpenAIService($c->resolve('httpClient'), $cfg->get('OPENAI_API_KEY'))
);
$container->singleton(PipedriveService::class,
    fn ($c) => new PipedriveService($c->resolve('httpClient'), $cfg->get('PIPEDRIVE_API_TOKEN'))
);

/* ---------- Dom / IA ---------- */
$container->singleton(AnalyticsService::class,
    fn ($c) => new AnalyticsService($c->resolve('callRepository'), $c->resolve(OpenAIService::class))
);
$container->alias(AnalyticsService::class, 'analyticsService');

/* ---------- exporta ---------- */
return $container;
