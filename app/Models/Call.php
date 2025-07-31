<?php

namespace FlujosDimension\Models;

use FlujosDimension\Core\Container;
use PDO;
use Exception;

/**
 * Modelo Call - Flujos Dimension v4.2
 * Modelo para gestión de llamadas con ORM básico
 */
class Call extends BaseModel
{
    protected string $table = 'calls';
    protected array $fillable = [
        'ringover_id', 'phone_number', 'direction', 'status', 'duration',
        'recording_url', 'ai_transcription', 'ai_summary', 'ai_keywords',
        'ai_sentiment', 'pipedrive_contact_id', 'pipedrive_deal_id'
    ];
    
    protected array $casts = [
        'id' => 'int',
        'duration' => 'int',
        'pipedrive_contact_id' => 'int',
        'pipedrive_deal_id' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    /**
     * Obtener llamadas por estado
     */
    public function getByStatus(string $status, int $limit = 50): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE status = :status ORDER BY created_at DESC LIMIT :limit";
        $stmt = $this->database->prepare($sql);
        $stmt->bindValue('status', $status);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Obtener llamadas por dirección
     */
    public function getByDirection(string $direction, int $limit = 50): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE direction = :direction ORDER BY created_at DESC LIMIT :limit";
        $stmt = $this->database->prepare($sql);
        $stmt->bindValue('direction', $direction);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Obtener llamadas por número de teléfono
     */
    public function getByPhoneNumber(string $phoneNumber): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE phone_number = :phone_number ORDER BY created_at DESC";
        $stmt = $this->database->prepare($sql);
        $stmt->execute(['phone_number' => $phoneNumber]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Obtener llamadas por rango de fechas
     */
    public function getByDateRange(string $startDate, string $endDate, int $limit = 100): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE created_at BETWEEN :start_date AND :end_date 
                ORDER BY created_at DESC 
                LIMIT :limit";
        
        $stmt = $this->database->prepare($sql);
        $stmt->bindValue('start_date', $startDate . ' 00:00:00');
        $stmt->bindValue('end_date', $endDate . ' 23:59:59');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Obtener estadísticas de llamadas
     */
    public function getStats(string $period = '24h'): array
    {
        $dateFilter = $this->getDateFilter($period);
        
        $sql = "SELECT 
            COUNT(*) as total_calls,
            COUNT(CASE WHEN status = 'answered' THEN 1 END) as answered_calls,
            COUNT(CASE WHEN status = 'missed' THEN 1 END) as missed_calls,
            COUNT(CASE WHEN direction = 'inbound' THEN 1 END) as inbound_calls,
            COUNT(CASE WHEN direction = 'outbound' THEN 1 END) as outbound_calls,
            AVG(CASE WHEN status = 'answered' THEN duration END) as avg_duration,
            SUM(CASE WHEN status = 'answered' THEN duration END) as total_duration,
            COUNT(CASE WHEN ai_sentiment = 'positive' THEN 1 END) as positive_sentiment,
            COUNT(CASE WHEN ai_sentiment = 'negative' THEN 1 END) as negative_sentiment,
            COUNT(CASE WHEN ai_sentiment = 'neutral' THEN 1 END) as neutral_sentiment
        FROM {$this->table} 
        WHERE {$dateFilter}";
        
        $stmt = $this->database->query($sql);
        return $stmt->fetch();
    }
    
    /**
     * Buscar llamadas con filtros múltiples
     */
    public function search(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];
        
        // Filtro por estado
        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }
        
        // Filtro por dirección
        if (!empty($filters['direction'])) {
            $where[] = 'direction = :direction';
            $params['direction'] = $filters['direction'];
        }
        
        // Filtro por teléfono
        if (!empty($filters['phone'])) {
            $where[] = 'phone_number LIKE :phone';
            $params['phone'] = '%' . $filters['phone'] . '%';
        }
        
        // Filtro por sentimiento
        if (!empty($filters['sentiment'])) {
            $where[] = 'ai_sentiment = :sentiment';
            $params['sentiment'] = $filters['sentiment'];
        }
        
        // Filtro por fecha desde
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        
        // Filtro por fecha hasta
        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        
        // Filtro por duración mínima
        if (!empty($filters['min_duration'])) {
            $where[] = 'duration >= :min_duration';
            $params['min_duration'] = $filters['min_duration'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Contar total
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} WHERE {$whereClause}";
        $countStmt = $this->database->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];
        
        // Obtener datos paginados
        $orderBy = $filters['order_by'] ?? 'created_at';
        $direction = strtoupper($filters['direction_sort'] ?? 'DESC');
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM {$this->table} 
                WHERE {$whereClause} 
                ORDER BY {$orderBy} {$direction} 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->database->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $data = $stmt->fetchAll();
        
        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => (int) $total,
                'total_pages' => ceil($total / $perPage),
                'has_next' => $page < ceil($total / $perPage),
                'has_prev' => $page > 1
            ]
        ];
    }
    
    /**
     * Obtener llamadas recientes
     */
    public function getRecent(int $limit = 10): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT :limit";
        $stmt = $this->database->prepare($sql);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Obtener llamadas perdidas
     */
    public function getMissedCalls(int $limit = 50): array
    {
        return $this->getByStatus('missed', $limit);
    }
    
    /**
     * Obtener llamadas respondidas
     */
    public function getAnsweredCalls(int $limit = 50): array
    {
        return $this->getByStatus('answered', $limit);
    }
    
    /**
     * Obtener llamadas con transcripción
     */
    public function getTranscribedCalls(int $limit = 50): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE ai_transcription IS NOT NULL AND ai_transcription != '' 
                ORDER BY created_at DESC 
                LIMIT :limit";
        
        $stmt = $this->database->prepare($sql);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Obtener llamadas por sentimiento
     */
    public function getBySentiment(string $sentiment, int $limit = 50): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE ai_sentiment = :sentiment 
                ORDER BY created_at DESC 
                LIMIT :limit";
        
        $stmt = $this->database->prepare($sql);
        $stmt->bindValue('sentiment', $sentiment);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Actualizar sentimiento de IA
     */
    public function updateAISentiment(int $id, string $sentiment, ?string $summary = null): bool
    {
        $sql = "UPDATE {$this->table} 
                SET ai_sentiment = :sentiment";
        
        $params = [
            'id' => $id,
            'sentiment' => $sentiment
        ];
        
        if ($summary !== null) {
            $sql .= ", ai_summary = :summary";
            $params['summary'] = $summary;
        }
        
        $sql .= ", updated_at = NOW() WHERE id = :id";
        
        $stmt = $this->database->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Actualizar transcripción de IA
     */
    public function updateAITranscription(int $id, string $transcription): bool
    {
        $sql = "UPDATE {$this->table}
                SET ai_transcription = :transcription, updated_at = NOW()
                WHERE id = :id";
        
        $stmt = $this->database->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'transcription' => $transcription
        ]);
    }

    /**
     * Update AI generated keywords
     */
    public function updateAIKeywords(int $id, string $keywords): bool
    {
        $sql = "UPDATE {$this->table}
                SET ai_keywords = :keywords, updated_at = NOW()
                WHERE id = :id";

        $stmt = $this->database->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'keywords' => $keywords
        ]);
    }
    
    /**
     * Vincular con contacto de Pipedrive
     */
    public function linkToPipedrive(int $id, int $contactId): bool
    {
        $sql = "UPDATE {$this->table} 
                SET pipedrive_contact_id = :contact_id, updated_at = NOW() 
                WHERE id = :id";
        
        $stmt = $this->database->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'contact_id' => $contactId
        ]);
    }
    
    /**
     * Obtener llamadas sin procesar por IA
     */
    public function getUnprocessedCalls(int $limit = 20): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE (ai_transcription IS NULL OR ai_sentiment IS NULL) 
                AND status = 'answered' 
                AND duration > 10 
                ORDER BY created_at DESC 
                LIMIT :limit";
        
        $stmt = $this->database->prepare($sql);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Marcar llamada como procesada
     */
    public function markAsProcessed(int $id): bool
    {
        $sql = "UPDATE {$this->table} 
                SET updated_at = NOW() 
                WHERE id = :id";
        
        $stmt = $this->database->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Obtener filtro de fecha según período
     */
    private function getDateFilter(string $period): string
    {
        return match($period) {
            '1h' => "created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            '24h' => "created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            '7d' => "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            '30d' => "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            '90d' => "created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
            default => "created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        };
    }
    
    /**
     * Validar datos antes de guardar
     */
    protected function validate(array $data): array
    {
        $errors = [];
        
        // Validar teléfono
        if (empty($data['phone_number'])) {
            $errors['phone_number'] = 'Phone number is required';
        }
        
        // Validar dirección
        if (!in_array($data['direction'] ?? '', ['inbound', 'outbound'])) {
            $errors['direction'] = 'Direction must be inbound or outbound';
        }
        
        // Validar estado
        if (!in_array($data['status'] ?? '', ['answered', 'missed', 'busy', 'failed'])) {
            $errors['status'] = 'Invalid status';
        }
        
        // Validar duración
        if (isset($data['duration']) && $data['duration'] < 0) {
            $errors['duration'] = 'Duration cannot be negative';
        }
        
        // Validar sentimiento
        if (isset($data['ai_sentiment']) && !in_array($data['ai_sentiment'], ['positive', 'negative', 'neutral'])) {
            $errors['ai_sentiment'] = 'Invalid sentiment value';
        }
        
        if (!empty($errors)) {
            throw new Exception('Validation failed: ' . json_encode($errors));
        }
        
        return $data;
    }
}

