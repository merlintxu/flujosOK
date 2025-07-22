<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
$container = require dirname(__DIR__, 2) . '/app/bootstrap/container.php';

use FlujosDimension\Core\JWT;

header('Content-Type: application/json');

use App\Services\PipedriveService;
use App\Repositories\CallRepository;

/** @var PipedriveService $crm */
$crm  = $container->resolve(PipedriveService::class);
/** @var CallRepository $repo */
$repo = $container->resolve('callRepository');

$pending = $repo->callsNotInCrm();   // SELECT * WHERE crm_synced=0
$created = 0;

foreach ($pending as $c) {
    $personId = $crm->findPersonByPhone($c['phone_number']) ?? null;
    $dealId   = $crm->createOrUpdateDeal([
        'title'     => 'Call '.$c['id'],
        'value'     => 0,
        'person_id' => $personId,
        'custom_fields'=>['Call_ID'=>$c['id']]
    ]);
    $repo->markCrmSynced($c['id'], $dealId);
    $created++;
}

echo json_encode(['success'=>true,'deals'=>$created]);
