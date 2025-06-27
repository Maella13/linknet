<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../config/database.php";

if (!isset($_SESSION["admin"])) {
    http_response_code(403);
    exit('Accès refusé');
}

if (!isset($_GET['id'])) {
    exit('ID post manquant');
}

$postId = (int)$_GET['id'];

// Récupérer les informations détaillées du post
$stmt = $conn->prepare("
    SELECT p.*, 
           u.username,
           u.email,
           u.profile_picture,
           u.bio,
           u.created_at as user_created_at,
           COUNT(DISTINCT c.id) as comments_count,
           COUNT(DISTINCT l.id) as likes_count,
           COUNT(DISTINCT fp.id) as is_featured,
           GROUP_CONCAT(DISTINCT h.tag) as hashtags
    FROM posts p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN comments c ON p.id = c.post_id
    LEFT JOIN likes l ON p.id = l.post_id
    LEFT JOIN featured_posts fp ON p.id = fp.post_id
    LEFT JOIN hashtags h ON p.id = h.post_id
    WHERE p.id = ?
    GROUP BY p.id
");
$stmt->execute([$postId]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    exit('Post non trouvé');
}

// Récupérer les commentaires du post
$commentsStmt = $conn->prepare("
    SELECT c.*, u.username, u.profile_picture
    FROM comments c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.post_id = ?
    ORDER BY c.created_at DESC
    LIMIT 10
");
$commentsStmt->execute([$postId]);
$comments = $commentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les likes du post
$likesStmt = $conn->prepare("
    SELECT l.*, u.username, u.profile_picture
    FROM likes l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE l.post_id = ?
    ORDER BY l.created_at DESC
    LIMIT 10
");
$likesStmt->execute([$postId]);
$likes = $likesStmt->fetchAll(PDO::FETCH_ASSOC);

// Convertir les hashtags en tableau
$hashtags = $post['hashtags'] ? explode(',', $post['hashtags']) : [];
?>

<div class="user-detail-container">
    <!-- En-tête du post -->
    <div class="user-header-detail">
        <div class="user-avatar-large">
            <img src="<?= !empty($post['profile_picture']) ? '../uploads/' . $post['profile_picture'] : '../uploads/default_profile.jpg' ?>" 
                 alt="Avatar" onerror="this.src='../uploads/default_profile.jpg'">
        </div>
        <div class="user-info-detail">
            <h1><?= htmlspecialchars($post['username']) ?></h1>
            <p class="user-email">Auteur : <?= htmlspecialchars($post['email']) ?></p>
            <?php if ($post['is_featured']): ?>
                <span class="role-badge-large role-admin"><i class="fas fa-star"></i> Post en vedette</span>
            <?php endif; ?>
            <p class="user-join-date">
                <i class="fas fa-calendar"></i>
                Posté le <?= date('d/m/Y à H:i', strtotime($post['created_at'])) ?>
            </p>
        </div>
    </div>

    <!-- Statistiques détaillées du post -->
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
        <div class="stat-detail-card">
            <div class="stat-icon">
                <i class="fas fa-eye"></i>
            </div>
            <div class="stat-content">
                <h3>Vues</h3>
                <div class="stat-value">-</div>
            </div>
        </div>
    </div>

    <!-- Section média du post -->
    <?php if (!empty($post['media'])): ?>
    <div class="section-detail">
        <h2>Média</h2>
        <div style="margin-bottom: 18px;">
            <?php
            $mediaPath = $post['media'];
            $extension = strtolower(pathinfo($mediaPath, PATHINFO_EXTENSION));
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                <img src="<?= $mediaPath ?>" alt="Media" class="media-content" style="max-width:100%;max-height:300px;object-fit:cover;border-radius:12px;">
            <?php elseif (in_array($extension, ['mp4', 'avi', 'mov', 'wmv'])): ?>
                <video controls class="media-content" style="max-width:100%;max-height:300px;border-radius:12px;">
                    <source src="<?= $mediaPath ?>" type="video/<?= $extension ?>">
                    Votre navigateur ne supporte pas la lecture de vidéos.
                </video>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Section contenu du post -->
    <div class="section-detail">
        <h2>Contenu du post</h2>
        <div class="post-content" style="margin-bottom: 18px;">
            <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
        </div>
        <?php if (!empty($hashtags)): ?>
            <div class="hashtags-section" style="margin-bottom: 18px;">
                <h4><i class="fas fa-hashtag"></i> Hashtags</h4>
                <div class="hashtags-list">
                    <?php foreach ($hashtags as $tag): ?>
                        <span class="hashtag">#<?= htmlspecialchars($tag) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Informations sur l'auteur -->
    <div class="section-detail">
        <h2>Informations sur l'auteur</h2>
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

    <!-- Commentaires récents -->
    <?php if (!empty($comments)): ?>
    <div class="section-detail">
        <h2><i class="fas fa-comments"></i> Commentaires récents (<?= count($comments) ?>)</h2>
        <div class="comments-list">
            <?php foreach ($comments as $comment): ?>
                <div class="comment-item">
                    <div class="comment-author">
                        <img src="<?= !empty($comment['profile_picture']) ? '../uploads/' . $comment['profile_picture'] : '../uploads/default_profile.jpg' ?>" 
                             alt="Avatar" class="comment-avatar">
                        <div class="comment-info">
                            <strong><?= htmlspecialchars($comment['username']) ?></strong>
                            <span class="comment-date"><i class="fas fa-calendar"></i> <?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?></span>
                        </div>
                    </div>
                    <div class="comment-text">
                        <?= nl2br(htmlspecialchars($comment['comment_text'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
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

.user-avatar-large img {
    width: 100%;
    height: 100%;
    object-fit: cover;
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

.hashtags-list {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.hashtag {
    background: #e0e7ff;
    color: #3730a3;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.95rem;
    font-weight: 500;
}

.comments-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.comment-item {
    background: white;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.comment-author {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.comment-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
}

.comment-info {
    display: flex;
    flex-direction: column;
}

.comment-info strong {
    color: #1e293b;
    font-size: 0.9rem;
}

.comment-date {
    color: #64748b;
    font-size: 0.8rem;
}

.comment-text {
    color: #374151;
    line-height: 1.5;
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
    
    .comments-list {
        gap: 10px;
    }
}
</style> 