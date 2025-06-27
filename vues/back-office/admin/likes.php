<?php
require_once 'menu.php';

// Récupération des likes avec pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Construction de la requête avec recherche
$whereClause = '';
$params = [];
if (!empty($search)) {
    $whereClause = "WHERE u.username LIKE ? OR p.content LIKE ?";
    $params = ["%$search%", "%$search%"];
}

// Comptage total
$countStmt = $conn->prepare("
    SELECT COUNT(*) FROM likes l 
    LEFT JOIN users u ON l.user_id = u.id 
    LEFT JOIN posts p ON l.post_id = p.id 
    $whereClause
");
$countStmt->execute($params);
$totalLikes = $countStmt->fetchColumn();
$totalPages = ceil($totalLikes / $limit);

// Récupération des likes
$stmt = $conn->prepare("
    SELECT l.*, 
           u.username,
           u.profile_picture,
           p.content as post_content,
           p.id as post_id
    FROM likes l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN posts p ON l.post_id = p.id
    $whereClause
    ORDER BY l.created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$likes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement des actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['like_id'])) {
        $likeId = (int)$_POST['like_id'];
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM likes WHERE id = ?");
                    $stmt->execute([$likeId]);
                    $message = "Like supprimé avec succès";
                    break;
            }
        } catch (PDOException $e) {
            $message = "Erreur lors de l'opération";
        }
    }
}
?>

<div class="main-content">
    <div class="dashboard-container">
        <div class="header">
            <h1>Gestion des Likes</h1>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Barre de recherche -->
        <div class="search-section">
            <form method="GET" class="search-form">
                <div class="search-input-group">
                    <input type="text" name="search" placeholder="Rechercher par utilisateur ou contenu..." 
                           value="<?= htmlspecialchars($search) ?>" class="search-input">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Likes</h3>
                    <div class="stat-value"><?= $totalLikes ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3>Utilisateurs Actifs</h3>
                    <div class="stat-value">
                        <?php
                        $activeUsersStmt = $conn->query("SELECT COUNT(DISTINCT user_id) FROM likes");
                        echo $activeUsersStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-pen-nib"></i>
                </div>
                <div class="stat-content">
                    <h3>Posts Likés</h3>
                    <div class="stat-value">
                        <?php
                        $likedPostsStmt = $conn->query("SELECT COUNT(DISTINCT post_id) FROM likes");
                        echo $likedPostsStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <h3>Moyenne par Post</h3>
                    <div class="stat-value">
                        <?php
                        $avgLikesStmt = $conn->query("SELECT ROUND(AVG(like_count), 1) FROM (SELECT COUNT(*) as like_count FROM likes GROUP BY post_id) as sub");
                        echo $avgLikesStmt->fetchColumn() ?: '0';
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des likes -->
        <div class="likes-table-container">
            <div class="table-header">
                <h2>Liste des Likes</h2>
                <div class="table-actions">
                    <span class="results-count"><?= $totalLikes ?> like(s) trouvé(s)</span>
                </div>
            </div>

            <div class="likes-grid">
                <?php foreach ($likes as $like): ?>
                    <div class="like-card">
                        <div class="like-header">
                            <div class="like-user">
                                <div class="user-avatar">
                                    <img src="<?= !empty($like['profile_picture']) ? '../uploads/' . $like['profile_picture'] : '../uploads/default_profile.jpg' ?>" 
                                         alt="Avatar" onerror="this.src='../uploads/default_profile.jpg'">
                                </div>
                                <div class="user-info">
                                    <h3><?= htmlspecialchars($like['username']) ?></h3>
                                    <span class="like-date">
                                        <i class="fas fa-heart"></i>
                                        Liké le <?= date('d/m/Y H:i', strtotime($like['created_at'])) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="post-context">
                            <h4>Post liké :</h4>
                            <div class="post-preview">
                                <p><?= htmlspecialchars(substr($like['post_content'], 0, 150)) ?>...</p>
                                <a href="posts.php?search=<?= urlencode($like['post_content']) ?>" class="view-post-link">
                                    <i class="fas fa-external-link-alt"></i>
                                    Voir le post
                                </a>
                            </div>
                        </div>
                        
                        <div class="like-actions">
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce like ?')">
                                <input type="hidden" name="like_id" value="<?= $like['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash"></i>
                                    Supprimer
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i> Précédent
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" 
                           class="page-link <?= $i === $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" class="page-link">
                            Suivant <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.search-section {
    margin-bottom: 30px;
}

.search-form {
    max-width: 500px;
}

.search-input-group {
    display: flex;
    gap: 10px;
}

.search-input {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

.search-btn {
    padding: 12px 20px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.search-btn:hover {
    background: #1d4ed8;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(37,99,235,0.07);
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #ef4444, #f87171);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.stat-content h3 {
    margin: 0 0 5px 0;
    font-size: 14px;
    color: #64748b;
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #ef4444;
}

.likes-table-container {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 12px rgba(37,99,235,0.07);
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f1f5f9;
}

.table-header h2 {
    margin: 0;
    color: #1e293b;
    font-size: 1.4rem;
}

.results-count {
    color: #64748b;
    font-size: 14px;
}

.likes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.like-card {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s;
    border-left: 4px solid #ef4444;
}

.like-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.like-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.like-user {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-info h3 {
    margin: 0 0 3px 0;
    color: #1e293b;
    font-size: 1rem;
}

.like-date {
    color: #ef4444;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 4px;
    font-weight: 600;
}

.post-context {
    margin-bottom: 15px;
    padding: 15px;
    background: #fef2f2;
    border-radius: 8px;
}

.post-context h4 {
    margin: 0 0 10px 0;
    color: #dc2626;
    font-size: 14px;
    font-weight: 600;
}

.post-preview p {
    margin: 0 0 10px 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.4;
}

.view-post-link {
    color: #ef4444;
    text-decoration: none;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.view-post-link:hover {
    text-decoration: underline;
}

.like-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.page-link {
    padding: 8px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    text-decoration: none;
    color: #64748b;
    transition: all 0.3s;
}

.page-link:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.page-link.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

@media (max-width: 768px) {
    .likes-grid {
        grid-template-columns: 1fr;
    }
    
    .like-actions {
        flex-direction: column;
    }
    
    .search-input-group {
        flex-direction: column;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<script>
// Auto-hide success messages after 3 seconds
document.addEventListener('DOMContentLoaded', function() {
    const successMessages = document.querySelectorAll('.alert-success');
    successMessages.forEach(function(message) {
        setTimeout(function() {
            message.style.transition = 'opacity 0.5s ease-out';
            message.style.opacity = '0';
            setTimeout(function() {
                message.remove();
            }, 500);
        }, 3000);
    });
});
</script> 