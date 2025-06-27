<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../config/database.php";

if (!isset($_SESSION["admin"])) {
    header("Location: login.php");
    exit();
}

$adminId = $_SESSION["admin"]["id"] ?? null;
$role = $_SESSION["admin"]["role"] ?? 'Modérateur';

if (!$adminId) {
    header("Location: login.php");
    exit();
}

// Initialisation
$error = $success = '';

// Récupération des données admin
try {
    $stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        header("Location: login.php");
        exit();
    }
} catch (PDOException $e) {
    $error = "Erreur de base de données";
}

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mise à jour des infos
    if (isset($_POST['update_info'])) {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($username) || empty($email)) {
            $error = "Tous les champs sont requis";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Email invalide";
        } else {
            try {
                $stmt = $conn->prepare("UPDATE admins SET username = ?, email = ? WHERE id = ?");
                $stmt->execute([$username, $email, $adminId]);
                
                $_SESSION['admin']['username'] = $username;
                $_SESSION['admin']['email'] = $email;
                $admin['username'] = $username;
                $admin['email'] = $email;
                
                $success = "Informations mises à jour avec succès";
            } catch (PDOException $e) {
                $error = "Erreur lors de la mise à jour";
            }
        }
    }
    
    // Changement de mot de passe
    if (isset($_POST['update_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if (empty($current) || empty($new) || empty($confirm)) {
            $error = "Tous les champs sont requis";
        } elseif (!password_verify($current, $admin['password'])) {
            $error = "Mot de passe actuel incorrect";
        } elseif ($new !== $confirm) {
            $error = "Les mots de passe ne correspondent pas";
        } elseif (strlen($new) < 8) {
            $error = "Le mot de passe doit contenir au moins 8 caractères";
        } else {
            try {
                $hash = password_hash($new, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
                $stmt->execute([$hash, $adminId]);
                $success = "Mot de passe mis à jour avec succès";
            } catch (PDOException $e) {
                $error = "Erreur lors de la mise à jour du mot de passe";
            }
        }
    }
    
    // Suppression de compte (seulement pour les administrateurs)
    if (isset($_POST['delete_account']) && $role === 'Administrateur') {
        $password = $_POST['delete_password'] ?? '';
        
        if (empty($password)) {
            $error = "Veuillez entrer votre mot de passe";
        } elseif (!password_verify($password, $admin['password'])) {
            $error = "Mot de passe incorrect";
        } else {
            try {
                $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
                $stmt->execute([$adminId]);
                session_destroy();
                header("Location: login.php");
                exit();
            } catch (PDOException $e) {
                $error = "Erreur lors de la suppression";
            }
        }
    }
}

function calculateTimeSince($date) {
    $now = new DateTime();
    $created = new DateTime($date);
    $interval = $now->diff($created);
    
    if ($interval->y > 0) {
        return $interval->y . ' an' . ($interval->y > 1 ? 's' : '');
    } elseif ($interval->m > 0) {
        return $interval->m . ' mois';
    } else {
        return $interval->d . ' jour' . ($interval->d > 1 ? 's' : '');
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Administrateur - Linknet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --secondary: #3b82f6;
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e42;
            --info: #0ea5e9;
            --light: #f8fafc;
            --dark: #1e293b;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* Menu styles */
        .menu-sidebar-pro {
            width: 240px;
            background: #fff;
            border-right: 1px solid #e5e7eb;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            transition: width 0.3s cubic-bezier(0.4,0,0.2,1);
            flex-shrink: 0;
            height: 100vh;
            position: sticky;
            top: 0;
            overflow-y: auto;
        }
        
        .menu-sidebar-pro.collapsed {
            width: 85px;
        }
        
        .menu-sidebar-pro.collapsed .ms-label,
        .menu-sidebar-pro.collapsed .menu-title {
            display: none;
        }
        
        .menu-sidebar-pro.collapsed .menu-header {
            justify-content: center;
        }
        
        .menu-sidebar-pro.collapsed .menu-sidebar-pro-link {
            justify-content: center;
            padding: 12px 8px;
            margin: 0 4px;
        }
        
        .menu-sidebar-pro.collapsed .menu-sidebar-pro-link .ms-icon {
            font-size: 18px;
            background: transparent;
            min-width: 32px;
            min-height: 32px;
        }
        
        .menu-sidebar-pro.collapsed .menu-sidebar-pro-profile,
        .menu-sidebar-pro.collapsed .menu-sidebar-pro-logout {
            justify-content: center;
            padding: 12px 8px;
            margin: 0 4px;
            gap: 0;
        }
        
        .menu-sidebar-pro.collapsed .menu-sidebar-pro-profile .ms-icon,
        .menu-sidebar-pro.collapsed .menu-sidebar-pro-logout .ms-icon {
            font-size: 18px;
            background: transparent;
            min-width: 32px;
            min-height: 32px;
        }
        
        .menu-sidebar-pro .menu-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 16px;
            border-bottom: 1px solid #f3f4f6;
            min-height: 70px;
        }
        
        .menu-sidebar-pro .menu-title {
            color: #1e40af;
            font-size: 20px;
            font-weight: 700;
            white-space: nowrap;
        }
        
        .menu-sidebar-pro .menu-toggle-btn {
            background: #eff6ff;
            border: none;
            color: #1e40af;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .menu-sidebar-pro-list {
            list-style: none;
            padding: 12px 0;
            margin: 0;
            flex: 1;
            overflow-y: auto;
        }
        
        .menu-sidebar-pro-link {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #4b5563;
            font-weight: 500;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            margin: 0 8px;
            white-space: nowrap;
        }
        
        .menu-sidebar-pro-link .ms-icon {
            min-width: 32px;
            min-height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            border-radius: 8px;
            background: #f9fafb;
            color: #4b5563;
            transition: all 0.2s;
        }
        
        .menu-sidebar-pro-link .ms-label { 
            transition: opacity 0.3s;
        }
        
        .menu-sidebar-pro-link:hover, .menu-sidebar-pro-link.active { 
            color: #fff; 
            background: #3b82f6; 
        }
        
        .menu-sidebar-pro-link:hover .ms-icon, 
        .menu-sidebar-pro-link.active .ms-icon { 
            background: rgba(255,255,255,0.2); 
            color: #fff; 
        }
        
        .menu-footer {
            padding: 16px;
            border-top: 1px solid #f3f4f6;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .menu-sidebar-pro-profile, .menu-sidebar-pro-logout {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            font-weight: 500;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            margin: 0 8px;
        }
        
        .menu-sidebar-pro-profile { 
            color: #4b5563; 
            background: #f9fafb; 
        }
        
        .menu-sidebar-pro-logout { 
            color: #dc2626; 
            background: #fef2f2; 
        }
        
        .menu-sidebar-pro-profile .ms-icon { 
            background: #f0fdf4; 
            color: #16a34a; 
        }
        
        .menu-sidebar-pro-logout .ms-icon { 
            background: #fee2e2; 
            color: #dc2626; 
        }
        
        .menu-sidebar-pro-profile:hover { 
            background: #16a34a; 
            color: #fff; 
        }
        
        .menu-sidebar-pro-profile:hover .ms-icon { 
            background: rgba(255,255,255,0.2); 
            color: #fff; 
        }
        
        .menu-sidebar-pro-logout:hover { 
            background: #dc2626; 
            color: #fff; 
        }
        
        .menu-sidebar-pro-logout:hover .ms-icon { 
            background: rgba(255,255,255,0.2); 
            color: #fff; 
        }
        
        /* Main content styles */
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            height: 100vh;
            max-width: calc(100vw - 240px);
        }
        
        .menu-sidebar-pro.collapsed + .main-content {
            max-width: calc(100vw - 85px);
        }
        
        .profile-container {
            max-width: 1500px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .header h1 {
            color: #2563eb;
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        /* Alertes */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid;
        }
        
        .alert-danger {
            background: #fef2f2;
            color: #dc2626;
            border-color: #ef4444;
        }
        
        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border-color: #22c55e;
        }
        
        /* Carte de profil */
        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(37,99,235,0.07);
            margin-bottom: 30px;
            border-left: 6px solid var(--primary);
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            box-shadow: 0 4px 12px rgba(37,99,235,0.3);
        }
        
        .profile-info h2 {
            color: #1e293b;
            font-size: 1.8rem;
            margin-bottom: 8px;
        }
        
        .profile-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        
        .role-badge {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 14px;
        }
        
        /* Sections */
        .profile-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(37,99,235,0.07);
            margin-bottom: 25px;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1e293b;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .section-title i {
            color: var(--primary);
        }
        
        /* Formulaires */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
            font-size: 14px;
        }
        
        .input-wrapper {
            position: relative;
            max-width: 400px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f9fafb;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6b7280;
            transition: color 0.3s;
        }
        
        .password-toggle:hover {
            color: var(--primary);
        }
        
        /* Boutons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37,99,235,0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #f87171);
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #dc2626, #ef4444);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239,68,68,0.3);
        }
        
        /* Zone dangereuse */
        .danger-zone {
            border-left: 4px solid var(--danger);
            background: linear-gradient(135deg, #fef2f2, #fecaca);
        }
        
        .warning-box {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.2);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .warning-box p {
            color: #dc2626;
            font-size: 14px;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
        }
        
        .modal-content {
            background: white;
            margin: 10vh auto;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 450px;
            position: relative;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        
        .close-modal {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
            transition: color 0.3s;
        }
        
        .close-modal:hover {
            color: var(--danger);
        }
        
        .modal h3 {
            color: #1e293b;
            font-size: 1.4rem;
            margin-bottom: 15px;
        }
        
        .modal p {
            color: #64748b;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .profile-container {
                max-width: 1200px;
            }
        }
        
        @media (max-width: 900px) {
            .main-content {
                padding: 15px;
                max-width: 100vw;
            }
            
            .menu-sidebar-pro.collapsed + .main-content {
                max-width: 100vw;
            }
            
            .profile-container {
                padding: 0 15px;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-meta {
                justify-content: center;
            }
            
            .input-wrapper {
                max-width: 100%;
            }
            
            .modal-content {
                margin: 5vh auto;
                padding: 20px;
            }
        }
        
        @media (max-width: 600px) {
            .main-content {
                padding: 10px;
            }
            
            .profile-container {
                padding: 0 10px;
            }
            
            .profile-card,
            .profile-section {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 1.5rem;
                margin-bottom: 20px;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-meta {
                justify-content: center;
                flex-direction: column;
                gap: 10px;
            }
            
            .input-wrapper {
                max-width: 100%;
            }
            
            .modal-content {
                margin: 5vh auto;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Menu Sidebar -->
    <div class="menu-sidebar-pro" id="menuSidebarPro">
        <div class="menu-header">
            <span class="menu-title">Admin Panel</span>
            <button class="menu-toggle-btn" id="menuSidebarProToggle" title="Masquer le menu">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        
        <ul class="menu-sidebar-pro-list">
            <li>
                <a class="menu-sidebar-pro-link" href="dashboard.php">
                    <span class="ms-icon"><i class="fas fa-tachometer-alt"></i></span>
                    <span class="ms-label">Dashboard</span>
                </a>
            </li>
            
            <li>
                <a class="menu-sidebar-pro-link" href="users.php">
                    <span class="ms-icon"><i class="fas fa-user"></i></span>
                    <span class="ms-label">Utilisateurs</span>
                </a>
            </li>
            
            <li>
                <a class="menu-sidebar-pro-link" href="posts.php">
                    <span class="ms-icon"><i class="fas fa-pen-nib"></i></span>
                    <span class="ms-label">Posts</span>
                </a>
            </li>
            
            <li>
                <a class="menu-sidebar-pro-link" href="comments.php">
                    <span class="ms-icon"><i class="fas fa-comment-dots"></i></span>
                    <span class="ms-label">Commentaires</span>
                </a>
            </li>
            
            <li>
                <a class="menu-sidebar-pro-link" href="likes.php">
                    <span class="ms-icon"><i class="fas fa-heart"></i></span>
                    <span class="ms-label">Likes</span>
                </a>
            </li>
            
            <li>
                <a class="menu-sidebar-pro-link" href="messages.php">
                    <span class="ms-icon"><i class="fas fa-envelope"></i></span>
                    <span class="ms-label">Messages</span>
                </a>
            </li>
            
            <li>
                <a class="menu-sidebar-pro-link" href="friends.php">
                    <span class="ms-icon"><i class="fas fa-user-friends"></i></span>
                    <span class="ms-label">Amitiés</span>
                </a>
            </li>
            
            <li>
                <a class="menu-sidebar-pro-link" href="followers.php">
                    <span class="ms-icon"><i class="fas fa-users"></i></span>
                    <span class="ms-label">Abonnés</span>
                </a>
            </li>
            
            <li>
                <a class="menu-sidebar-pro-link" href="featured_posts.php">
                    <span class="ms-icon"><i class="fas fa-star"></i></span>
                    <span class="ms-label">Posts en vedette</span>
                </a>
            </li>
            
            <li>
                <a class="menu-sidebar-pro-link" href="hashtags.php">
                    <span class="ms-icon"><i class="fas fa-hashtag"></i></span>
                    <span class="ms-label">Hashtags</span>
                </a>
            </li>
            
            <li>
                <a class="menu-sidebar-pro-link" href="notifications.php">
                    <span class="ms-icon"><i class="fas fa-bell"></i></span>
                    <span class="ms-label">Notifications</span>
                </a>
            </li>
            
            <li>
                <a class="menu-sidebar-pro-link" href="reports.php">
                    <span class="ms-icon"><i class="fas fa-flag"></i></span>
                    <span class="ms-label">Signalements</span>
                </a>
            </li>
            
            <?php if ($role === 'Administrateur'): ?>
            <li>
                <a class="menu-sidebar-pro-link" href="admins.php">
                    <span class="ms-icon"><i class="fas fa-user-shield"></i></span>
                    <span class="ms-label">Admins</span>
                </a>
            </li>
            
            <li>
                <a class="menu-sidebar-pro-link" href="friend_requests.php">
                    <span class="ms-icon"><i class="fas fa-user-plus"></i></span>
                    <span class="ms-label">Requêtes d'amis</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
        
        <div class="menu-footer">
            <a class="menu-sidebar-pro-profile active" href="profile.php">
                <span class="ms-icon"><i class="fas fa-user-circle"></i></span>
                <span class="ms-label">Profil</span>
            </a>
            <a class="menu-sidebar-pro-logout" href="logout.php">
                <span class="ms-icon"><i class="fas fa-sign-out-alt"></i></span>
                <span class="ms-label">Déconnexion</span>
            </a>
        </div>
    </div>

    <!-- Contenu principal -->
    <div class="main-content">
        <div class="profile-container">
            <div class="header">
                <h1>Profil Administrateur</h1>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <!-- Carte de profil -->
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="profile-info">
                        <h2><?= htmlspecialchars($admin['username']) ?></h2>
                        <div class="profile-meta">
                            <span class="role-badge"><?= htmlspecialchars($admin['role']) ?></span>
                            <span class="meta-item">
                                <i class="fas fa-envelope"></i>
                                <?= htmlspecialchars($admin['email']) ?>
                            </span>
                            <span class="meta-item">
                                <i class="fas fa-clock"></i>
                                Membre depuis <?= calculateTimeSince($admin['created_at']) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informations personnelles -->
            <div class="profile-section">
                <div class="section-title">
                    <i class="fas fa-user-cog"></i>
                    Informations personnelles
                </div>
                <form method="POST" class="profile-form">
                    <input type="hidden" name="update_info" value="1">
                    <div class="form-group">
                        <label class="form-label">Nom d'utilisateur</label>
                        <div class="input-wrapper">
                            <input type="text" name="username" class="form-input" value="<?= htmlspecialchars($admin['username']) ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <div class="input-wrapper">
                            <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($admin['email']) ?>" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Mettre à jour
                    </button>
                </form>
            </div>

            <!-- Sécurité du compte -->
            <div class="profile-section">
                <div class="section-title">
                    <i class="fas fa-key"></i>
                    Sécurité du compte
                </div>
                <form method="POST" class="profile-form">
                    <input type="hidden" name="update_password" value="1">
                    <div class="form-group">
                        <label class="form-label">Mot de passe actuel</label>
                        <div class="input-wrapper password-wrapper">
                            <input type="password" name="current_password" id="current_password" class="form-input" required>
                            <i class="fas fa-eye password-toggle" data-target="current_password"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nouveau mot de passe</label>
                        <div class="input-wrapper password-wrapper">
                            <input type="password" name="new_password" id="new_password" class="form-input" required minlength="8">
                            <i class="fas fa-eye password-toggle" data-target="new_password"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirmation</label>
                        <div class="input-wrapper password-wrapper">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-input" required minlength="8">
                            <i class="fas fa-eye password-toggle" data-target="confirm_password"></i>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key"></i>
                        Changer le mot de passe
                    </button>
                </form>
            </div>

            <!-- Zone dangereuse (seulement pour les administrateurs) -->
            <?php if ($role === 'Administrateur'): ?>
            <div class="profile-section danger-zone">
                <div class="section-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Zone dangereuse
                </div>
                <div class="warning-box">
                    <p>
                        <i class="fas fa-warning"></i>
                        La suppression de compte est irréversible. Toutes vos données seront perdues définitivement.
                    </p>
                </div>
                <button id="deleteBtn" class="btn btn-danger">
                    <i class="fas fa-trash"></i>
                    Supprimer mon compte
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de suppression (seulement pour les administrateurs) -->
    <?php if ($role === 'Administrateur'): ?>
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3>Confirmation de suppression</h3>
            <p>Pour confirmer la suppression de votre compte, veuillez entrer votre mot de passe :</p>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="delete_account" value="1">
                <div class="form-group">
                    <div class="input-wrapper password-wrapper">
                        <input type="password" name="delete_password" id="delete_password" class="form-input" required>
                        <i class="fas fa-eye password-toggle" data-target="delete_password"></i>
                    </div>
                </div>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash"></i>
                    Confirmer la suppression
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
    // Menu toggle functionality
    const sidebar = document.getElementById('menuSidebarPro');
    const toggleBtn = document.getElementById('menuSidebarProToggle');
    
    toggleBtn.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
    });
    
    // Store menu state in localStorage
    if (localStorage.getItem('menuCollapsed') === 'true') {
        sidebar.classList.add('collapsed');
    }
    
    toggleBtn.addEventListener('click', function() {
        const isCollapsed = sidebar.classList.contains('collapsed');
        localStorage.setItem('menuCollapsed', isCollapsed);
    });
    
    // Toggle password visibility
    document.querySelectorAll('.password-toggle').forEach(icon => {
        icon.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    });

    // Auto-hide success messages after 3 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const successMessages = document.querySelectorAll('.alert-success');
        const errorMessages = document.querySelectorAll('.alert-danger');
        
        // Auto-hide success messages
        successMessages.forEach(function(message) {
            setTimeout(function() {
                message.style.transition = 'opacity 0.5s ease-out';
                message.style.opacity = '0';
                setTimeout(function() {
                    message.remove();
                }, 500);
            }, 3000);
        });
        
        // Auto-hide error messages
        errorMessages.forEach(function(message) {
            setTimeout(function() {
                message.style.transition = 'opacity 0.5s ease-out';
                message.style.opacity = '0';
                setTimeout(function() {
                    message.remove();
                }, 500);
            }, 3000);
        });
    });

    // Gestion de la suppression (seulement pour les administrateurs)
    <?php if ($role === 'Administrateur'): ?>
    const deleteBtn = document.getElementById('deleteBtn');
    const deleteModal = document.getElementById('deleteModal');
    const deleteForm = document.getElementById('deleteForm');

    if (deleteBtn && deleteModal) {
        deleteBtn.addEventListener('click', () => {
            deleteModal.style.display = 'block';
        });

        document.querySelector('.close-modal').addEventListener('click', () => {
            deleteModal.style.display = 'none';
        });

        window.addEventListener('click', (event) => {
            if (event.target === deleteModal) {
                deleteModal.style.display = 'none';
            }
        });
    }
    <?php endif; ?>
    </script>
</body>
</html>

