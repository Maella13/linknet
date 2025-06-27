<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../config/database.php";

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

<div class="user-detail-container">
    <!-- En-tête administrateur -->
    <div class="user-header-detail">
        <div class="user-avatar-large">
            <i class="fas <?= $admin['role'] === 'Administrateur' ? 'fa-crown' : 'fa-user-cog' ?>" style="font-size: 4rem; color: <?= $admin['role'] === 'Administrateur' ? '#fbbf24' : '#6366f1' ?>;"></i>
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

<style>
.user-detail-container {
    padding: 20px;
    max-height: 70vh;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 #f1f5f9;
}

.user-detail-container::-webkit-scrollbar {
    width: 8px;
}

.user-detail-container::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}

.user-detail-container::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.user-detail-container::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.user-header-detail {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #f1f5f9;
}

.user-avatar-large {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
}

.user-info-detail h1 {
    margin: 0 0 10px 0;
    color: #1e293b;
    font-size: 1.8rem;
}

.user-email {
    color: #64748b;
    margin: 0 0 10px 0;
    font-size: 1rem;
}

.role-badge-large {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 10px;
}

.role-admin {
    background: #fef3c7;
    color: #92400e;
}

.role-moderator {
    background: #e0e7ff;
    color: #3730a3;
}

.user-join-date {
    color: #64748b;
    margin: 0;
    font-size: 0.9rem;
}

.stats-detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.stat-detail-card {
    background: white;
    border-radius: 10px;
    padding: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 12px;
}

.stat-detail-card .stat-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
}

.stat-detail-card .stat-content h3 {
    margin: 0 0 5px 0;
    font-size: 12px;
    color: #64748b;
}

.stat-detail-card .stat-value {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary);
}

.section-detail {
    margin-bottom: 30px;
}

.section-detail h2 {
    color: #1e293b;
    font-size: 1.3rem;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e5e7eb;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.info-item {
    background: white;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.info-item label {
    display: block;
    font-size: 0.8rem;
    color: #64748b;
    margin-bottom: 5px;
    text-transform: uppercase;
    font-weight: 600;
}

.info-item span {
    color: #1e293b;
    font-weight: 500;
}

.stats-overview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.stat-overview-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-overview-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-overview-icon.admin {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
}

.stat-overview-icon.moderator {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
}

.stat-overview-icon.users {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
}

.stat-overview-icon.content {
    background: linear-gradient(135deg, #10b981, #059669);
}

.stat-overview-icon.featured {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.stat-overview-icon.reports {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

.stat-overview-content h3 {
    margin: 0 0 5px 0;
    font-size: 14px;
    color: #64748b;
}

.stat-overview-value {
    font-size: 24px;
    font-weight: 700;
    color: #1e293b;
}

.permissions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
}

.permission-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 20px;
    border-radius: 10px;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.permission-item.admin {
    border-left: 4px solid #fbbf24;
}

.permission-item.moderator {
    border-left: 4px solid #6366f1;
}

.permission-item i {
    font-size: 1.5rem;
    margin-top: 2px;
}

.permission-item.admin i {
    color: #fbbf24;
}

.permission-item.moderator i {
    color: #6366f1;
}

.permission-item h4 {
    margin: 0 0 5px 0;
    color: #1e293b;
    font-size: 1rem;
}

.permission-item p {
    margin: 0;
    color: #64748b;
    font-size: 0.9rem;
    line-height: 1.4;
}

@media (max-width: 768px) {
    .user-header-detail {
        flex-direction: column;
        text-align: center;
    }
    
    .stats-detail-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-overview-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
    
    .permissions-grid {
        grid-template-columns: 1fr;
    }
}
</style> 