<?php
require_once "../menu.php";

// Gestion des actions POST
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action) {
        try {
            switch ($action) {
                case 'add_post':
                    // Seuls les administrateurs peuvent ajouter des posts
                    if ($role === 'Administrateur') {
                        $content = trim($_POST['content']);
                        $user_id = (int)$_POST['user_id'];
                        $media_url = trim($_POST['media_url'] ?? '');
                        
                        // Debug temporaire
                        error_log("Tentative d'ajout de post: " . $content . " - User ID: " . $user_id);
                        
                        // Validation
                        if (empty($content) || empty($user_id)) {
                            $message = "Tous les champs obligatoires doivent être remplis";
                            break;
                        }
                        
                        // Vérifier si l'utilisateur existe
                        $checkStmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
                        $checkStmt->execute([$user_id]);
                        if (!$checkStmt->fetch()) {
                            $message = "Utilisateur introuvable";
                            break;
                        }
                        
                        // Gestion de l'upload de fichier
                        $media_path = '';
                        if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
                            $upload_dir = '../../uploads/Posts/' . $user_id . '/';
                            
                            // Créer le dossier s'il n'existe pas
                            if (!is_dir($upload_dir)) {
                                mkdir($upload_dir, 0777, true);
                            }
                            
                            $file_extension = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
                            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'avi', 'mov', 'wmv'];
                            
                            if (in_array($file_extension, $allowed_extensions)) {
                                $new_filename = uniqid() . '.' . $file_extension;
                                $upload_path = $upload_dir . $new_filename;
                                
                                if (move_uploaded_file($_FILES['media_file']['tmp_name'], $upload_path)) {
                                    $media_path = $upload_path;
                                } else {
                                    $message = "Erreur lors de l'upload du fichier";
                                    break;
                                }
                            } else {
                                $message = "Type de fichier non autorisé. Formats acceptés: JPG, PNG, GIF, MP4, AVI, MOV, WMV";
                                break;
                            }
                        } elseif (!empty($media_url)) {
                            // Utiliser l'URL fournie
                            $media_path = $media_url;
                        }
                        
                        // Insérer le nouveau post
                        $insertStmt = $conn->prepare("INSERT INTO posts (user_id, content, media) VALUES (?, ?, ?)");
                        $result = $insertStmt->execute([$user_id, $content, $media_path]);
                        
                        if ($result) {
                            $message = "Post ajouté avec succès";
                            error_log("Post ajouté avec succès: " . $content);
                        } else {
                            $message = "Erreur lors de l'insertion en base de données";
                            error_log("Erreur insertion: " . print_r($insertStmt->errorInfo(), true));
                        }
                    } else {
                        $message = "Vous n'avez pas les permissions pour ajouter un post";
                    }
                    break;
                    
                case 'delete':
                    // Vérifier que post_id existe pour la suppression
                    if (!isset($_POST['post_id'])) {
                        $message = "ID post manquant";
                        break;
                    }
                    
                    $postId = (int)$_POST['post_id'];
                    
                    // Les modérateurs et administrateurs peuvent supprimer
                    if ($role === 'Administrateur' || $role === 'Modérateur') {
                        $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
                        $stmt->execute([$postId]);
                        $message = "Post supprimé avec succès";
                    } else {
                        $message = "Vous n'avez pas les permissions pour supprimer un post";
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
$order = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC';

// Construction de la requête avec recherche
$whereClause = "";
$params = [];

if (!empty($search)) {
    $whereClause = "WHERE p.content LIKE ? OR u.username LIKE ?";
    $params = ["%$search%", "%$search%"];
}

// Requête pour compter le total
$countQuery = "SELECT COUNT(*) FROM posts p JOIN users u ON p.user_id = u.id $whereClause";
$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$totalPosts = $countStmt->fetchColumn();

// Requête principale avec pagination et tri
$query = "
    SELECT 
        p.*,
        u.username,
        u.profile_picture,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count
    FROM posts p 
    JOIN users u ON p.user_id = u.id 
    $whereClause
    ORDER BY p.created_at $order 
    LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcul du nombre total de pages
$totalPages = ceil($totalPosts / $limit);

// Récupérer la liste des utilisateurs pour le formulaire d'ajout
$usersStmt = $conn->query("SELECT id, username FROM users ORDER BY username");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<link rel="stylesheet" href="/assets/css/back-office/posts.css">

<div class="main-content">
    <div class="dashboard-container">
        <div class="header">
            <h1>Gestion des Posts</h1>
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
                        <input type="text" name="search" placeholder="Rechercher par contenu ou utilisateur..." 
                               value="<?= htmlspecialchars($search) ?>" class="search-input">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <?php if ($role === 'Administrateur'): ?>
                    <button class="btn btn-primary" onclick="showAddPostModal()">
                        <i class="fas fa-plus"></i>
                        Ajouter un post
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-pen-nib"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Posts</h3>
                    <div class="stat-value"><?= $totalPosts ?></div>
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
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <h3>Posts en Vedette</h3>
                    <div class="stat-value">
                        <?php
                        $featuredStmt = $conn->query("SELECT COUNT(*) FROM featured_posts");
                        echo $featuredStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Switch d'affichage -->
        <div class="view-switch">
            <button class="view-btn active" id="cardViewBtn" type="button"><i class="fas fa-th"></i> Vue carte</button>
            <button class="view-btn" id="tableViewBtn" type="button"><i class="fas fa-table"></i> Vue tableau</button>
        </div>
        <!-- Liste des posts -->
        <div class="posts-table-container">
            <div class="table-header">
                <h2>Liste des Posts</h2>
                <div class="table-actions">
                    <span class="results-count"><?= $totalPosts ?> post(s) trouvé(s)</span>
                    <button id="deleteSelectedBtn" class="btn btn-danger btn-sm" style="display:none; margin-left:15px;" type="button">
                        <i class="fas fa-trash"></i> Supprimer la sélection
                    </button>
                </div>
            </div>
            <!-- Vue carte -->
            <div class="posts-grid" id="cardView">
                <?php foreach ($posts as $post): ?>
                    <div class="post-card" data-post-id="<?= $post['id'] ?>">
                        <div class="post-header">
                            <div class="post-avatar">
                                <img src="<?= !empty($post['profile_picture']) ? '../../uploads/' . $post['profile_picture'] : '../../uploads/default_profile.jpg' ?>" 
                                     alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
                            </div>
                            <div class="post-info">
                                <h3><?= htmlspecialchars($post['username']) ?></h3>
                                <p class="post-date"><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></p>
                            </div>
                        </div>
                        
                        <div class="post-content">
                            <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                            <?php if (!empty($post['media'])): ?>
                                <div class="post-media">
                                    <?php 
                                    $file_extension = strtolower(pathinfo($post['media'], PATHINFO_EXTENSION));
                                    $is_video = in_array($file_extension, ['mp4', 'avi', 'mov', 'wmv']);
                                    $mediaPath = (strpos($post['media'], '/vues/back-office/uploads/') === 0) ? $post['media'] : ('/vues/back-office/uploads/' . ltrim($post['media'], '/'));
                                    ?>
                                    <?php if ($is_video): ?>
                                        <div class="video-container">
                                            <video controls class="media-content">
                                                <source src="<?= htmlspecialchars($mediaPath) ?>" type="video/mp4">
                                                Votre navigateur ne supporte pas la lecture de vidéos.
                                            </video>
                                            <div class="media-overlay">
                                                <i class="fas fa-play-circle"></i>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="image-container">
                                            <img src="<?= htmlspecialchars($mediaPath) ?>" alt="Media" class="media-content" onerror="this.style.display='none'">
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
                                <span><?= $post['comments_count'] ?> commentaires</span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-heart"></i>
                                <span><?= $post['likes_count'] ?> likes</span>
                            </div>
                        </div>
                        
                        <div class="post-actions">
                            <button class="btn btn-info btn-sm" onclick="viewPost(<?= $post['id'] ?>)">
                                <i class="fas fa-eye"></i>
                                Voir
                            </button>
                            
                            <?php if ($role === 'Administrateur' || $role === 'Modérateur'): ?>
                                <button type="button" class="btn btn-danger btn-sm" onclick="deletePost(<?= $post['id'] ?>, '<?= htmlspecialchars($post['username']) ?>')">
                                    <i class="fas fa-trash"></i>
                                    Supprimer
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- Vue tableau -->
            <div class="posts-table-view" id="tableView" style="display:none;">
                <table class="posts-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllPosts"></th>
                            <th>Utilisateur</th>
                            <th>Contenu</th>
                            <th>Commentaires</th>
                            <th>Likes</th>
                            <th><button id="sortDateBtn" onclick="changeSortOrder()" style="background:none;border:none;cursor:pointer;font-weight:700;">Date</button></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): ?>
                        <tr data-post-id="<?= $post['id'] ?>">
                            <td><input type="checkbox" class="post-checkbox" value="<?= $post['id'] ?>"></td>
                            <td><?= htmlspecialchars($post['username']) ?></td>
                            <td><?= htmlspecialchars(substr($post['content'], 0, 60)) ?><?= strlen($post['content']) > 60 ? '...' : '' ?></td>
                            <td><?= $post['comments_count'] ?></td>
                            <td><?= $post['likes_count'] ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-info btn-sm" onclick="viewPost(<?= $post['id'] ?>)"><i class="fas fa-eye"></i></button>
                                <?php if ($role === 'Administrateur' || $role === 'Modérateur'): ?>
                                <button type="button" class="btn btn-danger btn-sm" onclick="deletePostAjax(<?= $post['id'] ?>)"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
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
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&order=<?= $order ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                            Précédent
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&order=<?= $order ?>" 
                           class="page-link <?= $i === $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&order=<?= $order ?>" class="page-link">
                            Suivant
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal d'ajout de post -->
<?php if ($role === 'Administrateur'): ?>
<div id="addPostModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Ajouter un Post</h2>
            <span class="close" onclick="closeAddPostModal()">&times;</span>
        </div>
        
        <form id="addPostForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_post">
            
            <div class="form-group">
                <label for="user_id">Utilisateur *</label>
                <select name="user_id" id="user_id" required>
                    <option value="">Sélectionner un utilisateur</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="content">Contenu *</label>
                <textarea name="content" id="content" rows="4" placeholder="Contenu du post..." required></textarea>
            </div>
            
            <div class="form-group">
                <label for="media_file">Fichier média (optionnel)</label>
                <input type="file" name="media_file" id="media_file" accept="image/*,video/*">
                <small class="form-help">Formats acceptés: JPG, PNG, GIF, MP4, AVI, MOV, WMV (max 10MB)</small>
            </div>
            
            <div class="form-group">
                <label for="media_url">OU URL média (optionnel)</label>
                <input type="url" name="media_url" id="media_url" placeholder="https://example.com/image.jpg">
                <small class="form-help">Entrez l'URL complète d'une image ou vidéo</small>
            </div>
            
            <div class="form-actions">
                <button type="button" onclick="closeAddPostModal()" class="btn btn-secondary">Annuler</button>
                <button type="submit" class="btn btn-primary">Ajouter</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modal de détail du post -->
<div id="postDetailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Détails du Post</h2>
            <span class="close" onclick="closePostDetailModal()">&times;</span>
        </div>
        <div id="postDetailContent">
            <!-- Le contenu sera chargé dynamiquement -->
        </div>
    </div>
</div>

<script>
// Fonctions pour les modals
function showAddPostModal() {
    document.getElementById('addPostModal').style.display = 'block';
}

function closeAddPostModal() {
    document.getElementById('addPostModal').style.display = 'none';
    document.getElementById('addPostForm').reset();
}

function closePostDetailModal() {
    document.getElementById('postDetailModal').style.display = 'none';
}

// Fonction de suppression avec confirmation
function deletePost(postId, username) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer le post de ${username} ?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="post_id" value="${postId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Fonction pour voir les détails d'un post
function viewPost(postId) {
    // Charger les détails du post via AJAX
    fetch(`post_detail.php?id=${postId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('postDetailContent').innerHTML = html;
            document.getElementById('postDetailModal').style.display = 'block';
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors du chargement des détails du post');
        });
}

// Validation du formulaire d'ajout de post
document.addEventListener('DOMContentLoaded', function() {
    const addPostForm = document.getElementById('addPostForm');
    if (addPostForm) {
        addPostForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const userId = document.getElementById('user_id').value;
            const content = document.getElementById('content').value.trim();
            const mediaFile = document.getElementById('media_file').files[0];
            const mediaUrl = document.getElementById('media_url').value.trim();
            
            if (!userId || !content) {
                showMessage('Tous les champs obligatoires doivent être remplis', false);
                return false;
            }
            
            if (content.length < 1) {
                showMessage('Le contenu du post ne peut pas être vide', false);
                return false;
            }
            
            // Vérifier la taille du fichier (max 10MB)
            if (mediaFile && mediaFile.size > 10 * 1024 * 1024) {
                showMessage('Le fichier est trop volumineux. Taille maximum: 10MB', false);
                return false;
            }
            
            // Vérifier le type de fichier
            if (mediaFile) {
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'video/mp4', 'video/avi', 'video/mov', 'video/wmv'];
                if (!allowedTypes.includes(mediaFile.type)) {
                    showMessage('Type de fichier non autorisé. Formats acceptés: JPG, PNG, GIF, MP4, AVI, MOV, WMV', false);
                    return false;
                }
            }
            
            // Vérifier qu'on n'a pas à la fois un fichier et une URL
            if (mediaFile && mediaUrl) {
                showMessage('Veuillez choisir soit un fichier soit une URL, pas les deux', false);
                return false;
            }
            
            // Envoyer les données en AJAX
            const formData = new FormData();
            formData.append('action', 'add_post');
            formData.append('user_id', userId);
            formData.append('content', content);
            formData.append('media_url', mediaUrl);
            
            if (mediaFile) {
                formData.append('media_file', mediaFile);
            }
            
            // Afficher un indicateur de chargement
            const submitBtn = addPostForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout en cours...';
            submitBtn.disabled = true;
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Extraire le message de la réponse
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const alertElement = doc.querySelector('.alert');
                
                if (alertElement) {
                    const message = alertElement.textContent.trim();
                    const isSuccess = alertElement.classList.contains('alert-success');
                    
                    // Afficher le message
                    showMessage(message, isSuccess);
                    
                    // Si succès, fermer le modal et ajouter dynamiquement le post
                    if (isSuccess) {
                        closeAddPostModal();
                        // Récupérer les champs du formulaire
                        const userSelect = document.getElementById('user_id');
                        const username = userSelect ? userSelect.options[userSelect.selectedIndex].text : '';
                        const content = document.getElementById('content').value.trim();
                        const now = new Date();
                        const dateStr = now.toLocaleDateString('fr-FR') + ' ' + now.toLocaleTimeString('fr-FR', {hour: '2-digit', minute:'2-digit'});
                        const tempId = 'new_' + Date.now();
                        // Ajouter la carte
                        const cardView = document.getElementById('cardView');
                        if (cardView) {
                            const card = document.createElement('div');
                            card.className = 'post-card fade-in';
                            card.setAttribute('data-post-id', tempId);
                            card.innerHTML = `
                                <div class="post-header">
                                    <div class="post-avatar">
                                        <img src="../../uploads/default_profile.jpg" alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
                                    </div>
                                    <div class="post-info">
                                        <h3>${username}</h3>
                                        <p class="post-date">${dateStr}</p>
                                    </div>
                                </div>
                                <div class="post-content">
                                    <p>${content}</p>
                                </div>
                                <div class="post-stats">
                                    <div class="stat-item"><i class="fas fa-comment"></i> <span>0 commentaires</span></div>
                                    <div class="stat-item"><i class="fas fa-heart"></i> <span>0 likes</span></div>
                                </div>
                                <div class="post-actions">
                                    <button class="btn btn-info btn-sm" onclick="viewPost('${tempId}')"><i class="fas fa-eye"></i> Voir</button>
                                </div>
                            `;
                            cardView.prepend(card);
                            setTimeout(() => card.classList.remove('fade-in'), 350);
                        }
                        // Ajouter la ligne au tableau
                        const tableBody = document.querySelector('.posts-table tbody');
                        if (tableBody) {
                            const tr = document.createElement('tr');
                            tr.className = 'fade-in';
                            tr.setAttribute('data-post-id', tempId);
                            tr.innerHTML = `
                                <td><input type="checkbox" class="post-checkbox" value="${tempId}"></td>
                                <td>${username}</td>
                                <td>${content.substring(0, 60)}${content.length > 60 ? '...' : ''}</td>
                                <td>0</td>
                                <td>0</td>
                                <td>${dateStr}</td>
                                <td><button class="btn btn-info btn-sm" onclick="viewPost('${tempId}')"><i class="fas fa-eye"></i></button></td>
                            `;
                            tableBody.prepend(tr);
                            setTimeout(() => tr.classList.remove('fade-in'), 350);
                        }
                        updatePostCount();
                    }
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showMessage('Erreur lors de l\'ajout du post', false);
            })
            .finally(() => {
                // Restaurer le bouton
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
        
        // Améliorer l'expérience utilisateur pour les champs de média
        const mediaFile = document.getElementById('media_file');
        const mediaUrl = document.getElementById('media_url');
        
        if (mediaFile && mediaUrl) {
            mediaFile.addEventListener('change', function() {
                if (this.files.length > 0) {
                    mediaUrl.value = '';
                    mediaUrl.disabled = true;
                    showFileInfo(this.files[0]);
                } else {
                    mediaUrl.disabled = false;
                    hideFileInfo();
                }
            });
            
            mediaUrl.addEventListener('input', function() {
                if (this.value.trim()) {
                    mediaFile.value = '';
                    mediaFile.disabled = true;
                } else {
                    mediaFile.disabled = false;
                }
            });
        }
    }
});

// Fonction pour afficher les informations du fichier sélectionné
function showFileInfo(file) {
    let fileInfo = document.getElementById('file-info');
    if (!fileInfo) {
        fileInfo = document.createElement('div');
        fileInfo.id = 'file-info';
        fileInfo.className = 'file-info';
        document.getElementById('media_file').parentNode.appendChild(fileInfo);
    }
    
    const size = (file.size / (1024 * 1024)).toFixed(2);
    const isVideo = file.type.startsWith('video/');
    const icon = isVideo ? 'fa-video' : 'fa-image';
    
    fileInfo.innerHTML = `
        <div class="file-info-content">
            <i class="fas ${icon}"></i>
            <span>${file.name}</span>
            <small>${size} MB</small>
        </div>
    `;
}

// Fonction pour masquer les informations du fichier
function hideFileInfo() {
    const fileInfo = document.getElementById('file-info');
    if (fileInfo) {
        fileInfo.remove();
    }
}

// Fonction pour afficher les messages
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

// Fermer les modals en cliquant à l'extérieur
window.onclick = function(event) {
    const addModal = document.getElementById('addPostModal');
    const detailModal = document.getElementById('postDetailModal');
    
    if (event.target === addModal) {
        closeAddPostModal();
    }
    if (event.target === detailModal) {
        closePostDetailModal();
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

// Switch d'affichage carte/tableau pour les posts
const cardViewBtn = document.getElementById('cardViewBtn');
const tableViewBtn = document.getElementById('tableViewBtn');
const cardView = document.getElementById('cardView');
const tableView = document.getElementById('tableView');
const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');

function applySavedView() {
    const savedView = localStorage.getItem('postsViewMode');
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
        localStorage.setItem('postsViewMode', 'card');
        applySavedView();
    });
    tableViewBtn.addEventListener('click', function() {
        localStorage.setItem('postsViewMode', 'table');
        applySavedView();
    });
    applySavedView();
}

// Gestion sélection multiple
const selectAllPosts = document.getElementById('selectAllPosts');
function updateDeleteSelectedBtn() {
    const checked = document.querySelectorAll('.post-checkbox:checked');
    deleteSelectedBtn.style.display = (checked.length > 0) ? '' : 'none';
}
if (selectAllPosts) {
    selectAllPosts.addEventListener('change', function() {
        document.querySelectorAll('.post-checkbox').forEach(cb => {
            cb.checked = selectAllPosts.checked;
        });
        updateDeleteSelectedBtn();
    });
}
document.querySelectorAll('.post-checkbox').forEach(cb => {
    cb.addEventListener('change', updateDeleteSelectedBtn);
});
if (deleteSelectedBtn) {
    deleteSelectedBtn.addEventListener('click', function() {
        const checked = Array.from(document.querySelectorAll('.post-checkbox:checked'));
        if (checked.length === 0) return;
        if (!confirm(`Supprimer ${checked.length} post(s) sélectionné(s) ? Cette action est irréversible.`)) return;
        checked.forEach(cb => {
            const postId = cb.value;
            deletePostAjax(postId);
        });
        if (selectAllPosts) selectAllPosts.checked = false;
        updateDeleteSelectedBtn();
    });
}
// Suppression AJAX d'un post (simple ou multiple)
function deletePostAjax(postId, isBulk = false) {
    if (!postId) return;
    if (!isBulk && !confirm('Êtes-vous sûr de vouloir supprimer ce post ?')) return;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('post_id', postId);
    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.text())
    .then(html => {
        // Retirer la carte et la ligne du tableau avec animation fade-out
        const card = document.querySelector(`.post-card[data-post-id="${postId}"]`);
        if (card) {
            card.classList.add('fade-out');
            setTimeout(() => card.remove(), 300);
        }
        const tr = document.querySelector(`tr[data-post-id="${postId}"]`);
        if (tr) {
            tr.classList.add('fade-out');
            setTimeout(() => tr.remove(), 300);
        }
        updatePostCount();
        // Extraire le message de la réponse
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const alertElement = doc.querySelector('.alert');
        if (alertElement && !isBulk) { // Afficher le message seulement pour suppression simple
            const message = alertElement.textContent.trim();
            const isSuccess = alertElement.classList.contains('alert-success');
            showMessage(message, isSuccess);
        }
        // Pour la suppression multiple, on compte le nombre de suppressions et on affiche un message global à la fin
        if (isBulk) {
            window.__postsBulkDeleteCount = (window.__postsBulkDeleteCount || 0) + 1;
            window.__postsBulkDeleteTotal = window.__postsBulkDeleteTotal || 0;
            if (window.__postsBulkDeleteCount === window.__postsBulkDeleteTotal) {
                showMessage(`${window.__postsBulkDeleteTotal} post(s) supprimé(s) avec succès`, true);
                window.__postsBulkDeleteCount = 0;
                window.__postsBulkDeleteTotal = 0;
            }
        }
    })
    .catch(error => {
        if (!isBulk) showMessage('Erreur lors de la suppression du post', false);
        console.error('Erreur:', error);
    });
}
// Mise à jour du compteur de posts
function updatePostCount() {
    const postCards = document.querySelectorAll('.post-card');
    const totalPosts = postCards.length;
    // Mettre à jour les statistiques
    const statValues = document.querySelectorAll('.stat-value');
    if (statValues.length >= 1) {
        statValues[0].textContent = totalPosts; // Total posts
    }
    // Mettre à jour le compteur de résultats
    const resultsCount = document.querySelector('.results-count');
    if (resultsCount) {
        resultsCount.textContent = `${totalPosts} post(s) trouvé(s)`;
    }
}

// Ajout du tri ASC/DESC sur la colonne Date
const dateTh = document.querySelector('.posts-table th:nth-child(6)');
if (dateTh) {
    // Ajout de l'icône de tri
    let sortOrder = localStorage.getItem('posts_sort_order') || (new URLSearchParams(window.location.search).get('order') || 'desc');
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
        localStorage.setItem('posts_sort_order', sortOrder);
        const params = new URLSearchParams(window.location.search);
        params.set('order', sortOrder);
        window.location.search = params.toString();
    }
    document.addEventListener('DOMContentLoaded', updateSortUI);
    // Appliquer le tri au chargement
    (function() {
        const order = localStorage.getItem('postsSortOrder');
        if (order && order !== 'desc') {
            const url = new URL(window.location.href);
            if (url.searchParams.get('order') !== order) {
                url.searchParams.set('order', order);
                window.location.href = url.toString();
            }
        }
    })();
}
</script>

