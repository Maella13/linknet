<?php
require_once "menu.php";

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
                            $upload_dir = '../uploads/Posts/' . $user_id . '/';
                            
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

// Requête principale avec pagination
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
    ORDER BY p.created_at DESC 
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

        <!-- Liste des posts -->
        <div class="posts-table-container">
            <div class="table-header">
                <h2>Liste des Posts</h2>
                <div class="table-actions">
                    <span class="results-count"><?= $totalPosts ?> post(s) trouvé(s)</span>
                </div>
            </div>

            <div class="posts-grid">
                <?php foreach ($posts as $post): ?>
                    <div class="post-card" data-post-id="<?= $post['id'] ?>">
                        <div class="post-header">
                            <div class="post-avatar">
                                <img src="<?= !empty($post['profile_picture']) ? '../uploads/' . $post['profile_picture'] : '../uploads/default_profile.jpg' ?>" 
                                     alt="Avatar" onerror="this.src='../uploads/default_profile.jpg'">
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
                                    ?>
                                    <?php if ($is_video): ?>
                                        <div class="video-container">
                                            <video controls class="media-content">
                                                <source src="<?= htmlspecialchars($post['media']) ?>" type="video/mp4">
                                                Votre navigateur ne supporte pas la lecture de vidéos.
                                            </video>
                                            <div class="media-overlay">
                                                <i class="fas fa-play-circle"></i>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="image-container">
                                            <img src="<?= htmlspecialchars($post['media']) ?>" alt="Media" class="media-content" onerror="this.style.display='none'">
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
                    
                    // Si succès, fermer le modal et rafraîchir la liste
                    if (isSuccess) {
                        closeAddPostModal();
                        // Rafraîchir la page pour afficher le nouveau post
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
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
</script>

<style>
:root {
    --primary: #2563eb;
    --secondary: #3b82f6;
    --success: #22c55e;
    --danger: #ef4444;
    --warning: #f59e42;
    --info: #0ea5e9;
    --light: #f8fafc;
    --dark: #1e293b;
}

.search-section {
    margin-bottom: 30px;
}

.search-form {
    max-width: 500px;
}

.search-input-group {
    display: flex;
    gap: 10px;
}

.search-input {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

.search-btn {
    padding: 12px 20px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.search-btn:hover {
    background: #1d4ed8;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(37,99,235,0.07);
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.stat-content h3 {
    margin: 0 0 5px 0;
    font-size: 14px;
    color: #64748b;
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary);
}

.posts-table-container {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 12px rgba(37,99,235,0.07);
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e5e7eb;
}

.table-header h2 {
    margin: 0;
    color: var(--primary);
    font-size: 1.5rem;
}

.results-count {
    color: #64748b;
    font-size: 14px;
}

.posts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.post-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.post-card:hover {
    box-shadow: 0 8px 25px rgba(37,99,235,0.15);
    transform: translateY(-4px);
    border-color: var(--primary);
}

.post-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 18px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f1f5f9;
}

.post-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    border: 3px solid #e5e7eb;
    transition: border-color 0.3s ease;
}

.post-card:hover .post-avatar {
    border-color: var(--primary);
}

.post-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.post-info h3 {
    margin: 0 0 4px 0;
    font-size: 16px;
    font-weight: 600;
    color: var(--primary);
}

.post-date {
    margin: 0;
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
}

.post-content {
    margin-bottom: 18px;
}

.post-content p {
    margin: 0 0 15px 0;
    line-height: 1.6;
    color: #374151;
    font-size: 15px;
}

.post-media {
    margin-top: 15px;
    border-radius: 12px;
    overflow: hidden;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    position: relative;
}

.image-container,
.video-container {
    position: relative;
    width: 100%;
    height: 250px;
    overflow: hidden;
    border-radius: 12px;
}

.media-content {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.3s ease;
}

.media-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.media-overlay i {
    font-size: 2rem;
    color: white;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
}

.image-container:hover .media-content,
.video-container:hover .media-content {
    transform: scale(1.05);
}

.image-container:hover .media-overlay,
.video-container:hover .media-overlay {
    opacity: 1;
}

.post-stats {
    display: flex;
    gap: 24px;
    margin-bottom: 18px;
    padding: 12px 0;
    border-top: 1px solid #f1f5f9;
    border-bottom: 1px solid #f1f5f9;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    color: #64748b;
    font-weight: 500;
}

.stat-item i {
    color: var(--primary);
    font-size: 16px;
}

.post-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

/* Styles pour les modals */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 0;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 1px solid #e5e7eb;
}

.modal-header h2 {
    margin: 0;
    color: var(--primary);
    font-size: 1.3rem;
}

.close {
    color: #64748b;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.3s;
}

.close:hover {
    color: #1e293b;
}

/* Styles pour le formulaire */
.form-group {
    margin-bottom: 20px;
    padding: 0 25px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
}

.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s;
    box-sizing: border-box;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

.form-group input[type="file"] {
    padding: 12px;
    border: 2px dashed #d1d5db;
    background: #f9fafb;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.form-group input[type="file"]:hover {
    border-color: var(--primary);
    background: #eff6ff;
}

.form-group input[type="file"]:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

.form-help {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: #64748b;
}

/* Styles pour les informations de fichier */
.file-info {
    margin-top: 10px;
    padding: 12px;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.file-info-content {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
}

.file-info-content i {
    color: var(--primary);
    font-size: 16px;
}

.file-info-content span {
    font-weight: 500;
    color: #1e40af;
    flex: 1;
}

.file-info-content small {
    color: #64748b;
    font-size: 12px;
}

/* Amélioration du formulaire */
.form-group input[type="url"] {
    font-family: 'Courier New', monospace;
    font-size: 13px;
}

.form-group input[type="url"]:focus {
    background: #f8fafc;
}

/* Animation pour les boutons */
.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn i.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Styles pour les boutons */
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    font-size: 14px;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
}

.btn-info {
    background: var(--info);
    color: white;
}

.btn-info:hover {
    background: #0284c7;
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.btn-sm {
    padding: 8px 12px;
    font-size: 12px;
}

/* Styles pour les alertes */
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s;
    font-weight: 500;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.alert i {
    font-size: 1.1rem;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.page-link {
    padding: 10px 15px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    color: #64748b;
    text-decoration: none;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 5px;
}

.page-link:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.page-link.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Responsive */
@media (max-width: 768px) {
    .search-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-form {
        max-width: none;
    }
    
    .modal-content {
        width: 95%;
        margin: 10% auto;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .posts-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .post-stats {
        flex-direction: column;
        gap: 8px;
    }
    
    .post-actions {
        flex-direction: column;
    }
    
    .pagination {
        gap: 5px;
    }
    
    .page-link {
        padding: 8px 12px;
        font-size: 14px;
    }
}

/* Styles pour la barre de recherche */
.search-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.search-form {
    flex: 1;
    max-width: 500px;
}
</style>
