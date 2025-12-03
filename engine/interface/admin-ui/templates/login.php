<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вхід в адмін-панель - Flowaxy CMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= UrlHelper::admin('assets/styles/font-awesome/css/all.min.css') ?>" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
        }
        
        .login-card {
            background: #ffffff;
            border: 1px solid #e1e8ed;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        .login-header {
            background: #2c3e50;
            padding: 2.5rem 2rem;
            text-align: center;
            border-bottom: 3px solid #3498db;
        }
        
        .logo-icon {
            width: 70px;
            height: 70px;
            background: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
        }
        
        .logo-icon i {
            font-size: 2rem;
            color: #ffffff;
        }
        
        .login-header h1 {
            font-size: 1.5rem;
            margin: 0 0 0.5rem 0;
            font-weight: 600;
            color: #ffffff;
            letter-spacing: 0.5px;
        }
        
        .login-header p {
            margin: 0;
            font-size: 0.875rem;
            color: #bdc3c7;
            font-weight: 400;
        }
        
        .login-body {
            padding: 2.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 500;
            color: #34495e;
            margin-bottom: 0.5rem;
            display: block;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
            font-size: 1rem;
            z-index: 2;
        }
        
        .form-control {
            border: 1px solid #dce1e6;
            border-radius: 0;
            padding: 0.875rem 1rem 0.875rem 3rem;
            font-size: 0.9375rem;
            transition: border-color 0.15s ease;
            background: #ffffff;
            color: #2c3e50;
        }
        
        .form-control:focus {
            border-color: #3498db;
            box-shadow: none;
            background: #ffffff;
            outline: none;
        }
        
        .form-control::placeholder {
            color: #95a5a6;
        }
        
        .btn-login {
            background: #3498db;
            border: none;
            border-radius: 0;
            padding: 0.875rem 1rem;
            font-weight: 600;
            font-size: 0.9375rem;
            color: #ffffff;
            width: 100%;
            transition: background-color 0.15s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-login:hover {
            background: #2980b9;
        }
        
        .btn-login:active {
            background: #21618c;
        }
        
        .alert {
            border-radius: 0;
            border: none;
            border-left: 4px solid #e74c3c;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            background: #fadbd8;
            color: #c0392b;
        }
        
        .alert i {
            color: #e74c3c;
        }
        
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .back-link a {
            color: #7f8c8d;
            text-decoration: none;
            font-size: 0.875rem;
            transition: color 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .back-link a:hover {
            color: #3498db;
        }
        
        .security-badge {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #ecf0f1;
        }
        
        .security-badge small {
            font-size: 0.8125rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        /* Адаптивность */
        @media (max-width: 576px) {
            .login-body {
                padding: 2rem 1.5rem;
            }
            
            .login-header {
                padding: 2rem 1.5rem;
            }
            
            .login-header h1 {
                font-size: 1.25rem;
            }
            
            .logo-icon {
                width: 60px;
                height: 60px;
            }
            
            .logo-icon i {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-icon">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <h1>Flowaxy CMS</h1>
                <p>Адміністративна панель</p>
            </div>
            
            <div class="login-body">
                <?php if (! empty($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['message']) && $_GET['message'] === 'session_expired'): ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Ваш аккаунт був використаний з іншого пристрою або браузера. Будь ласка, увійдіть знову.
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    
                    <div class="form-group">
                        <label for="username" class="form-label">Логін</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user input-icon"></i>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="username" 
                                name="username" 
                                placeholder="Введіть ваш логін"
                                required 
                                autofocus
                            >
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Пароль</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input 
                                type="password" 
                                class="form-control" 
                                id="password" 
                                name="password" 
                                placeholder="Введіть ваш пароль"
                                required
                            >
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>Увійти в систему
                    </button>
                </form>
                
                <div class="security-badge">
                    <small>
                        <?php if (isset($isSecureConnection) && $isSecureConnection): ?>
                            <i class="fas fa-shield-alt" style="color: #27ae60;"></i>
                            <span style="color: #27ae60;">Захищено SSL та CSRF токеном</span>
                        <?php else: ?>
                            <i class="fas fa-shield-alt" style="color: #f39c12;"></i>
                            <span style="color: #f39c12;">Захищено CSRF токеном</span>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
        
        <div class="back-link">
            <a href="/">
                <i class="fas fa-arrow-left"></i>
                Повернутися на сайт
            </a>
        </div>
    </div>
</body>
</html>
