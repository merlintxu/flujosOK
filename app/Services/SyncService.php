<?php
declare(strict_types=1);

namespace FlujosDimension\Services;

use DateTimeInterface;

/**
 * High level orchestrator combining call import, analysis and CRM push.
 *
 * It also exposes helper methods used by the admin panel to run legacy
 * synchronisation scripts located under <project>/admin/api.
 */
final class SyncService
{
    private string $basePath;

    public function __construct(
        private readonly CallService $calls,
        private readonly AnalysisService $analysis,
        private readonly CRMService $crm,
        ?string $basePath = null
    ) {
        $this->basePath = $basePath ?? dirname(__DIR__, 2);
    }

    /**
     * Synchronize calls since the given date.
     * Returns number of calls processed.
     */
    public function sync(DateTimeInterface $since): int
    {
        $count = 0;
        foreach ($this->calls->getCalls($since) as $call) {
            // Placeholder for analysis and CRM integration.
            $count++;
        }
        return $count;
    }

    /**
     * Execute one of the legacy admin scripts capturing its output.
     *
     * @param string $script    Filename inside admin/api
     * @param array  $params    Incoming parameters
     * @param string[] $allow   Whitelisted parameter names
     * @return array{ok:bool,output:string,meta:array}
     */
    private function execute(string $script, array $params, array $allow): array
    {
        $filtered = array_intersect_key($params, array_flip($allow));
        $oldGet  = $_GET;
        $oldPost = $_POST;
        $_GET = [];
        $_POST = $filtered;

        if (!defined('FD_TESTING')) {
            define('FD_TESTING', true);
        }

        ob_start();
        $ok   = false;
        $meta = [];

        try {
            include $this->basePath . '/admin/api/' . $script;
        } catch (\Throwable $e) {
            $output = ob_get_clean();
            $_GET   = $oldGet;
            $_POST  = $oldPost;
            return [
                'ok'     => false,
                'output' => $output,
                'meta'   => ['error' => $e->getMessage()],
            ];
        }

        $output = ob_get_clean();
        $_GET   = $oldGet;
        $_POST  = $oldPost;

        $decoded = json_decode($output, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $ok   = (bool)($decoded['success'] ?? $decoded['ok'] ?? false);
            $meta = $decoded;
        }

        return ['ok' => $ok, 'output' => $output, 'meta' => $meta];
    }

    /** Run Ringover synchronisation script. */
    public function runRingover(array $params): array
    {
        $allow = ['download', 'full', 'fields', 'since', 'log_level', 'csrf_token'];
        return $this->execute('sync_ringover.php', $params, $allow);
    }

    /** Run OpenAI batch processing script. */
    public function runBatchOpenAI(array $params): array
    {
        $allow = ['max', 'csrf_token'];
        return $this->execute('batch_openai.php', $params, $allow);
    }

    /** Push pending data to Pipedrive. */
    public function pushPipedrive(array $params): array
    {
        $allow = ['limit', 'csrf_token'];
        return $this->execute('push_pipedrive.php', $params, $allow);
    }
}
