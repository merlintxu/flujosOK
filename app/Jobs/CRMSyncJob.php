<?php
namespace FlujosDimension\Jobs;

use FlujosDimension\Repositories\CallRepository;
use FlujosDimension\Infrastructure\Http\PipedriveClient;
use FlujosDimension\Support\Validator;
use Psr\Log\LoggerInterface;

class CRMSyncJob implements JobInterface
{
    public function __construct(
        private CallRepository $calls,
        private PipedriveClient $pipedrive,
        private LoggerInterface $logger
    ) {}

    /** @param array<string,mixed> $payload */
    public function handle(array $payload): void
    {
        $errors = Validator::validate($payload, [
            'call_id' => 'required|integer',
        ]);
        if ($errors) {
            throw new \InvalidArgumentException('Invalid job payload');
        }

        $callId = (int)$payload['call_id'];
        $correlationId = $payload['correlation_id'] ?? bin2hex(random_bytes(16));
        $batchId = $payload['batch_id'] ?? null;

        try {
            // Get call data
            $call = $this->calls->find($callId);
            if (!$call) {
                throw new \RuntimeException("Call not found: {$callId}");
            }

            // Skip if already synced
            if ($call['crm_synced']) {
                return;
            }

            $phoneNumber = $call['phone_number'] ?? '';
            $callIdStr = $call['call_id'] ?? '';

            // Step 1: Find or create person in Pipedrive
            $personId = null;
            if (!empty($phoneNumber)) {
                $personId = $this->pipedrive->findPersonByPhone($phoneNumber, $batchId, $correlationId);
            }

            // Step 2: Find existing deal or create new one
            $dealId = $this->pipedrive->findOpenDeal($callIdStr, $phoneNumber, $batchId, $correlationId);

            if (!$dealId) {
                // Create new deal
                $dealData = [
                    'title' => "Llamada - " . ($phoneNumber ?: 'Sin número'),
                    'person_id' => $personId,
                    'status' => 'open',
                    'custom_fields' => [
                        'Call_ID' => $callIdStr,
                        'Call_Direction' => $call['direction'] ?? '',
                        'Call_Duration' => $call['duration'] ?? 0,
                        'Call_Status' => $call['status'] ?? '',
                    ]
                ];

                // Add AI analysis data if available
                if (!empty($call['ai_summary'])) {
                    $dealData['custom_fields']['Call_Summary'] = $call['ai_summary'];
                }

                if (!empty($call['ai_sentiment'])) {
                    $dealData['custom_fields']['Call_Sentiment'] = $call['ai_sentiment'];
                }

                if (!empty($call['ai_keywords'])) {
                    $dealData['custom_fields']['Call_Keywords'] = $call['ai_keywords'];
                }

                // Add structured notes if available
                if (!empty($call['action_items'])) {
                    $structuredNotes = json_decode($call['action_items'], true);
                    if (is_array($structuredNotes)) {
                        // Add key insights to deal notes
                        $notes = [];
                        
                        if (!empty($structuredNotes['necesidades_cliente'])) {
                            $notes[] = "Necesidades: " . $structuredNotes['necesidades_cliente'];
                        }
                        
                        if (!empty($structuredNotes['metros_cuadrados'])) {
                            $notes[] = "Metros cuadrados: " . $structuredNotes['metros_cuadrados'];
                        }
                        
                        if (!empty($structuredNotes['urgencia'])) {
                            $notes[] = "Urgencia: " . $structuredNotes['urgencia'];
                        }
                        
                        if (!empty($structuredNotes['situacion_vivienda'])) {
                            $notes[] = "Situación vivienda: " . $structuredNotes['situacion_vivienda'];
                        }

                        if (!empty($notes)) {
                            $dealData['notes'] = implode("\n", $notes);
                        }
                    }
                }

                $dealId = $this->pipedrive->createOrUpdateDeal($dealData, $batchId, $correlationId);
            }

            // Step 3: Mark call as synced
            if ($dealId) {
                $this->calls->markCrmSynced($callId, $dealId, $batchId, $correlationId);
            }

        } catch (\Exception $e) {
            // Log error
            $this->calls->logCrmSync($callId, 'failed', $e->getMessage(), $batchId, $correlationId);
            
            $this->logger->error('crmsync_failed', [
                'call_id' => $callId,
                'error' => $e->getMessage()
            ]);
            
            throw $e; // Re-throw for job retry mechanism
        }
    }
}

