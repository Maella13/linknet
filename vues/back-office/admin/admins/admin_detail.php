<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../../config/database.php";

if (!isset($_SESSION["admin"])) {
    http_response_code(403);
    exit('Accès refusé');
}

$role = $_SESSION["admin"]["role"] ?? 'Modérateur';

if (!isset($_GET['id'])) {
    exit('ID administrateur manquant');
}

$adminId = (int)$_GET['id'];

// Récupérer les informations détaillées de l'administrateur
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    exit('Administrateur non trouvé');
}

// Récupérer les vraies statistiques de la base de données
$statsStmt = $conn->prepare("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM posts) as total_posts,
        (SELECT COUNT(*) FROM comments) as total_comments,
        (SELECT COUNT(*) FROM reports) as total_reports,
        (SELECT COUNT(*) FROM admins WHERE role = 'Administrateur') as total_admins,
        (SELECT COUNT(*) FROM admins WHERE role = 'Modérateur') as total_moderators,
        (SELECT COUNT(*) FROM likes) as total_likes,
        (SELECT COUNT(*) FROM messages) as total_messages,
        (SELECT COUNT(*) FROM friends WHERE status = 'accepted') as total_friendships,
        (SELECT COUNT(*) FROM followers) as total_followers,
        (SELECT COUNT(*) FROM notifications) as total_notifications,
        (SELECT COUNT(*) FROM featured_posts) as total_featured_posts
");
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les actions récentes de cet admin (exemple avec les logs si disponibles)
$recentActionsStmt = $conn->prepare("
    SELECT 
        'Connexion' as action_type,
        created_at as action_date,
        'Dernière connexion' as description
    FROM admins 
    WHERE id = ?
    UNION ALL
    SELECT 
        'Modification' as action_type,
        updated_at as action_date,
        'Dernière modification du profil' as description
    FROM admins 
    WHERE id = ? AND updated_at IS NOT NULL
    ORDER BY action_date DESC
    LIMIT 5
");
$recentActionsStmt->execute([$adminId, $adminId]);
$recentActions = $recentActionsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<link rel="stylesheet" href="/assets/css/back-office/details/admin_detail.css">

<div class="user-detail-container">
    <!-- En-tête administrateur -->
    <div class="user-header-detail">
        <div class="user-avatar-large">
            <i class="fas <?= $admin['role'] === 'Administrateur' ? 'fa-crown' : 'fa-user-cog' ?>" style="font-size: 4rem; color: <?= $admin['role'] === 'Administrateur' ? '#7c3aed' : '#a78bfa' ?>;"></i>
        </div>
        <div class="user-info-detail">
            <h1><?= htmlspecialchars($admin['username']) ?></h1>
            <p class="user-email"><?= htmlspecialchars($admin['email']) ?></p>
            <span class="role-badge-large <?= $admin['role'] === 'Administrateur' ? 'role-admin' : 'role-moderator' ?>">
                <?= htmlspecialchars($admin['role']) ?>
            </span>
            <p class="user-join-date">
                <i class="fas fa-calendar"></i>
                Créé le <?= date('d/m/Y à H:i', strtotime($admin['created_at'])) ?>
            </p>
        </div>
    </div>

    <!-- Statistiques détaillées -->
    <div class="stats-detail-grid">
        <div class="stat-detail-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <h3>Utilisateurs gérés</h3>
                <div class="stat-value"><?= $stats['total_users'] ?></div>
            </div>
        </div>
        
        <div class="stat-detail-card">
            <div class="stat-icon">
                <i class="fas fa-pen"></i>
            </div>
            <div class="stat-content">
                <h3>Posts surveillés</h3>
                <div class="stat-value"><?= $stats['total_posts'] ?></div>
            </div>
        </div>
        
        <div class="stat-detail-card">
            <div class="stat-icon">
                <i class="fas fa-comment"></i>
            </div>
            <div class="stat-content">
                <h3>Commentaires modérés</h3>
                <div class="stat-value"><?= $stats['total_comments'] ?></div>
            </div>
        </div>
        
        <div class="stat-detail-card">
            <div class="stat-icon">
                <i class="fas fa-flag"></i>
            </div>
            <div class="stat-content">
                <h3>Signalements traités</h3>
                <div class="stat-value"><?= $stats['total_reports'] ?></div>
            </div>
        </div>
        
        <div class="stat-detail-card">
            <div class="stat-icon">
                <i class="fas fa-heart"></i>
            </div>
            <div class="stat-content">
                <h3>Likes totaux</h3>
                <div class="stat-value"><?= $stats['total_likes'] ?></div>
            </div>
        </div>
        
        <div class="stat-detail-card">
            <div class="stat-icon">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="stat-content">
                <h3>Messages échangés</h3>
                <div class="stat-value"><?= $stats['total_messages'] ?></div>
            </div>
        </div>
        
        <div class="stat-detail-card">
            <div class="stat-icon">
                <i class="fas fa-user-friends"></i>
            </div>
            <div class="stat-content">
                <h3>Amitiés formées</h3>
                <div class="stat-value"><?= $stats['total_friendships'] ?></div>
            </div>
        </div>
        
        <div class="stat-detail-card">
            <div class="stat-icon">
                <i class="fas fa-bell"></i>
            </div>
            <div class="stat-content">
                <h3>Notifications envoyées</h3>
                <div class="stat-value"><?= $stats['total_notifications'] ?></div>
            </div>
        </div>
    </div>

    <!-- Informations du compte -->
    <div class="section-detail">
        <h2>Informations du compte</h2>
        <div class="info-grid">
            <div class="info-item">
                <label>Nom d'utilisateur</label>
                <span><?= htmlspecialchars($admin['username']) ?></span>
            </div>
            <div class="info-item">
                <label>Email</label>
                <span><?= htmlspecialchars($admin['email']) ?></span>
            </div>
            <div class="info-item">
                <label>Rôle</label>
                <span class="role-badge <?= $admin['role'] === 'Administrateur' ? 'role-admin' : 'role-moderator' ?>">
                    <?= htmlspecialchars($admin['role']) ?>
                </span>
            </div>
            <div class="info-item">
                <label>Date de création</label>
                <span><?= date('d/m/Y à H:i', strtotime($admin['created_at'])) ?></span>
            </div>
            <div class="info-item">
                <label>Dernière modification</label>
                <span><?= isset($admin['updated_at']) && $admin['updated_at'] ? date('d/m/Y à H:i', strtotime($admin['updated_at'])) : 'Aucune' ?></span>
            </div>
            <div class="info-item">
                <label>ID Administrateur</label>
                <span>#<?= $admin['id'] ?></span>
            </div>
        </div>
    </div>

    <!-- Statistiques globales du système -->
    <div class="section-detail">
        <h2>Statistiques globales du système</h2>
        <div class="stats-overview-grid">
            <div class="stat-overview-card">
                <div class="stat-overview-icon admin">
                    <i class="fas fa-crown"></i>
                </div>
                <div class="stat-overview-content">
                    <h3>Administrateurs</h3>
                    <div class="stat-overview-value"><?= $stats['total_admins'] ?></div>
                </div>
            </div>
            
            <div class="stat-overview-card">
                <div class="stat-overview-icon moderator">
                    <i class="fas fa-user-cog"></i>
                </div>
                <div class="stat-overview-content">
                    <h3>Modérateurs</h3>
                    <div class="stat-overview-value"><?= $stats['total_moderators'] ?></div>
                </div>
            </div>
            
            <div class="stat-overview-card">
                <div class="stat-overview-icon users">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-overview-content">
                    <h3>Utilisateurs</h3>
                    <div class="stat-overview-value"><?= $stats['total_users'] ?></div>
                </div>
            </div>
            
            <div class="stat-overview-card">
                <div class="stat-overview-icon content">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-overview-content">
                    <h3>Posts</h3>
                    <div class="stat-overview-value"><?= $stats['total_posts'] ?></div>
                </div>
            </div>
            
            <div class="stat-overview-card">
                <div class="stat-overview-icon featured">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-overview-content">
                    <h3>Posts en vedette</h3>
                    <div class="stat-overview-value"><?= $stats['total_featured_posts'] ?></div>
                </div>
            </div>
            
            <div class="stat-overview-card">
                <div class="stat-overview-icon reports">
                    <i class="fas fa-flag"></i>
                </div>
                <div class="stat-overview-content">
                    <h3>Signalements</h3>
                    <div class="stat-overview-value"><?= $stats['total_reports'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Permissions selon le rôle -->
    <div class="section-detail">
        <h2>Permissions</h2>
        <div class="permissions-grid">
            <?php if ($admin['role'] === 'Administrateur'): ?>
                <div class="permission-item admin">
                    <i class="fas fa-crown"></i>
                    <div>
                        <h4>Gestion complète</h4>
                        <p>Accès à toutes les fonctionnalités du système</p>
                    </div>
                </div>
                <div class="permission-item admin">
                    <i class="fas fa-user-shield"></i>
                    <div>
                        <h4>Gestion des administrateurs</h4>
                        <p>Ajouter, modifier et supprimer des administrateurs</p>
                    </div>
                </div>
                <div class="permission-item admin">
                    <i class="fas fa-users-cog"></i>
                    <div>
                        <h4>Gestion des utilisateurs</h4>
                        <p>Gestion complète des utilisateurs et de leurs comptes</p>
                    </div>
                </div>
                <div class="permission-item admin">
                    <i class="fas fa-database"></i>
                    <div>
                        <h4>Gestion du contenu</h4>
                        <p>Modération de tous les posts, commentaires et signalements</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="permission-item moderator">
                    <i class="fas fa-user-cog"></i>
                    <div>
                        <h4>Modération limitée</h4>
                        <p>Gestion des utilisateurs et du contenu</p>
                    </div>
                </div>
                <div class="permission-item moderator">
                    <i class="fas fa-users"></i>
                    <div>
                        <h4>Gestion des utilisateurs</h4>
                        <p>Consultation et suppression des utilisateurs</p>
                    </div>
                </div>
                <div class="permission-item moderator">
                    <i class="fas fa-comment-slash"></i>
                    <div>
                        <h4>Modération du contenu</h4>
                        <p>Suppression de posts et commentaires inappropriés</p>
                    </div>
                </div>
                <div class="permission-item moderator">
                    <i class="fas fa-flag"></i>
                    <div>
                        <h4>Traitement des signalements</h4>
                        <p>Examen et traitement des signalements</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div> 