CREATE TABLE IF NOT EXISTS error_logs(
  id INT AUTO_INCREMENT PRIMARY KEY,
  error_type VARCHAR(50) NOT NULL,
  error_message TEXT NOT NULL,
  error_code VARCHAR(50) NULL,
  file_name VARCHAR(255) NULL,
  line_number INT NULL,
  stack_trace TEXT NULL,
  request_data TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS sync_logs(
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id VARCHAR(100) NOT NULL,
  service_name VARCHAR(50) NOT NULL,
  operation VARCHAR(50) NOT NULL,
  status ENUM('started','in_progress','completed','failed') DEFAULT 'started',
  total_records INT DEFAULT 0,
  processed_records INT DEFAULT 0,
  error_count INT DEFAULT 0,
  execution_time DECIMAL(8,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
