<?php
require_once '../menu.php';
echo '<link rel="stylesheet" href="/assets/css/back-office/friends.css">';

// Récupération des amitiés avec pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
// Ajout du tri ASC/DESC
$sort = isset($_GET['sort']) && strtolower($_GET['sort']) === 'asc' ? 'ASC' : 'DESC';

// Construction de la requête avec recherche
$filters = ["f.status = 'accepted'"];
$params = [];
if (!empty($search)) {
    $filters[] = "(u1.username LIKE ? OR u2.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$whereClause = "WHERE " . implode(" AND ", $filters);

// Comptage total
$countStmt = $conn->prepare("
    SELECT COUNT(*) FROM friends f 
    LEFT JOIN users u1 ON f.sender_id = u1.id 
    LEFT JOIN users u2 ON f.receiver_id = u2.id 
    $whereClause
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
    $whereClause
    ORDER BY f.created_at $sort
    LIMIT $limit OFFSET $offset
");
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

// --- API AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Action invalide'];
    try {
        switch ($action) {
            case 'delete':
                if (!isset($_POST['friendship_id'])) {
                    $response['message'] = "ID manquant";
                    break;
                }
                $friendshipId = (int)$_POST['friendship_id'];
                $stmt = $conn->prepare("DELETE FROM friends WHERE id = ?");
                $stmt->execute([$friendshipId]);
                $response['success'] = true;
                $response['message'] = "Amitié supprimée avec succès";
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
        'total' => (int)$conn->query("SELECT COUNT(*) FROM friends WHERE status = 'accepted'")->fetchColumn(),
        'active' => (int)$conn->query("SELECT COUNT(DISTINCT sender_id) + COUNT(DISTINCT receiver_id) FROM friends WHERE status = 'accepted'")->fetchColumn(),
        'pending' => (int)$conn->query("SELECT COUNT(*) FROM friends WHERE status = 'pending'")->fetchColumn(),
        'rejected' => (int)$conn->query("SELECT COUNT(*) FROM friends WHERE status = 'rejected'")->fetchColumn(),
    ];
}
// --- FIN API AJAX ---
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
        <div class="view-switch" style="margin-left:auto;display:flex;gap:10px;">
            <button class="view-btn active" id="cardViewBtn" type="button"><i class="fas fa-th"></i> Vue carte</button>
            <button class="view-btn" id="tableViewBtn" type="button"><i class="fas fa-table"></i> Vue tableau</button>
        </div>
        <!-- Liste des amitiés -->
        <div class="friendships-table-container">
            <div class="table-header">
                <h2>Liste des Amitiés</h2>
                <div class="table-actions">
                    <span class="results-count"><?= $totalFriendships ?> amitié(s) trouvée(s)</span>
                    <button id="deleteSelectedBtn" class="btn btn-danger btn-sm" style="display:none; margin-left:15px;" type="button">
                        <i class="fas fa-trash"></i> Supprimer la sélection
                    </button>
                </div>
            </div>
            <!-- Vue carte -->
            <div class="friendships-grid" id="cardView">
                <?php foreach ($friendships as $friendship): ?>
                    <div class="friendship-card" data-friendship-id="<?= $friendship['id'] ?>">
                        <div class="friendship-header">
                            <div class="friendship-users">
                                <div class="user user1">
                                    <div class="user-avatar">
                                        <img src="<?= !empty($friendship['sender_picture']) ? '../../uploads/' . $friendship['sender_picture'] : '../../uploads/default_profile.jpg' ?>" 
                                             alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
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
                                        <img src="<?= !empty($friendship['receiver_picture']) ? '../../uploads/' . $friendship['receiver_picture'] : '../../uploads/default_profile.jpg' ?>" 
                                             alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
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
            <!-- Vue tableau -->
            <div class="friendships-table-view" id="tableView" style="display:none; margin-top:20px;">
                <table class="friendships-table" style="width:100%; border-collapse:collapse; font-size:14px;">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllFriendships"></th>
                            <th>Demandeur</th>
                            <th>Destinataire</th>
                            <th><button id="sortDateBtn" onclick="changeSortOrder()" style="background:none;border:none;cursor:pointer;font-weight:700;">Date</button></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($friendships as $friendship): ?>
                            <tr data-friendship-id="<?= $friendship['id'] ?>">
                                <td><input type="checkbox" class="friendship-checkbox" value="<?= $friendship['id'] ?>"></td>
                                <td>
                                    <div class="user">
                                        <div class="user-avatar">
                                            <img src="<?= !empty($friendship['sender_picture']) ? '../../uploads/' . $friendship['sender_picture'] : '../../uploads/default_profile.jpg' ?>" alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
                                        </div>
                                        <span><?= htmlspecialchars($friendship['sender_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="user">
                                        <div class="user-avatar">
                                            <img src="<?= !empty($friendship['receiver_picture']) ? '../../uploads/' . $friendship['receiver_picture'] : '../../uploads/default_profile.jpg' ?>" alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
                                        </div>
                                        <span><?= htmlspecialchars($friendship['receiver_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="friendship-date">
                                        <i class="fas fa-calendar"></i>
                                        <?= date('d/m/Y', strtotime($friendship['created_at'])) ?>
                                    </span>
                                </td>
                                <td>
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
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
let sortOrder = localStorage.getItem('friends_sort_order') || (new URLSearchParams(window.location.search).get('sort') || 'desc');
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
    localStorage.setItem('friends_sort_order', sortOrder);
    const params = new URLSearchParams(window.location.search);
    params.set('sort', sortOrder);
    window.location.search = params.toString();
}
document.addEventListener('DOMContentLoaded', updateSortUI);
// --- FIN TRI ---

// --- SUPPRESSION AJAX ---
function deleteFriendshipAjax(friendshipId, card) {
    card.classList.add('fade-out');
    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ action: 'delete', friendship_id: friendshipId })
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
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.friendship-card form').forEach(form => {
        form.onsubmit = function(e) {
            e.preventDefault();
            if (!confirm('Êtes-vous sûr de vouloir supprimer cette amitié ?')) return false;
            const card = form.closest('.friendship-card');
            const friendshipId = form.querySelector('input[name="friendship_id"]').value;
            deleteFriendshipAjax(friendshipId, card);
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
    if (stats.pending !== undefined) document.querySelectorAll('.stat-value')[2].textContent = stats.pending;
    if (stats.rejected !== undefined) document.querySelectorAll('.stat-value')[3].textContent = stats.rejected;
    document.querySelector('.results-count').textContent = `${stats.total} amitié(s) trouvée(s)`;
}
// --- FIN STATS ---

// --- FADE-IN/FADE-OUT CSS ---
const style = document.createElement('style');
style.innerHTML = `.fade-in { animation: fadeIn .35s; } .fade-out { opacity:0; transform:translateY(-10px); transition:all .3s; } @keyframes fadeIn { from { opacity:0; transform:translateY(-10px);} to {opacity:1;transform:none;} }`;
document.head.appendChild(style);

// --- SWITCH VUE CARTE / TABLEAU ---
const cardViewBtn = document.getElementById('cardViewBtn');
const tableViewBtn = document.getElementById('tableViewBtn');
const cardView = document.getElementById('cardView');
const tableView = document.getElementById('tableView');
const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
function applySavedView() {
    const savedView = localStorage.getItem('friendsViewMode');
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
        localStorage.setItem('friendsViewMode', 'card');
        applySavedView();
    });
    tableViewBtn.addEventListener('click', function() {
        localStorage.setItem('friendsViewMode', 'table');
        applySavedView();
    });
    applySavedView();
}
// --- GESTION SÉLECTION MULTIPLE ---
const selectAllFriendships = document.getElementById('selectAllFriendships');
function updateDeleteSelectedBtn() {
    const checked = document.querySelectorAll('.friendship-checkbox:checked');
    deleteSelectedBtn.style.display = (checked.length > 0) ? '' : 'none';
}
if (selectAllFriendships) {
    selectAllFriendships.addEventListener('change', function() {
        document.querySelectorAll('.friendship-checkbox').forEach(cb => {
            cb.checked = selectAllFriendships.checked;
        });
        updateDeleteSelectedBtn();
    });
}
document.querySelectorAll('.friendship-checkbox').forEach(cb => {
    cb.addEventListener('change', updateDeleteSelectedBtn);
});
if (deleteSelectedBtn) {
    deleteSelectedBtn.addEventListener('click', function() {
        const checked = Array.from(document.querySelectorAll('.friendship-checkbox:checked'));
        if (checked.length === 0) return;
        if (!confirm(`Supprimer ${checked.length} amitié(s) sélectionnée(s) ? Cette action est irréversible.`)) return;
        checked.forEach(cb => {
            const friendshipId = cb.value;
            const row = document.querySelector(`tr[data-friendship-id='${friendshipId}']`);
            const card = document.querySelector(`.friendship-card[data-friendship-id='${friendshipId}']`);
            if (row) deleteFriendshipAjax(friendshipId, row);
            if (card) deleteFriendshipAjax(friendshipId, card);
        });
        if (selectAllFriendships) selectAllFriendships.checked = false;
        updateDeleteSelectedBtn();
    });
}
// --- FIN SWITCH ---
</script> 