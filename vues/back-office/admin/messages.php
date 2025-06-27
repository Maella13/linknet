<?php
require_once 'menu.php';

// Récupération des messages avec pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Construction de la requête avec recherche
$whereClause = '';
$params = [];
if (!empty($search)) {
    $whereClause = "WHERE m.message LIKE ? OR u1.username LIKE ? OR u2.username LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

// Comptage total
$countStmt = $conn->prepare("
    SELECT COUNT(*) FROM messages m 
    LEFT JOIN users u1 ON m.sender_id = u1.id 
    LEFT JOIN users u2 ON m.receiver_id = u2.id 
    $whereClause
");
$countStmt->execute($params);
$totalMessages = $countStmt->fetchColumn();
$totalPages = ceil($totalMessages / $limit);

// Récupération des messages
$stmt = $conn->prepare("
    SELECT m.*, 
           u1.username as sender_name,
           u1.profile_picture as sender_picture,
           u2.username as receiver_name,
           u2.profile_picture as receiver_picture
    FROM messages m
    LEFT JOIN users u1 ON m.sender_id = u1.id
    LEFT JOIN users u2 ON m.receiver_id = u2.id
    $whereClause
    ORDER BY m.created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement des actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['message_id'])) {
        $messageId = (int)$_POST['message_id'];
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
                    $stmt->execute([$messageId]);
                    $message = "Message supprimé avec succès";
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
            <h1>Gestion des Messages</h1>
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
                    <input type="text" name="search" placeholder="Rechercher dans les messages..." 
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
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Messages</h3>
                    <div class="stat-value"><?= $totalMessages ?></div>
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
                        $activeUsersStmt = $conn->query("SELECT COUNT(DISTINCT sender_id) + COUNT(DISTINCT receiver_id) FROM messages");
                        echo $activeUsersStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="stat-content">
                    <h3>Messages Lus</h3>
                    <div class="stat-value">
                        <?php
                        $readMessagesStmt = $conn->query("SELECT COUNT(*) FROM messages WHERE is_read = 1");
                        echo $readMessagesStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-eye-slash"></i>
                </div>
                <div class="stat-content">
                    <h3>Messages Non Lus</h3>
                    <div class="stat-value">
                        <?php
                        $unreadMessagesStmt = $conn->query("SELECT COUNT(*) FROM messages WHERE is_read = 0");
                        echo $unreadMessagesStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des messages -->
        <div class="messages-table-container">
            <div class="table-header">
                <h2>Liste des Messages</h2>
                <div class="table-actions">
                    <span class="results-count"><?= $totalMessages ?> message(s) trouvé(s)</span>
                </div>
            </div>

            <div class="messages-grid">
                <?php foreach ($messages as $msg): ?>
                    <div class="message-card <?= $msg['is_read'] ? 'read' : 'unread' ?>">
                        <div class="message-header">
                            <div class="message-participants">
                                <div class="participant sender">
                                    <div class="participant-avatar">
                                        <img src="<?= !empty($msg['sender_picture']) ? '../uploads/' . $msg['sender_picture'] : '../uploads/default_profile.jpg' ?>" 
                                             alt="Avatar" onerror="this.src='../uploads/default_profile.jpg'">
                                    </div>
                                    <div class="participant-info">
                                        <h3><?= htmlspecialchars($msg['sender_name']) ?></h3>
                                        <span class="participant-role">Expéditeur</span>
                                    </div>
                                </div>
                                
                                <div class="message-arrow">
                                    <i class="fas fa-arrow-right"></i>
                                </div>
                                
                                <div class="participant receiver">
                                    <div class="participant-avatar">
                                        <img src="<?= !empty($msg['receiver_picture']) ? '../uploads/' . $msg['receiver_picture'] : '../uploads/default_profile.jpg' ?>" 
                                             alt="Avatar" onerror="this.src='../uploads/default_profile.jpg'">
                                    </div>
                                    <div class="participant-info">
                                        <h3><?= htmlspecialchars($msg['receiver_name']) ?></h3>
                                        <span class="participant-role">Destinataire</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="message-meta">
                                <span class="message-date">
                                    <i class="fas fa-calendar"></i>
                                    <?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?>
                                </span>
                                <span class="message-status <?= $msg['is_read'] ? 'read' : 'unread' ?>">
                                    <i class="fas <?= $msg['is_read'] ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                                    <?= $msg['is_read'] ? 'Lu' : 'Non lu' ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="message-content">
                            <p><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                        </div>
                        
                        <div class="message-actions">
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce message ?')">
                                <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
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

.messages-table-container {
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

.messages-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
    gap: 20px;
}

.message-card {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s;
}

.message-card.unread {
    border-left: 4px solid #ef4444;
    background: #fef2f2;
}

.message-card.read {
    border-left: 4px solid #22c55e;
}

.message-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.message-participants {
    display: flex;
    align-items: center;
    gap: 15px;
    flex: 1;
}

.participant {
    display: flex;
    align-items: center;
    gap: 8px;
}

.participant-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
}

.participant-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.participant-info h3 {
    margin: 0 0 2px 0;
    color: #1e293b;
    font-size: 14px;
}

.participant-role {
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
}

.message-arrow {
    color: #64748b;
    font-size: 12px;
}

.message-meta {
    display: flex;
    flex-direction: column;
    gap: 5px;
    align-items: flex-end;
}

.message-date {
    color: #64748b;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.message-status {
    padding: 2px 6px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 3px;
}

.message-status.read {
    background: #dcfce7;
    color: #16a34a;
}

.message-status.unread {
    background: #fee2e2;
    color: #dc2626;
}

.message-content {
    margin-bottom: 15px;
    padding: 15px;
    background: #f8fafc;
    border-radius: 8px;
    border-left: 4px solid var(--primary);
}

.message-content p {
    margin: 0;
    line-height: 1.6;
    color: #374151;
}

.message-actions {
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
    .messages-grid {
        grid-template-columns: 1fr;
    }
    
    .message-participants {
        flex-direction: column;
        gap: 10px;
    }
    
    .message-arrow {
        transform: rotate(90deg);
    }
    
    .message-actions {
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