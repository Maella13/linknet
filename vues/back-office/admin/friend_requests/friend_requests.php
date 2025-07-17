<?php
require_once '../menu.php';

// Récupération des relations d'amis avec pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'desc';

$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(u1.username LIKE ? OR u1.email LIKE ? OR u2.username LIKE ? OR u2.email LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}
if (!empty($status_filter)) {
    $whereConditions[] = "f.status = ?";
    $params[] = $status_filter;
}
$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$countStmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM friends f
    JOIN users u1 ON f.sender_id = u1.id
    JOIN users u2 ON f.receiver_id = u2.id
    $whereClause
");
$countStmt->execute($params);
$totalRequests = $countStmt->fetchColumn();
$totalPages = ceil($totalRequests / $limit);

$stmt = $conn->prepare("
    SELECT f.*,
           u1.username as sender_username,
           u1.email as sender_email,
           u1.profile_picture as sender_picture,
           u2.username as receiver_username,
           u2.email as receiver_email,
           u2.profile_picture as receiver_picture
    FROM friends f
    JOIN users u1 ON f.sender_id = u1.id
    JOIN users u2 ON f.receiver_id = u2.id
    $whereClause
    ORDER BY f.created_at " . ($sort === 'asc' ? 'ASC' : 'DESC') . "
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$friendRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        try {
            switch ($action) {
                case 'accept':
                    $requestId = (int)$_POST['request_id'];
                    $updateStmt = $conn->prepare("UPDATE friends SET status = 'accepted' WHERE id = ?");
                    $updateStmt->execute([$requestId]);
                    $message = "Relation acceptée avec succès";
                    break;
                case 'reject':
                    $requestId = (int)$_POST['request_id'];
                    $updateStmt = $conn->prepare("UPDATE friends SET status = 'rejected' WHERE id = ?");
                    $updateStmt->execute([$requestId]);
                    $message = "Relation rejetée avec succès";
                    break;
                case 'delete':
                    $requestId = (int)$_POST['request_id'];
                    $deleteStmt = $conn->prepare("DELETE FROM friends WHERE id = ?");
                    $deleteStmt->execute([$requestId]);
                    $message = "Relation supprimée avec succès";
                    break;
            }
        } catch (PDOException $e) {
            $message = "Erreur lors de l'opération: " . $e->getMessage();
        }
    }
}

// --- API AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Action invalide'];
    try {
        switch ($action) {
            case 'accept':
                $requestId = (int)$_POST['request_id'];
                $updateStmt = $conn->prepare("UPDATE friends SET status = 'accepted' WHERE id = ?");
                $updateStmt->execute([$requestId]);
                $response['success'] = true;
                $response['message'] = "Relation acceptée avec succès";
                $response['stats'] = getStats($conn);
                break;
            case 'reject':
                $requestId = (int)$_POST['request_id'];
                $updateStmt = $conn->prepare("UPDATE friends SET status = 'rejected' WHERE id = ?");
                $updateStmt->execute([$requestId]);
                $response['success'] = true;
                $response['message'] = "Relation rejetée avec succès";
                $response['stats'] = getStats($conn);
                break;
            case 'delete':
                $requestId = (int)$_POST['request_id'];
                $deleteStmt = $conn->prepare("DELETE FROM friends WHERE id = ?");
                $deleteStmt->execute([$requestId]);
                $response['success'] = true;
                $response['message'] = "Relation supprimée avec succès";
                $response['stats'] = getStats($conn);
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
    $statsStmt = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM friends
    ");
    return $statsStmt->fetch(PDO::FETCH_ASSOC);
}
// --- FIN API AJAX ---

$statsStmt = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM friends
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
?>
<link rel="stylesheet" href="/assets/css/back-office/friend_requests.css"> 
<div class="main-content">
    <div class="dashboard-container">
        <div class="header">
            <h1>Gestion des Relations & Demandes d'Amis</h1>
        </div>

        <?php if ($message): ?>
            <div class="alert <?= strpos($message, 'succès') !== false ? 'alert-success' : 'alert-error' ?>">
                <i class="fas <?= strpos($message, 'succès') !== false ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="search-section">
            <div class="search-header">
                <form method="GET" class="search-form">
                    <div class="search-input-group">
                        <input type="text" name="search" placeholder="Rechercher par nom ou email..." value="<?= htmlspecialchars($search) ?>" class="search-input">
                        <select name="status" class="filter-select">
                            <option value="">Tous les statuts</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>En attente</option>
                            <option value="accepted" <?= $status_filter === 'accepted' ? 'selected' : '' ?>>Acceptées</option>
                            <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejetées</option>
                        </select>
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Relations</h3>
                    <div class="stat-value"><?= $stats['total'] ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3>En Attente</h3>
                    <div class="stat-value"><?= $stats['pending'] ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>Acceptées</h3>
                    <div class="stat-value"><?= $stats['accepted'] ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>Rejetées</h3>
                    <div class="stat-value"><?= $stats['rejected'] ?></div>
                </div>
            </div>
        </div>

        <div class="users-table-container">
            <div class="table-header">
                <h2>Liste des Relations & Demandes</h2>
                <div class="table-actions">
                    <span class="results-count"><?= $totalRequests ?> relation(s) trouvée(s)</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Expéditeur</th>
                            <th>Destinataire</th>
                            <th>Statut</th>
                            <th><button id="sortDateBtn" onclick="changeSortOrder()" style="background:none;border:none;cursor:pointer;font-weight:700;">Date</button></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($friendRequests)): ?>
                            <tr><td colspan="5" style="text-align:center;color:#888;">Aucune relation trouvée</td></tr>
                        <?php else: foreach ($friendRequests as $request): ?>
                        <tr>
                            <td>
                                <div class="user-table-info">
                                    <img src="<?= !empty($request['sender_picture']) ? '../../uploads/' . $request['sender_picture'] : '../../uploads/default_profile.jpg' ?>" class="user-avatar-table" alt="avatar">
                                    <div>
                                        <div class="username-table"><?= htmlspecialchars($request['sender_username']) ?></div>
                                        <div class="email-table"><?= htmlspecialchars($request['sender_email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="user-table-info">
                                    <img src="<?= !empty($request['receiver_picture']) ? '../../uploads/' . $request['receiver_picture'] : '../../uploads/default_profile.jpg' ?>" class="user-avatar-table" alt="avatar">
                                    <div>
                                        <div class="username-table"><?= htmlspecialchars($request['receiver_username']) ?></div>
                                        <div class="email-table"><?= htmlspecialchars($request['receiver_email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $request['status'] ?>">
                                    <?php
                                    switch ($request['status']) {
                                        case 'pending':
                                            echo '<i class="fas fa-clock"></i> En attente';
                                            break;
                                        case 'accepted':
                                            echo '<i class="fas fa-check"></i> Acceptée';
                                            break;
                                        case 'rejected':
                                            echo '<i class="fas fa-times"></i> Rejetée';
                                            break;
                                    }
                                    ?>
                                </span>
                            </td>
                            <td>
                                <span class="join-date">
                                    <i class="fas fa-calendar"></i> <?= date('d/m/Y à H:i', strtotime($request['created_at'])) ?>
                                </span>
                            </td>
                            <td>
                                <div class="user-actions">
                                    <?php if ($request['status'] === 'pending'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="accept">
                                            <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                            <button type="submit" class="btn btn-success btn-sm" title="Accepter"><i class="fas fa-check"></i></button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                            <button type="submit" class="btn btn-warning btn-sm" title="Rejeter"><i class="fas fa-times"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Supprimer"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i> Précédent
                        </a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" class="page-link <?= $i === $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" class="page-link">
                            Suivant <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
let sortOrder = localStorage.getItem('friendreq_sort_order') || (new URLSearchParams(window.location.search).get('sort') || 'desc');
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
    localStorage.setItem('friendreq_sort_order', sortOrder);
    const params = new URLSearchParams(window.location.search);
    params.set('sort', sortOrder);
    window.location.search = params.toString();
}
document.addEventListener('DOMContentLoaded', updateSortUI);

function friendRequestAction(action, requestId, row) {
    row.classList.add('fade-out');
    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ action, request_id: requestId })
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
        showMessage('Erreur lors de l\'opération', false);
    });
}
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.data-table tbody tr').forEach(row => {
        row.querySelectorAll('form').forEach(form => {
            form.onsubmit = function(e) {
                e.preventDefault();
                const action = form.querySelector('input[name="action"]').value;
                const requestId = form.querySelector('input[name="request_id"]').value;
                friendRequestAction(action, requestId, row);
                return false;
            };
        });
    });
});
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
function updateStats(stats) {
    if (!stats) return;
    if (stats.total !== undefined) document.querySelectorAll('.stat-value')[0].textContent = stats.total;
    if (stats.pending !== undefined) document.querySelectorAll('.stat-value')[1].textContent = stats.pending;
    if (stats.accepted !== undefined) document.querySelectorAll('.stat-value')[2].textContent = stats.accepted;
    if (stats.rejected !== undefined) document.querySelectorAll('.stat-value')[3].textContent = stats.rejected;
    document.querySelector('.results-count').textContent = `${stats.total} relation(s) trouvée(s)`;
}
const style = document.createElement('style');
style.innerHTML = `.fade-in { animation: fadeIn .35s; } .fade-out { opacity:0; transform:translateY(-10px); transition:all .3s; } @keyframes fadeIn { from { opacity:0; transform:translateY(-10px);} to {opacity:1;transform:none;} }`;
document.head.appendChild(style);
</script>

