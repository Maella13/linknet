<?php
require_once '../menu.php';
echo '<link rel="stylesheet" href="/assets/css/back-office/messages.css">';
$role = $_SESSION["admin"]["role"] ?? 'Modérateur';

// Récupération des conversations (groupes de deux utilisateurs)
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
// Ajout du tri ASC/DESC
$sort = isset($_GET['sort']) && strtolower($_GET['sort']) === 'asc' ? 'ASC' : 'DESC';

$whereClause = '';
$params = [];
if (!empty($search)) {
    $whereClause = "WHERE m.message LIKE ?";
    $params = ["%$search%"];
}

// Récupérer toutes les conversations (pagination sur les conversations)
$convStmt = $conn->prepare("
    SELECT 
        LEAST(sender_id, receiver_id) as user1,
        GREATEST(sender_id, receiver_id) as user2,
        COUNT(*) as total_messages,
        MAX(created_at) as last_message_date
    FROM messages m
    $whereClause
    GROUP BY user1, user2
    ORDER BY last_message_date $sort
    LIMIT $limit OFFSET $offset
");
$convStmt->execute($params);
$conversations = $convStmt->fetchAll(PDO::FETCH_ASSOC);

// Compter le nombre total de conversations pour la pagination
$countStmt = $conn->prepare("
    SELECT COUNT(*) as nb FROM (
        SELECT 1 FROM messages m $whereClause GROUP BY LEAST(sender_id, receiver_id), GREATEST(sender_id, receiver_id)
    ) as convs
");
$countStmt->execute($params);
$totalConvs = $countStmt->fetchColumn();
$totalPages = ceil($totalConvs / $limit);

// Pour chaque conversation, récupérer les infos utilisateurs et les messages
foreach ($conversations as &$conv) {
    $user1 = $conv['user1'];
    $user2 = $conv['user2'];
    // Infos utilisateurs
    $usersStmt = $conn->prepare("SELECT id, username, profile_picture FROM users WHERE id IN (?, ?)");
    $usersStmt->execute([$user1, $user2]);
    $users = [];
    while ($u = $usersStmt->fetch(PDO::FETCH_ASSOC)) {
        $users[$u['id']] = $u;
    }
    $conv['users'] = $users;
    // Tous les messages de la conversation
    $msgsStmt = $conn->prepare("
        SELECT * FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at ASC
    ");
    $msgsStmt->execute([$user1, $user2, $user2, $user1]);
    $conv['messages'] = $msgsStmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($conv);

// --- API AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Action invalide'];
    try {
        switch ($action) {
            case 'delete':
                if (!isset($_POST['message_id'])) {
                    $response['message'] = "ID manquant";
                    break;
                }
                $messageId = (int)$_POST['message_id'];
                $stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
                $stmt->execute([$messageId]);
                $response['success'] = true;
                $response['message'] = "Message supprimé avec succès";
                $response['stats'] = getStats($conn);
                break;
        }
    } catch (PDOException $e) {
        $response['message'] = "Erreur lors de l'opération";
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
function getStats($conn) {
    return [
        'total' => (int)$conn->query("SELECT COUNT(*) FROM (SELECT 1 FROM messages GROUP BY LEAST(sender_id, receiver_id), GREATEST(sender_id, receiver_id)) as convs")->fetchColumn(),
        'active' => (int)$conn->query("SELECT COUNT(DISTINCT sender_id) + COUNT(DISTINCT receiver_id) FROM messages")->fetchColumn(),
        'read' => (int)$conn->query("SELECT COUNT(*) FROM messages WHERE is_read = 1")->fetchColumn(),
        'unread' => (int)$conn->query("SELECT COUNT(*) FROM messages WHERE is_read = 0")->fetchColumn(),
    ];
}
// --- FIN API AJAX ---

?>

<div class="main-content">
    <div class="dashboard-container">
        <div class="header">
            <h1>Conversations entre utilisateurs</h1>
        </div>

        <?php
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
                    <div class="stat-value"><?= $totalConvs ?></div>
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

        <!-- Switch d'affichage carte/tableau -->
        <div class="view-switch">
            <button class="view-btn active" id="cardViewBtn" type="button"><i class="fas fa-th"></i> Vue carte</button>
            <button class="view-btn" id="tableViewBtn" type="button"><i class="fas fa-table"></i> Vue tableau</button>
        </div>

        <!-- Liste des conversations -->
        <div class="messages-table-container">
            <div class="table-header">
                <h2>Conversations</h2>
                <div class="table-actions">
                    <span class="results-count"><?= $totalConvs ?> conversation(s) trouvée(s)</span>
                    <button id="deleteSelectedBtn" class="btn btn-danger btn-sm" style="display:none; margin-left:15px;" type="button">
                        <i class="fas fa-trash"></i> Supprimer la sélection
                    </button>
                </div>
            </div>

            <!-- Vue carte -->
            <div class="messages-grid" id="cardView">
                <?php foreach ($conversations as $conv): ?>
                    <div class="message-card">
                        <div class="message-header">
                            <div class="message-participants">
                                <div class="participant">
                                    <div class="participant-avatar">
                                        <img src="<?= !empty($conv['users'][$conv['user1']]['profile_picture']) ? '../../uploads/' . $conv['users'][$conv['user1']]['profile_picture'] : '../../uploads/default_profile.jpg' ?>" alt="Avatar">
                                    </div>
                                    <div class="participant-info">
                                        <h3><?= htmlspecialchars($conv['users'][$conv['user1']]['username']) ?></h3>
                                    </div>
                                </div>
                                <div class="message-arrow">
                                    <i class="fas fa-arrows-alt-h"></i>
                                </div>
                                <div class="participant">
                                    <div class="participant-avatar">
                                        <img src="<?= !empty($conv['users'][$conv['user2']]['profile_picture']) ? '../../uploads/' . $conv['users'][$conv['user2']]['profile_picture'] : '../../uploads/default_profile.jpg' ?>" alt="Avatar">
                                    </div>
                                    <div class="participant-info">
                                        <h3><?= htmlspecialchars($conv['users'][$conv['user2']]['username']) ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="conversation-messages">
                            <?php foreach ($conv['messages'] as $msg): ?>
                                <div class="message-row" style="display: flex; align-items: center; justify-content: space-between; margin-bottom:10px;">
                                    <div>
                                        <span class="msg-author" style="font-weight:bold; color:#2563eb;">
                                            <?= htmlspecialchars($conv['users'][$msg['sender_id']]['username']) ?>:
                                        </span>
                                        <?php if ($role === 'Administrateur'): ?>
                                            <span class="msg-text" style="margin-left:8px;">
                                                <a href="#" class="toggle-message-eye" data-msg-id="msg-<?= $msg['id'] ?>" title="Afficher le message"><i class="fas fa-eye"></i> <span style="font-size:12px;">Afficher</span></a>
                                                <span id="msg-<?= $msg['id'] ?>" class="hidden-message" style="display:none; margin-left:8px; color:#374151; background:#f3f4f6; padding:2px 8px; border-radius:6px;"> <?= nl2br(htmlspecialchars($msg['message'])) ?> </span>
                                            </span>
                                        <?php elseif ($role === 'Modérateur'): ?>
                                            <span class="msg-text" style="margin-left:8px; text-decoration:line-through; color:#a1a1aa;"> <?= nl2br(htmlspecialchars($msg['message'])) ?> </span>
                                        <?php else: ?>
                                            <span class="msg-text" style="margin-left:8px;"> <?= nl2br(htmlspecialchars($msg['message'])) ?> </span>
                                        <?php endif; ?>
                                        <span class="msg-date" style="margin-left:12px; color:#64748b; font-size:12px;">
                                            <?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?>
                                        </span>
                                    </div>
                                    <form method="POST" class="delete-form" onsubmit="return confirm('Supprimer ce message ?')">
                                        <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="delete-icon-btn" title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- Vue tableau -->
            <div class="messages-table-view" id="tableView" style="display:none;">
                <table class="messages-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllMessages"></th>
                            <th>Expéditeur</th>
                            <th>Destinataire</th>
                            <th>Message</th>
                            <th><button id="sortDateBtn" onclick="changeSortOrder()" style="background:none;border:none;cursor:pointer;font-weight:700;">Date</button></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conversations as $conv): ?>
                            <tr data-message-id="<?= $conv['user1'] . '-' . $conv['user2'] ?>">
                                <td><input type="checkbox" class="message-checkbox" value="<?= $conv['user1'] . '-' . $conv['user2'] ?>"></td>
                                <td>
                                    <div class="participant">
                                        <div class="participant-avatar">
                                            <img src="<?= !empty($conv['users'][$conv['user1']]['profile_picture']) ? '../../uploads/' . $conv['users'][$conv['user1']]['profile_picture'] : '../../uploads/default_profile.jpg' ?>" alt="Avatar">
                                        </div>
                                        <div class="participant-info">
                                            <h3><?= htmlspecialchars($conv['users'][$conv['user1']]['username']) ?></h3>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="participant">
                                        <div class="participant-avatar">
                                            <img src="<?= !empty($conv['users'][$conv['user2']]['profile_picture']) ? '../../uploads/' . $conv['users'][$conv['user2']]['profile_picture'] : '../../uploads/default_profile.jpg' ?>" alt="Avatar">
                                        </div>
                                        <div class="participant-info">
                                            <h3><?= htmlspecialchars($conv['users'][$conv['user2']]['username']) ?></h3>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="message-content">
                                        <?php if ($role === 'Administrateur'): ?>
                                            <a href="#" class="toggle-message-eye" data-msg-id="table-msg-<?= $conv['messages'][0]['id'] ?>" title="Afficher le message"><i class="fas fa-eye"></i> <span style="font-size:12px;">Afficher</span></a>
                                            <span id="table-msg-<?= $conv['messages'][0]['id'] ?>" class="hidden-message" style="display:none; margin-left:8px; color:#374151; background:#f3f4f6; padding:2px 8px; border-radius:6px;"> <?= nl2br(htmlspecialchars($conv['messages'][0]['message'])) ?> </span>
                                        <?php elseif ($role === 'Modérateur'): ?>
                                            <span style="text-decoration:line-through; color:#a1a1aa;"> <?= nl2br(htmlspecialchars($conv['messages'][0]['message'])) ?> </span>
                                        <?php else: ?>
                                            <span> <?= nl2br(htmlspecialchars($conv['messages'][0]['message'])) ?> </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="msg-date" style="color:#64748b; font-size:12px;">
                                        <?= date('d/m/Y H:i', strtotime($conv['messages'][0]['created_at'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" class="delete-form" onsubmit="return confirm('Supprimer cette conversation ?')">
                                        <input type="hidden" name="message_id" value="<?= $conv['user1'] . '-' . $conv['user2'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="delete-icon-btn" title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
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

<script>
// --- TRI ASC/DESC ---
let sortOrder = localStorage.getItem('messages_sort_order') || (new URLSearchParams(window.location.search).get('sort') || 'desc');
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
    localStorage.setItem('messages_sort_order', sortOrder);
    const params = new URLSearchParams(window.location.search);
    params.set('sort', sortOrder);
    window.location.search = params.toString();
}
document.addEventListener('DOMContentLoaded', updateSortUI);
// --- FIN TRI ---

// --- SUPPRESSION AJAX ---
function deleteMessageAjax(messageId, row) {
    row.classList.add('fade-out');
    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ action: 'delete', message_id: messageId })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            setTimeout(() => {
                row.remove();
                updateStats(res.stats);
                showMessage(res.message, true);
            }, 300);
        } else {
            row.classList.remove('fade-out');
            showMessage(res.message, false);
        }
    })
    .catch(() => {
        row.classList.remove('fade-out');
        showMessage('Erreur lors de la suppression', false);
    });
}
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.delete-form').forEach(form => {
        form.onsubmit = function(e) {
            e.preventDefault();
            if (!confirm('Supprimer ce message ?')) return false;
            const row = form.closest('.message-row');
            const messageId = form.querySelector('input[name="message_id"]').value;
            deleteMessageAjax(messageId, row);
            return false;
        };
    });
});
// --- FIN SUPPRESSION ---

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
    if (stats.active !== undefined) document.querySelectorAll('.stat-value')[1].textContent = stats.active;
    if (stats.read !== undefined) document.querySelectorAll('.stat-value')[2].textContent = stats.read;
    if (stats.unread !== undefined) document.querySelectorAll('.stat-value')[3].textContent = stats.unread;
    document.querySelector('.results-count').textContent = `${stats.total} conversation(s) trouvée(s)`;
}
// --- FIN STATS ---

// --- FADE-IN/FADE-OUT CSS ---
const style = document.createElement('style');
style.innerHTML = `.fade-in { animation: fadeIn .35s; } .fade-out { opacity:0; transform:translateY(-10px); transition:all .3s; } @keyframes fadeIn { from { opacity:0; transform:translateY(-10px);} to {opacity:1;transform:none;} }`;
document.head.appendChild(style);

// Switch d'affichage carte/tableau
const cardViewBtn = document.getElementById('cardViewBtn');
const tableViewBtn = document.getElementById('tableViewBtn');
const cardView = document.getElementById('cardView');
const tableView = document.getElementById('tableView');
const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
function applySavedView() {
    const savedView = localStorage.getItem('messagesViewMode');
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
        localStorage.setItem('messagesViewMode', 'card');
        applySavedView();
    });
    tableViewBtn.addEventListener('click', function() {
        localStorage.setItem('messagesViewMode', 'table');
        applySavedView();
    });
    applySavedView();
}
// Gestion sélection multiple
const selectAllMessages = document.getElementById('selectAllMessages');
function updateDeleteSelectedBtn() {
    const checked = document.querySelectorAll('.message-checkbox:checked');
    deleteSelectedBtn.style.display = (checked.length > 0) ? '' : 'none';
}
if (selectAllMessages) {
    selectAllMessages.addEventListener('change', function() {
        document.querySelectorAll('.message-checkbox').forEach(cb => {
            cb.checked = selectAllMessages.checked;
        });
        updateDeleteSelectedBtn();
    });
}
document.querySelectorAll('.message-checkbox').forEach(cb => {
    cb.addEventListener('change', updateDeleteSelectedBtn);
});
if (deleteSelectedBtn) {
    deleteSelectedBtn.addEventListener('click', function() {
        const checked = Array.from(document.querySelectorAll('.message-checkbox:checked'));
        if (checked.length === 0) return;
        if (!confirm(`Supprimer ${checked.length} message(s) sélectionné(s) ? Cette action est irréversible.`)) return;
        checked.forEach(cb => {
            const messageId = cb.value;
            deleteMessageAjax(messageId);
        });
        if (selectAllMessages) selectAllMessages.checked = false;
        updateDeleteSelectedBtn();
    });
}

// --- TOGGLE MESSAGE EYE ---
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.toggle-message-eye').forEach(function(eye) {
        eye.addEventListener('click', function(e) {
            e.preventDefault();
            var msgId = this.getAttribute('data-msg-id');
            var msgSpan = document.getElementById(msgId);
            if (msgSpan) {
                if (msgSpan.style.display === 'none' || msgSpan.style.display === '') {
                    msgSpan.style.display = 'inline';
                    this.querySelector('span').textContent = 'Masquer';
                    this.querySelector('i').classList.remove('fa-eye');
                    this.querySelector('i').classList.add('fa-eye-slash');
                } else {
                    msgSpan.style.display = 'none';
                    this.querySelector('span').textContent = 'Afficher';
                    this.querySelector('i').classList.remove('fa-eye-slash');
                    this.querySelector('i').classList.add('fa-eye');
                }
            }
        });
    });
});
</script> 