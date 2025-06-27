<?php
require_once 'menu.php';

// Récupération des abonnements avec pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Construction de la requête avec recherche
$whereClause = '';
$params = [];
if (!empty($search)) {
    $whereClause = "WHERE u1.username LIKE ? OR u2.username LIKE ?";
    $params = ["%$search%", "%$search%"];
}

// Comptage total
$countStmt = $conn->prepare("
    SELECT COUNT(*) FROM followers f 
    LEFT JOIN users u1 ON f.user_id = u1.id 
    LEFT JOIN users u2 ON f.follower_id = u2.id 
    $whereClause
");
$countStmt->execute($params);
$totalFollowers = $countStmt->fetchColumn();
$totalPages = ceil($totalFollowers / $limit);

// Récupération des abonnements
$stmt = $conn->prepare("
    SELECT f.*, 
           u1.username as user_name,
           u1.profile_picture as user_picture,
           u2.username as follower_name,
           u2.profile_picture as follower_picture
    FROM followers f
    LEFT JOIN users u1 ON f.user_id = u1.id
    LEFT JOIN users u2 ON f.follower_id = u2.id
    $whereClause
    ORDER BY f.created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$followers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement des actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['follower_id'])) {
        $followerId = (int)$_POST['follower_id'];
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM followers WHERE id = ?");
                    $stmt->execute([$followerId]);
                    $message = "Abonnement supprimé avec succès";
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
            <h1>Gestion des Abonnés</h1>
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
                    <input type="text" name="search" placeholder="Rechercher par nom d'utilisateur..." 
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
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Abonnements</h3>
                    <div class="stat-value"><?= $totalFollowers ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div class="stat-content">
                    <h3>Utilisateurs Suivis</h3>
                    <div class="stat-value">
                        <?php
                        $followedUsersStmt = $conn->query("SELECT COUNT(DISTINCT user_id) FROM followers");
                        echo $followedUsersStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-content">
                    <h3>Abonnés Actifs</h3>
                    <div class="stat-value">
                        <?php
                        $activeFollowersStmt = $conn->query("SELECT COUNT(DISTINCT follower_id) FROM followers");
                        echo $activeFollowersStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <h3>Moyenne par Utilisateur</h3>
                    <div class="stat-value">
                        <?php
                        $avgFollowersStmt = $conn->query("SELECT ROUND(AVG(follower_count), 1) FROM (SELECT COUNT(*) as follower_count FROM followers GROUP BY user_id) as sub");
                        echo $avgFollowersStmt->fetchColumn() ?: '0';
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des abonnements -->
        <div class="followers-table-container">
            <div class="table-header">
                <h2>Liste des Abonnements</h2>
                <div class="table-actions">
                    <span class="results-count"><?= $totalFollowers ?> abonnement(s) trouvé(s)</span>
                </div>
            </div>

            <div class="followers-grid">
                <?php foreach ($followers as $follower): ?>
                    <div class="follower-card">
                        <div class="follower-header">
                            <div class="follower-relationship">
                                <div class="user followed">
                                    <div class="user-avatar">
                                        <img src="<?= !empty($follower['user_picture']) ? '../uploads/' . $follower['user_picture'] : '../uploads/default_profile.jpg' ?>" 
                                             alt="Avatar" onerror="this.src='../uploads/default_profile.jpg'">
                                    </div>
                                    <div class="user-info">
                                        <h3><?= htmlspecialchars($follower['user_name']) ?></h3>
                                        <span class="user-role">Suivi</span>
                                    </div>
                                </div>
                                
                                <div class="follower-arrow">
                                    <i class="fas fa-arrow-left"></i>
                                </div>
                                
                                <div class="user follower">
                                    <div class="user-avatar">
                                        <img src="<?= !empty($follower['follower_picture']) ? '../uploads/' . $follower['follower_picture'] : '../uploads/default_profile.jpg' ?>" 
                                             alt="Avatar" onerror="this.src='../uploads/default_profile.jpg'">
                                    </div>
                                    <div class="user-info">
                                        <h3><?= htmlspecialchars($follower['follower_name']) ?></h3>
                                        <span class="user-role">Abonné</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="follower-meta">
                                <span class="follower-date">
                                    <i class="fas fa-calendar"></i>
                                    Abonné depuis le <?= date('d/m/Y', strtotime($follower['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="follower-actions">
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet abonnement ?')">
                                <input type="hidden" name="follower_id" value="<?= $follower['id'] ?>">
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

.followers-table-container {
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

.followers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 20px;
}

.follower-card {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s;
    border-left: 4px solid var(--primary);
}

.follower-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.follower-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.follower-relationship {
    display: flex;
    align-items: center;
    gap: 15px;
    flex: 1;
}

.user {
    display: flex;
    align-items: center;
    gap: 8px;
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
    margin: 0 0 2px 0;
    color: #1e293b;
    font-size: 14px;
}

.user-role {
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
}

.follower-arrow {
    color: var(--primary);
    font-size: 16px;
}

.follower-meta {
    display: flex;
    flex-direction: column;
    gap: 5px;
    align-items: flex-end;
}

.follower-date {
    color: #64748b;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.follower-actions {
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
    .followers-grid {
        grid-template-columns: 1fr;
    }
    
    .follower-relationship {
        flex-direction: column;
        gap: 10px;
    }
    
    .follower-arrow {
        transform: rotate(90deg);
    }
    
    .follower-actions {
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