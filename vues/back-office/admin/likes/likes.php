<?php
require_once '../menu.php';

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
    LIMIT $limit OFFSET $offset
");
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

// --- API AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Action invalide'];
    try {
        switch ($action) {
            case 'delete':
                if (!isset($_POST['like_id'])) {
                    $response['message'] = "ID manquant";
                    break;
                }
                $likeId = (int)$_POST['like_id'];
                $stmt = $conn->prepare("DELETE FROM likes WHERE id = ?");
                $stmt->execute([$likeId]);
                $response['success'] = true;
                $response['message'] = "Like supprimé avec succès";
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
        'total' => (int)$conn->query("SELECT COUNT(*) FROM likes")->fetchColumn(),
        'active' => (int)$conn->query("SELECT COUNT(DISTINCT user_id) FROM likes")->fetchColumn(),
        'posts' => (int)$conn->query("SELECT COUNT(DISTINCT post_id) FROM likes")->fetchColumn(),
        'avg' => (float)$conn->query("SELECT ROUND(AVG(like_count), 1) FROM (SELECT COUNT(*) as like_count FROM likes GROUP BY post_id) as sub")->fetchColumn() ?: 0,
    ];
}
// --- FIN API AJAX ---
?>

<link rel="stylesheet" href="/assets/css/back-office/likes.css">
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

        <!-- Switch d'affichage carte/tableau -->
        <div class="view-switch">
            <button class="view-btn active" id="cardViewBtn" type="button"><i class="fas fa-th"></i> Vue carte</button>
            <button class="view-btn" id="tableViewBtn" type="button"><i class="fas fa-table"></i> Vue tableau</button>
        </div>

        <!-- Liste des likes -->
        <div class="likes-table-container">
            <div class="table-header">
                <h2>Liste des Likes</h2>
                <div class="table-actions">
                    <span class="results-count"><?= $totalLikes ?> like(s) trouvé(s)</span>
                    <button id="deleteSelectedBtn" class="btn btn-danger btn-sm" style="display:none; margin-left:15px;" type="button">
                        <i class="fas fa-trash"></i> Supprimer la sélection
                    </button>
                </div>
            </div>
            <!-- Vue carte -->
            <div class="likes-grid" id="cardView">
                <?php foreach ($likes as $like): ?>
                    <div class="like-card" data-like-id="<?= $like['id'] ?>">
                        <div class="like-header">
                            <div class="like-user">
                                <div class="user-avatar">
                                    <img src="<?= !empty($like['profile_picture']) ? '../../uploads/' . $like['profile_picture'] : '../../uploads/default_profile.jpg' ?>" 
                                         alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
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
                                <a href="../posts/posts.php?search=<?= urlencode($like['post_content']) ?>" class="view-post-link">
                                    <i class="fas fa-external-link-alt"></i>
                                    Voir le post
                                </a>
                            </div>
                        </div>
                        
                        <div class="like-actions">
                            <button class="btn btn-info btn-sm" onclick="viewLike(<?= $like['id'] ?>)"><i class="fas fa-eye"></i> Voir</button>
                            <button class="btn btn-danger btn-sm" onclick="deleteLikeAjax(<?= $like['id'] ?>)"><i class="fas fa-trash"></i> Supprimer</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- Vue tableau -->
            <div class="likes-table-view" id="tableView" style="display:none;">
                <table class="likes-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllLikes"></th>
                            <th>Utilisateur</th>
                            <th>Post</th>
                            <th><button id="sortDateBtn" onclick="changeSortOrder()" style="background:none;border:none;cursor:pointer;font-weight:700;">Date</button></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($likes as $like): ?>
                            <tr data-like-id="<?= $like['id'] ?>">
                                <td><input type="checkbox" class="like-checkbox" value="<?= $like['id'] ?>"></td>
                                <td>
                                    <div class="user-avatar">
                                        <img src="<?= !empty($like['profile_picture']) ? '../../uploads/' . $like['profile_picture'] : '../../uploads/default_profile.jpg' ?>" 
                                             alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
                                    </div>
                                    <span><?= htmlspecialchars($like['username']) ?></span>
                                </td>
                                <td>
                                    <div class="post-preview">
                                        <p><?= htmlspecialchars(substr($like['post_content'], 0, 150)) ?>...</p>
                                        <a href="../posts/posts.php?search=<?= urlencode($like['post_content']) ?>" class="view-post-link">
                                            <i class="fas fa-external-link-alt"></i>
                                            Voir le post
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    <span class="like-date">
                                        <i class="fas fa-heart"></i>
                                        <?= date('d/m/Y H:i', strtotime($like['created_at'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="like-actions">
                                        <button class="btn btn-info btn-sm" onclick="viewLike(<?= $like['id'] ?>)"><i class="fas fa-eye"></i> Voir</button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteLikeAjax(<?= $like['id'] ?>)"><i class="fas fa-trash"></i> Supprimer</button>
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
let sortOrder = localStorage.getItem('likes_sort_order') || (new URLSearchParams(window.location.search).get('sort') || 'desc');
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
    localStorage.setItem('likes_sort_order', sortOrder);
    const params = new URLSearchParams(window.location.search);
    params.set('sort', sortOrder);
    window.location.search = params.toString();
}
document.addEventListener('DOMContentLoaded', updateSortUI);
// --- FIN TRI ---

// --- SUPPRESSION AJAX ---
function deleteLikeAjax(likeId, card) {
    card.classList.add('fade-out');
    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ action: 'delete', like_id: likeId })
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
    document.querySelectorAll('.like-card form').forEach(form => {
        form.onsubmit = function(e) {
            e.preventDefault();
            if (!confirm('Êtes-vous sûr de vouloir supprimer ce like ?')) return false;
            const card = form.closest('.like-card');
            const likeId = form.querySelector('input[name="like_id"]').value;
            deleteLikeAjax(likeId, card);
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
    if (stats.posts !== undefined) document.querySelectorAll('.stat-value')[2].textContent = stats.posts;
    if (stats.avg !== undefined) document.querySelectorAll('.stat-value')[3].textContent = stats.avg;
    document.querySelector('.results-count').textContent = `${stats.total} like(s) trouvé(s)`;
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
    const savedView = localStorage.getItem('likesViewMode');
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
        localStorage.setItem('likesViewMode', 'card');
        applySavedView();
    });
    tableViewBtn.addEventListener('click', function() {
        localStorage.setItem('likesViewMode', 'table');
        applySavedView();
    });
    applySavedView();
}
// Gestion sélection multiple
const selectAllLikes = document.getElementById('selectAllLikes');
function updateDeleteSelectedBtn() {
    const checked = document.querySelectorAll('.like-checkbox:checked');
    deleteSelectedBtn.style.display = (checked.length > 0) ? '' : 'none';
}
if (selectAllLikes) {
    selectAllLikes.addEventListener('change', function() {
        document.querySelectorAll('.like-checkbox').forEach(cb => {
            cb.checked = selectAllLikes.checked;
        });
        updateDeleteSelectedBtn();
    });
}
document.querySelectorAll('.like-checkbox').forEach(cb => {
    cb.addEventListener('change', updateDeleteSelectedBtn);
});
if (deleteSelectedBtn) {
    deleteSelectedBtn.addEventListener('click', function() {
        const checked = Array.from(document.querySelectorAll('.like-checkbox:checked'));
        if (checked.length === 0) return;
        if (!confirm(`Supprimer ${checked.length} like(s) sélectionné(s) ? Cette action est irréversible.`)) return;
        checked.forEach(cb => {
            const likeId = cb.value;
            deleteLikeAjax(likeId);
        });
        if (selectAllLikes) selectAllLikes.checked = false;
        updateDeleteSelectedBtn();
    });
}
</script> 