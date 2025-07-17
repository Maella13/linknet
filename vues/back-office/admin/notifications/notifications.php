<?php
require_once "../menu.php";
echo '<link rel="stylesheet" href="/assets/css/back-office/notifications.css">';

// Gestion des actions POST
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action) {
        try {
            switch ($action) {
                case 'add_notification':
                    if ($role === 'Administrateur') {
                        $user_id = (int)($_POST['user_id'] ?? 0);
                        $sender_id = (int)($_POST['sender_id'] ?? 0);
                        $type = $_POST['type'] ?? '';
                        $post_id = !empty($_POST['post_id']) ? (int)$_POST['post_id'] : null;
                        
                        if ($user_id <= 0) {
                            $message = "Veuillez sélectionner un utilisateur destinataire";
                            break;
                        }
                        
                        if ($sender_id <= 0) {
                            $message = "Veuillez sélectionner un utilisateur expéditeur";
                            break;
                        }
                        
                        if (!in_array($type, ['friend_request', 'follow', 'like', 'comment'])) {
                            $message = "Type de notification invalide";
                            break;
                        }
                        
                        $insertStmt = $conn->prepare("INSERT INTO notifications (user_id, sender_id, type, post_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                        if ($insertStmt->execute([$user_id, $sender_id, $type, $post_id])) {
                            $message = "Notification ajoutée avec succès";
                        } else {
                            $message = "Erreur lors de l'ajout";
                        }
                    } else {
                        $message = "Vous n'avez pas les permissions pour ajouter une notification";
                    }
                    break;
                    
                case 'delete':
                    if (!isset($_POST['notification_id'])) {
                        $message = "ID notification manquant";
                        break;
                    }
                    $notificationId = (int)$_POST['notification_id'];
                    if ($role === 'Administrateur' || $role === 'Modérateur') {
                        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
                        $stmt->execute([$notificationId]);
                        $message = "Notification supprimée avec succès";
                    } else {
                        $message = "Vous n'avez pas les permissions pour supprimer une notification";
                    }
                    break;
                    
                case 'mark_read':
                    if (!isset($_POST['notification_id'])) {
                        $message = "ID notification manquant";
                        break;
                    }
                    $notificationId = (int)$_POST['notification_id'];
                    if ($role === 'Administrateur' || $role === 'Modérateur') {
                        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
                        $stmt->execute([$notificationId]);
                        $message = "Notification marquée comme lue";
                    } else {
                        $message = "Vous n'avez pas les permissions pour modifier une notification";
                    }
                    break;
            }
        } catch (PDOException $e) {
            $message = "Erreur lors de l'opération: " . $e->getMessage();
        }
    }
}

// Paramètres de pagination et recherche
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

$whereClause = '';
$params = [];
$conditions = [];

if (!empty($search)) {
    $conditions[] = "(u.username LIKE ? OR s.username LIKE ? OR p.content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_type)) {
    $conditions[] = "n.type = ?";
    $params[] = $filter_type;
}

if (!empty($filter_status)) {
    if ($filter_status === 'read') {
        $conditions[] = "n.is_read = 1";
    } else {
        $conditions[] = "n.is_read = 0";
    }
}

if (!empty($conditions)) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

$countQuery = "SELECT COUNT(*) FROM notifications n 
               LEFT JOIN users u ON n.user_id = u.id 
               LEFT JOIN users s ON n.sender_id = s.id 
               LEFT JOIN posts p ON n.post_id = p.id 
               $whereClause";
$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $limit);

$query = "SELECT n.*, 
                 u.username as recipient_username, u.profile_picture as recipient_picture,
                 s.username as sender_username, s.profile_picture as sender_picture,
                 p.content as post_content
          FROM notifications n 
          LEFT JOIN users u ON n.user_id = u.id 
          LEFT JOIN users s ON n.sender_id = s.id 
          LEFT JOIN posts p ON n.post_id = p.id 
          $whereClause
          ORDER BY n.created_at DESC 
          LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération de tous les utilisateurs pour le select d'ajout
$usersQuery = "SELECT id, username FROM users ORDER BY username";
$usersList = $conn->query($usersQuery)->fetchAll(PDO::FETCH_ASSOC);

// Récupération de tous les posts pour le select d'ajout
$postsQuery = "SELECT p.id, p.content, u.username 
               FROM posts p 
               JOIN users u ON p.user_id = u.id 
               ORDER BY p.created_at DESC 
               LIMIT 100";
$postsList = $conn->query($postsQuery)->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="main-content">
    <div class="dashboard-container">
        <div class="header">
            <h1>Gestion des Notifications</h1>
        </div>

        <?php if ($message): ?>
            <div class="alert <?= strpos($message, 'succès') !== false ? 'alert-success' : 'alert-error' ?>">
                <i class="fas <?= strpos($message, 'succès') !== false ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Barre de recherche et filtres -->
        <div class="search-section">
            <div class="search-header">
                <form method="GET" class="search-form">
                    <div class="search-input-group">
                        <input type="text" name="search" placeholder="Rechercher par utilisateur ou contenu..." 
                               value="<?= htmlspecialchars($search) ?>" class="search-input">
                        <select name="type" class="filter-select">
                            <option value="">Tous les types</option>
                            <option value="friend_request" <?= $filter_type === 'friend_request' ? 'selected' : '' ?>>Demande d'ami</option>
                            <option value="follow" <?= $filter_type === 'follow' ? 'selected' : '' ?>>Abonnement</option>
                            <option value="like" <?= $filter_type === 'like' ? 'selected' : '' ?>>Like</option>
                            <option value="comment" <?= $filter_type === 'comment' ? 'selected' : '' ?>>Commentaire</option>
                        </select>
                        <select name="status" class="filter-select">
                            <option value="">Tous les statuts</option>
                            <option value="unread" <?= $filter_status === 'unread' ? 'selected' : '' ?>>Non lues</option>
                            <option value="read" <?= $filter_status === 'read' ? 'selected' : '' ?>>Lues</option>
                        </select>
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <?php if ($role === 'Administrateur'): ?>
                    <button class="btn btn-primary" onclick="showAddNotificationModal()">
                        <i class="fas fa-plus"></i>
                        Ajouter une notification
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Notifications</h3>
                    <div class="stat-value"><?= $total ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="stat-content">
                    <h3>Notifications Lues</h3>
                    <div class="stat-value">
                        <?php
                        $readStmt = $conn->query("SELECT COUNT(*) FROM notifications WHERE is_read = 1");
                        echo $readStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-eye-slash"></i>
                </div>
                <div class="stat-content">
                    <h3>Notifications Non Lues</h3>
                    <div class="stat-value">
                        <?php
                        $unreadStmt = $conn->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0");
                        echo $unreadStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3>Utilisateurs Notifiés</h3>
                    <div class="stat-value">
                        <?php
                        $usersStmt = $conn->query("SELECT COUNT(DISTINCT user_id) FROM notifications");
                        echo $usersStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="view-switch" style="margin-left:auto;display:flex;gap:10px;">
                        <button class="view-btn" id="cardViewBtn" type="button"><i class="fas fa-th"></i> Vue carte</button>
                        <button class="view-btn" id="tableViewBtn" type="button"><i class="fas fa-table"></i> Vue tableau</button>
        </div>
        <!-- Liste des notifications -->
        <div class="notifications-table-container">
            <div class="table-header">
                <h2>Liste des Notifications</h2>
                <div class="table-actions">
                    <span class="results-count"><?= $total ?> notification(s) trouvée(s)</span>
                    <button id="deleteSelectedBtn" class="btn btn-danger btn-sm" style="display:none; margin-left:15px;" type="button">
                        <i class="fas fa-trash"></i> Supprimer la sélection
                    </button>
                </div>
            </div>
            <!-- Vue carte -->
            <div class="notifications-grid" id="cardView">
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-card <?= !$notification['is_read'] ? 'unread' : '' ?>" data-notification-id="<?= $notification['id'] ?>">
                        <div class="notification-header">
                            <div class="notification-avatar">
                                <img src="<?= !empty($notification['sender_picture']) ? '../../uploads/' . $notification['sender_picture'] : '../../uploads/default_profile.jpg' ?>" 
                                     alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
                            </div>
                            <div class="notification-info">
                                <h3><?= htmlspecialchars($notification['sender_username']) ?></h3>
                                <p class="notification-date"><?= date('d/m/Y H:i', strtotime($notification['created_at'])) ?></p>
                            </div>
                            <div class="notification-status <?= $notification['is_read'] ? 'read' : 'unread' ?>">
                                <i class="fas <?= $notification['is_read'] ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                            </div>
                        </div>
                        
                        <div class="notification-content">
                            <div class="notification-type">
                                <span class="type-badge type-<?= $notification['type'] ?>">
                                    <?php
                                    $typeLabels = [
                                        'friend_request' => 'Demande d\'ami',
                                        'follow' => 'Abonnement',
                                        'like' => 'Like',
                                        'comment' => 'Commentaire'
                                    ];
                                    echo $typeLabels[$notification['type']] ?? $notification['type'];
                                    ?>
                                </span>
                            </div>
                            
                            <div class="notification-details">
                                <p><strong>Destinataire :</strong> <?= htmlspecialchars($notification['recipient_username']) ?></p>
                                <?php if (!empty($notification['post_content'])): ?>
                                    <p><strong>Post :</strong> <?= htmlspecialchars(substr($notification['post_content'], 0, 100)) ?>...</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="notification-actions">
                            <button class="btn btn-info btn-sm" onclick="viewNotification(<?= $notification['id'] ?>)"><i class="fas fa-eye"></i> Voir</button>
                            <button class="btn btn-danger btn-sm" onclick="deleteNotification(<?= $notification['id'] ?>)"><i class="fas fa-trash"></i> Supprimer</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- Vue tableau -->
            <div class="notifications-table-view" id="tableView" style="display:none;">
                <table class="notifications-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllNotifications"></th>
                            <th>Utilisateur</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th><button id="sortDateBtn" onclick="changeSortOrder()" style="background:none;border:none;cursor:pointer;font-weight:700;">Date</button></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notifications as $notification): ?>
                            <tr data-notification-id="<?= $notification['id'] ?>">
                                <td><input type="checkbox" class="notification-checkbox" value="<?= $notification['id'] ?>"></td>
                                <td>
                                    <div class="notification-header">
                                        <div class="notification-avatar">
                                            <img src="<?= !empty($notification['sender_picture']) ? '../../uploads/' . $notification['sender_picture'] : '../../uploads/default_profile.jpg' ?>" 
                                                 alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
                                        </div>
                                        <div class="notification-info">
                                            <h3><?= htmlspecialchars($notification['sender_username']) ?></h3>
                                            <p class="notification-date"><?= date('d/m/Y H:i', strtotime($notification['created_at'])) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="notification-type">
                                        <span class="type-badge type-<?= $notification['type'] ?>">
                                            <?php
                                            $typeLabels = [
                                                'friend_request' => 'Demande d\'ami',
                                                'follow' => 'Abonnement',
                                                'like' => 'Like',
                                                'comment' => 'Commentaire'
                                            ];
                                            echo $typeLabels[$notification['type']] ?? $notification['type'];
                                            ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="notification-status <?= $notification['is_read'] ? 'read' : 'unread' ?>">
                                        <i class="fas <?= $notification['is_read'] ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                                    </div>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($notification['created_at'])) ?></td>
                                <td>
                                    <div class="notification-actions">
                                        <button class="btn btn-info btn-sm" onclick="viewNotification(<?= $notification['id'] ?>)"><i class="fas fa-eye"></i> Voir</button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteNotification(<?= $notification['id'] ?>)"><i class="fas fa-trash"></i> Supprimer</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($filter_type) ?>&status=<?= urlencode($filter_status) ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                            Précédent
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($filter_type) ?>&status=<?= urlencode($filter_status) ?>" 
                           class="page-link <?= $i === $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($filter_type) ?>&status=<?= urlencode($filter_status) ?>" class="page-link">
                            Suivant
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal d'ajout de notification -->
<?php if ($role === 'Administrateur' || $role === 'Modérateur'): ?>
<div id="addNotificationModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Ajouter une Notification</h2>
            <span class="close" onclick="closeAddNotificationModal()">&times;</span>
        </div>
        
        <form id="addNotificationForm" method="POST">
            <input type="hidden" name="action" value="add_notification">
            
            <div class="form-group">
                <label for="user_id">Destinataire *</label>
                <select name="user_id" id="user_id" required>
                    <option value="">Sélectionner un destinataire</option>
                    <?php foreach ($usersList as $user): ?>
                        <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="sender_id">Expéditeur *</label>
                <select name="sender_id" id="sender_id" required>
                    <option value="">Sélectionner un expéditeur</option>
                    <?php foreach ($usersList as $user): ?>
                        <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="type">Type de notification *</label>
                <select name="type" id="type" required onchange="togglePostField()">
                    <option value="">Sélectionner un type</option>
                    <option value="friend_request">Demande d'ami</option>
                    <option value="follow">Abonnement</option>
                    <option value="like">Like</option>
                    <option value="comment">Commentaire</option>
                </select>
            </div>
            
            <div class="form-group" id="postField" style="display: none;">
                <label for="post_id">Post associé</label>
                <select name="post_id" id="post_id">
                    <option value="">Aucun post</option>
                    <?php foreach ($postsList as $post): ?>
                        <option value="<?= $post['id'] ?>"><?= htmlspecialchars(substr($post['content'], 0, 60)) ?>... (<?= htmlspecialchars($post['username']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <small class="form-help">Obligatoire pour les likes et commentaires</small>
            </div>
            
            <div class="form-actions">
                <button type="button" onclick="closeAddNotificationModal()" class="btn btn-secondary">Annuler</button>
                <button type="submit" class="btn btn-primary">Ajouter</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// Fonctions pour les modals
function showAddNotificationModal() {
    document.getElementById('addNotificationModal').style.display = 'block';
}

function closeAddNotificationModal() {
    document.getElementById('addNotificationModal').style.display = 'none';
    document.getElementById('addNotificationForm').reset();
    document.getElementById('postField').style.display = 'none';
}

// Fonction pour afficher/masquer le champ post selon le type
function togglePostField() {
    const type = document.getElementById('type').value;
    const postField = document.getElementById('postField');
    const postSelect = document.getElementById('post_id');
    
    if (type === 'like' || type === 'comment') {
        postField.style.display = 'block';
        postSelect.required = true;
    } else {
        postField.style.display = 'none';
        postSelect.required = false;
        postSelect.value = '';
    }
}

// Fonction de suppression avec confirmation
function deleteNotification(notificationId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette notification ?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="notification_id" value="${notificationId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Fonction pour marquer comme lue
function markAsRead(notificationId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="mark_read">
        <input type="hidden" name="notification_id" value="${notificationId}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Validation du formulaire d'ajout
document.addEventListener('DOMContentLoaded', function() {
    const addNotificationForm = document.getElementById('addNotificationForm');
    if (addNotificationForm) {
        addNotificationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const userId = document.getElementById('user_id').value;
            const senderId = document.getElementById('sender_id').value;
            const type = document.getElementById('type').value;
            const postId = document.getElementById('post_id').value;
            
            if (!userId || !senderId || !type) {
                showMessage('Tous les champs obligatoires doivent être remplis', false);
                return false;
            }
            
            if (userId === senderId) {
                showMessage('Le destinataire et l\'expéditeur ne peuvent pas être identiques', false);
                return false;
            }
            
            if ((type === 'like' || type === 'comment') && !postId) {
                showMessage('Un post est obligatoire pour les likes et commentaires', false);
                return false;
            }
            
            const formData = new FormData();
            formData.append('action', 'add_notification');
            formData.append('user_id', userId);
            formData.append('sender_id', senderId);
            formData.append('type', type);
            formData.append('post_id', postId);
            
            const submitBtn = addNotificationForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout en cours...';
            submitBtn.disabled = true;
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const alertElement = doc.querySelector('.alert');
                
                if (alertElement) {
                    const message = alertElement.textContent.trim();
                    const isSuccess = alertElement.classList.contains('alert-success');
                    
                    showMessage(message, isSuccess);
                    
                    if (isSuccess) {
                        closeAddNotificationModal();
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showMessage('Erreur lors de l\'ajout de la notification', false);
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});

// Fonction pour afficher les messages
function showMessage(message, isSuccess) {
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert ${isSuccess ? 'alert-success' : 'alert-error'}`;
    alertDiv.innerHTML = `
        <i class="fas ${isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        ${message}
    `;
    
    const header = document.querySelector('.header');
    header.parentNode.insertBefore(alertDiv, header.nextSibling);
    
    setTimeout(() => {
        alertDiv.style.opacity = '0';
        setTimeout(() => alertDiv.remove(), 300);
    }, 3000);
}

// Fermer les modals en cliquant à l'extérieur
window.onclick = function(event) {
    const addModal = document.getElementById('addNotificationModal');
    
    if (event.target === addModal) {
        closeAddNotificationModal();
    }
}

// Auto-hide des messages de succès/erreur
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 3000);
    });
});

// --- API AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Action invalide'];
    try {
        switch ($action) {
            case 'add_notification':
                if ($role === 'Administrateur') {
                    $user_id = (int)($_POST['user_id'] ?? 0);
                    $sender_id = (int)($_POST['sender_id'] ?? 0);
                    $type = $_POST['type'] ?? '';
                    $post_id = !empty($_POST['post_id']) ? (int)$_POST['post_id'] : null;
                    if ($user_id <= 0) {
                        $response['message'] = "Veuillez sélectionner un utilisateur destinataire";
                        break;
                    }
                    if ($sender_id <= 0) {
                        $response['message'] = "Veuillez sélectionner un utilisateur expéditeur";
                        break;
                    }
                    if (!in_array($type, ['friend_request', 'follow', 'like', 'comment'])) {
                        $response['message'] = "Type de notification invalide";
                        break;
                    }
                    $insertStmt = $conn->prepare("INSERT INTO notifications (user_id, sender_id, type, post_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                    if ($insertStmt->execute([$user_id, $sender_id, $type, $post_id])) {
                        $response['success'] = true;
                        $response['message'] = "Notification ajoutée avec succès";
                        $response['stats'] = getStats($conn);
                    } else {
                        $response['message'] = "Erreur lors de l'ajout";
                    }
                } else {
                    $response['message'] = "Vous n'avez pas les permissions pour ajouter une notification";
                }
                break;
            case 'delete':
                if (!isset($_POST['notification_id'])) {
                    $response['message'] = "ID notification manquant";
                    break;
                }
                $notificationId = (int)$_POST['notification_id'];
                if ($role === 'Administrateur' || $role === 'Modérateur') {
                    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
                    $stmt->execute([$notificationId]);
                    $response['success'] = true;
                    $response['message'] = "Notification supprimée avec succès";
                    $response['stats'] = getStats($conn);
                } else {
                    $response['message'] = "Vous n'avez pas les permissions pour supprimer une notification";
                }
                break;
            case 'mark_read':
                if (!isset($_POST['notification_id'])) {
                    $response['message'] = "ID notification manquant";
                    break;
                }
                $notificationId = (int)$_POST['notification_id'];
                if ($role === 'Administrateur' || $role === 'Modérateur') {
                    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
                    $stmt->execute([$notificationId]);
                    $response['success'] = true;
                    $response['message'] = "Notification marquée comme lue";
                    $response['stats'] = getStats($conn);
                } else {
                    $response['message'] = "Vous n'avez pas les permissions pour modifier une notification";
                }
                break;
        }
    } catch (PDOException $e) {
        $response['message'] = "Erreur lors de l'opération: " . $e->getMessage();
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
function getStats($conn) {
    return [
        'total' => (int)$conn->query("SELECT COUNT(*) FROM notifications")->fetchColumn(),
        'read' => (int)$conn->query("SELECT COUNT(*) FROM notifications WHERE is_read = 1")->fetchColumn(),
        'unread' => (int)$conn->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn(),
        'users' => (int)$conn->query("SELECT COUNT(DISTINCT user_id) FROM notifications")->fetchColumn(),
    ];
}
// --- FIN API AJAX ---

// --- SUPPRESSION/MARQUAGE AJAX ---
function deleteNotificationAjax(notificationId, card) {
    card.classList.add('fade-out');
    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ action: 'delete', notification_id: notificationId })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            setTimeout(() => {
                card.remove();
                updateStats(res.stats);
                showMessage(res.message, true);
            }, 300);
        } else {
            card.classList.remove('fade-out');
            showMessage(res.message, false);
        }
    })
    .catch(() => {
        card.classList.remove('fade-out');
        showMessage('Erreur lors de la suppression', false);
    });
}
function markAsReadAjax(notificationId, card) {
    card.classList.add('fade-out');
    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ action: 'mark_read', notification_id: notificationId })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            setTimeout(() => {
                card.classList.remove('fade-out');
                card.classList.remove('unread');
                card.querySelector('.notification-status').classList.remove('unread');
                card.querySelector('.notification-status').classList.add('read');
                card.querySelector('.notification-status i').className = 'fas fa-eye';
                updateStats(res.stats);
                showMessage(res.message, true);
            }, 300);
        } else {
            card.classList.remove('fade-out');
            showMessage(res.message, false);
        }
    })
    .catch(() => {
        card.classList.remove('fade-out');
        showMessage('Erreur lors du marquage', false);
    });
}
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.notification-card .btn-danger').forEach(btn => {
        btn.onclick = function(e) {
            e.preventDefault();
            if (!confirm('Êtes-vous sûr de vouloir supprimer cette notification ?')) return false;
            const card = btn.closest('.notification-card');
            const notificationId = card.getAttribute('data-notification-id');
            deleteNotificationAjax(notificationId, card);
            return false;
        };
    });
    document.querySelectorAll('.notification-card .btn-success').forEach(btn => {
        btn.onclick = function(e) {
            e.preventDefault();
            const card = btn.closest('.notification-card');
            const notificationId = card.getAttribute('data-notification-id');
            markAsReadAjax(notificationId, card);
            return false;
        };
    });
    // Ajout AJAX
    const addNotificationForm = document.getElementById('addNotificationForm');
    if (addNotificationForm) {
        addNotificationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const userId = document.getElementById('user_id').value;
            const senderId = document.getElementById('sender_id').value;
            const type = document.getElementById('type').value;
            const postId = document.getElementById('post_id').value;
            if (!userId || !senderId || !type) {
                showMessage('Tous les champs obligatoires doivent être remplis', false);
                return false;
            }
            if (userId === senderId) {
                showMessage('Le destinataire et l\'expéditeur ne peuvent pas être identiques', false);
                return false;
            }
            if ((type === 'like' || type === 'comment') && !postId) {
                showMessage('Un post est obligatoire pour les likes et commentaires', false);
                return false;
            }
            const formData = new FormData();
            formData.append('action', 'add_notification');
            formData.append('user_id', userId);
            formData.append('sender_id', senderId);
            formData.append('type', type);
            formData.append('post_id', postId);
            const submitBtn = addNotificationForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout en cours...';
            submitBtn.disabled = true;
            fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    closeAddNotificationModal();
                    updateStats(res.stats);
                    showMessage(res.message, true);
                } else {
                    showMessage(res.message, false);
                }
            })
            .catch(() => showMessage('Erreur lors de l\'ajout de la notification', false))
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});
// --- FIN SUPPRESSION/MARQUAGE/AJOUT ---

// --- MESSAGES DYNAMIQUES ---
function showMessage(message, isSuccess) {
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert ${isSuccess ? 'alert-success' : 'alert-error'}`;
    alertDiv.innerHTML = `
        <i class="fas ${isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        ${message}
    `;
    const header = document.querySelector('.header');
    header.parentNode.insertBefore(alertDiv, header.nextSibling);
    setTimeout(() => {
        alertDiv.style.opacity = '0';
        setTimeout(() => alertDiv.remove(), 300);
    }, 3000);
}
// --- FIN MESSAGES ---

// --- STATS DYNAMIQUES ---
function updateStats(stats) {
    if (!stats) return;
    if (stats.total !== undefined) document.querySelectorAll('.stat-value')[0].textContent = stats.total;
    if (stats.read !== undefined) document.querySelectorAll('.stat-value')[1].textContent = stats.read;
    if (stats.unread !== undefined) document.querySelectorAll('.stat-value')[2].textContent = stats.unread;
    if (stats.users !== undefined) document.querySelectorAll('.stat-value')[3].textContent = stats.users;
    document.querySelector('.results-count').textContent = `${stats.total} notification(s) trouvée(s)`;
}
// --- FIN STATS ---

// --- FADE-IN/FADE-OUT CSS ---
const style = document.createElement('style');
style.innerHTML = `.fade-in { animation: fadeIn .35s; } .fade-out { opacity:0; transform:translateY(-10px); transition:all .3s; } @keyframes fadeIn { from { opacity:0; transform:translateY(-10px);} to {opacity:1;transform:none;} }`;
document.head.appendChild(style);
// --- FIN FADE-IN/FADE-OUT ---

// --- TRI ASC/DESC ---
let sortOrder = localStorage.getItem('notifications_sort_order') || (new URLSearchParams(window.location.search).get('sort') || 'desc');
function updateSortUI() {
    const sortBtn = document.getElementById('sortDateBtn');
    if (sortBtn) {
        sortBtn.innerHTML = sortOrder === 'asc'
            ? '<i class="fas fa-sort-amount-up-alt"></i> Date <span style="font-size:12px">(ASC)</span>'
            : '<i class="fas fa-sort-amount-down-alt"></i> Date <span style="font-size:12px">(DESC)</span>';
    }
}
function changeSortOrder() {
    sortOrder = sortOrder === 'asc' ? 'desc' : 'asc';
    localStorage.setItem('notifications_sort_order', sortOrder);
    const params = new URLSearchParams(window.location.search);
    params.set('sort', sortOrder);
    window.location.search = params.toString();
}
document.addEventListener('DOMContentLoaded', updateSortUI);
// --- FIN TRI ---

// Switch d'affichage carte/tableau
const cardViewBtn = document.getElementById('cardViewBtn');
const tableViewBtn = document.getElementById('tableViewBtn');
const cardView = document.getElementById('cardView');
const tableView = document.getElementById('tableView');
const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
function applySavedView() {
    const savedView = localStorage.getItem('notificationsViewMode');
    if (savedView === 'table') {
        cardView.style.display = 'none';
        tableView.style.display = '';
        cardViewBtn.classList.remove('active');
        tableViewBtn.classList.add('active');
        updateDeleteSelectedBtn();
    } else {
        cardView.style.display = '';
        tableView.style.display = 'none';
        cardViewBtn.classList.add('active');
        tableViewBtn.classList.remove('active');
        deleteSelectedBtn.style.display = 'none';
    }
}
if (cardViewBtn && tableViewBtn && cardView && tableView) {
    cardViewBtn.addEventListener('click', function() {
        localStorage.setItem('notificationsViewMode', 'card');
        applySavedView();
    });
    tableViewBtn.addEventListener('click', function() {
        localStorage.setItem('notificationsViewMode', 'table');
        applySavedView();
    });
    applySavedView();
}
// Gestion sélection multiple
const selectAllNotifications = document.getElementById('selectAllNotifications');
function updateDeleteSelectedBtn() {
    const checked = document.querySelectorAll('.notification-checkbox:checked');
    deleteSelectedBtn.style.display = (checked.length > 0) ? '' : 'none';
}
if (selectAllNotifications) {
    selectAllNotifications.addEventListener('change', function() {
        document.querySelectorAll('.notification-checkbox').forEach(cb => {
            cb.checked = selectAllNotifications.checked;
        });
        updateDeleteSelectedBtn();
    });
}
document.querySelectorAll('.notification-checkbox').forEach(cb => {
    cb.addEventListener('change', updateDeleteSelectedBtn);
});
if (deleteSelectedBtn) {
    deleteSelectedBtn.addEventListener('click', function() {
        const checked = Array.from(document.querySelectorAll('.notification-checkbox:checked'));
        if (checked.length === 0) return;
        if (!confirm(`Supprimer ${checked.length} notification(s) sélectionnée(s) ? Cette action est irréversible.`)) return;
        checked.forEach(cb => {
            const notifId = cb.value;
            deleteNotificationAjax(notifId);
        });
        if (selectAllNotifications) selectAllNotifications.checked = false;
        updateDeleteSelectedBtn();
    });
}
</script>

