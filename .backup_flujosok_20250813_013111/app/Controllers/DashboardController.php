<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Container;
use FlujosDimension\Core\Request;
use FlujosDimension\Core\Response;
use FlujosDimension\Services\AnalyticsService;
use FlujosDimension\Services\RingoverService;
use Exception;

/**
 * Controlador de Dashboard - Flujos Dimension v4.2
 * Dashboard con datos reales de base de datos
 */
class DashboardController extends BaseController
{
    private AnalyticsService $analyticsService;
    private RingoverService $ringoverService;
    
    public function __construct(Container $container, Request $request)
    {
        parent::__construct($container, $request);
        
        $this->analyticsService = $container->resolve('analyticsService');
        $this->ringoverService = $container->resolve('ringoverService');
    }
    
    /**
     * Obtener datos del dashboard principal
     * GET /api/analytics/dashboard
     */
    public function index(): Response
    {
        try {
            $period = $this->request->get('period', '24h');
            $refresh = $this->request->get('refresh', false);
            
            // Si se solicita refresh, limpiar cache
            if ($refresh) {
                $this->analyticsService->clearCache();
            }
            
            $dashboardData = $this->analyticsService->getDashboardData($period);
            
            if (!$dashboardData['success']) {
                return $this->errorResponse('Error loading dashboard data: ' . $dashboardData['error']);
            }
            
            // Añadir información del sistema
            $systemInfo = $this->getSystemInfo();
            $dashboardData['data']['system'] = $systemInfo;
            
            $this->logActivity('dashboard_viewed', ['period' => $period]);
            
            return $this->jsonResponse($dashboardData);
            
        } catch (Exception $e) {
            return $this->handleError($e, 'Error loading dashboard');
        }
    }
    
    /**
     * Obtener estadísticas rápidas
     * GET /api/analytics/quick-stats
     */
    public function quickStats(): Response
    {
        try {
            $database = $this->container->resolve('database');
            
            // Estadísticas de hoy
            $today = date('Y-m-d');
            $sql = "SELECT 
                COUNT(*) as calls_today,
                COUNT(CASE WHEN status = 'answered' THEN 1 END) as answered_today,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as calls_last_hour,
                AVG(CASE WHEN status = 'answered' AND DATE(created_at) = :today THEN duration END) as avg_duration_today
            FROM calls 
            WHERE DATE(created_at) = :today";
            
            $stmt = $database->prepare($sql);
            $stmt->execute(['today' => $today]);
            $todayStats = $stmt->fetch();
            
            // Estadísticas de ayer para comparación
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $sql = "SELECT 
                COUNT(*) as calls_yesterday,
                COUNT(CASE WHEN status = 'answered' THEN 1 END) as answered_yesterday
            FROM calls 
            WHERE DATE(created_at) = :yesterday";
            
            $stmt = $database->prepare($sql);
            $stmt->execute(['yesterday' => $yesterday]);
            $yesterdayStats = $stmt->fetch();
            
            $callsToday = (int) $todayStats['calls_today'];
            $callsYesterday = (int) $yesterdayStats['calls_yesterday'];
            $answeredToday = (int) $todayStats['answered_today'];
            $answeredYesterday = (int) $yesterdayStats['answered_yesterday'];
            
            $result = [
                'success' => true,
                'data' => [
                    'today' => [
                        'total_calls' => $callsToday,
                        'answered_calls' => $answeredToday,
                        'calls_last_hour' => (int) $todayStats['calls_last_hour'],
                        'answer_rate' => $callsToday > 0 ? round(($answeredToday / $callsToday) * 100, 2) : 0,
                        'avg_duration' => round((float) ($todayStats['avg_duration_today'] ?? 0), 2)
                    ],
                    'yesterday' => [
                        'total_calls' => $callsYesterday,
                        'answered_calls' => $answeredYesterday,
                        'answer_rate' => $callsYesterday > 0 ? round(($answeredYesterday / $callsYesterday) * 100, 2) : 0
                    ],
                    'changes' => [
                        'calls_change' => $callsToday - $callsYesterday,
                        'calls_change_percent' => $callsYesterday > 0 ? round((($callsToday - $callsYesterday) / $callsYesterday) * 100, 2) : 0,
                        'answered_change' => $answeredToday - $answeredYesterday
                    ],
                    'last_updated' => date('Y-m-d H:i:s')
                ]
            ];
            
            return $this->jsonResponse($result);
            
        } catch (Exception $e) {
            return $this->handleError($e, 'Error loading quick stats');
        }
    }
    
    /**
     * Obtener actividad en tiempo real
     * GET /api/analytics/realtime
     */
    public function realtime(): Response
    {
        try {
            $database = $this->container->resolve('database');
            
            // Llamadas de los últimos 30 minutos
            $sql = "SELECT 
                id, phone_number, direction, status, duration, 
                ai_sentiment, created_at,
                TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutes_ago
            FROM calls 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            ORDER BY created_at DESC
            LIMIT 20";
            
            $stmt = $database->query($sql);
            $recentCalls = $stmt->fetchAll();
            
            // Estadísticas de los últimos 30 minutos
            $sql = "SELECT 
                COUNT(*) as total_calls,
                COUNT(CASE WHEN status = 'answered' THEN 1 END) as answered_calls,
                COUNT(CASE WHEN direction = 'inbound' THEN 1 END) as inbound_calls,
                COUNT(CASE WHEN direction = 'outbound' THEN 1 END) as outbound_calls,
                AVG(CASE WHEN status = 'answered' THEN duration END) as avg_duration
            FROM calls 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
            
            $stmt = $database->query($sql);
            $stats = $stmt->fetch();
            
            $result = [
                'success' => true,
                'data' => [
                    'recent_calls' => $recentCalls,
                    'stats_30min' => [
                        'total_calls' => (int) $stats['total_calls'],
                        'answered_calls' => (int) $stats['answered_calls'],
                        'inbound_calls' => (int) $stats['inbound_calls'],
                        'outbound_calls' => (int) $stats['outbound_calls'],
                        'avg_duration' => round((float) ($stats['avg_duration'] ?? 0), 2)
                    ],
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
            
            return $this->jsonResponse($result);
            
        } catch (Exception $e) {
            return $this->handleError($e, 'Error loading realtime data');
        }
    }
    
    /**
     * Obtener información del sistema
     */
    private function getSystemInfo(): array
    {
        try {
            $database = $this->container->resolve('database');
            
            // Información de la base de datos
            $stmt = $database->query("SELECT COUNT(*) as total_calls FROM calls");
            $totalCalls = $stmt->fetch()['total_calls'];
            
            $stmt = $database->query("SELECT MAX(created_at) as last_call FROM calls");
            $lastCall = $stmt->fetch()['last_call'];
            
            // Estado de las APIs
            $apiStatus = [
                'ringover' => $this->checkRingoverStatus(),
                'database' => true,
                'openai' => $this->checkOpenAIStatus(),
                'pipedrive' => $this->checkPipedriveStatus()
            ];
            
            return [
                'database' => [
                    'total_calls' => (int) $totalCalls,
                    'last_call' => $lastCall,
                    'status' => 'connected'
                ],
                'apis' => $apiStatus,
                'system' => [
                    'version' => '4.2',
                    'environment' => $this->config('APP_ENV', 'production'),
                    'timezone' => $this->config('TIMEZONE', 'Europe/Madrid'),
                    'uptime' => $this->getSystemUptime()
                ]
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Error getting system info: ' . $e->getMessage());
            return [
                'error' => 'Could not retrieve system information'
            ];
        }
    }
    
    /**
     * Verificar estado de Ringover
     */
    private function checkRingoverStatus(): bool
    {
        try {
            $result = $this->ringoverService->testConnection();
            return $result['success'];
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Verificar estado de OpenAI
     */
    private function checkOpenAIStatus(): bool
    {
        try {
            $apiKey = $this->config('OPENAI_API_KEY');
            if (empty($apiKey)) {
                return false;
            }
            
            // Test simple de conectividad
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.openai.com/v1/models',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 10
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 200;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Verificar estado de Pipedrive
     */
    private function checkPipedriveStatus(): bool
    {
        try {
            $apiToken = $this->config('PIPEDRIVE_API_TOKEN');
            if (empty($apiToken)) {
                return false;
            }
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.pipedrive.com/v1/users/me?api_token=' . $apiToken,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 200;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Obtener uptime del sistema
     */
    private function getSystemUptime(): string
    {
        try {
            // Intentar obtener uptime del sistema
            if (function_exists('sys_getloadavg') && is_readable('/proc/uptime')) {
                $uptime = file_get_contents('/proc/uptime');
                $uptimeSeconds = (int) explode(' ', $uptime)[0];
                
                $days = floor($uptimeSeconds / 86400);
                $hours = floor(($uptimeSeconds % 86400) / 3600);
                $minutes = floor(($uptimeSeconds % 3600) / 60);
                
                return "{$days}d {$hours}h {$minutes}m";
            }
            
            return 'Unknown';
            
        } catch (Exception $e) {
            return 'Unknown';
        }
    }
    
    /**
     * Exportar datos del dashboard
     * GET /api/analytics/export
     */
    public function export(): Response
    {
        try {
            $format = $this->request->get('format', 'json');
            $period = $this->request->get('period', '30d');
            
            $dashboardData = $this->analyticsService->getDashboardData($period);
            
            if (!$dashboardData['success']) {
                return $this->errorResponse('Error exporting data: ' . $dashboardData['error']);
            }
            
            $exportData = [
                'export_info' => [
                    'generated_at' => date('Y-m-d H:i:s'),
                    'period' => $period,
                    'format' => $format,
                    'version' => '4.2'
                ],
                'data' => $dashboardData['data']
            ];
            
            $this->logActivity('dashboard_exported', [
                'format' => $format,
                'period' => $period
            ]);
            
            switch ($format) {
                case 'csv':
                    return $this->exportToCsv($exportData);
                case 'json':
                default:
                    return $this->jsonResponse([
                        'success' => true,
                        'export' => $exportData
                    ]);
            }
            
        } catch (Exception $e) {
            return $this->handleError($e, 'Error exporting dashboard data');
        }
    }
    
    /**
     * Exportar a CSV
     */
    private function exportToCsv(array $data): Response
    {
        $csv = "Date,Total Calls,Answered Calls,Missed Calls,Answer Rate,Avg Duration\n";
        
        if (isset($data['data']['call_trends'])) {
            foreach ($data['data']['call_trends'] as $trend) {
                $answerRate = $trend['total_calls'] > 0 ? 
                    round(($trend['answered_calls'] / $trend['total_calls']) * 100, 2) : 0;
                
                $csv .= sprintf(
                    "%s,%d,%d,%d,%.2f,%.2f\n",
                    $trend['date'],
                    $trend['total_calls'],
                    $trend['answered_calls'],
                    $trend['missed_calls'],
                    $answerRate,
                    $trend['avg_duration'] ?? 0
                );
            }
        }
        
        return new Response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="dashboard_export_' . date('Y-m-d') . '.csv"'
        ]);
    }
}

