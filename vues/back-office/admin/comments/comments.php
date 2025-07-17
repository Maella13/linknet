<?php
require_once '../menu.php';

// Récupération des commentaires avec pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Ajout du paramètre de tri
$order = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC';

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
    ORDER BY c.created_at $order
    LIMIT $limit OFFSET $offset
");
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
<link rel="stylesheet" href="/assets/css/back-office/comments.css">
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
        <div class="view-switch">
            <button class="view-btn active" id="cardViewBtn" type="button"><i class="fas fa-th"></i> Vue carte</button>
            <button class="view-btn" id="tableViewBtn" type="button"><i class="fas fa-table"></i> Vue tableau</button>
        </div>
        <div class="comments-table-container">
            <div class="table-header">
                <h2>Liste des Commentaires</h2>
                <div class="table-actions">
                    <span class="results-count"><?= $totalComments ?> commentaire(s) trouvé(s)</span>
                    <button id="deleteSelectedBtn" class="btn btn-danger btn-sm" style="display:none; margin-left:15px;" type="button">
                        <i class="fas fa-trash"></i> Supprimer la sélection
                    </button>
                </div>
            </div>
            <!-- Vue carte -->
            <div class="comments-grid" id="cardView">
                <?php foreach ($comments as $comment): ?>
                    <div class="comment-card" data-comment-id="<?= $comment['id'] ?>">
                        <div class="comment-header">
                            <div class="comment-author">
                                <div class="author-avatar">
                                    <img src="<?= !empty($comment['profile_picture']) ? '../../uploads/' . $comment['profile_picture'] : '../../uploads/default_profile.jpg' ?>" 
                                         alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
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
                                <a href="../posts/posts.php?search=<?= urlencode($comment['post_content']) ?>" class="view-post-link">
                                    <i class="fas fa-external-link-alt"></i>
                                    Voir le post
                                </a>
                            </div>
                        </div>
                        
                        <div class="comment-actions">
                            <button class="btn btn-info btn-sm" onclick="viewComment(<?= $comment['id'] ?>)"><i class="fas fa-eye"></i> Voir</button>
                            <button class="btn btn-danger btn-sm" onclick="deleteCommentAjax(<?= $comment['id'] ?>)"><i class="fas fa-trash"></i> Supprimer</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- Vue tableau -->
            <div class="comments-table-view" id="tableView" style="display:none;">
                <table class="comments-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllComments"></th>
                            <th>Utilisateur</th>
                            <th>Commentaire</th>
                            <th>Post</th>
                            <th><button id="sortDateBtn" onclick="changeSortOrder()" style="background:none;border:none;cursor:pointer;font-weight:700;">Date</button></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comments as $comment): ?>
                            <tr data-comment-id="<?= $comment['id'] ?>">
                                <td><input type="checkbox" class="comment-checkbox" value="<?= $comment['id'] ?>"></td>
                                <td>
                                    <div class="comment-author">
                                        <div class="author-avatar">
                                            <img src="<?= !empty($comment['profile_picture']) ? '../../uploads/' . $comment['profile_picture'] : '../../uploads/default_profile.jpg' ?>" 
                                                 alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
                                        </div>
                                        <div class="author-info">
                                            <h3><?= htmlspecialchars($comment['username']) ?></h3>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="comment-content">
                                        <p><?= nl2br(htmlspecialchars($comment['comment_text'])) ?></p>
                                    </div>
                                </td>
                                <td>
                                    <div class="post-context">
                                        <h4>Post original :</h4>
                                        <div class="post-preview">
                                            <p><?= htmlspecialchars(substr($comment['post_content'], 0, 100)) ?>...</p>
                                            <a href="../posts/posts.php?search=<?= urlencode($comment['post_content']) ?>" class="view-post-link">
                                                <i class="fas fa-external-link-alt"></i>
                                                Voir le post
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="comment-date">
                                        <i class="fas fa-calendar"></i>
                                        <?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="comment-actions">
                                        <button class="btn btn-info btn-sm" onclick="viewComment(<?= $comment['id'] ?>)"><i class="fas fa-eye"></i> Voir</button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteCommentAjax(<?= $comment['id'] ?>)"><i class="fas fa-trash"></i> Supprimer</button>
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
// Suppression AJAX d'un commentaire
function deleteCommentAjax(commentId) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ?')) return;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('comment_id', commentId);
    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.text())
    .then(html => {
        // Retirer la carte avec animation fade-out
        const card = document.querySelector(`.comment-card[data-comment-id="${commentId}"]`);
        if (card) {
            card.classList.add('fade-out');
            setTimeout(() => card.remove(), 300);
        }
        updateCommentCount();
        // Extraire le message de la réponse
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const alertElement = doc.querySelector('.alert');
        if (alertElement) {
            const message = alertElement.textContent.trim();
            showMessage(message, true);
        }
    })
    .catch(error => {
        showMessage('Erreur lors de la suppression du commentaire', false);
        console.error('Erreur:', error);
    });
}
// Mise à jour du compteur de commentaires
function updateCommentCount() {
    const commentCards = document.querySelectorAll('.comment-card');
    const totalComments = commentCards.length;
    // Mettre à jour les statistiques
    const statValues = document.querySelectorAll('.stat-value');
    if (statValues.length >= 1) {
        statValues[0].textContent = totalComments; // Total commentaires
    }
    // Mettre à jour le compteur de résultats
    const resultsCount = document.querySelector('.results-count');
    if (resultsCount) {
        resultsCount.textContent = `${totalComments} commentaire(s) trouvé(s)`;
    }
}
// Affichage des messages
function showMessage(message, isSuccess) {
    // Supprimer les anciens messages
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    // Créer le nouveau message
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert ${isSuccess ? 'alert-success' : 'alert-error'}`;
    alertDiv.innerHTML = `
        <i class="fas ${isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        ${message}
    `;
    // Insérer le message après le header
    const header = document.querySelector('.header');
    header.parentNode.insertBefore(alertDiv, header.nextSibling);
    // Auto-hide après 3 secondes
    setTimeout(() => {
        alertDiv.style.opacity = '0';
        setTimeout(() => alertDiv.remove(), 300);
    }, 3000);
}
let sortOrder = localStorage.getItem('comments_sort_order') || (new URLSearchParams(window.location.search).get('order') || 'desc');
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
    localStorage.setItem('comments_sort_order', sortOrder);
    const params = new URLSearchParams(window.location.search);
    params.set('order', sortOrder);
    window.location.search = params.toString();
}
document.addEventListener('DOMContentLoaded', updateSortUI);
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

// Switch d'affichage carte/tableau
const cardViewBtn = document.getElementById('cardViewBtn');
const tableViewBtn = document.getElementById('tableViewBtn');
const cardView = document.getElementById('cardView');
const tableView = document.getElementById('tableView');
const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
function applySavedView() {
    const savedView = localStorage.getItem('commentsViewMode');
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
        localStorage.setItem('commentsViewMode', 'card');
        applySavedView();
    });
    tableViewBtn.addEventListener('click', function() {
        localStorage.setItem('commentsViewMode', 'table');
        applySavedView();
    });
    applySavedView();
}
// Gestion sélection multiple
const selectAllComments = document.getElementById('selectAllComments');
function updateDeleteSelectedBtn() {
    const checked = document.querySelectorAll('.comment-checkbox:checked');
    deleteSelectedBtn.style.display = (checked.length > 0) ? '' : 'none';
}
if (selectAllComments) {
    selectAllComments.addEventListener('change', function() {
        document.querySelectorAll('.comment-checkbox').forEach(cb => {
            cb.checked = selectAllComments.checked;
        });
        updateDeleteSelectedBtn();
    });
}
document.querySelectorAll('.comment-checkbox').forEach(cb => {
    cb.addEventListener('change', updateDeleteSelectedBtn);
});
if (deleteSelectedBtn) {
    deleteSelectedBtn.addEventListener('click', function() {
        const checked = Array.from(document.querySelectorAll('.comment-checkbox:checked'));
        if (checked.length === 0) return;
        if (!confirm(`Supprimer ${checked.length} commentaire(s) sélectionné(s) ? Cette action est irréversible.`)) return;
        checked.forEach(cb => {
            const commentId = cb.value;
            deleteCommentAjax(commentId);
        });
        if (selectAllComments) selectAllComments.checked = false;
        updateDeleteSelectedBtn();
    });
}
</script> 