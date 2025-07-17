<?php
session_start();
require_once "../../config/database.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"];
    $password = $_POST["password"];
    $remember = isset($_POST["remember"]);

    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin["password"])) {
        $_SESSION["admin"] = [
            'id' => $admin['id'],
            'username' => $admin['username'],
            'email' => $admin['email'],
            'role' => $admin['role'],
            'created_at' => $admin['created_at'],
        ];
        
        if ($remember) {
            // Stocker les infos dans un cookie (30 jours)
            setcookie('admin_remember', $username, time() + 30*24*3600, '/');
        }
        
        header("Location: /vues/back-office/admin/dashboard/dashboard.php");
        exit();
    } else {
        $error = "Identifiants invalides !";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin</title>
    <link rel="stylesheet" href="/assets/css/back-office/auth/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2>Connexion Admin</h2>
            <p>Veuillez entrer vos identifiants</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST" id="loginForm">
            <div class="form-group">
                <input type="text" class="form-control" name="username" id="username" placeholder="Nom d'utilisateur" required>
            </div>
            
            <div class="form-group">
                <input type="password" class="form-control" name="password" id="password" placeholder="Mot de passe" required>
            </div>
            
            <div class="remember-forgot">
                <div class="remember-me">
                    <input type="checkbox" name="remember" id="remember">
                    <label for="remember">Se souvenir de moi</label>
                </div>
                <div class="forgot-password">
                    <a href="forgot-password.php">Mot de passe oublié ?</a>
                </div>
            </div>
            
            <button type="submit" class="btn">Se connecter</button>
        </form>
    </div>

    <script>
        // Gestion de "Se souvenir de moi" avec sessionStorage
        document.addEventListener('DOMContentLoaded', function() {
            const rememberCheckbox = document.getElementById('remember');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const loginForm = document.getElementById('loginForm');
            
            // Vérifier si des informations sont stockées
            if (sessionStorage.getItem('rememberAdmin') === 'true') {
                rememberCheckbox.checked = true;
                usernameInput.value = sessionStorage.getItem('adminUsername') || '';
                passwordInput.value = sessionStorage.getItem('adminPassword') || '';
            }
            
            // Gérer la soumission du formulaire
            loginForm.addEventListener('submit', function() {
                if (rememberCheckbox.checked) {
                    sessionStorage.setItem('rememberAdmin', 'true');
                    sessionStorage.setItem('adminUsername', usernameInput.value);
                    sessionStorage.setItem('adminPassword', passwordInput.value);
                } else {
                    sessionStorage.removeItem('rememberAdmin');
                    sessionStorage.removeItem('adminUsername');
                    sessionStorage.removeItem('adminPassword');
                }
            });
        });
    </script>
</body>
</html>