<?php
declare(strict_types=1);

namespace FlujosDimension\Infrastructure\Http;

use FlujosDimension\Core\Config;
use RuntimeException;

/**
 * HTTP client wrapper for the OpenAI API.
 */
final class OpenAIClient
{
    private const BASE = 'https://api.openai.com/v1';
    private string $model;

    public function __construct(
        private readonly HttpClient $http,
        private readonly string $apiKey
    ) {
        $this->model = Config::getInstance()->get('OPENAI_MODEL', 'gpt-4o-transcribe');
    }

    /**
     * Perform a chat completion request.
     *
     * @param array<int,array<string,mixed>> $messages
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public function chat(array $messages, array $extra = [], ?string $batchId = null, ?string $correlationId = null): array
    {
        $resp = $this->http->request('POST', self::BASE . '/chat/completions', [
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type'  => 'application/json',
            ],
            'json' => ['model' => $this->model, 'messages' => $messages] + $extra,
            'service'        => 'OpenAI',
            'batch_id'       => $batchId,
            'correlation_id' => $correlationId,
        ]);

        if ($resp->getStatusCode() !== 200) {
            throw new RuntimeException("OpenAI error {$resp->getStatusCode()}");
        }

        return json_decode((string)$resp->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Transcribe audio file using OpenAI Whisper API.
     *
     * @param string $audioFilePath Path to the audio file
     * @param array<string,mixed> $options Additional options for transcription
     * @param string|null $batchId Batch ID for tracking
     * @param string|null $correlationId Correlation ID for tracing
     * @return array<string,mixed> Transcription result
     * @throws RuntimeException If transcription fails
     */
    public function transcribe(string $audioFilePath, array $options = [], ?string $batchId = null, ?string $correlationId = null): array
    {
        if (!is_file($audioFilePath)) {
            throw new RuntimeException("Audio file not found: {$audioFilePath}");
        }

        // Prepare multipart form data
        $multipart = [
            [
                'name' => 'file',
                'contents' => fopen($audioFilePath, 'r'),
                'filename' => basename($audioFilePath)
            ],
            [
                'name' => 'model',
                'contents' => $options['model'] ?? 'whisper-1'
            ]
        ];

        // Add optional parameters
        if (isset($options['language'])) {
            $multipart[] = [
                'name' => 'language',
                'contents' => $options['language']
            ];
        }

        if (isset($options['prompt'])) {
            $multipart[] = [
                'name' => 'prompt',
                'contents' => $options['prompt']
            ];
        }

        if (isset($options['response_format'])) {
            $multipart[] = [
                'name' => 'response_format',
                'contents' => $options['response_format']
            ];
        }

        if (isset($options['temperature'])) {
            $multipart[] = [
                'name' => 'temperature',
                'contents' => (string)$options['temperature']
            ];
        }

        $resp = $this->http->request('POST', self::BASE . '/audio/transcriptions', [
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
            ],
            'multipart' => $multipart,
            'service' => 'OpenAI',
            'batch_id' => $batchId,
            'correlation_id' => $correlationId,
        ]);

        if ($resp->getStatusCode() !== 200) {
            $errorBody = (string)$resp->getBody();
            throw new RuntimeException("OpenAI transcription error {$resp->getStatusCode()}: {$errorBody}");
        }

        return json_decode((string)$resp->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Analyze transcribed text for sentiment, keywords and structured notes.
     *
     * @param string $transcriptionText The transcribed text to analyze
     * @param string|null $batchId Batch ID for tracking
     * @param string|null $correlationId Correlation ID for tracing
     * @return array<string,mixed> Analysis result
     */
    public function analyzeCall(string $transcriptionText, ?string $batchId = null, ?string $correlationId = null): array
    {
        $prompt = "Analiza la siguiente transcripción de una llamada telefónica y extrae la información estructurada en formato JSON:

Transcripción:
{$transcriptionText}

Devuelve un JSON con la siguiente estructura:
{
  \"sentiment\": {
    \"label\": \"positive|negative|neutral\",
    \"score\": -1.0 a 1.0,
    \"confidence\": 0.0 a 1.0
  },
  \"keywords\": [
    {\"term\": \"palabra_clave\", \"frequency\": 1, \"relevance\": 0.0-1.0}
  ],
  \"summary\": \"Resumen breve de la llamada\",
  \"notas_estructuradas\": {
    \"necesidades_cliente\": \"descripción\",
    \"metros_cuadrados\": número o null,
    \"pain_points_emocionales\": [\"lista de problemas\"],
    \"menciona_metodo_vesta\": true/false,
    \"menciona_garantia_50\": true/false,
    \"urgencia\": \"inmediato|1-2_meses|3+_meses\",
    \"situacion_vivienda\": \"comprada_en_arras|vivienda_actual|otro\",
    \"plazo_obra\": \"descripción del plazo\",
    \"menciona_precio_interiorismo_≤10pct\": true/false,
    \"precio_obra_m2_mencionado\": \"rango_1200_1500|otro|no_menciona\",
    \"menciona_iva\": true/false,
    \"textos_raw\": [\"fragmentos relevantes de la transcripción\"]
  }
}

Responde únicamente con el JSON, sin texto adicional.";

        $messages = [
            [
                'role' => 'system',
                'content' => 'Eres un experto analista de llamadas telefónicas especializado en el sector de reformas y construcción. Analiza las transcripciones y extrae información estructurada relevante para el negocio.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ];

        return $this->chat($messages, [
            'temperature' => 0.1,
            'max_tokens' => 2000
        ], $batchId, $correlationId);
    }
}
