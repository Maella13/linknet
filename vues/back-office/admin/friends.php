<?php
require_once 'menu.php';

// Récupération des amitiés avec pagination
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
    SELECT COUNT(*) FROM friends f 
    LEFT JOIN users u1 ON f.sender_id = u1.id 
    LEFT JOIN users u2 ON f.receiver_id = u2.id 
    WHERE f.status = 'accepted' $whereClause
");
$countStmt->execute($params);
$totalFriendships = $countStmt->fetchColumn();
$totalPages = ceil($totalFriendships / $limit);

// Récupération des amitiés
$stmt = $conn->prepare("
    SELECT f.*, 
           u1.username as sender_name,
           u1.profile_picture as sender_picture,
           u2.username as receiver_name,
           u2.profile_picture as receiver_picture
    FROM friends f
    LEFT JOIN users u1 ON f.sender_id = u1.id
    LEFT JOIN users u2 ON f.receiver_id = u2.id
    WHERE f.status = 'accepted' $whereClause
    ORDER BY f.created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$friendships = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement des actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['friendship_id'])) {
        $friendshipId = (int)$_POST['friendship_id'];
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM friends WHERE id = ?");
                    $stmt->execute([$friendshipId]);
                    $message = "Amitié supprimée avec succès";
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
            <h1>Gestion des Amitiés</h1>
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
                    <i class="fas fa-user-friends"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Amitiés</h3>
                    <div class="stat-value"><?= $totalFriendships ?></div>
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
                        $activeUsersStmt = $conn->query("SELECT COUNT(DISTINCT sender_id) + COUNT(DISTINCT receiver_id) FROM friends WHERE status = 'accepted'");
                        echo $activeUsersStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3>En Attente</h3>
                    <div class="stat-value">
                        <?php
                        $pendingStmt = $conn->query("SELECT COUNT(*) FROM friends WHERE status = 'pending'");
                        echo $pendingStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>Refusées</h3>
                    <div class="stat-value">
                        <?php
                        $rejectedStmt = $conn->query("SELECT COUNT(*) FROM friends WHERE status = 'rejected'");
                        echo $rejectedStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des amitiés -->
        <div class="friendships-table-container">
            <div class="table-header">
                <h2>Liste des Amitiés</h2>
                <div class="table-actions">
                    <span class="results-count"><?= $totalFriendships ?> amitié(s) trouvée(s)</span>
                </div>
            </div>

            <div class="friendships-grid">
                <?php foreach ($friendships as $friendship): ?>
                    <div class="friendship-card">
                        <div class="friendship-header">
                            <div class="friendship-users">
                                <div class="user user1">
                                    <div class="user-avatar">
                                        <img src="<?= !empty($friendship['sender_picture']) ? '../uploads/' . $friendship['sender_picture'] : '../uploads/default_profile.jpg' ?>" 
                                             alt="Avatar" onerror="this.src='../uploads/default_profile.jpg'">
                                    </div>
                                    <div class="user-info">
                                        <h3><?= htmlspecialchars($friendship['sender_name']) ?></h3>
                                        <span class="user-role">Demandeur</span>
                                    </div>
                                </div>
                                
                                <div class="friendship-arrow">
                                    <i class="fas fa-heart"></i>
                                </div>
                                
                                <div class="user user2">
                                    <div class="user-avatar">
                                        <img src="<?= !empty($friendship['receiver_picture']) ? '../uploads/' . $friendship['receiver_picture'] : '../uploads/default_profile.jpg' ?>" 
                                             alt="Avatar" onerror="this.src='../uploads/default_profile.jpg'">
                                    </div>
                                    <div class="user-info">
                                        <h3><?= htmlspecialchars($friendship['receiver_name']) ?></h3>
                                        <span class="user-role">Destinataire</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="friendship-meta">
                                <span class="friendship-date">
                                    <i class="fas fa-calendar"></i>
                                    Amis depuis le <?= date('d/m/Y', strtotime($friendship['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="friendship-actions">
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette amitié ?')">
                                <input type="hidden" name="friendship_id" value="<?= $friendship['id'] ?>">
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
    background: linear-gradient(135deg, #22c55e, #16a34a);
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
    color: #22c55e;
}

.friendships-table-container {
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

.friendships-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 20px;
}

.friendship-card {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s;
    border-left: 4px solid #22c55e;
}

.friendship-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.friendship-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.friendship-users {
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

.friendship-arrow {
    color: #22c55e;
    font-size: 16px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.friendship-meta {
    display: flex;
    flex-direction: column;
    gap: 5px;
    align-items: flex-end;
}

.friendship-date {
    color: #64748b;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.friendship-actions {
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
    .friendships-grid {
        grid-template-columns: 1fr;
    }
    
    .friendship-users {
        flex-direction: column;
        gap: 10px;
    }
    
    .friendship-arrow {
        transform: rotate(90deg);
    }
    
    .friendship-actions {
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