<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Flujos Dimension v4.5</title>
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
        
        .login-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 90%;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            font-size: 2.5em;
            color: #333;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #666;
            font-size: 1.1em;
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
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .login-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: transform 0.2s;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e1e5e9;
        }
        
        .login-footer p {
            color: #666;
            font-size: 14px;
        }
        
        .version-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
        }
        
        .version-info h4 {
            color: #333;
            margin-bottom: 5px;
        }
        
        .version-info p {
            color: #666;
            font-size: 12px;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 20px;
                margin: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🚀</h1>
            <h1>Flujos Dimension</h1>
            <p>Panel de Administración v4.1</p>
        </div>
        
        <?php if (isset($_GET['setup']) && $_GET['setup'] === 'success'): ?>
            <div class="alert alert-success">
                ✅ Configuración inicial completada exitosamente. Ahora puedes iniciar sesión.
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
            <div class="alert alert-success">
                ✅ Sesión cerrada correctamente.
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="?action=login">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf ?? ''); ?>">
            <div class="form-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="login-btn">🔐 Iniciar Sesión</button>
        </form>
        
        <div class="version-info">
            <h4>Flujos Dimension v4.1</h4>
            <p>Sistema de gestión de llamadas con IA</p>
            <p>Integración: Ringover • OpenAI • Pipedrive</p>
        </div>
        
        <div class="login-footer">
            <p>© 2025 Flujos Dimension - Desarrollado por Manus AI</p>
        </div>
    </div>

    <script>
        // Auto-focus en el campo de usuario
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
        
        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!username || !password) {
                e.preventDefault();
                alert('Por favor, completa todos los campos');
                return;
            }
            
            if (username.length < 3) {
                e.preventDefault();
                alert('El usuario debe tener al menos 3 caracteres');
                return;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 6 caracteres');
                return;
            }
        });
        
        // Manejo de tecla Enter
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const form = document.querySelector('form');
                form.submit();
            }
        });
    </script>
</body>
</html>

