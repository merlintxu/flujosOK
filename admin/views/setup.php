<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Inicial - Flujos Dimension v4.1</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .setup-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .setup-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .setup-header h1 {
            font-size: 2.5em;
            color: #333;
            margin-bottom: 10px;
        }
        
        .setup-header p {
            color: #666;
            font-size: 1.1em;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
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
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .setup-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: transform 0.2s;
        }
        
        .setup-btn:hover {
            transform: translateY(-2px);
        }
        
        .progress-bar {
            background: #e1e5e9;
            border-radius: 10px;
            height: 8px;
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .progress-fill {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            width: 0%;
            transition: width 0.3s;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e1e5e9;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: 600;
            color: #666;
        }
        
        .step.active {
            background: #667eea;
            color: white;
        }
        
        .step.completed {
            background: #28a745;
            color: white;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .setup-container {
                padding: 20px;
                margin: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1>🚀 Flujos Dimension v4.1</h1>
            <p>Configuración inicial del sistema</p>
        </div>
        
        <div class="step-indicator">
            <div class="step active">1</div>
            <div class="step">2</div>
            <div class="step">3</div>
        </div>
        
        <div class="progress-bar">
            <div class="progress-fill" style="width: 33%;"></div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="?action=setup">
            <div class="section-title">👤 Administrador del Sistema</div>
            <div class="form-row">
                <div class="form-group">
                    <label for="admin_user">Usuario Administrador</label>
                    <input type="text" id="admin_user" name="admin_user" value="admin" required>
                    <div class="help-text">Usuario para acceder al panel de administración</div>
                </div>
                <div class="form-group">
                    <label for="admin_pass">Contraseña Administrador</label>
                    <input type="password" id="admin_pass" name="admin_pass" required>
                    <div class="help-text">Contraseña segura para el administrador</div>
                </div>
            </div>
            
            <div class="section-title">🗄️ Base de Datos</div>
            <div class="form-row">
                <div class="form-group">
                    <label for="db_host">Host de Base de Datos</label>
                    <input type="text" id="db_host" name="db_host" value="localhost" required>
                </div>
                <div class="form-group">
                    <label for="db_port">Puerto</label>
                    <input type="number" id="db_port" name="db_port" value="3306" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="db_name">Nombre de Base de Datos</label>
                    <input type="text" id="db_name" name="db_name" placeholder="flujos_dimension_v41" required>
                </div>
                <div class="form-group">
                    <label for="db_user">Usuario de Base de Datos</label>
                    <input type="text" id="db_user" name="db_user" required>
                </div>
            </div>
            <div class="form-group">
                <label for="db_pass">Contraseña de Base de Datos</label>
                <input type="password" id="db_pass" name="db_pass" required>
            </div>
            
            <div class="section-title">📞 API Ringover (Opcional)</div>
            <div class="form-row">
                <div class="form-group">
                    <label for="ringover_url">URL de API Ringover</label>
                    <input type="url" id="ringover_url" name="ringover_url" value="https://public-api.ringover.com/v2">
                </div>
                <div class="form-group">
                    <label for="ringover_token">Token de API Ringover</label>
                    <input type="text" id="ringover_token" name="ringover_token" placeholder="Token opcional">
                    <div class="help-text">Puedes configurarlo después desde el panel</div>
                </div>
            </div>
            
            <div class="section-title">🤖 API OpenAI (Opcional)</div>
            <div class="form-row">
                <div class="form-group">
                    <label for="openai_url">URL de API OpenAI</label>
                    <input type="url" id="openai_url" name="openai_url" value="https://api.openai.com/v1">
                </div>
                <div class="form-group">
                    <label for="openai_key">Clave de API OpenAI</label>
                    <input type="text" id="openai_key" name="openai_key" placeholder="sk-...">
                    <div class="help-text">Puedes configurarlo después desde el panel</div>
                </div>
            </div>
            
            <div class="section-title">🏢 API Pipedrive (Opcional)</div>
            <div class="form-row">
                <div class="form-group">
                    <label for="pipedrive_url">URL de API Pipedrive</label>
                    <input type="url" id="pipedrive_url" name="pipedrive_url" value="https://api.pipedrive.com/v1">
                </div>
                <div class="form-group">
                    <label for="pipedrive_token">Token de API Pipedrive</label>
                    <input type="text" id="pipedrive_token" name="pipedrive_token" placeholder="Token opcional">
                    <div class="help-text">Puedes configurarlo después desde el panel</div>
                </div>
            </div>
            
            <button type="submit" class="setup-btn">🚀 Completar Configuración</button>
        </form>
    </div>

    <script>
        // Generar contraseña segura automáticamente
        document.addEventListener('DOMContentLoaded', function() {
            const passField = document.getElementById('admin_pass');
            if (!passField.value) {
                const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
                let password = '';
                for (let i = 0; i < 12; i++) {
                    password += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                passField.value = password;
            }
        });
        
        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const requiredFields = ['admin_user', 'admin_pass', 'db_host', 'db_name', 'db_user', 'db_pass'];
            let hasErrors = false;
            
            requiredFields.forEach(fieldName => {
                const field = document.getElementById(fieldName);
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc3545';
                    hasErrors = true;
                } else {
                    field.style.borderColor = '#e1e5e9';
                }
            });
            
            if (hasErrors) {
                e.preventDefault();
                alert('Por favor, completa todos los campos requeridos');
            }
        });
    </script>
</body>
</html>

