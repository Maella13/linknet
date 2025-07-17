<?php
require_once '../menu.php';

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
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$followers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement des actions
$message = '';
// --- API AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Action invalide'];
    try {
        switch ($action) {
            case 'delete':
                if (!isset($_POST['follower_id'])) {
                    $response['message'] = "ID manquant";
                    break;
                }
                $followerId = (int)$_POST['follower_id'];
                $stmt = $conn->prepare("DELETE FROM followers WHERE id = ?");
                $stmt->execute([$followerId]);
                $response['success'] = true;
                $response['message'] = "Abonnement supprimé avec succès";
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
        'total' => (int)$conn->query("SELECT COUNT(*) FROM followers")->fetchColumn(),
        'followed' => (int)$conn->query("SELECT COUNT(DISTINCT user_id) FROM followers")->fetchColumn(),
        'active' => (int)$conn->query("SELECT COUNT(DISTINCT follower_id) FROM followers")->fetchColumn(),
        'avg' => (float)$conn->query("SELECT ROUND(AVG(follower_count), 1) FROM (SELECT COUNT(*) as follower_count FROM followers GROUP BY user_id) as sub")->fetchColumn() ?: 0,
    ];
}
// --- FIN API AJAX ---
?>
<link rel="stylesheet" href="/assets/css/back-office/followers.css">

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
        <div class="view-switch">
            <button class="view-btn active" id="cardViewBtn" type="button"><i class="fas fa-th"></i> Vue carte</button>
            <button class="view-btn" id="tableViewBtn" type="button"><i class="fas fa-table"></i> Vue tableau</button>
        </div>
        <div class="followers-table-container">
            <div class="table-header">
                <h2>Liste des Abonnés</h2>
                <div class="table-actions">
                    <span class="results-count"><?= $totalFollowers ?> abonné(s) trouvé(s)</span>
                    <button id="deleteSelectedBtn" class="btn btn-danger btn-sm" style="display:none; margin-left:15px;" type="button">
                        <i class="fas fa-trash"></i> Supprimer la sélection
                    </button>
                </div>
            </div>
            <!-- Vue carte -->
            <div class="followers-grid" id="cardView">
                <?php foreach ($followers as $follower): ?>
                <div class="follower-card" data-follower-id="<?= $follower['id'] ?>">
                    <div class="follower-header">
                        <div class="follower-relationship">
                            <div class="user followed">
                                <div class="user-avatar">
                                    <img src="<?= !empty($follower['user_picture']) ? '../../uploads/' . $follower['user_picture'] : '../../uploads/default_profile.jpg' ?>" 
                                         alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
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
                                    <img src="<?= !empty($follower['follower_picture']) ? '../../uploads/' . $follower['follower_picture'] : '../../uploads/default_profile.jpg' ?>" 
                                         alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
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
            <!-- Vue tableau -->
            <div class="followers-table-view" id="tableView" style="display:none;">
                <table class="followers-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllFollowers"></th>
                            <th>Utilisateur</th>
                            <th>Abonné</th>
                            <th><button id="sortDateBtn" onclick="changeSortOrder()" style="background:none;border:none;cursor:pointer;font-weight:700;">Date d'abonnement</button></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($followers as $follower): ?>
                        <tr data-follower-id="<?= $follower['id'] ?>">
                            <td><input type="checkbox" class="follower-checkbox" value="<?= $follower['id'] ?>"></td>
                            <td>
                                <div class="user">
                                    <div class="user-avatar">
                                        <img src="<?= !empty($follower['user_picture']) ? '../../uploads/' . $follower['user_picture'] : '../../uploads/default_profile.jpg' ?>" 
                                             alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
                                    </div>
                                    <div class="user-info">
                                        <h3><?= htmlspecialchars($follower['user_name']) ?></h3>
                                        <span class="user-role">Suivi</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="user">
                                    <div class="user-avatar">
                                        <img src="<?= !empty($follower['follower_picture']) ? '../../uploads/' . $follower['follower_picture'] : '../../uploads/default_profile.jpg' ?>" 
                                             alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
                                    </div>
                                    <div class="user-info">
                                        <h3><?= htmlspecialchars($follower['follower_name']) ?></h3>
                                        <span class="user-role">Abonné</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="follower-date">
                                    <i class="fas fa-calendar"></i>
                                    <?= date('d/m/Y', strtotime($follower['created_at'])) ?>
                                </span>
                            </td>
                            <td>
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
let sortOrder = localStorage.getItem('followers_sort_order') || (new URLSearchParams(window.location.search).get('sort') || 'desc');
function updateSortUI() {
    const sortBtn = document.getElementById('sortDateBtn');
    if (sortBtn) {
        sortBtn.innerHTML = sortOrder === 'asc'
            ? '<i class="fas fa-sort-amount-up-alt"></i> Date d\'abonnement <span style="font-size:12px">(ASC)</span>'
            : '<i class="fas fa-sort-amount-down-alt"></i> Date d\'abonnement <span style="font-size:12px">(DESC)</span>';
    }
}
function changeSortOrder() {
    sortOrder = sortOrder === 'asc' ? 'desc' : 'asc';
    localStorage.setItem('followers_sort_order', sortOrder);
    const params = new URLSearchParams(window.location.search);
    params.set('sort', sortOrder);
    window.location.search = params.toString();
}
document.addEventListener('DOMContentLoaded', updateSortUI);
// --- FIN TRI ---

// --- SUPPRESSION AJAX ---
function deleteFollowerAjax(followerId, card) {
    card.classList.add('fade-out');
    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ action: 'delete', follower_id: followerId })
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
    document.querySelectorAll('.follower-card form').forEach(form => {
        form.onsubmit = function(e) {
            e.preventDefault();
            if (!confirm('Êtes-vous sûr de vouloir supprimer cet abonnement ?')) return false;
            const card = form.closest('.follower-card');
            const followerId = form.querySelector('input[name="follower_id"]').value;
            deleteFollowerAjax(followerId, card);
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
    if (stats.followed !== undefined) document.querySelectorAll('.stat-value')[1].textContent = stats.followed;
    if (stats.active !== undefined) document.querySelectorAll('.stat-value')[2].textContent = stats.active;
    if (stats.avg !== undefined) document.querySelectorAll('.stat-value')[3].textContent = stats.avg;
    document.querySelector('.results-count').textContent = `${stats.total} abonnement(s) trouvé(s)`;
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
    const savedView = localStorage.getItem('followersViewMode');
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
        localStorage.setItem('followersViewMode', 'card');
        applySavedView();
    });
    tableViewBtn.addEventListener('click', function() {
        localStorage.setItem('followersViewMode', 'table');
        applySavedView();
    });
    applySavedView();
}
// Gestion sélection multiple
const selectAllFollowers = document.getElementById('selectAllFollowers');
function updateDeleteSelectedBtn() {
    const checked = document.querySelectorAll('.follower-checkbox:checked');
    deleteSelectedBtn.style.display = (checked.length > 0) ? '' : 'none';
}
if (selectAllFollowers) {
    selectAllFollowers.addEventListener('change', function() {
        document.querySelectorAll('.follower-checkbox').forEach(cb => {
            cb.checked = selectAllFollowers.checked;
        });
        updateDeleteSelectedBtn();
    });
}
document.querySelectorAll('.follower-checkbox').forEach(cb => {
    cb.addEventListener('change', updateDeleteSelectedBtn);
});
if (deleteSelectedBtn) {
    deleteSelectedBtn.addEventListener('click', function() {
        const checked = Array.from(document.querySelectorAll('.follower-checkbox:checked'));
        if (checked.length === 0) return;
        if (!confirm(`Supprimer ${checked.length} abonné(s) sélectionné(s) ? Cette action est irréversible.`)) return;
        checked.forEach(cb => {
            const followerId = cb.value;
            deleteFollowerAjax(followerId);
        });
        if (selectAllFollowers) selectAllFollowers.checked = false;
        updateDeleteSelectedBtn();
    });
}
</script> 