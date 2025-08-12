<?php
namespace FlujosDimension\Jobs;

use FlujosDimension\Repositories\CallRepository;
use FlujosDimension\Repositories\AsyncTaskRepository;
use FlujosDimension\Infrastructure\Http\OpenAIClient;
use FlujosDimension\Support\Validator;
use FlujosDimension\Core\Config;

class TranscriptionJob implements JobInterface
{
    public function __construct(
        private CallRepository $calls,
        private OpenAIClient $openai,
        private AsyncTaskRepository $tasks
    ) {}

    /** @param array<string,mixed> $payload */
    public function handle(array $payload): void
    {
        $errors = Validator::validate($payload, [
            'call_id' => 'required|integer',
            'path'    => 'required|string',
        ]);
        if ($errors) {
            throw new \InvalidArgumentException('Invalid job payload');
        }

        $callId = (int)$payload['call_id'];
        $path = $payload['path'];
        
        if (!is_file($path)) {
            throw new \RuntimeException('Audio file not found');
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowed = ['mp3', 'wav', 'ogg', 'm4a'];
        if (!in_array($ext, $allowed, true)) {
            throw new \RuntimeException('Unsupported audio format');
        }

        $maxMb = (int) Config::getInstance()->get('RINGOVER_MAX_RECORDING_MB', 100);
        if (filesize($path) > $maxMb * 1024 * 1024) {
            throw new \RuntimeException('Recording exceeds max size');
        }

        // Generate correlation ID for tracing
        $correlationId = $payload['correlation_id'] ?? bin2hex(random_bytes(16));
        $batchId = $payload['batch_id'] ?? null;

        try {
            // Step 1: Transcribe audio using OpenAI Whisper
            $transcriptionOptions = [
                'language' => 'es', // Spanish language
                'response_format' => 'verbose_json', // Get detailed response with timestamps
                'temperature' => 0.0 // Deterministic output
            ];

            $transcriptionResult = $this->openai->transcribe($path, $transcriptionOptions, $batchId, $correlationId);
            
            if (!isset($transcriptionResult['text']) || empty($transcriptionResult['text'])) {
                throw new \RuntimeException('Empty transcription result from OpenAI');
            }

            $transcriptionText = $transcriptionResult['text'];
            $confidence = $transcriptionResult['confidence'] ?? 0.0;
            $language = $transcriptionResult['language'] ?? 'es';
            $duration = $transcriptionResult['duration'] ?? 0;

            // Step 2: Save transcription to database
            $this->calls->saveTranscription($callId, [
                'original_text' => $transcriptionText,
                'processed_text' => $transcriptionText,
                'confidence_score' => $confidence,
                'language' => $language,
                'processing_time' => $duration * 1000, // Convert to milliseconds
                'correlation_id' => $correlationId
            ]);

            // Step 3: Analyze transcription for insights
            $analysisResult = $this->openai->analyzeCall($transcriptionText, $batchId, $correlationId);
            
            if (isset($analysisResult['choices'][0]['message']['content'])) {
                $analysisContent = $analysisResult['choices'][0]['message']['content'];
                $analysisData = json_decode($analysisContent, true);
                
                if (json_last_error() === JSON_ERROR_NONE && is_array($analysisData)) {
                    // Extract structured data
                    $sentiment = $analysisData['sentiment'] ?? null;
                    $keywords = $analysisData['keywords'] ?? [];
                    $summary = $analysisData['summary'] ?? '';
                    $notasEstructuradas = $analysisData['notas_estructuradas'] ?? [];

                    // Update call with analysis results
                    $this->calls->updateAnalysis($callId, [
                        'ai_transcription' => $transcriptionText,
                        'transcription_confidence' => $confidence,
                        'analysis' => $analysisContent,
                        'sentiment' => $sentiment['label'] ?? null,
                        'sentiment_confidence' => $sentiment['confidence'] ?? 0.0,
                        'ai_sentiment' => $sentiment['label'] ?? 'neutral',
                        'ai_summary' => $summary,
                        'ai_keywords' => $this->extractKeywordsString($keywords),
                        'ai_processed_at' => date('Y-m-d H:i:s'),
                        'pending_transcriptions' => 0,
                        'pending_analysis' => 0,
                        'correlation_id' => $correlationId
                    ]);

                    // Save structured notes as JSON in analysis field
                    if (!empty($notasEstructuradas)) {
                        $this->calls->saveStructuredNotes($callId, $notasEstructuradas);
                    }

                    // Queue CRM sync job
                    $this->tasks->enqueue(\FlujosDimension\Jobs\CRMSyncJob::class, [
                        'call_id' => $callId,
                        'correlation_id' => $correlationId,
                        'batch_id' => $batchId
                    ], 5, $correlationId);
                } else {
                    // Fallback: save raw analysis even if JSON parsing fails
                    $this->calls->updateAnalysis($callId, [
                        'ai_transcription' => $transcriptionText,
                        'transcription_confidence' => $confidence,
                        'analysis' => $analysisContent,
                        'ai_processed_at' => date('Y-m-d H:i:s'),
                        'pending_transcriptions' => 0,
                        'pending_analysis' => 0,
                        'correlation_id' => $correlationId
                    ]);
                }
            }

            // Mark as processed
            $this->calls->markAsProcessed($callId);

        } catch (\Exception $e) {
            // Log error and mark as failed
            error_log("TranscriptionJob failed for call {$callId}: " . $e->getMessage());
            
            $this->calls->updateAnalysis($callId, [
                'pending_transcriptions' => 0,
                'pending_analysis' => 0,
                'correlation_id' => $correlationId
            ]);
            
            throw $e; // Re-throw for job retry mechanism
        }
    }

    /**
     * Extract keywords as comma-separated string from keywords array
     */
    private function extractKeywordsString(array $keywords): string
    {
        if (empty($keywords)) {
            return '';
        }

        $keywordTerms = [];
        foreach ($keywords as $keyword) {
            if (is_array($keyword) && isset($keyword['term'])) {
                $keywordTerms[] = $keyword['term'];
            } elseif (is_string($keyword)) {
                $keywordTerms[] = $keyword;
            }
        }

        return implode(', ', $keywordTerms);
    }
}
