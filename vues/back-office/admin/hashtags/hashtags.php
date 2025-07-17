<?php
require_once "../menu.php";
echo '<link rel="stylesheet" href="/assets/css/back-office/hashtags.css">';

// Gestion des actions POST
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action) {
        try {
            switch ($action) {
                case 'add_hashtag':
                    if ($role === 'Administrateur') {
                        $tag = trim($_POST['tag'] ?? '');
                        $post_id = (int)($_POST['post_id'] ?? 0);
                        
                        if (empty($tag)) {
                            $message = "Le nom du hashtag est obligatoire";
                            break;
                        }
                        
                        if ($post_id <= 0) {
                            $message = "Veuillez sélectionner un post";
                            break;
                        }
                        
                        // Vérifier si le post existe
                        $checkPostStmt = $conn->prepare("SELECT id FROM posts WHERE id = ?");
                        $checkPostStmt->execute([$post_id]);
                        if (!$checkPostStmt->fetch()) {
                            $message = "Post introuvable";
                            break;
                        }
                        
                        // Vérifier si le hashtag existe déjà pour ce post
                        $checkStmt = $conn->prepare("SELECT id FROM hashtags WHERE tag = ? AND post_id = ?");
                        $checkStmt->execute([$tag, $post_id]);
                        if ($checkStmt->fetch()) {
                            $message = "Ce hashtag existe déjà pour ce post";
                            break;
                        }
                        
                        $insertStmt = $conn->prepare("INSERT INTO hashtags (tag, post_id, created_at) VALUES (?, ?, NOW())");
                        if ($insertStmt->execute([$tag, $post_id])) {
                            $message = "Hashtag ajouté avec succès";
                        } else {
                            $message = "Erreur lors de l'ajout";
                        }
                    } else {
                        $message = "Vous n'avez pas les permissions pour ajouter un hashtag";
                    }
                    break;
                    
                case 'delete':
                    if (!isset($_POST['hashtag_id'])) {
                        $message = "ID hashtag manquant";
                        break;
                    }
                    $hashtagId = (int)$_POST['hashtag_id'];
                    if ($role === 'Administrateur' || $role === 'Modérateur') {
                        $stmt = $conn->prepare("DELETE FROM hashtags WHERE id = ?");
                        $stmt->execute([$hashtagId]);
                        $message = "Hashtag supprimé avec succès";
                    } else {
                        $message = "Vous n'avez pas les permissions pour supprimer un hashtag";
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
    $whereClause = "WHERE h.tag LIKE ? OR p.content LIKE ?";
    $params = ["%$search%", "%$search%"];
}

$countQuery = "SELECT COUNT(DISTINCT h.id) FROM hashtags h 
               LEFT JOIN posts p ON h.post_id = p.id 
               $whereClause";
$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $limit);

$query = "SELECT h.*, p.content as post_content, u.username as post_author,
                 (SELECT COUNT(*) FROM hashtags WHERE tag = h.tag) as usage_count,
                 (SELECT COUNT(DISTINCT post_id) FROM hashtags WHERE tag = h.tag) as posts_count
          FROM hashtags h 
          LEFT JOIN posts p ON h.post_id = p.id 
          LEFT JOIN users u ON p.user_id = u.id 
          $whereClause
          GROUP BY h.tag
          ORDER BY h.created_at DESC 
          LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$hashtags = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            <h1>Gestion des Hashtags</h1>
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
                        <input type="text" name="search" placeholder="Rechercher par nom ou contenu de post..." 
                               value="<?= htmlspecialchars($search) ?>" class="search-input">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <?php if ($role === 'Administrateur'): ?>
                    <button class="btn btn-primary" onclick="showAddHashtagModal()">
                        <i class="fas fa-plus"></i>
                        Ajouter un hashtag
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-hashtag"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Hashtags</h3>
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
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Utilisateurs</h3>
                    <div class="stat-value">
                        <?php
                        $usersStmt = $conn->query("SELECT COUNT(*) FROM users");
                        echo $usersStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <h3>Hashtags Populaires</h3>
                    <div class="stat-value">
                        <?php
                        $popularStmt = $conn->query("SELECT COUNT(DISTINCT tag) FROM hashtags WHERE tag IN (SELECT tag FROM hashtags GROUP BY tag HAVING COUNT(*) > 1)");
                        echo $popularStmt->fetchColumn();
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
        <!-- Liste des hashtags -->
        <div class="posts-table-container">
            <div class="table-header">
                <h2>Liste des Hashtags</h2>
                <div class="table-actions">
                    <span class="results-count"><?= $total ?> hashtag(s) trouvé(s)</span>
                    <button id="deleteSelectedBtn" class="btn btn-danger btn-sm" style="display:none; margin-left:15px;" type="button">
                        <i class="fas fa-trash"></i> Supprimer la sélection
                    </button>
                </div>
            </div>
            <!-- Vue carte -->
            <div class="posts-grid" id="cardView">
                <?php foreach ($hashtags as $hashtag): ?>
                    <div class="post-card" data-hashtag-id="<?= $hashtag['id'] ?>">
                        <div class="post-header">
                            <div class="post-avatar">
                                <div class="hashtag-icon">
                                    <i class="fas fa-hashtag"></i>
                                </div>
                            </div>
                            <div class="post-info">
                                <h3>#<?= htmlspecialchars($hashtag['tag']) ?></h3>
                                <p class="post-date">Créé le <?= date('d/m/Y H:i', strtotime($hashtag['created_at'])) ?></p>
                            </div>
                        </div>
                        <div class="post-content">
                            <p><strong>Post associé :</strong> <?= !empty($hashtag['post_content']) ? htmlspecialchars(substr($hashtag['post_content'], 0, 100)) . '...' : 'Post supprimé' ?></p>
                            <p><strong>Auteur :</strong> <?= htmlspecialchars($hashtag['post_author'] ?? 'Inconnu') ?></p>
                        </div>
                        <div class="post-stats">
                            <div class="stat-item">
                                <i class="fas fa-pen-nib"></i>
                                <span><?= $hashtag['posts_count'] ?> posts</span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-hashtag"></i>
                                <span><?= $hashtag['usage_count'] ?> utilisations</span>
                            </div>
                        </div>
                        <div class="post-actions">
                            <button class="btn btn-info btn-sm" onclick="viewHashtag(<?= $hashtag['id'] ?>)">
                                <i class="fas fa-eye"></i>
                                Voir
                            </button>
                            <?php if ($role === 'Administrateur' || $role === 'Modérateur'): ?>
                                <button type="button" class="btn btn-danger btn-sm" onclick="deleteHashtag(<?= $hashtag['id'] ?>, '<?= htmlspecialchars($hashtag['tag']) ?>')">
                                    <i class="fas fa-trash"></i>
                                    Supprimer
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- Vue tableau -->
            <div class="hashtags-table-view" id="tableView" style="display:none; margin-top:20px;">
                <table class="hashtags-table" style="width:100%; border-collapse:collapse; font-size:14px;">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllHashtags"></th>
                            <th>Hashtag</th>
                            <th>Post associé</th>
                            <th>Auteur</th>
                            <th>Utilisations</th>
                            <th><button id="sortDateBtn" onclick="changeSortOrder()" style="background:none;border:none;cursor:pointer;font-weight:700;">Date</button></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hashtags as $hashtag): ?>
                            <tr data-hashtag-id="<?= $hashtag['id'] ?>">
                                <td><input type="checkbox" class="hashtag-checkbox" value="<?= $hashtag['id'] ?>"></td>
                                <td>#<?= htmlspecialchars($hashtag['tag']) ?></td>
                                <td><?= !empty($hashtag['post_content']) ? htmlspecialchars(substr($hashtag['post_content'], 0, 60)) . '...' : 'Post supprimé' ?></td>
                                <td><?= htmlspecialchars($hashtag['post_author'] ?? 'Inconnu') ?></td>
                                <td><?= $hashtag['usage_count'] ?></td>
                                <td>
                                    <span class="post-date">
                                        <?= date('d/m/Y H:i', strtotime($hashtag['created_at'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="post-actions">
                                        <button class="btn btn-info btn-sm" onclick="viewHashtag(<?= $hashtag['id'] ?>)"><i class="fas fa-eye"></i></button>
                                        <?php if ($role === 'Administrateur' || $role === 'Modérateur'): ?>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="deleteHashtag(<?= $hashtag['id'] ?>, '<?= htmlspecialchars($hashtag['tag']) ?>')"><i class="fas fa-trash"></i></button>
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

<!-- Modal d'ajout de hashtag -->
<?php if ($role === 'Administrateur'): ?>
<div id="addHashtagModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Ajouter un Hashtag</h2>
            <span class="close" onclick="closeAddHashtagModal()">&times;</span>
        </div>
        
        <form id="addHashtagForm" method="POST">
            <input type="hidden" name="action" value="add_hashtag">
            
            <div class="form-group">
                <label for="tag">Nom du hashtag *</label>
                <input type="text" name="tag" id="tag" placeholder="exemple" required>
                <small class="form-help">Entrez le nom sans le # (il sera ajouté automatiquement)</small>
            </div>
            
            <div class="form-group">
                <label for="post_id">Post associé *</label>
                <select name="post_id" id="post_id" required>
                    <option value="">Sélectionner un post</option>
                    <?php foreach ($postsList as $post): ?>
                        <option value="<?= $post['id'] ?>"><?= htmlspecialchars(substr($post['content'], 0, 60)) ?>... (<?= htmlspecialchars($post['username']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <small class="form-help">Choisissez le post auquel associer ce hashtag</small>
            </div>
            
            <div class="form-actions">
                <button type="button" onclick="closeAddHashtagModal()" class="btn btn-secondary">Annuler</button>
                <button type="submit" class="btn btn-primary">Ajouter</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modal de détail du hashtag -->
<div id="hashtagDetailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Détails du Hashtag</h2>
            <span class="close" onclick="closeHashtagDetailModal()">&times;</span>
        </div>
        <div id="hashtagDetailContent">
            <!-- Le contenu sera chargé dynamiquement -->
        </div>
    </div>
</div>

<script>
// Fonctions pour les modals
function showAddHashtagModal() {
    document.getElementById('addHashtagModal').style.display = 'block';
}
function closeAddHashtagModal() {
    document.getElementById('addHashtagModal').style.display = 'none';
    document.getElementById('addHashtagForm').reset();
}
function closeHashtagDetailModal() {
    document.getElementById('hashtagDetailModal').style.display = 'none';
}
// Fonction pour voir les détails d'un hashtag (toujours dans le scope global)
function viewHashtag(hashtagId) {
    fetch(`hashtag_detail.php?id=${hashtagId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('hashtagDetailContent').innerHTML = html;
            document.getElementById('hashtagDetailModal').style.display = 'block';
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors du chargement des détails du hashtag');
        });
}
// Validation du formulaire d'ajout
document.addEventListener('DOMContentLoaded', function() {
    const addHashtagForm = document.getElementById('addHashtagForm');
    if (addHashtagForm) {
        addHashtagForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const tag = document.getElementById('tag').value.trim();
            const postId = document.getElementById('post_id').value;
            
            if (!tag) {
                showMessage('Le nom du hashtag est obligatoire', false);
                return false;
            }
            
            if (!postId) {
                showMessage('Veuillez sélectionner un post', false);
                return false;
            }
            
            if (tag.length < 2) {
                showMessage('Le nom du hashtag doit contenir au moins 2 caractères', false);
                return false;
            }
            
            // Nettoyer le nom (enlever les caractères spéciaux sauf lettres, chiffres et underscore)
            const cleanTag = tag.replace(/[^a-zA-Z0-9_]/g, '');
            if (cleanTag !== tag) {
                showMessage('Le nom du hashtag ne peut contenir que des lettres, chiffres et underscore', false);
                return false;
            }
            
            const formData = new FormData();
            formData.append('action', 'add_hashtag');
            formData.append('tag', cleanTag);
            formData.append('post_id', postId);
            
            const submitBtn = addHashtagForm.querySelector('button[type="submit"]');
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
                        closeAddHashtagModal();
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showMessage('Erreur lors de l\'ajout du hashtag', false);
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
    const addModal = document.getElementById('addHashtagModal');
    const detailModal = document.getElementById('hashtagDetailModal');
    
    if (event.target === addModal) {
        closeAddHashtagModal();
    }
    if (event.target === detailModal) {
        closeHashtagDetailModal();
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
            case 'add_hashtag':
                if ($role === 'Administrateur') {
                    $tag = trim($_POST['tag'] ?? '');
                    $post_id = (int)($_POST['post_id'] ?? 0);
                    if (empty($tag)) {
                        $response['message'] = "Le nom du hashtag est obligatoire";
                        break;
                    }
                    if ($post_id <= 0) {
                        $response['message'] = "Veuillez sélectionner un post";
                        break;
                    }
                    $checkPostStmt = $conn->prepare("SELECT id FROM posts WHERE id = ?");
                    $checkPostStmt->execute([$post_id]);
                    if (!$checkPostStmt->fetch()) {
                        $response['message'] = "Post introuvable";
                        break;
                    }
                    $checkStmt = $conn->prepare("SELECT id FROM hashtags WHERE tag = ? AND post_id = ?");
                    $checkStmt->execute([$tag, $post_id]);
                    if ($checkStmt->fetch()) {
                        $response['message'] = "Ce hashtag existe déjà pour ce post";
                        break;
                    }
                    $insertStmt = $conn->prepare("INSERT INTO hashtags (tag, post_id, created_at) VALUES (?, ?, NOW())");
                    if ($insertStmt->execute([$tag, $post_id])) {
                        $hashtagId = $conn->lastInsertId();
                        $query = "SELECT h.*, p.content as post_content, u.username as post_author, (SELECT COUNT(*) FROM hashtags WHERE tag = h.tag) as usage_count, (SELECT COUNT(DISTINCT post_id) FROM hashtags WHERE tag = h.tag) as posts_count FROM hashtags h LEFT JOIN posts p ON h.post_id = p.id LEFT JOIN users u ON p.user_id = u.id WHERE h.id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$hashtagId]);
                        $hashtag = $stmt->fetch(PDO::FETCH_ASSOC);
                        ob_start();
                        include 'hashtag_card.php';
                        $response['newCardHtml'] = ob_get_clean();
                        $response['success'] = true;
                        $response['message'] = "Hashtag ajouté avec succès";
                        $response['stats'] = getStats($conn);
                    } else {
                        $response['message'] = "Erreur lors de l'ajout";
                    }
                } else {
                    $response['message'] = "Vous n'avez pas les permissions pour ajouter un hashtag";
                }
                break;
            case 'delete':
                if (!isset($_POST['hashtag_id'])) {
                    $response['message'] = "ID hashtag manquant";
                    break;
                }
                $hashtagId = (int)$_POST['hashtag_id'];
                if ($role === 'Administrateur' || $role === 'Modérateur') {
                    $stmt = $conn->prepare("DELETE FROM hashtags WHERE id = ?");
                    $stmt->execute([$hashtagId]);
                    $response['success'] = true;
                    $response['message'] = "Hashtag supprimé avec succès";
                    $response['stats'] = getStats($conn);
                } else {
                    $response['message'] = "Vous n'avez pas les permissions pour supprimer un hashtag";
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
        'total' => (int)$conn->query("SELECT COUNT(DISTINCT id) FROM hashtags")->fetchColumn(),
        'posts' => (int)$conn->query("SELECT COUNT(*) FROM posts")->fetchColumn(),
        'users' => (int)$conn->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'popular' => (int)$conn->query("SELECT COUNT(DISTINCT tag) FROM hashtags WHERE tag IN (SELECT tag FROM hashtags GROUP BY tag HAVING COUNT(*) > 1)")->fetchColumn(),
    ];
}
// --- FIN API AJAX ---

// --- SUPPRESSION AJAX ---
function deleteHashtagAjax(hashtagId, card) {
    card.classList.add('fade-out');
    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ action: 'delete', hashtag_id: hashtagId })
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
    document.querySelectorAll('.post-card .btn-danger').forEach(btn => {
        btn.onclick = function(e) {
            e.preventDefault();
            if (!confirm('Êtes-vous sûr de vouloir supprimer ce hashtag ?')) return false;
            const card = btn.closest('.post-card');
            const hashtagId = card.getAttribute('data-hashtag-id');
            deleteHashtagAjax(hashtagId, card);
            return false;
        };
    });
    // Ajout AJAX
    const addHashtagForm = document.getElementById('addHashtagForm');
    if (addHashtagForm) {
        addHashtagForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const tag = document.getElementById('tag').value.trim();
            const postId = document.getElementById('post_id').value;
            if (!tag) {
                showMessage('Le nom du hashtag est obligatoire', false);
                return false;
            }
            if (!postId) {
                showMessage('Veuillez sélectionner un post', false);
                return false;
            }
            if (tag.length < 2) {
                showMessage('Le nom du hashtag doit contenir au moins 2 caractères', false);
                return false;
            }
            const cleanTag = tag.replace(/[^a-zA-Z0-9_]/g, '');
            if (cleanTag !== tag) {
                showMessage('Le nom du hashtag ne peut contenir que des lettres, chiffres et underscore', false);
                return false;
            }
            const formData = new FormData();
            formData.append('action', 'add_hashtag');
            formData.append('tag', cleanTag);
            formData.append('post_id', postId);
            const submitBtn = addHashtagForm.querySelector('button[type="submit"]');
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
                    closeAddHashtagModal();
                    addCardWithFadeIn(res.newCardHtml);
                    updateStats(res.stats);
                    showMessage(res.message, true);
                } else {
                    showMessage(res.message, false);
                }
            })
            .catch(() => showMessage('Erreur lors de l\'ajout du hashtag', false))
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});
function addCardWithFadeIn(html) {
    const grid = document.querySelector('.posts-grid');
    if (!grid) return;
    const temp = document.createElement('div');
    temp.innerHTML = html.trim();
    const card = temp.firstChild;
    card.classList.add('fade-in');
    grid.prepend(card);
    setTimeout(() => card.classList.remove('fade-in'), 350);
}
// --- FIN SUPPRESSION/AJOUT ---

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
    if (stats.users !== undefined) document.querySelectorAll('.stat-value')[2].textContent = stats.users;
    if (stats.popular !== undefined) document.querySelectorAll('.stat-value')[3].textContent = stats.popular;
    document.querySelector('.results-count').textContent = `${stats.total} hashtag(s) trouvé(s)`;
}
// --- FIN STATS ---

// --- FADE-IN/FADE-OUT CSS ---
const style = document.createElement('style');
style.innerHTML = `.fade-in { animation: fadeIn .35s; } .fade-out { opacity:0; transform:translateY(-10px); transition:all .3s; } @keyframes fadeIn { from { opacity:0; transform:translateY(-10px);} to {opacity:1;transform:none;} }`;
document.head.appendChild(style);
// --- FIN FADE-IN/FADE-OUT ---

// --- TRI ASC/DESC ---
let sortOrder = localStorage.getItem('hashtags_sort_order') || (new URLSearchParams(window.location.search).get('sort') || 'desc');
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
    localStorage.setItem('hashtags_sort_order', sortOrder);
    const params = new URLSearchParams(window.location.search);
    params.set('sort', sortOrder);
    window.location.search = params.toString();
}
document.addEventListener('DOMContentLoaded', updateSortUI);
// --- FIN TRI ---

// --- SWITCH VUE CARTE / TABLEAU ---
const cardViewBtn = document.getElementById('cardViewBtn');
const tableViewBtn = document.getElementById('tableViewBtn');
const cardView = document.getElementById('cardView');
const tableView = document.getElementById('tableView');
const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
function applySavedView() {
    const savedView = localStorage.getItem('hashtagsViewMode');
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
        localStorage.setItem('hashtagsViewMode', 'card');
        applySavedView();
    });
    tableViewBtn.addEventListener('click', function() {
        localStorage.setItem('hashtagsViewMode', 'table');
        applySavedView();
    });
    applySavedView();
}
// --- GESTION SÉLECTION MULTIPLE ---
const selectAllHashtags = document.getElementById('selectAllHashtags');
function updateDeleteSelectedBtn() {
    const checked = document.querySelectorAll('.hashtag-checkbox:checked');
    deleteSelectedBtn.style.display = (checked.length > 0) ? '' : 'none';
}
if (selectAllHashtags) {
    selectAllHashtags.addEventListener('change', function() {
        document.querySelectorAll('.hashtag-checkbox').forEach(cb => {
            cb.checked = selectAllHashtags.checked;
        });
        updateDeleteSelectedBtn();
    });
}
document.querySelectorAll('.hashtag-checkbox').forEach(cb => {
    cb.addEventListener('change', updateDeleteSelectedBtn);
});
if (deleteSelectedBtn) {
    deleteSelectedBtn.addEventListener('click', function() {
        const checked = Array.from(document.querySelectorAll('.hashtag-checkbox:checked'));
        if (checked.length === 0) return;
        if (!confirm(`Supprimer ${checked.length} hashtag(s) sélectionné(s) ? Cette action est irréversible.`)) return;
        checked.forEach(cb => {
            const hashtagId = cb.value;
            const row = document.querySelector(`tr[data-hashtag-id='${hashtagId}']`);
            const card = document.querySelector(`.post-card[data-hashtag-id='${hashtagId}']`);
            if (row) deleteHashtagAjax(hashtagId, row);
            if (card) deleteHashtagAjax(hashtagId, card);
        });
        if (selectAllHashtags) selectAllHashtags.checked = false;
        updateDeleteSelectedBtn();
    });
}
</script> 