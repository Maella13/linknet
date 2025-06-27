<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../config/database.php";
if (!isset($_SESSION["admin"])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION["admin"]["role"] ?? 'Modérateur';

// Table => [fichier, icône FontAwesome]
$tables = [
    'Utilisateurs' => ['users.php', 'fa-user'],
    'Posts' => ['posts.php', 'fa-pen-nib'],
    'Commentaires' => ['comments.php', 'fa-comment-dots'],
    'Likes' => ['likes.php', 'fa-heart'],
    'Messages' => ['messages.php', 'fa-envelope'],
    'Amitiés' => ['friends.php', 'fa-user-friends'],
    'Abonnés' => ['followers.php', 'fa-users'],
    'Posts en vedette' => ['featured_posts.php', 'fa-star'],
    'Hashtags' => ['hashtags.php', 'fa-hashtag'],
    'Notifications' => ['notifications.php', 'fa-bell'],
    'Signalements' => ['reports.php', 'fa-flag'],
    'Admins' => ['admins.php', 'fa-user-shield'],
    'Requêtes d\'amis' => ['friend_requests.php', 'fa-user-plus'],
];

$mod_links = ['Utilisateurs', 'Posts', 'Commentaires', 'Likes', 'Messages', 'Amitiés', 'Abonnés', 'Hashtags', 'Notifications'];
$admin_links = array_keys($tables);
$accessible = ($role === 'Administrateur') ? $admin_links : $mod_links;

// Couleurs de survol par index
$hover_colors = [
    '#2563eb', // bleu
    '#a16207', // marron/doré
    '#22c55e', // vert
    '#f59e42', // orange
    '#8b5cf6', // violet
    '#0ea5e9', // bleu clair
    '#f43f5e', // rose/rouge
    '#fbbf24', // jaune
    '#14b8a6', // turquoise
    '#6366f1', // indigo
    '#e11d48', // rouge foncé
    '#7c3aed', // violet foncé
    '#475569', // gris foncé
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
            color: #333;
            display: flex;
            min-height: 100vh;
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
        
        <?php foreach ($hover_colors as $i => $color): ?>
        .menu-sidebar-pro-link.menu-hover-<?= $i ?>:hover, 
        .menu-sidebar-pro-link.menu-hover-<?= $i ?>.active { 
            background: <?= $color ?> !important; 
        }
        <?php endforeach; ?>
        
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
        }
        
        .dashboard-container {
            max-width: 1500px;
            margin: 0 auto;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .menu-categories {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .menu-btn {
            padding: 10px 22px;
            border: none;
            border-radius: 6px;
            background-color: #e9ecef;
            color: #2563eb;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 16px;
        }
        
        .menu-btn.active, .menu-btn:hover {
            background-color: var(--primary);
            color: #fff;
        }
        
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px 20px;
            box-shadow: 0 4px 12px rgba(37,99,235,0.07);
            border-left: 6px solid var(--primary);
            transition: box-shadow 0.3s;
            position: relative;
        }
        
        .stat-card[data-cat="users"] { border-color: var(--primary); }
        .stat-card[data-cat="posts"] { border-color: var(--info); }
        .stat-card[data-cat="interactions"] { border-color: var(--success); }
        .stat-card[data-cat="admin"] { border-color: var(--dark); }
        .stat-card[data-cat="reports"] { border-color: var(--danger); }
        
        .stat-card h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 17px;
            color: #2563eb;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .chart-section,
        .chart-circle {
            width: 49%;
            max-width: 49%;
        }
        
        @media (max-width: 900px) {
            .analytics-grid { grid-template-columns: 1fr; }
            .charts-row {
                flex-direction: column;
                gap: 0;
            }
            .charts-row > .chart-section,
            .charts-row > .chart-circle {
                width: 100%;
                max-width: 100%;
            }
        }
        
        .period-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .period-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            background-color: #e9ecef;
            color: #2563eb;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .period-btn.active, .period-btn:hover {
            background-color: var(--primary);
            color: #fff;
        }
        
        .charts-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0 2%;
            margin-bottom: 30px;
        }
        
        .charts-row > .chart-section,
        .charts-row > .chart-circle {
            width: 49%;
            max-width: 49%;
            margin: 0;
        }
        
        @media (max-width: 900px) {
            .charts-row {
                flex-direction: column;
                gap: 0;
            }
            .charts-row > .chart-section,
            .charts-row > .chart-circle {
                width: 100%;
                max-width: 100%;
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
                <a class="menu-sidebar-pro-link menu-hover-0" href="dashboard.php">
                    <span class="ms-icon"><i class="fas fa-tachometer-alt"></i></span>
                    <span class="ms-label">Dashboard</span>
                </a>
            </li>
            
            <?php $i = 1; foreach ($tables as $label => $arr): ?>
                <?php if (in_array($label, $accessible)): ?>
                    <li>
                        <a class="menu-sidebar-pro-link menu-hover-<?= $i ?>" href="<?= htmlspecialchars($arr[0]) ?>">
                            <span class="ms-icon"><i class="fas <?= $arr[1] ?>"></i></span>
                            <span class="ms-label"><?= htmlspecialchars($label) ?></span>
                        </a>
                    </li>
                    <?php $i++; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
        
        <div class="menu-footer">
            <a class="menu-sidebar-pro-profile" href="profile.php">
                <span class="ms-icon"><i class="fas fa-user-circle"></i></span>
                <span class="ms-label">Profil</span>
            </a>
            <a class="menu-sidebar-pro-logout" href="logout.php">
                <span class="ms-icon"><i class="fas fa-sign-out-alt"></i></span>
                <span class="ms-label">Déconnexion</span>
            </a>
        </div>
    </div>
    
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
    
    // Gestion de l'état actif des liens du menu
    const menuLinks = document.querySelectorAll('.menu-sidebar-pro-link');
    
    // Fonction pour définir le lien actif
    function setActiveLink(clickedLink) {
        // Retirer la classe 'active' de tous les liens
        menuLinks.forEach(link => {
            link.classList.remove('active');
        });
        // Ajouter la classe 'active' au lien cliqué
        clickedLink.classList.add('active');
    }
    
    // Ajouter l'événement click à tous les liens du menu
    menuLinks.forEach(link => {
        link.addEventListener('click', function() {
            setActiveLink(this);
        });
    });
    
    // Définir le lien actif selon la page courante
    const currentPage = window.location.pathname.split('/').pop();
    menuLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage) {
            setActiveLink(link);
        }
    });
    </script>