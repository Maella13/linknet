<?php
require_once 'menu.php';

// Récupération des commentaires avec pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Construction de la requête avec recherche
$whereClause = '';
$params = [];
if (!empty($search)) {
    $whereClause = "WHERE c.comment_text LIKE ? OR u.username LIKE ? OR p.content LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

// Comptage total
$countStmt = $conn->prepare("
    SELECT COUNT(*) FROM comments c 
    LEFT JOIN users u ON c.user_id = u.id 
    LEFT JOIN posts p ON c.post_id = p.id 
    $whereClause
");
$countStmt->execute($params);
$totalComments = $countStmt->fetchColumn();
$totalPages = ceil($totalComments / $limit);

// Récupération des commentaires
$stmt = $conn->prepare("
    SELECT c.*, 
           u.username,
           u.profile_picture,
           p.content as post_content,
           p.id as post_id
    FROM comments c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN posts p ON c.post_id = p.id
    $whereClause
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement des actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['comment_id'])) {
        $commentId = (int)$_POST['comment_id'];
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
                    $stmt->execute([$commentId]);
                    $message = "Commentaire supprimé avec succès";
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
            <h1>Gestion des Commentaires</h1>
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
                    <input type="text" name="search" placeholder="Rechercher dans les commentaires..." 
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
                    <i class="fas fa-comments"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Commentaires</h3>
                    <div class="stat-value"><?= $totalComments ?></div>
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
                        $activeUsersStmt = $conn->query("SELECT COUNT(DISTINCT user_id) FROM comments");
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
                    <h3>Posts Commentés</h3>
                    <div class="stat-value">
                        <?php
                        $commentedPostsStmt = $conn->query("SELECT COUNT(DISTINCT post_id) FROM comments");
                        echo $commentedPostsStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des commentaires -->
        <div class="comments-table-container">
            <div class="table-header">
                <h2>Liste des Commentaires</h2>
                <div class="table-actions">
                    <span class="results-count"><?= $totalComments ?> commentaire(s) trouvé(s)</span>
                </div>
            </div>

            <div class="comments-grid">
                <?php foreach ($comments as $comment): ?>
                    <div class="comment-card">
                        <div class="comment-header">
                            <div class="comment-author">
                                <div class="author-avatar">
                                    <img src="<?= !empty($comment['profile_picture']) ? '../uploads/' . $comment['profile_picture'] : '../uploads/default_profile.jpg' ?>" 
                                         alt="Avatar" onerror="this.src='../uploads/default_profile.jpg'">
                                </div>
                                <div class="author-info">
                                    <h3><?= htmlspecialchars($comment['username']) ?></h3>
                                    <span class="comment-date">
                                        <i class="fas fa-calendar"></i>
                                        <?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="comment-content">
                            <p><?= nl2br(htmlspecialchars($comment['comment_text'])) ?></p>
                        </div>
                        
                        <div class="post-context">
                            <h4>Post original :</h4>
                            <div class="post-preview">
                                <p><?= htmlspecialchars(substr($comment['post_content'], 0, 100)) ?>...</p>
                                <a href="posts.php?search=<?= urlencode($comment['post_content']) ?>" class="view-post-link">
                                    <i class="fas fa-external-link-alt"></i>
                                    Voir le post
                                </a>
                            </div>
                        </div>
                        
                        <div class="comment-actions">
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ?')">
                                <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
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
    background: linear-gradient(135deg, var(--primary), var(--secondary));
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
    color: var(--primary);
}

.comments-table-container {
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

.comments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 20px;
}

.comment-card {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s;
}

.comment-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.comment-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.comment-author {
    display: flex;
    align-items: center;
    gap: 12px;
}

.author-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
}

.author-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.author-info h3 {
    margin: 0 0 3px 0;
    color: #1e293b;
    font-size: 1rem;
}

.comment-date {
    color: #64748b;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.comment-content {
    margin-bottom: 15px;
    padding: 15px;
    background: #f8fafc;
    border-radius: 8px;
    border-left: 4px solid var(--primary);
}

.comment-content p {
    margin: 0;
    line-height: 1.6;
    color: #374151;
}

.post-context {
    margin-bottom: 15px;
    padding: 15px;
    background: #f1f5f9;
    border-radius: 8px;
}

.post-context h4 {
    margin: 0 0 10px 0;
    color: #64748b;
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
    color: var(--primary);
    text-decoration: none;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.view-post-link:hover {
    text-decoration: underline;
}

.comment-actions {
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
    .comments-grid {
        grid-template-columns: 1fr;
    }
    
    .comment-actions {
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