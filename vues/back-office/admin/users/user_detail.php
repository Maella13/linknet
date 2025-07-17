<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../../config/database.php";

if (!isset($_SESSION["admin"])) {
    http_response_code(403);
    exit('Accès refusé');
}

$role = $_SESSION["admin"]["role"] ?? 'Modérateur';

if (!isset($_GET['id'])) {
    exit('ID utilisateur manquant');
}

$userId = (int)$_GET['id'];

// Récupérer les informations détaillées de l'utilisateur
$stmt = $conn->prepare("
    SELECT u.*, 
           COUNT(DISTINCT p.id) as posts_count,
           COUNT(DISTINCT c.id) as comments_count,
           COUNT(DISTINCT l.id) as likes_count,
           COUNT(DISTINCT f.id) as friends_count,
           COUNT(DISTINCT fl.id) as followers_count,
           COUNT(DISTINCT fp.id) as featured_posts_count,
           COUNT(DISTINCT h.id) as hashtags_count,
           COUNT(DISTINCT n.id) as notifications_count,
           COUNT(DISTINCT m.id) as messages_count
    FROM users u
    LEFT JOIN posts p ON u.id = p.user_id
    LEFT JOIN comments c ON u.id = c.user_id
    LEFT JOIN likes l ON u.id = l.user_id
    LEFT JOIN friends f ON (u.id = f.sender_id OR u.id = f.receiver_id) AND f.status = 'accepted'
    LEFT JOIN followers fl ON u.id = fl.user_id
    LEFT JOIN featured_posts fp ON p.id = fp.post_id
    LEFT JOIN hashtags h ON p.id = h.post_id
    LEFT JOIN notifications n ON u.id = n.user_id
    LEFT JOIN messages m ON u.id = m.sender_id
    WHERE u.id = ?
    GROUP BY u.id
");

$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    exit('Utilisateur non trouvé');
}

// Récupérer les derniers posts
$postsStmt = $conn->prepare("
    SELECT p.*, COUNT(c.id) as comments_count, COUNT(l.id) as likes_count
    FROM posts p
    LEFT JOIN comments c ON p.id = c.post_id
    LEFT JOIN likes l ON p.id = l.post_id
    WHERE p.user_id = ?
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 5
");
$postsStmt->execute([$userId]);
$recentPosts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les amis
$friendsStmt = $conn->prepare("
    SELECT u.id, u.username, u.profile_picture
    FROM users u
    INNER JOIN friends f ON (u.id = f.sender_id OR u.id = f.receiver_id)
    WHERE (f.sender_id = ? OR f.receiver_id = ?) 
    AND f.status = 'accepted'
    AND u.id != ?
    LIMIT 10
");
$friendsStmt->execute([$userId, $userId, $userId]);
$friends = $friendsStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les abonnés
$followersStmt = $conn->prepare("
    SELECT u.id, u.username, u.profile_picture
    FROM users u
    INNER JOIN followers f ON u.id = f.follower_id
    WHERE f.user_id = ?
    LIMIT 10
");
$followersStmt->execute([$userId]);
$followers = $followersStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<link rel="stylesheet" href="/assets/css/back-office/details/user_detail.css">

<div class="user-detail-container">
    <div class="modal-header" style="display:flex;align-items:center;justify-content:space-between;padding-bottom:18px;">
        <h2 style="margin:0;font-size:1.5rem;font-weight:700;">Détails de l'utilisateur</h2>
        <span class="close" onclick="closeUserDetailModal()" style="font-size:32px;cursor:pointer;">&times;</span>
    </div>
    <!-- En-tête utilisateur -->
    <div class="user-header-detail">
        <div class="user-avatar-large">
            <img src="<?= !empty($user['profile_picture']) ? '../../uploads/' . $user['profile_picture'] : '../../uploads/default_profile.jpg' ?>" 
                 alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
        </div>
        <div class="user-info-detail">
            <h1><?= htmlspecialchars($user['username']) ?></h1>
            <p class="user-email"><?= htmlspecialchars($user['email']) ?></p>
            <?php if (!empty($user['bio'])): ?>
                <p class="user-bio"><?= htmlspecialchars($user['bio']) ?></p>
            <?php endif; ?>
            <p class="user-join-date">
                <i class="fas fa-calendar"></i>
                Membre depuis le <?= date('d/m/Y', strtotime($user['created_at'])) ?>
            </p>
        </div>
    </div>

    <!-- Statistiques détaillées -->
    <div class="stats-detail-grid">
        <div class="stat-detail-card">
            <div class="stat-icon">
                <i class="fas fa-pen"></i>
            </div>
            <div class="stat-content">
                <h3>Posts</h3>
                <div class="stat-value"><?= $user['posts_count'] ?></div>
            </div>
        </div>
        
        <div class="stat-detail-card">
            <div class="stat-icon">
                <i class="fas fa-comment"></i>
            </div>
            <div class="stat-content">
                <h3>Commentaires</h3>
                <div class="stat-value"><?= $user['comments_count'] ?></div>
            </div>
        </div>
        
        <div class="stat-detail-card">
            <div class="stat-icon">
                <i class="fas fa-heart"></i>
            </div>
            <div class="stat-content">
                <h3>Likes donnés</h3>
                <div class="stat-value"><?= $user['likes_count'] ?></div>
            </div>
        </div>
        
        <div class="stat-detail-card">
            <div class="stat-icon">
                <i class="fas fa-user-friends"></i>
            </div>
            <div class="stat-content">
                <h3>Amis</h3>
                <div class="stat-value"><?= $user['friends_count'] ?></div>
            </div>
        </div>
        
        <div class="stat-detail-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <h3>Abonnés</h3>
                <div class="stat-value"><?= $user['followers_count'] ?></div>
            </div>
        </div>
        
        <div class="stat-detail-card">
            <div class="stat-icon">
                <i class="fas fa-star"></i>
            </div>
            <div class="stat-content">
                <h3>Posts en vedette</h3>
                <div class="stat-value"><?= $user['featured_posts_count'] ?></div>
            </div>
        </div>
        
        <div class="stat-detail-card">
            <div class="stat-icon">
                <i class="fas fa-hashtag"></i>
            </div>
            <div class="stat-content">
                <h3>Hashtags utilisés</h3>
                <div class="stat-value"><?= $user['hashtags_count'] ?></div>
            </div>
        </div>
        
        <div class="stat-detail-card">
            <div class="stat-icon">
                <i class="fas fa-bell"></i>
            </div>
            <div class="stat-content">
                <h3>Notifications</h3>
                <div class="stat-value"><?= $user['notifications_count'] ?></div>
            </div>
        </div>
        
        <div class="stat-detail-card">
            <div class="stat-icon">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="stat-content">
                <h3>Messages envoyés</h3>
                <div class="stat-value"><?= $user['messages_count'] ?></div>
            </div>
        </div>
    </div>

    <!-- Derniers posts -->
    <?php if (!empty($recentPosts)): ?>
    <div class="section-detail">
        <h2>Derniers Posts</h2>
        <div class="posts-grid">
            <?php foreach ($recentPosts as $post): ?>
                <div class="post-card">
                    <div class="post-content">
                        <p><?= htmlspecialchars(substr($post['content'], 0, 100)) ?><?= strlen($post['content']) > 100 ? '...' : '' ?></p>
                    </div>
                    <?php if (!empty($post['media'])): ?>
                        <div class="post-media">
                            <img src="<?= $post['media'] ?>" alt="Media" onerror="this.style.display='none'">
                        </div>
                    <?php endif; ?>
                    <div class="post-meta">
                        <span><i class="fas fa-comment"></i> <?= $post['comments_count'] ?></span>
                        <span><i class="fas fa-heart"></i> <?= $post['likes_count'] ?></span>
                        <span class="post-date"><?= date('d/m/Y', strtotime($post['created_at'])) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Amis -->
    <?php if (!empty($friends)): ?>
    <div class="section-detail">
        <h2>Amis (<?= count($friends) ?>)</h2>
        <div class="users-list">
            <?php foreach ($friends as $friend): ?>
                <div class="user-item">
                    <img src="<?= !empty($friend['profile_picture']) ? '../../uploads/' . $friend['profile_picture'] : '../../uploads/default_profile.jpg' ?>" 
                         alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
                    <span><?= htmlspecialchars($friend['username']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Abonnés -->
    <?php if (!empty($followers)): ?>
    <div class="section-detail">
        <h2>Abonnés (<?= count($followers) ?>)</h2>
        <div class="users-list">
            <?php foreach ($followers as $follower): ?>
                <div class="user-item">
                    <img src="<?= !empty($follower['profile_picture']) ? '../../uploads/' . $follower['profile_picture'] : '../../uploads/default_profile.jpg' ?>" 
                         alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
                    <span><?= htmlspecialchars($follower['username']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Fonction pour vérifier si tout le contenu est visible et ajuster la hauteur
function adjustContainerHeight() {
    const container = document.querySelector('.user-detail-container');
    const lastSection = container.querySelector('.section-detail:last-child');
    
    if (!container || !lastSection) return;
    
    // Obtenir les positions
    const containerRect = container.getBoundingClientRect();
    const lastSectionRect = lastSection.getBoundingClientRect();
    
    // Calculer si la dernière section est complètement visible avec un espace supplémentaire
    const requiredSpace = 100; // Espace supplémentaire requis en pixels
    const isLastSectionFullyVisible = (lastSectionRect.bottom + requiredSpace) <= containerRect.bottom;
    
    // Si la dernière section n'est pas complètement visible ou s'il n'y a pas assez d'espace
    if (!isLastSectionFullyVisible) {
        const currentHeight = container.style.maxHeight || '95vh';
        const currentValue = parseInt(currentHeight);
        
        // Augmenter la hauteur de 10vh à chaque fois
        const newHeight = Math.min(currentValue + 10, 150); // Maximum 150vh
        container.style.maxHeight = newHeight + 'vh';
        
        // Ajuster aussi le padding-bottom
        const currentPadding = parseInt(container.style.paddingBottom) || 800;
        container.style.paddingBottom = (currentPadding + 100) + 'px';
        
        // Vérifier à nouveau après l'ajustement
        setTimeout(adjustContainerHeight, 100);
    } else {
        // Même si tout est visible, s'assurer qu'il y a au moins 150px d'espace en bas
        const currentPadding = parseInt(container.style.paddingBottom) || 800;
        const minRequiredPadding = 150;
        
        if (currentPadding < minRequiredPadding) {
            container.style.paddingBottom = minRequiredPadding + 'px';
        }
    }
}

// Fonction pour vérifier la visibilité lors du scroll
function checkVisibilityOnScroll() {
    const container = document.querySelector('.user-detail-container');
    const lastSection = container.querySelector('.section-detail:last-child');
    
    if (!container || !lastSection) return;
    
    const containerRect = container.getBoundingClientRect();
    const lastSectionRect = lastSection.getBoundingClientRect();
    
    // Si on est proche du bas et que la dernière section n'est pas complètement visible avec espace
    const requiredSpace = 100;
    if (container.scrollTop + container.clientHeight >= container.scrollHeight - 50) {
        if ((lastSectionRect.bottom + requiredSpace) > containerRect.bottom) {
            adjustContainerHeight();
        }
    }
}

// Initialiser quand le DOM est chargé
document.addEventListener('DOMContentLoaded', function() {
    // Ajuster la hauteur initiale
    setTimeout(adjustContainerHeight, 500);
    
    // Ajouter un listener pour le scroll
    const container = document.querySelector('.user-detail-container');
    if (container) {
        container.addEventListener('scroll', checkVisibilityOnScroll);
    }
    
    // Vérifier aussi lors du redimensionnement de la fenêtre
    window.addEventListener('resize', function() {
        setTimeout(adjustContainerHeight, 100);
    });
});

// Fonction pour forcer l'ajustement (peut être appelée manuellement)
function forceAdjustHeight() {
    adjustContainerHeight();
}
</script>
