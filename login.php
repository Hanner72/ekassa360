<?php
/**
 * Login-Seite
 * EKassa360
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Bereits eingeloggt?
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$info = '';

// Login-Versuch
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $benutzername = trim($_POST['benutzername'] ?? '');
    $passwort = $_POST['passwort'] ?? '';
    
    if (empty($benutzername) || empty($passwort)) {
        $error = 'Bitte Benutzername und Passwort eingeben.';
    } else {
        $result = loginUser($benutzername, $passwort);
        
        if ($result['success']) {
            $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
            unset($_SESSION['redirect_after_login']);
            
            if ($result['passwort_muss_geaendert']) {
                header('Location: passwort_aendern.php');
            } else {
                header('Location: ' . $redirect);
            }
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Info-Nachricht aus Session
if (isset($_SESSION['login_info'])) {
    $info = $_SESSION['login_info'];
    unset($_SESSION['login_info']);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EKassa360</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --ek-primary: #1e3c72;
            --ek-primary-light: #2a5298;
            --ek-gradient: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        }
        body {
            background: var(--ek-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 15px;
        }
        .login-card {
            background: #fff;
            border: none;
            border-radius: 0;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        .login-header {
            background: var(--ek-gradient);
            color: #fff;
            padding: 2rem;
            text-align: center;
        }
        .login-header .logo {
            width: 60px;
            height: 60px;
            background: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        .login-header .logo i {
            font-size: 2rem;
            color: var(--ek-primary);
        }
        .login-body {
            padding: 2rem;
        }
        .form-control, .input-group-text, .btn {
            border-radius: 0;
        }
        .form-control {
            padding: 0.75rem 1rem;
        }
        .form-control:focus {
            border-color: var(--ek-primary);
            box-shadow: 0 0 0 3px rgba(30, 60, 114, 0.15);
        }
        .btn-login {
            background: var(--ek-gradient);
            border: none;
            padding: 0.75rem;
            font-weight: 600;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #152a50 0%, #1e3c72 100%);
        }
        .input-group-text {
            background: #f8f9fa;
        }
        .alert {
            border-radius: 0;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card login-card">
            <div class="login-header">
                <div class="logo">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <h4 class="mb-1">EKassa360</h4>
                <small class="opacity-75">Buchhaltung für Österreich</small>
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($info): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i><?= htmlspecialchars($info) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Benutzername</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="benutzername" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['benutzername'] ?? '') ?>" 
                                   placeholder="Benutzername" required autofocus>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Passwort</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="passwort" class="form-control" 
                                   placeholder="Passwort" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-login w-100">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Anmelden
                    </button>
                </form>
            </div>
        </div>
        
        <p class="text-center text-white-50 mt-3 small">
            &copy; <?= date('Y') ?> EKassa360
        </p>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
