<?php
declare(strict_types=1);

require __DIR__ . '/init.php';

use FlujosDimension\Services\PipedriveService;
use FlujosDimension\Repositories\CallRepository;

/** @var PipedriveService $crm */
$crm  = $container->resolve(PipedriveService::class);
/** @var CallRepository $repo */
$repo = $container->resolve('callRepository');

$pending = $repo->callsNotInCrm();   // SELECT * WHERE crm_synced=0
$created = 0;

foreach ($pending as $c) {
    // Skip Pipedrive person search when no phone number is available
    if (!empty($c['phone_number'])) {
        $personId = $crm->findPersonByPhone($c['phone_number']) ?? null;
    } else {
        $personId = null;
    }
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
