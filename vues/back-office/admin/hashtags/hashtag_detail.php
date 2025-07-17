<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../../config/database.php";
echo '<link rel="stylesheet" href="/assets/css/back-office/details/hashtag_detail.css">';

if (!isset($_SESSION["admin"])) {
    http_response_code(403);
    exit('Accès refusé');
}

$role = $_SESSION["admin"]["role"] ?? 'Modérateur';

if (!isset($_GET['id'])) {
    exit('ID hashtag manquant');
}

$hashtagId = (int)$_GET['id'];

// Récupérer les informations détaillées du hashtag
$stmt = $conn->prepare("
    SELECT h.*, 
           COUNT(DISTINCT h2.id) as total_occurrences,
           COUNT(DISTINCT h2.post_id) as total_posts,
           COUNT(DISTINCT p.id) as posts_with_hashtag,
           COUNT(DISTINCT c.id) as total_comments,
           COUNT(DISTINCT l.id) as total_likes,
           COUNT(DISTINCT u.id) as unique_users
    FROM hashtags h
    LEFT JOIN hashtags h2 ON h.tag = h2.tag
    LEFT JOIN posts p ON h2.post_id = p.id
    LEFT JOIN comments c ON p.id = c.post_id
    LEFT JOIN likes l ON p.id = l.post_id
    LEFT JOIN users u ON p.user_id = u.id
    WHERE h.id = ?
    GROUP BY h.id, h.tag
");

$stmt->execute([$hashtagId]);
$hashtag = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hashtag) {
    exit('Hashtag non trouvé');
}

// Récupérer tous les posts utilisant ce hashtag
$postsStmt = $conn->prepare("
    SELECT p.*, u.username, u.profile_picture,
           COUNT(DISTINCT c.id) as comments_count,
           COUNT(DISTINCT l.id) as likes_count,
           h.created_at as hashtag_created_at
    FROM posts p
    INNER JOIN hashtags h ON p.id = h.post_id
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN comments c ON p.id = c.post_id
    LEFT JOIN likes l ON p.id = l.post_id
    WHERE h.tag = ?
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 10
");
$postsStmt->execute([$hashtag['tag']]);
$postsWithHashtag = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les utilisateurs les plus actifs avec ce hashtag
$usersStmt = $conn->prepare("
    SELECT u.id, u.username, u.profile_picture,
           COUNT(DISTINCT h.id) as hashtag_usage,
           COUNT(DISTINCT p.id) as total_posts
    FROM users u
    INNER JOIN posts p ON u.id = p.user_id
    INNER JOIN hashtags h ON p.id = h.post_id
    WHERE h.tag = ?
    GROUP BY u.id
    ORDER BY hashtag_usage DESC
    LIMIT 8
");
$usersStmt->execute([$hashtag['tag']]);
$activeUsers = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les hashtags similaires (utilisés dans les mêmes posts)
$similarStmt = $conn->prepare("
    SELECT h2.tag, COUNT(*) as co_occurrences
    FROM hashtags h1
    INNER JOIN hashtags h2 ON h1.post_id = h2.post_id
    WHERE h1.tag = ? AND h2.tag != ?
    GROUP BY h2.tag
    ORDER BY co_occurrences DESC
    LIMIT 8
");
$similarStmt->execute([$hashtag['tag'], $hashtag['tag']]);
$similarHashtags = $similarStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="hashtag-detail-container">
    <!-- En-tête du hashtag -->
    <div class="hashtag-header">
        <div class="hashtag-icon-large">
            <i class="fas fa-hashtag"></i>
        </div>
        <div class="hashtag-info">
            <h1>#<?= htmlspecialchars($hashtag['tag']) ?></h1>
            <div class="hashtag-meta">
                <div class="meta-item">
                    <i class="fas fa-calendar"></i>
                    Créé le <?= date('d/m/Y H:i', strtotime($hashtag['created_at'])) ?>
                </div>
                <div class="meta-item">
                    <i class="fas fa-pen-nib"></i>
                    <?= $hashtag['total_occurrences'] ?> occurrence(s)
                </div>
                <div class="meta-item">
                    <i class="fas fa-users"></i>
                    <?= $hashtag['unique_users'] ?> utilisateur(s)
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-hashtag"></i>
            </div>
            <div class="stat-content">
                <h3>Total Occurrences</h3>
                <div class="stat-value"><?= $hashtag['total_occurrences'] ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-pen-nib"></i>
            </div>
            <div class="stat-content">
                <h3>Posts Utilisés</h3>
                <div class="stat-value"><?= $hashtag['total_posts'] ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-comment"></i>
            </div>
            <div class="stat-content">
                <h3>Commentaires</h3>
                <div class="stat-value"><?= $hashtag['total_comments'] ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-heart"></i>
            </div>
            <div class="stat-content">
                <h3>Likes</h3>
                <div class="stat-value"><?= $hashtag['total_likes'] ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <h3>Utilisateurs Uniques</h3>
                <div class="stat-value"><?= $hashtag['unique_users'] ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <h3>Popularité</h3>
                <div class="stat-value">
                    <?php
                    $popularity = $hashtag['total_occurrences'] > 10 ? 'Élevée' : 
                                ($hashtag['total_occurrences'] > 5 ? 'Moyenne' : 'Faible');
                    echo $popularity;
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenu principal -->
    <div class="content-sections">
        <!-- Section principale -->
        <div class="main-section">
            <h2 class="section-title">Posts utilisant ce hashtag</h2>
            
            <?php if (empty($postsWithHashtag)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>Aucun post trouvé avec ce hashtag</p>
                </div>
            <?php else: ?>
                <div class="posts-list">
                    <?php foreach ($postsWithHashtag as $post): ?>
                        <div class="post-item">
                            <div class="post-header">
                                <div class="user-avatar">
                                    <img src="<?= !empty($post['profile_picture']) ? '../../uploads/' . $post['profile_picture'] : '../../uploads/default_profile.jpg' ?>" 
                                         alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
                                </div>
                                <div class="post-meta">
                                    <p class="post-author"><?= htmlspecialchars($post['username']) ?></p>
                                    <p class="post-date">
                                        <?= date('d/m/Y H:i', strtotime($post['created_at'])) ?>
                                        <span style="margin-left: 10px; color: var(--primary);">
                                            <i class="fas fa-hashtag"></i> Ajouté le <?= date('d/m/Y', strtotime($post['hashtag_created_at'])) ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="post-content">
                                <?= htmlspecialchars(substr($post['content'], 0, 150)) ?>
                                <?= strlen($post['content']) > 150 ? '...' : '' ?>
                            </div>
                            
                            <div class="post-stats">
                                <div class="post-stat">
                                    <i class="fas fa-comment"></i>
                                    <?= $post['comments_count'] ?> commentaires
                                </div>
                                <div class="post-stat">
                                    <i class="fas fa-heart"></i>
                                    <?= $post['likes_count'] ?> likes
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="sidebar-section">
            <!-- Utilisateurs actifs -->
            <h3 class="section-title">Utilisateurs actifs</h3>
            
            <?php if (empty($activeUsers)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <p>Aucun utilisateur actif</p>
                </div>
            <?php else: ?>
                <div class="users-list">
                    <?php foreach ($activeUsers as $user): ?>
                        <div class="user-item">
                            <div class="user-avatar-small">
                                <img src="<?= !empty($user['profile_picture']) ? '../../uploads/' . $user['profile_picture'] : '../../uploads/default_profile.jpg' ?>" 
                                     alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
                            </div>
                            <div class="user-info">
                                <p class="user-name"><?= htmlspecialchars($user['username']) ?></p>
                                <p class="user-stats">
                                    <?= $user['hashtag_usage'] ?> utilisation(s) • <?= $user['total_posts'] ?> posts
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Hashtags similaires -->
            <h3 class="section-title" style="margin-top: 30px;">Hashtags similaires</h3>
            
            <?php if (empty($similarHashtags)): ?>
                <div class="empty-state">
                    <i class="fas fa-tags"></i>
                    <p>Aucun hashtag similaire</p>
                </div>
            <?php else: ?>
                <div class="similar-hashtags">
                    <?php foreach ($similarHashtags as $similar): ?>
                        <span class="similar-tag">
                            #<?= htmlspecialchars($similar['tag']) ?> (<?= $similar['co_occurrences'] ?>)
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div> 