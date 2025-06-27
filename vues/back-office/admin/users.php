<?php
require_once 'menu.php';

// Récupération des utilisateurs avec pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Construction de la requête avec recherche
$whereClause = '';
$params = [];
if (!empty($search)) {
    $whereClause = "WHERE username LIKE ? OR email LIKE ?";
    $params = ["%$search%", "%$search%"];
}

// Comptage total
$countStmt = $conn->prepare("SELECT COUNT(*) FROM users $whereClause");
$countStmt->execute($params);
$totalUsers = $countStmt->fetchColumn();
$totalPages = ceil($totalUsers / $limit);

// Récupération des utilisateurs
$stmt = $conn->prepare("
    SELECT u.*, 
           COUNT(DISTINCT p.id) as posts_count,
           COUNT(DISTINCT f.id) as friends_count,
           COUNT(DISTINCT fl.id) as followers_count
    FROM users u
    LEFT JOIN posts p ON u.id = p.user_id
    LEFT JOIN friends f ON (u.id = f.sender_id OR u.id = f.receiver_id) AND f.status = 'accepted'
    LEFT JOIN followers fl ON u.id = fl.user_id
    $whereClause
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT $limit OFFSET $offset
");

// Exécution avec les paramètres de recherche seulement
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement des actions selon le rôle
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'add_user':
                    // Seuls les administrateurs peuvent ajouter des utilisateurs
                    if ($role === 'Administrateur') {
                        $username = trim($_POST['username']);
                        $email = trim($_POST['email']);
                        $password = $_POST['password'];
                        $bio = trim($_POST['bio'] ?? '');
                        
                        // Debug temporaire
                        error_log("Tentative d'ajout d'utilisateur: " . $username . " - " . $email);
                        
                        // Validation
                        if (empty($username) || empty($email) || empty($password)) {
                            $message = "Tous les champs obligatoires doivent être remplis";
                            break;
                        }
                        
                        // Vérifier si l'email existe déjà
                        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                        $checkStmt->execute([$email]);
                        if ($checkStmt->fetch()) {
                            $message = "Cette adresse email est déjà utilisée";
                            break;
                        }
                        
                        // Vérifier si le nom d'utilisateur existe déjà
                        $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                        $checkStmt->execute([$username]);
                        if ($checkStmt->fetch()) {
                            $message = "Ce nom d'utilisateur est déjà pris";
                            break;
                        }
                        
                        // Hasher le mot de passe
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Insérer le nouvel utilisateur
                        $insertStmt = $conn->prepare("INSERT INTO users (username, email, password, bio) VALUES (?, ?, ?, ?)");
                        $result = $insertStmt->execute([$username, $email, $hashedPassword, $bio]);
                        
                        if ($result) {
                            $message = "Utilisateur ajouté avec succès";
                            error_log("Utilisateur ajouté avec succès: " . $username);
                        } else {
                            $message = "Erreur lors de l'insertion en base de données";
                            error_log("Erreur insertion: " . print_r($insertStmt->errorInfo(), true));
                        }
                    } else {
                        $message = "Vous n'avez pas les permissions pour ajouter un utilisateur";
                    }
                    break;
                    
                case 'delete':
                    // Vérifier que user_id existe pour la suppression
                    if (!isset($_POST['user_id'])) {
                        $message = "ID utilisateur manquant";
                        break;
                    }
                    
                    $userId = (int)$_POST['user_id'];
                    
                    // Les modérateurs et administrateurs peuvent supprimer
                    if ($role === 'Administrateur' || $role === 'Modérateur') {
                        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$userId]);
                        $message = "Utilisateur supprimé avec succès";
                    } else {
                        $message = "Vous n'avez pas les permissions pour supprimer un utilisateur";
                    }
                    break;
            }
        } catch (PDOException $e) {
            $message = "Erreur lors de l'opération: " . $e->getMessage();
        }
    }
}
?>

<div class="main-content">
    <div class="dashboard-container">
        <div class="header">
            <h1>Gestion des Utilisateurs</h1>
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
                        <input type="text" name="search" placeholder="Rechercher par nom ou email..." 
                               value="<?= htmlspecialchars($search) ?>" class="search-input">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <?php if ($role === 'Administrateur'): ?>
                    <button class="btn btn-primary" onclick="showAddUserModal()">
                        <i class="fas fa-plus"></i>
                        Ajouter un utilisateur
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Utilisateurs</h3>
                    <div class="stat-value"><?= $totalUsers ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <h3>Utilisateurs Actifs</h3>
                    <div class="stat-value">
                        <?php
                        $activeStmt = $conn->query("SELECT COUNT(*) FROM users");
                        echo $activeStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des utilisateurs -->
        <div class="users-table-container">
            <div class="table-header">
                <h2>Liste des Utilisateurs</h2>
                <div class="table-actions">
                    <span class="results-count"><?= $totalUsers ?> utilisateur(s) trouvé(s)</span>
                </div>
            </div>

            <div class="users-grid">
                <?php foreach ($users as $user): ?>
                    <div class="user-card" data-user-id="<?= $user['id'] ?>">
                        <div class="user-header">
                            <div class="user-avatar">
                                <img src="<?= !empty($user['profile_picture']) ? '../uploads/' . $user['profile_picture'] : '../uploads/default_profile.jpg' ?>" 
                                     alt="Avatar" onerror="this.src='../uploads/default_profile.jpg'">
                            </div>
                            <div class="user-info">
                                <h3><?= htmlspecialchars($user['username']) ?></h3>
                                <p class="user-email"><?= htmlspecialchars($user['email']) ?></p>
                            </div>
                        </div>
                        
                        <div class="user-stats">
                            <div class="stat-item">
                                <i class="fas fa-pen"></i>
                                <span><?= $user['posts_count'] ?> posts</span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-user-friends"></i>
                                <span><?= $user['friends_count'] ?> amis</span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-users"></i>
                                <span><?= $user['followers_count'] ?> abonnés</span>
                            </div>
                        </div>
                        
                        <div class="user-meta">
                            <span class="join-date">
                                <i class="fas fa-calendar"></i>
                                Inscrit le <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                            </span>
                        </div>
                        
                        <div class="user-actions">
                            <button class="btn btn-info btn-sm" onclick="viewUser(<?= $user['id'] ?>)">
                                <i class="fas fa-eye"></i>
                                Voir
                            </button>
                            
                            <?php if ($role === 'Administrateur' || $role === 'Modérateur'): ?>
                                <button type="button" class="btn btn-danger btn-sm" onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
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

<!-- Modal pour ajouter un utilisateur -->
<div id="addUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Ajouter un nouvel utilisateur</h2>
            <span class="close" onclick="closeAddUserModal()">&times;</span>
        </div>
        <form id="addUserForm" method="POST">
            <input type="hidden" name="action" value="add_user">
            <div class="form-group">
                <label for="username">Nom d'utilisateur *</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe *</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="bio">Bio</label>
                <textarea id="bio" name="bio" rows="3"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" onclick="closeAddUserModal()" class="btn btn-secondary">Annuler</button>
                <button type="submit" class="btn btn-primary">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal pour voir les détails d'un utilisateur -->
<div id="userDetailModal" class="modal">
    <div class="modal-content large">
        <div class="modal-header">
            <h2>Détails de l'utilisateur</h2>
            <span class="close" onclick="closeUserDetailModal()">&times;</span>
        </div>
        <div id="userDetailContent">
            <!-- Le contenu sera chargé dynamiquement -->
        </div>
    </div>
</div>

<script>
// Fonctions pour les modals
function showAddUserModal() {
    document.getElementById('addUserModal').style.display = 'block';
}

function closeAddUserModal() {
    document.getElementById('addUserModal').style.display = 'none';
    document.getElementById('addUserForm').reset();
}

function showUserDetailModal() {
    document.getElementById('userDetailModal').style.display = 'block';
}

function closeUserDetailModal() {
    document.getElementById('userDetailModal').style.display = 'none';
}

// Fonction pour voir les détails d'un utilisateur
function viewUser(userId) {
    fetch(`user_detail.php?id=${userId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('userDetailContent').innerHTML = html;
            showUserDetailModal();
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors du chargement des détails');
        });
}

// Fonction pour supprimer un utilisateur
function deleteUser(userId, username) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer l'utilisateur "${username}" ?\n\nCette action est irréversible.`)) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('user_id', userId);
        
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
                
                // Si succès, supprimer la carte utilisateur de l'interface
                if (isSuccess) {
                    const userCard = document.querySelector(`[data-user-id="${userId}"]`);
                    if (userCard) {
                        userCard.classList.add('fade-out');
                        setTimeout(() => {
                            userCard.remove();
                            updateUserCount();
                        }, 300);
                    }
                }
            } else {
                showMessage('Erreur lors de la suppression', false);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showMessage('Erreur lors de la suppression', false);
        });
    }
}

// Fonction pour afficher un message
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

// Fonction pour mettre à jour le compteur d'utilisateurs
function updateUserCount() {
    const userCards = document.querySelectorAll('.user-card');
    const totalUsers = userCards.length;
    
    // Mettre à jour les statistiques
    const statValues = document.querySelectorAll('.stat-value');
    if (statValues.length >= 2) {
        statValues[0].textContent = totalUsers; // Total utilisateurs
        statValues[1].textContent = totalUsers; // Utilisateurs actifs
    }
    
    // Mettre à jour le compteur de résultats
    const resultsCount = document.querySelector('.results-count');
    if (resultsCount) {
        resultsCount.textContent = `${totalUsers} utilisateur(s) trouvé(s)`;
    }
}

// Validation du formulaire d'ajout d'utilisateur
document.addEventListener('DOMContentLoaded', function() {
    const addUserForm = document.getElementById('addUserForm');
    if (addUserForm) {
        addUserForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const bio = document.getElementById('bio').value.trim();
            
            if (!username || !email || !password) {
                showMessage('Tous les champs obligatoires doivent être remplis', false);
                return false;
            }
            
            if (password.length < 6) {
                showMessage('Le mot de passe doit contenir au moins 6 caractères', false);
                return false;
            }
            
            if (!email.includes('@')) {
                showMessage('Veuillez entrer une adresse email valide', false);
                return false;
            }
            
            // Envoyer les données en AJAX
            const formData = new FormData();
            formData.append('action', 'add_user');
            formData.append('username', username);
            formData.append('email', email);
            formData.append('password', password);
            formData.append('bio', bio);
            
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
                        closeAddUserModal();
                        // Rafraîchir la page pour afficher le nouvel utilisateur
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showMessage('Erreur lors de l\'ajout de l\'utilisateur', false);
            });
        });
    }
});

// Fermer les modals en cliquant à l'extérieur
window.onclick = function(event) {
    const addModal = document.getElementById('addUserModal');
    const detailModal = document.getElementById('userDetailModal');
    
    if (event.target === addModal) {
        closeAddUserModal();
    }
    if (event.target === detailModal) {
        closeUserDetailModal();
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

.users-table-container {
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
    border-bottom: 2px solid #f1f5f9;
}

.table-header h2 {
    margin: 0;
    color: #1e293b;
    font-size: 1.4rem;
}

.results-count {
    color: #64748b;
    font-size: 14px;
}

.users-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.user-card {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s ease;
}

.user-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.user-card.fade-out {
    opacity: 0;
    transform: translateY(-10px);
}

.user-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.user-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-info h3 {
    margin: 0 0 5px 0;
    color: #1e293b;
    font-size: 1.1rem;
}

.user-email {
    color: #64748b;
    margin: 0;
    font-size: 0.9rem;
}

.user-stats {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #64748b;
    font-size: 0.9rem;
}

.stat-item i {
    color: var(--primary);
}

.user-meta {
    margin-bottom: 15px;
}

.join-date {
    color: #64748b;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 5px;
}

.user-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
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
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 0;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.modal-content.large {
    max-width: 800px;
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
    color: #1e293b;
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
.form-group textarea {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s;
    box-sizing: border-box;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding: 20px 25px;
    border-top: 1px solid #e5e7eb;
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
    
    .users-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .user-stats {
        flex-direction: column;
        gap: 8px;
    }
    
    .user-actions {
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
</style> 