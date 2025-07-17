<?php
session_start();
require_once '../menu.php';

// Récupération des utilisateurs avec pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$order = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC';

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
    ORDER BY u.created_at $order
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
        
        // Si AJAX et suppression multiple, désactiver tout output HTML
        if ($action === 'delete_multiple' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            while (ob_get_level()) ob_end_clean(); // Vide tous les buffers
        }
        
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
                case 'delete_multiple':
                    // Suppression multiple
                    if (!isset($_POST['user_ids']) || !is_array($_POST['user_ids'])) {
                        $message = "Aucun utilisateur sélectionné";
                        // Pour AJAX, on retourne JSON
                        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => false, 'message' => $message]);
                            exit;
                        }
                        break;
                    }
                    $userIds = array_map('intval', $_POST['user_ids']);
                    if (empty($userIds)) {
                        $message = "Aucun utilisateur sélectionné";
                        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => false, 'message' => $message]);
                            exit;
                        }
                        break;
                    }
                    // Les modérateurs et administrateurs peuvent supprimer
                    if ($role === 'Administrateur' || $role === 'Modérateur') {
                        // Empêcher de se supprimer soi-même (utiliser l'ID de session)
                        $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
                        if ($currentUserId && in_array($currentUserId, $userIds)) {
                            $message = "Vous ne pouvez pas vous supprimer vous-même.";
                            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                                header('Content-Type: application/json');
                                echo json_encode(['success' => false, 'message' => $message]);
                                exit;
                            }
                            break;
                        }
                        // Suppression groupée
                        $in = str_repeat('?,', count($userIds) - 1) . '?';
                        $stmt = $conn->prepare("DELETE FROM users WHERE id IN ($in)");
                        $stmt->execute($userIds);
                        $message = count($userIds) . " utilisateur(s) supprimé(s) avec succès";
                        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => true, 'message' => $message, 'ids' => $userIds]);
                            exit;
                        }
                    } else {
                        $message = "Vous n'avez pas les permissions pour supprimer ces utilisateurs";
                        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => false, 'message' => $message]);
                            exit;
                        }
                    }
                    break;
            }
        } catch (PDOException $e) {
            $message = "Erreur lors de l'opération: " . $e->getMessage();
        }
    }
}
?>
<link rel="stylesheet" href="/assets/css/back-office/users.css">
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

        <!-- Switch d'affichage -->
        <div class="view-switch">
            <button class="view-btn active" id="cardViewBtn" type="button"><i class="fas fa-th"></i> Vue carte</button>
            <button class="view-btn" id="tableViewBtn" type="button"><i class="fas fa-table"></i> Vue tableau</button>
        </div>
        <!-- Liste des utilisateurs -->
        <div class="users-table-container">
            <div class="table-header">
                <h2>Liste des Utilisateurs</h2>
                <div class="table-actions">
                    <span class="results-count"><?= $totalUsers ?> utilisateur(s) trouvé(s)</span>
                    <button id="deleteSelectedBtn" class="btn btn-danger btn-sm" style="display:none; margin-left:15px;" type="button">
                        <i class="fas fa-trash"></i> Supprimer la sélection
                    </button>
                </div>
            </div>
            <!-- Vue carte -->
            <div class="users-grid" id="cardView">
                <?php foreach ($users as $user): ?>
                    <div class="user-card" data-user-id="<?= $user['id'] ?>">
                        <div class="user-header">
                            <div class="user-avatar">
                                <img src="<?= !empty($user['profile_picture']) ? '../../uploads/' . $user['profile_picture'] : '../../uploads/default_profile.jpg' ?>" 
                                     alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
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
            <!-- Vue tableau -->
            <div class="users-table-view" id="tableView" style="display:none;">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllUsers"></th>
                            <th>Nom d'utilisateur</th>
                            <th>Email</th>
                            <th>Posts</th>
                            <th>Amis</th>
                            <th>Abonnés</th>
                            <th><button id="sortDateBtn" onclick="changeSortOrder()" style="background:none;border:none;cursor:pointer;font-weight:700;">Inscription</button></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr data-user-id="<?= $user['id'] ?>">
                            <td><input type="checkbox" class="user-checkbox" value="<?= $user['id'] ?>"></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= $user['posts_count'] ?></td>
                            <td><?= $user['friends_count'] ?></td>
                            <td><?= $user['followers_count'] ?></td>
                            <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-info btn-sm" onclick="viewUser(<?= $user['id'] ?>)"><i class="fas fa-eye"></i></button>
                                <?php if ($role === 'Administrateur' || $role === 'Modérateur'): ?>
                                <button type="button" class="btn btn-danger btn-sm" onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')"><i class="fas fa-trash"></i></button>
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
                            <i class="fas fa-chevron-left"></i> Précédent
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
                    // Suppression ligne tableau
                    const userTr = document.querySelector(`tr[data-user-id="${userId}"]`);
                    if (userTr) {
                        userTr.classList.add('fade-out');
                        setTimeout(() => {
                            userTr.remove();
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
                    
                    // Si succès, fermer le modal et ajouter dynamiquement l'utilisateur
                    if (isSuccess) {
                        closeAddUserModal();
                        // Récupérer les champs du formulaire
                        const username = document.getElementById('username').value.trim();
                        const email = document.getElementById('email').value.trim();
                        const bio = document.getElementById('bio').value.trim();
                        const now = new Date();
                        const dateStr = now.toLocaleDateString('fr-FR');
                        // Générer un id temporaire unique (timestamp)
                        const tempId = 'new_' + Date.now();
                        // Ajouter la carte
                        const cardView = document.getElementById('cardView');
                        if (cardView) {
                            const card = document.createElement('div');
                            card.className = 'user-card fade-in';
                            card.setAttribute('data-user-id', tempId);
                            card.innerHTML = `
                                <div class="user-header">
                                    <div class="user-avatar">
                                        <img src="../../uploads/default_profile.jpg" alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
                                    </div>
                                    <div class="user-info">
                                        <h3>${username}</h3>
                                        <p class="user-email">${email}</p>
                                    </div>
                                </div>
                                <div class="user-stats">
                                    <div class="stat-item"><i class="fas fa-pen"></i> <span>0 posts</span></div>
                                    <div class="stat-item"><i class="fas fa-user-friends"></i> <span>0 amis</span></div>
                                    <div class="stat-item"><i class="fas fa-users"></i> <span>0 abonnés</span></div>
                                </div>
                                <div class="user-meta">
                                    <span class="join-date"><i class="fas fa-calendar"></i> Inscrit le ${dateStr}</span>
                                </div>
                                <div class="user-actions">
                                    <button class="btn btn-info btn-sm" onclick="viewUser('${tempId}')"><i class="fas fa-eye"></i> Voir</button>
                                </div>
                            `;
                            cardView.prepend(card);
                            setTimeout(() => card.classList.remove('fade-in'), 350);
                        }
                        // Ajouter la ligne au tableau
                        const tableBody = document.querySelector('.users-table tbody');
                        if (tableBody) {
                            const tr = document.createElement('tr');
                            tr.className = 'fade-in';
                            tr.setAttribute('data-user-id', tempId);
                            tr.innerHTML = `
                                <td><input type="checkbox" class="user-checkbox" value="${tempId}"></td>
                                <td>${username}</td>
                                <td>${email}</td>
                                <td>0</td>
                                <td>0</td>
                                <td>0</td>
                                <td>${dateStr}</td>
                                <td><button class="btn btn-info btn-sm" onclick="viewUser('${tempId}')"><i class="fas fa-eye"></i></button></td>
                            `;
                            tableBody.prepend(tr);
                            setTimeout(() => tr.classList.remove('fade-in'), 350);
                        }
                        updateUserCount();
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

// Switch d'affichage carte/tableau
const cardViewBtn = document.getElementById('cardViewBtn');
const tableViewBtn = document.getElementById('tableViewBtn');
const cardView = document.getElementById('cardView');
const tableView = document.getElementById('tableView');
const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');

// Fonction pour appliquer la vue sauvegardée
function applySavedView() {
    const savedView = localStorage.getItem('usersViewMode');
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
        localStorage.setItem('usersViewMode', 'card');
        applySavedView();
    });
    tableViewBtn.addEventListener('click', function() {
        localStorage.setItem('usersViewMode', 'table');
        applySavedView();
    });
    // Appliquer la vue au chargement
    applySavedView();
}

// Gestion sélection multiple
const selectAllUsers = document.getElementById('selectAllUsers');
const userCheckboxes = document.querySelectorAll('.user-checkbox');

function updateDeleteSelectedBtn() {
    const checked = document.querySelectorAll('.user-checkbox:checked');
    deleteSelectedBtn.style.display = (checked.length > 0) ? '' : 'none';
}

if (selectAllUsers) {
    selectAllUsers.addEventListener('change', function() {
        document.querySelectorAll('.user-checkbox').forEach(cb => {
            cb.checked = selectAllUsers.checked;
        });
        updateDeleteSelectedBtn();
    });
}
document.querySelectorAll('.user-checkbox').forEach(cb => {
    cb.addEventListener('change', updateDeleteSelectedBtn);
});

if (deleteSelectedBtn) {
    deleteSelectedBtn.addEventListener('click', function() {
        const checked = Array.from(document.querySelectorAll('.user-checkbox:checked'));
        if (checked.length === 0) return;
        if (!confirm(`Supprimer ${checked.length} utilisateur(s) sélectionné(s) ? Cette action est irréversible.`)) return;
        checked.forEach(cb => {
            const userId = cb.value;
            const username = cb.closest('tr') ? cb.closest('tr').querySelector('td:nth-child(2)').textContent.trim() : '';
            deleteUser(userId, username);
        });
        // Décocher tout après suppression
        if (selectAllUsers) selectAllUsers.checked = false;
        updateDeleteSelectedBtn();
    });
}

// Ajout du tri ASC/DESC sur la colonne Inscription
const inscriptionTh = document.querySelector('.users-table th:nth-child(7)');
if (inscriptionTh) {
    let order = localStorage.getItem('usersSortOrder') || 'desc';
    function updateSortIcon() {
        inscriptionTh.innerHTML = `Inscription <i class="fas fa-sort-${order === 'asc' ? 'up' : 'down'}"></i>`;
    }
    updateSortIcon();
    inscriptionTh.style.cursor = 'pointer';
    inscriptionTh.onclick = function() {
        order = (order === 'asc') ? 'desc' : 'asc';
        localStorage.setItem('usersSortOrder', order);
        updateSortIcon();
        // Recharger la page avec le bon paramètre
        const url = new URL(window.location.href);
        url.searchParams.set('order', order);
        window.location.href = url.toString();
    };
}
(function() {
    const order = localStorage.getItem('usersSortOrder');
    if (order && order !== 'desc') {
        const url = new URL(window.location.href);
        if (url.searchParams.get('order') !== order) {
            url.searchParams.set('order', order);
            window.location.href = url.toString();
        }
    }
})();

let sortOrder = localStorage.getItem('users_sort_order') || (new URLSearchParams(window.location.search).get('order') || 'desc');
function updateSortUI() {
    const sortBtn = document.getElementById('sortDateBtn');
    if (sortBtn) {
        sortBtn.innerHTML = sortOrder === 'asc'
            ? '<i class="fas fa-sort-amount-up-alt"></i> Inscription <span style="font-size:12px">(ASC)</span>'
            : '<i class="fas fa-sort-amount-down-alt"></i> Inscription <span style="font-size:12px">(DESC)</span>';
    }
}
function changeSortOrder() {
    sortOrder = sortOrder === 'asc' ? 'desc' : 'asc';
    localStorage.setItem('users_sort_order', sortOrder);
    const params = new URLSearchParams(window.location.search);
    params.set('order', sortOrder);
    window.location.search = params.toString();
}
document.addEventListener('DOMContentLoaded', updateSortUI);
</script>
