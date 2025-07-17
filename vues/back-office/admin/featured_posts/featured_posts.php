<?php
require_once "../menu.php";

// Gestion des actions POST
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action) {
        try {
            switch ($action) {
                case 'add_featured':
                    if ($role === 'Administrateur') {
                        $post_id = (int)($_POST['post_id'] ?? 0);
                        $checkStmt = $conn->prepare("SELECT id FROM posts WHERE id = ?");
                        $checkStmt->execute([$post_id]);
                        if (!$checkStmt->fetch()) {
                            $message = "Post introuvable";
                            break;
                        }
                        $checkStmt = $conn->prepare("SELECT id FROM featured_posts WHERE post_id = ?");
                        $checkStmt->execute([$post_id]);
                        if ($checkStmt->fetch()) {
                            $message = "Ce post est déjà en vedette";
                            break;
                        }
                        $insertStmt = $conn->prepare("INSERT INTO featured_posts (post_id, created_at) VALUES (?, NOW())");
                        if ($insertStmt->execute([$post_id])) {
                            $message = "Post ajouté aux vedettes avec succès";
                        } else {
                            $message = "Erreur lors de l'ajout";
                        }
                    } else {
                        $message = "Vous n'avez pas les permissions pour ajouter un post en vedette";
                    }
                    break;
                case 'delete':
                    if (!isset($_POST['featured_id'])) {
                        $message = "ID manquant";
                        break;
                    }
                    $featuredId = (int)$_POST['featured_id'];
                    if ($role === 'Administrateur' || $role === 'Modérateur') {
                        $stmt = $conn->prepare("DELETE FROM featured_posts WHERE id = ?");
                        $stmt->execute([$featuredId]);
                        $message = "Post retiré des vedettes avec succès";
                    } else {
                        $message = "Vous n'avez pas les permissions pour retirer un post en vedette";
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
$limit = 12;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$whereClause = '';
$params = [];
if (!empty($search)) {
    $whereClause = "WHERE p.content LIKE ? OR u.username LIKE ?";
    $params = ["%$search%", "%$search%"];
}

$countQuery = "SELECT COUNT(*) FROM featured_posts fp JOIN posts p ON fp.post_id = p.id JOIN users u ON p.user_id = u.id $whereClause";
$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $limit);

$query = "SELECT fp.id as featured_id, fp.post_id, fp.created_at as featured_at,
                 p.content, p.media, p.created_at as post_at,
                 u.username, u.profile_picture,
                 (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count,
                 (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count
          FROM featured_posts fp 
          JOIN posts p ON fp.post_id = p.id 
          JOIN users u ON p.user_id = u.id 
          $whereClause
          ORDER BY fp.created_at DESC 
          LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$featured = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération de tous les posts pour le select d'ajout
$postsQuery = "SELECT p.id, p.content, u.username 
               FROM posts p 
               JOIN users u ON p.user_id = u.id 
               WHERE p.id NOT IN (SELECT post_id FROM featured_posts)
               ORDER BY p.created_at DESC 
               LIMIT 100";
$postsList = $conn->query($postsQuery)->fetchAll(PDO::FETCH_ASSOC);
?>
<link rel="stylesheet" href="/assets/css/back-office/featured_posts.css">
<div class="main-content">
    <div class="dashboard-container">
        <div class="header">
            <h1>Gestion des Posts en Vedette</h1>
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
                        <input type="text" name="search" placeholder="Rechercher par contenu ou auteur..." 
                               value="<?= htmlspecialchars($search) ?>" class="search-input">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <?php if ($role === 'Administrateur'): ?>
                    <button class="btn btn-primary" onclick="showAddFeaturedModal()">
                        <i class="fas fa-plus"></i>
                        Ajouter un post en vedette
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Posts en Vedette</h3>
                    <div class="stat-value"><?= $total ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-pen-nib"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Posts</h3>
                    <div class="stat-value">
                        <?php
                        $postsStmt = $conn->query("SELECT COUNT(*) FROM posts");
                        echo $postsStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-comment-dots"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Commentaires</h3>
                    <div class="stat-value">
                        <?php
                        $commentsStmt = $conn->query("SELECT COUNT(*) FROM comments");
                        echo $commentsStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Likes</h3>
                    <div class="stat-value">
                        <?php
                        $likesStmt = $conn->query("SELECT COUNT(*) FROM likes");
                        echo $likesStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="view-switch" style="margin-left:auto;display:flex;gap:10px;">
                    <button class="view-btn" id="cardViewBtn" type="button"><i class="fas fa-th"></i> Vue carte</button>
                    <button class="view-btn" id="tableViewBtn" type="button"><i class="fas fa-table"></i> Vue tableau</button>
                </div>
        <!-- Liste des posts en vedette -->
        <div class="featured-table-container">
            <div class="table-header">
                <h2>Posts en Vedette</h2>
            </div>
                <div class="table-actions">
                    <span class="results-count"><?= $total ?> post(s) en vedette trouvé(s)</span>
                <button id="deleteSelectedBtn" class="btn btn-danger btn-sm" style="display:none; margin-left:15px;" type="button">
                    <i class="fas fa-trash"></i> Supprimer la sélection
                </button>
                </div>
            <!-- Vue carte -->
            <div class="featured-grid" id="cardView">
                <?php foreach ($featured as $f): ?>
                    <div class="post-card" data-featured-id="<?= $f['featured_id'] ?>">
                        <div class="post-header">
                            <div class="post-avatar">
                                <img src="<?= !empty($f['profile_picture']) ? '../../uploads/' . $f['profile_picture'] : '../../uploads/default_profile.jpg' ?>" 
                                     alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
                            </div>
                            <div class="post-info">
                                <h3><?= htmlspecialchars($f['username']) ?></h3>
                                <p class="post-date">Ajouté le <?= date('d/m/Y H:i', strtotime($f['featured_at'])) ?></p>
                            </div>
                        </div>
                        
                        <div class="post-content">
                            <p><?= nl2br(htmlspecialchars($f['content'])) ?></p>
                            <?php if (!empty($f['media'])): ?>
                                <div class="post-media">
                                    <?php 
                                    $file_extension = strtolower(pathinfo($f['media'], PATHINFO_EXTENSION));
                                    $is_video = in_array($file_extension, ['mp4', 'avi', 'mov', 'wmv']);
                                    ?>
                                    <?php if ($is_video): ?>
                                        <div class="video-container">
                                            <video controls class="media-content">
                                                <source src="<?= htmlspecialchars($f['media']) ?>" type="video/mp4">
                                                Votre navigateur ne supporte pas la lecture de vidéos.
                                            </video>
                                            <div class="media-overlay">
                                                <i class="fas fa-play-circle"></i>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="image-container">
                                            <img src="<?= htmlspecialchars($f['media']) ?>" alt="Media" class="media-content" onerror="this.style.display='none'">
                                            <div class="media-overlay">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="post-stats">
                            <div class="stat-item">
                                <i class="fas fa-comment"></i>
                                <span><?= $f['comments_count'] ?> commentaires</span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-heart"></i>
                                <span><?= $f['likes_count'] ?> likes</span>
                            </div>
                        </div>
                        
                        <div class="post-actions">
                            <button class="btn btn-info btn-sm" onclick="viewFeaturedPost(<?= $f['post_id'] ?>)">
                                <i class="fas fa-eye"></i>
                                Voir
                            </button>
                            
                            <?php if ($role === 'Administrateur' || $role === 'Modérateur'): ?>
                                <button type="button" class="btn btn-danger btn-sm" onclick="deleteFeatured(<?= $f['featured_id'] ?>, '<?= htmlspecialchars($f['username']) ?>')">
                                    <i class="fas fa-trash"></i>
                                    Retirer
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- Vue tableau -->
            <div class="featured-table-view" id="tableView" style="display:none;">
                <table class="featured-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllFeatured"></th>
                            <th>Utilisateur</th>
                            <th>Contenu</th>
                            <th>Commentaires</th>
                            <th>Likes</th>
                            <th><button id="sortDateBtn" onclick="changeSortOrder()" style="background:none;border:none;cursor:pointer;font-weight:700;">Date d'ajout</button></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($featured as $f): ?>
                        <tr data-featured-id="<?= $f['featured_id'] ?>">
                            <td><input type="checkbox" class="featured-checkbox" value="<?= $f['featured_id'] ?>"></td>
                            <td>
                                <div class="post-avatar">
                                    <img src="<?= !empty($f['profile_picture']) ? '../../uploads/' . $f['profile_picture'] : '../../uploads/default_profile.jpg' ?>" 
                                         alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
                                </div>
                                <span><?= htmlspecialchars($f['username']) ?></span>
                            </td>
                            <td>
                                <p><?= nl2br(htmlspecialchars($f['content'])) ?></p>
                                <?php if (!empty($f['media'])): ?>
                                    <div class="post-media">
                                        <?php 
                                        $file_extension = strtolower(pathinfo($f['media'], PATHINFO_EXTENSION));
                                        $is_video = in_array($file_extension, ['mp4', 'avi', 'mov', 'wmv']);
                                        ?>
                                        <?php if ($is_video): ?>
                                            <div class="video-container">
                                                <video controls class="media-content">
                                                    <source src="<?= htmlspecialchars($f['media']) ?>" type="video/mp4">
                                                    Votre navigateur ne supporte pas la lecture de vidéos.
                                                </video>
                                                <div class="media-overlay">
                                                    <i class="fas fa-play-circle"></i>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="image-container">
                                                <img src="<?= htmlspecialchars($f['media']) ?>" alt="Media" class="media-content" onerror="this.style.display='none'">
                                                <div class="media-overlay">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= $f['comments_count'] ?></td>
                            <td><?= $f['likes_count'] ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($f['featured_at'])) ?></td>
                            <td>
                                <div class="post-actions">
                                    <button class="btn btn-info btn-sm" onclick="viewFeaturedPost(<?= $f['post_id'] ?>)">
                                        <i class="fas fa-eye"></i>
                                        Voir
                                    </button>
                                    
                                    <?php if ($role === 'Administrateur' || $role === 'Modérateur'): ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteFeatured(<?= $f['featured_id'] ?>, '<?= htmlspecialchars($f['username']) ?>')">
                                            <i class="fas fa-trash"></i>
                                            Retirer
                                        </button>
                                    <?php endif; ?>
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
                            <i class="fas fa-chevron-left"></i>
                            Précédent
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
                            Suivant
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal d'ajout de post en vedette -->
<?php if ($role === 'Administrateur'): ?>
<div id="addFeaturedModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Ajouter un Post en Vedette</h2>
            <span class="close" onclick="closeAddFeaturedModal()">&times;</span>
        </div>
        
        <form id="addFeaturedForm" method="POST">
            <input type="hidden" name="action" value="add_featured">
            
            <div class="form-group">
                <label for="post_id">Sélectionner un post *</label>
                <select name="post_id" id="post_id" required>
                    <option value="">Sélectionner un post</option>
                    <?php foreach ($postsList as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars(substr($p['content'], 0, 60)) ?>... (<?= htmlspecialchars($p['username']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <small class="form-help">Choisissez un post à mettre en vedette</small>
            </div>
            
            <div class="form-actions">
                <button type="button" onclick="closeAddFeaturedModal()" class="btn btn-secondary">Annuler</button>
                <button type="submit" class="btn btn-primary">Ajouter</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modal de détail du post en vedette -->
<div id="featuredPostDetailModal" class="modal">
    <div class="modal-content large">
        <div class="modal-header">
            <h2>Détail du post en vedette</h2>
            <span class="close" onclick="closeFeaturedPostDetailModal()">&times;</span>
        </div>
        <div id="featuredPostDetailContent">
            <!-- Le contenu sera chargé dynamiquement -->
        </div>
    </div>
</div>

<script>
// Fonctions pour les modals
function showAddFeaturedModal() {
    document.getElementById('addFeaturedModal').style.display = 'block';
}

function closeAddFeaturedModal() {
    document.getElementById('addFeaturedModal').style.display = 'none';
    document.getElementById('addFeaturedForm').reset();
}

function closeFeaturedPostDetailModal() {
    document.getElementById('featuredPostDetailModal').style.display = 'none';
}

// Fonction pour voir les détails d'un post
function viewFeaturedPost(postId) {
    fetch('featured_post_detail.php?id=' + postId)
        .then(response => response.text())
        .then(html => {
            document.getElementById('featuredPostDetailContent').innerHTML = html;
            document.getElementById('featuredPostDetailModal').style.display = 'block';
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors du chargement des détails du post en vedette');
        });
}

// --- TRI ASC/DESC ---
let sortOrder = localStorage.getItem('featured_sort_order') || (new URLSearchParams(window.location.search).get('sort') || 'desc');
function updateSortUI() {
    const sortBtn = document.getElementById('sortDateBtn');
    if (sortBtn) {
        sortBtn.innerHTML = sortOrder === 'asc'
            ? '<i class="fas fa-sort-amount-up-alt"></i> Date d\'ajout <span style="font-size:12px">(ASC)</span>'
            : '<i class="fas fa-sort-amount-down-alt"></i> Date d\'ajout <span style="font-size:12px">(DESC)</span>';
    }
}
function changeSortOrder() {
    sortOrder = sortOrder === 'asc' ? 'desc' : 'asc';
    localStorage.setItem('featured_sort_order', sortOrder);
    const params = new URLSearchParams(window.location.search);
    params.set('sort', sortOrder);
    window.location.search = params.toString();
}
document.addEventListener('DOMContentLoaded', updateSortUI);
// --- FIN TRI ---

// --- SUPPRESSION AJAX ---
function deleteFeatured(featuredId, username) {
    if (!confirm(`Êtes-vous sûr de vouloir retirer le post de ${username} des vedettes ?`)) return;
    const card = document.querySelector(`.post-card[data-featured-id='${featuredId}']`);
    if (!card) return;
    card.classList.add('fade-out');
    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ action: 'delete', featured_id: featuredId })
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
// --- FIN SUPPRESSION ---

// --- AJOUT AJAX ---
document.addEventListener('DOMContentLoaded', function() {
    const addFeaturedForm = document.getElementById('addFeaturedForm');
    if (addFeaturedForm) {
        addFeaturedForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const postId = document.getElementById('post_id').value;
            if (!postId) {
                showMessage('Veuillez sélectionner un post', false);
                return false;
            }
            const formData = new FormData();
            formData.append('action', 'add_featured');
            formData.append('post_id', postId);
            const submitBtn = addFeaturedForm.querySelector('button[type="submit"]');
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
                if (res.success && res.newCardHtml) {
                        closeAddFeaturedModal();
                    addCardWithFadeIn(res.newCardHtml);
                    updateStats(res.stats);
                    showMessage(res.message, true);
                } else {
                    showMessage(res.message, false);
                }
            })
            .catch(() => showMessage('Erreur lors de l\'ajout du post en vedette', false))
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});
function addCardWithFadeIn(html) {
    const grid = document.querySelector('.featured-grid');
    if (!grid) return;
    const temp = document.createElement('div');
    temp.innerHTML = html.trim();
    const card = temp.firstChild;
    card.classList.add('fade-in');
    grid.prepend(card);
    setTimeout(() => card.classList.remove('fade-in'), 350);
}
// --- FIN AJOUT ---

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
    if (stats.posts !== undefined) document.querySelectorAll('.stat-value')[1].textContent = stats.posts;
    if (stats.comments !== undefined) document.querySelectorAll('.stat-value')[2].textContent = stats.comments;
    if (stats.likes !== undefined) document.querySelectorAll('.stat-value')[3].textContent = stats.likes;
    document.querySelector('.results-count').textContent = `${stats.total} post(s) en vedette trouvé(s)`;
}
// --- FIN STATS ---

// --- FADE-IN CSS ---
const style = document.createElement('style');
style.innerHTML = `.fade-in { animation: fadeIn .35s; } @keyframes fadeIn { from { opacity:0; transform:translateY(-10px);} to {opacity:1;transform:none;} }`;
document.head.appendChild(style);

// Fermer les modals en cliquant à l'extérieur
window.onclick = function(event) {
    const addModal = document.getElementById('addFeaturedModal');
    const detailModal = document.getElementById('featuredPostDetailModal');
    
    if (event.target === addModal) {
        closeAddFeaturedModal();
    }
    if (event.target === detailModal) {
        closeFeaturedPostDetailModal();
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

// Switch d'affichage carte/tableau
const cardViewBtn = document.getElementById('cardViewBtn');
const tableViewBtn = document.getElementById('tableViewBtn');
const cardView = document.getElementById('cardView');
const tableView = document.getElementById('tableView');
const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
function applySavedView() {
    const savedView = localStorage.getItem('featuredViewMode');
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
        localStorage.setItem('featuredViewMode', 'card');
        applySavedView();
    });
    tableViewBtn.addEventListener('click', function() {
        localStorage.setItem('featuredViewMode', 'table');
        applySavedView();
    });
    applySavedView();
}
// Gestion sélection multiple
const selectAllFeatured = document.getElementById('selectAllFeatured');
function updateDeleteSelectedBtn() {
    const checked = document.querySelectorAll('.featured-checkbox:checked');
    deleteSelectedBtn.style.display = (checked.length > 0) ? '' : 'none';
}
if (selectAllFeatured) {
    selectAllFeatured.addEventListener('change', function() {
        document.querySelectorAll('.featured-checkbox').forEach(cb => {
            cb.checked = selectAllFeatured.checked;
        });
        updateDeleteSelectedBtn();
    });
}
document.querySelectorAll('.featured-checkbox').forEach(cb => {
    cb.addEventListener('change', updateDeleteSelectedBtn);
});
if (deleteSelectedBtn) {
    deleteSelectedBtn.addEventListener('click', function() {
        const checked = Array.from(document.querySelectorAll('.featured-checkbox:checked'));
        if (checked.length === 0) return;
        if (!confirm(`Supprimer ${checked.length} post(s) en vedette sélectionné(s) ? Cette action est irréversible.`)) return;
        checked.forEach(cb => {
            const featuredId = cb.value;
            deleteFeaturedAjax(featuredId);
        });
        if (selectAllFeatured) selectAllFeatured.checked = false;
        updateDeleteSelectedBtn();
    });
}
</script> 