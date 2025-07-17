<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../../config/database.php";

if (!isset($_SESSION["admin"])) {
    http_response_code(403);
    exit('Accès refusé');
}

if (!isset($_GET['id'])) {
    exit('ID du post en vedette manquant');
}

$postId = (int)$_GET['id'];

// Récupérer les infos du post, de l'auteur, stats, hashtags
$stmt = $conn->prepare('
    SELECT p.*, u.username, u.email, u.profile_picture, u.bio, u.created_at as user_created_at,
           (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count,
           (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
           GROUP_CONCAT(DISTINCT h.tag) as hashtags
    FROM posts p
    INNER JOIN users u ON p.user_id = u.id
    LEFT JOIN hashtags h ON p.id = h.post_id
    WHERE p.id = ?
    GROUP BY p.id
');
$stmt->execute([$postId]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    exit('Post en vedette non trouvé');
}

// Récupérer les derniers commentaires
$commentsStmt = $conn->prepare('
    SELECT c.*, u.username, u.profile_picture
    FROM comments c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.post_id = ?
    ORDER BY c.created_at DESC
    LIMIT 5
');
$commentsStmt->execute([$postId]);
$comments = $commentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les derniers likes
$likesStmt = $conn->prepare('
    SELECT l.*, u.username, u.profile_picture
    FROM likes l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE l.post_id = ?
    ORDER BY l.created_at DESC
    LIMIT 5
');
$likesStmt->execute([$postId]);
$likes = $likesStmt->fetchAll(PDO::FETCH_ASSOC);

$hashtags = $post['hashtags'] ? explode(',', $post['hashtags']) : [];
?>
<link rel="stylesheet" href="/assets/css/back-office/details/featured_post_detail.css">

<div class="user-detail-container">
    <!-- En-tête du post en vedette -->
    <div class="user-header-detail">
        <div class="user-avatar-large">
            <?php if (!empty($post['profile_picture'])): ?>
                <img src="<?= '../../uploads/' . $post['profile_picture'] ?>" alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
            <?php else: ?>
                <img src="../../uploads/default_profile.jpg" alt="Avatar">
            <?php endif; ?>
        </div>
        <div class="user-info-detail">
            <h1><?= htmlspecialchars($post['username']) ?></h1>
            <p class="user-email">Auteur : <?= htmlspecialchars($post['email']) ?></p>
            <span class="role-badge-large role-admin"><i class="fas fa-star"></i> Post en vedette</span>
            <p class="user-join-date">
                <i class="fas fa-calendar"></i>
                Posté le <?= date('d/m/Y à H:i', strtotime($post['created_at'])) ?>
            </p>
        </div>
    </div>

    <!-- Statistiques détaillées -->
    <div class="stats-detail-grid">
        <div class="stat-detail-card">
            <div class="stat-icon">
                <i class="fas fa-comment"></i>
            </div>
            <div class="stat-content">
                <h3>Commentaires</h3>
                <div class="stat-value"><?= $post['comments_count'] ?></div>
            </div>
        </div>
        <div class="stat-detail-card">
            <div class="stat-icon">
                <i class="fas fa-heart"></i>
            </div>
            <div class="stat-content">
                <h3>Likes</h3>
                <div class="stat-value"><?= $post['likes_count'] ?></div>
            </div>
        </div>
    </div>

    <!-- Informations du post -->
    <div class="section-detail">
        <h2>Informations du post</h2>
        <div class="info-grid">
            <div class="info-item">
                <label>Contenu</label>
                <span><?= nl2br(htmlspecialchars($post['content'])) ?></span>
            </div>
            <div class="info-item">
                <label>Média</label>
                <?php if (!empty($post['media'])): ?>
                    <?php
                    $mediaPath = $post['media'];
                    $extension = strtolower(pathinfo($mediaPath, PATHINFO_EXTENSION));
                    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                        <img src="<?= $mediaPath ?>" alt="Media" style="max-width:100%;max-height:120px;border-radius:8px;">
                    <?php elseif (in_array($extension, ['mp4', 'avi', 'mov', 'wmv'])): ?>
                        <video controls style="max-width:100%;max-height:120px;border-radius:8px;">
                            <source src="<?= $mediaPath ?>" type="video/<?= $extension ?>">
                            Votre navigateur ne supporte pas la lecture de vidéos.
                        </video>
                    <?php else: ?>
                        <span>Aucun média</span>
                    <?php endif; ?>
                <?php else: ?>
                    <span>Aucun média</span>
                <?php endif; ?>
            </div>
            <div class="info-item">
                <label>Hashtags</label>
                <span>
                    <?php if (!empty($hashtags)): ?>
                        <?php foreach ($hashtags as $tag): ?>
                            <span class="hashtag">#<?= htmlspecialchars($tag) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        Aucun
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <label>Date de création</label>
                <span><?= date('d/m/Y à H:i', strtotime($post['created_at'])) ?></span>
            </div>
            <div class="info-item">
                <label>ID du post</label>
                <span>#<?= $post['id'] ?></span>
            </div>
        </div>
    </div>

    <!-- Informations sur l'auteur -->
    <div class="section-detail">
        <h2>Auteur du post</h2>
        <div class="info-grid">
            <div class="info-item">
                <label>Nom d'utilisateur</label>
                <span><?= htmlspecialchars($post['username']) ?></span>
            </div>
            <div class="info-item">
                <label>Email</label>
                <span><?= htmlspecialchars($post['email']) ?></span>
            </div>
            <div class="info-item">
                <label>Bio</label>
                <span><?= !empty($post['bio']) ? htmlspecialchars($post['bio']) : 'Aucune bio' ?></span>
            </div>
            <div class="info-item">
                <label>Membre depuis</label>
                <span><?= date('d/m/Y', strtotime($post['user_created_at'])) ?></span>
            </div>
        </div>
    </div>

    <!-- Derniers commentaires -->
    <?php if (!empty($comments)): ?>
    <div class="section-detail">
        <h2>Commentaires récents (<?= count($comments) ?>)</h2>
        <div class="info-grid">
            <?php foreach ($comments as $comment): ?>
                <div class="info-item">
                    <label><?= htmlspecialchars($comment['username']) ?> <span style="color:#64748b;font-size:0.9em;">(<?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?>)</span></label>
                    <span><?= nl2br(htmlspecialchars($comment['comment_text'])) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Derniers likes -->
    <?php if (!empty($likes)): ?>
    <div class="section-detail">
        <h2>Likes récents (<?= count($likes) ?>)</h2>
        <div class="info-grid">
            <?php foreach ($likes as $like): ?>
                <div class="info-item">
                    <label><?= htmlspecialchars($like['username']) ?> <span style="color:#64748b;font-size:0.9em;">(<?= date('d/m/Y H:i', strtotime($like['created_at'])) ?>)</span></label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div> 