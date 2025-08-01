<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Variables Entorno - Flujos Dimension v4.1</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1000px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.8em;
            font-weight: 600;
        }
        
        .back-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin: 25px 0 15px 0;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 8px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group input[type="password"] {
            font-family: monospace;
        }
        
        .form-group .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .save-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        
        .save-btn:hover {
            transform: translateY(-2px);
        }
        
        .test-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .test-btn:hover {
            background: #218838;
        }
        
        .config-status {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }
        
        .status-item:last-child {
            border-bottom: none;
        }
        
        .status-ok {
            color: #28a745;
            font-weight: 600;
        }
        
        .status-error {
            color: #dc3545;
            font-weight: 600;
        }
        
        .status-warning {
            color: #ffc107;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>⚙️ Editor de Variables de Entorno</h1>
            <a href="?action=dashboard" class="back-btn">← Volver al Dashboard</a>
        </div>
    </div>

    <div class="container">
        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="config-status">
            <h3>Estado de la Configuración</h3>
            <?php
            $configStatus = [
                'Base de Datos' => !empty($this->config->get('DB_HOST')) && !empty($this->config->get('DB_NAME')),
                'Administrador' => !empty($this->config->get('ADMIN_USER')) && !empty($this->config->get('ADMIN_PASS')),
                'JWT Secret' => !empty($this->config->get('JWT_SECRET')),
                'API Ringover' => !empty($this->config->get('RINGOVER_API_TOKEN')),
                'API OpenAI' => !empty($this->config->get('OPENAI_API_KEY')),
                'API Pipedrive' => !empty($this->config->get('PIPEDRIVE_API_TOKEN'))
            ];
            
            foreach ($configStatus as $item => $status): ?>
                <div class="status-item">
                    <span><?php echo $item; ?></span>
                    <span class="<?php echo $status ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $status ? '✅ Configurado' : '❌ Falta configurar'; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="form-container">
            <form method="POST" action="">
                <div class="section-title">👤 Administrador del Sistema</div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="ADMIN_USER">Usuario Administrador</label>
                        <input type="text" id="ADMIN_USER" name="ADMIN_USER" 
                               value="<?php echo htmlspecialchars($this->config->get('ADMIN_USER', 'admin')); ?>" required>
                        <div class="help-text">Usuario para acceder al panel de administración</div>
                    </div>
                    <div class="form-group">
                        <label for="ADMIN_PASS_NEW">Nueva Contraseña (opcional)</label>
                        <input type="password" id="ADMIN_PASS_NEW" name="ADMIN_PASS_NEW" placeholder="Dejar vacío para mantener actual">
                        <div class="help-text">Solo completar si deseas cambiar la contraseña</div>
                    </div>
                </div>
                
                <div class="section-title">🗄️ Base de Datos</div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="DB_HOST">Host de Base de Datos</label>
                        <input type="text" id="DB_HOST" name="DB_HOST" 
                               value="<?php echo htmlspecialchars($this->config->get('DB_HOST', 'localhost')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="DB_PORT">Puerto</label>
                        <input type="number" id="DB_PORT" name="DB_PORT" 
                               value="<?php echo htmlspecialchars($this->config->get('DB_PORT', '3306')); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="DB_NAME">Nombre de Base de Datos</label>
                        <input type="text" id="DB_NAME" name="DB_NAME" 
                               value="<?php echo htmlspecialchars($this->config->get('DB_NAME', '')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="DB_USER">Usuario de Base de Datos</label>
                        <input type="text" id="DB_USER" name="DB_USER" 
                               value="<?php echo htmlspecialchars($this->config->get('DB_USER', '')); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="DB_PASS">Contraseña de Base de Datos</label>
                    <input type="password" id="DB_PASS" name="DB_PASS" 
                           value="<?php echo htmlspecialchars($this->config->get('DB_PASS', '')); ?>" required>
                </div>
                
                <div class="section-title">📞 API Ringover</div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="RINGOVER_API_URL">URL de API Ringover</label>
                        <input type="url" id="RINGOVER_API_URL" name="RINGOVER_API_URL"
                               value="<?php echo htmlspecialchars($this->config->get('RINGOVER_API_URL', 'https://public-api.ringover.com/v2')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="RINGOVER_API_TOKEN">Token de API Ringover</label>
                        <input type="text" id="RINGOVER_API_TOKEN" name="RINGOVER_API_TOKEN"
                               value="<?php echo htmlspecialchars($this->config->get('RINGOVER_API_TOKEN', '')); ?>" required>
                        <button type="button" class="test-btn" onclick="testRingoverApi()">Probar</button>
                    </div>
                    <div class="form-group">
                        <label for="RINGOVER_MAX_RECORDING_MB">Tamaño máximo grabación (MB)</label>
                        <input type="number" id="RINGOVER_MAX_RECORDING_MB" name="RINGOVER_MAX_RECORDING_MB"
                               value="<?php echo htmlspecialchars($this->config->get('RINGOVER_MAX_RECORDING_MB', '100')); ?>" required>
                    </div>
                </div>
                
                <div class="section-title">🤖 API OpenAI</div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="OPENAI_API_URL">URL de API OpenAI</label>
                        <input type="url" id="OPENAI_API_URL" name="OPENAI_API_URL" 
                               value="<?php echo htmlspecialchars($this->config->get('OPENAI_API_URL', 'https://api.openai.com/v1')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="OPENAI_API_KEY">Clave de API OpenAI</label>
                        <input type="text" id="OPENAI_API_KEY" name="OPENAI_API_KEY" 
                               value="<?php echo htmlspecialchars($this->config->get('OPENAI_API_KEY', '')); ?>" required>
                        <button type="button" class="test-btn" onclick="testOpenAiApi()">Probar</button>
                    </div>
                </div>
                
                <div class="section-title">🏢 API Pipedrive</div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="PIPEDRIVE_API_URL">URL de API Pipedrive</label>
                        <input type="url" id="PIPEDRIVE_API_URL" name="PIPEDRIVE_API_URL" 
                               value="<?php echo htmlspecialchars($this->config->get('PIPEDRIVE_API_URL', 'https://api.pipedrive.com/v1')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="PIPEDRIVE_API_TOKEN">Token de API Pipedrive</label>
                        <input type="text" id="PIPEDRIVE_API_TOKEN" name="PIPEDRIVE_API_TOKEN" 
                               value="<?php echo htmlspecialchars($this->config->get('PIPEDRIVE_API_TOKEN', '')); ?>" required>
                        <button type="button" class="test-btn" onclick="testPipedriveApi()">Probar</button>
                    </div>
                </div>
                
                <div class="section-title">🔐 Seguridad y JWT</div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="JWT_SECRET">Clave Secreta JWT</label>
                        <input type="text" id="JWT_SECRET" name="JWT_SECRET" 
                               value="<?php echo htmlspecialchars($this->config->get('JWT_SECRET', '')); ?>" required>
                        <div class="help-text">Mínimo 32 caracteres para mayor seguridad</div>
                    </div>
                    <div class="form-group">
                        <label for="JWT_EXPIRATION_HOURS">Expiración JWT (horas)</label>
                        <input type="number" id="JWT_EXPIRATION_HOURS" name="JWT_EXPIRATION_HOURS" 
                               value="<?php echo htmlspecialchars($this->config->get('JWT_EXPIRATION_HOURS', '24')); ?>" required>
                    </div>
                </div>
                
                <div class="section-title">⚙️ Configuración del Sistema</div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="APP_ENV">Entorno de Aplicación</label>
                        <select id="APP_ENV" name="APP_ENV" style="width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px;">
                            <option value="production" <?php echo $this->config->get('APP_ENV') === 'production' ? 'selected' : ''; ?>>Producción</option>
                            <option value="development" <?php echo $this->config->get('APP_ENV') === 'development' ? 'selected' : ''; ?>>Desarrollo</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="API_TIMEOUT">Timeout de API (segundos)</label>
                        <input type="number" id="API_TIMEOUT" name="API_TIMEOUT" 
                               value="<?php echo htmlspecialchars($this->config->get('API_TIMEOUT', '30')); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="TIMEZONE">Zona Horaria</label>
                        <input type="text" id="TIMEZONE" name="TIMEZONE" 
                               value="<?php echo htmlspecialchars($this->config->get('TIMEZONE', 'Europe/Madrid')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="SESSION_LIFETIME">Duración de Sesión (segundos)</label>
                        <input type="number" id="SESSION_LIFETIME" name="SESSION_LIFETIME" 
                               value="<?php echo htmlspecialchars($this->config->get('SESSION_LIFETIME', '7200')); ?>" required>
                    </div>
                </div>
                
                <button type="submit" name="submit" class="save-btn">💾 Guardar Configuración</button>
            </form>
        </div>
    </div>

    <script>
        function testRingoverApi() {
            const token = document.getElementById('RINGOVER_API_TOKEN').value;
            if (!token) {
                alert('Por favor, introduce el token de Ringover primero');
                return;
            }
            
            alert('Función de test de Ringover en desarrollo. Token configurado: ' + (token ? 'Sí' : 'No'));
        }
        
        function testOpenAiApi() {
            const key = document.getElementById('OPENAI_API_KEY').value;
            if (!key) {
                alert('Por favor, introduce la clave de OpenAI primero');
                return;
            }
            
            alert('Función de test de OpenAI en desarrollo. Clave configurada: ' + (key ? 'Sí' : 'No'));
        }
        
        function testPipedriveApi() {
            const token = document.getElementById('PIPEDRIVE_API_TOKEN').value;
            if (!token) {
                alert('Por favor, introduce el token de Pipedrive primero');
                return;
            }
            
            alert('Función de test de Pipedrive en desarrollo. Token configurado: ' + (token ? 'Sí' : 'No'));
        }
        
        // Generar JWT Secret automáticamente si está vacío
        document.addEventListener('DOMContentLoaded', function() {
            const jwtSecretField = document.getElementById('JWT_SECRET');
            if (!jwtSecretField.value) {
                // Generar una clave aleatoria de 64 caracteres
                const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                let secret = '';
                for (let i = 0; i < 64; i++) {
                    secret += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                jwtSecretField.value = secret;
            }
        });
    </script>
</body>
</html>

