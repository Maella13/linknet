<?php
require_once '../menu.php';

// Récupération des admins avec pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Construction de la requête avec recherche
$whereClause = '';
$params = [];
if (!empty($search)) {
    $whereClause = "WHERE username LIKE ? OR email LIKE ? OR role LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

// Comptage total
$countStmt = $conn->prepare("SELECT COUNT(*) FROM admins $whereClause");
$countStmt->execute($params);
$totalAdmins = $countStmt->fetchColumn();
$totalPages = ceil($totalAdmins / $limit);

// Récupération des admins
$stmt = $conn->prepare("
    SELECT * FROM admins 
    $whereClause
    ORDER BY created_at DESC
    LIMIT $limit OFFSET $offset
");

// Exécution avec les paramètres de recherche seulement
$stmt->execute($params);
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement des actions selon le rôle
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'add_admin':
                    // Seuls les administrateurs peuvent ajouter des admins
                    if ($role === 'Administrateur') {
                        $username = trim($_POST['username']);
                        $email = trim($_POST['email']);
                        $password = $_POST['password'];
                        $adminRole = $_POST['role'];
                        
                        // Validation
                        if (empty($username) || empty($email) || empty($password)) {
                            $message = "Tous les champs obligatoires doivent être remplis";
                            break;
                        }
                        
                        if (!in_array($adminRole, ['Administrateur', 'Modérateur'])) {
                            $message = "Rôle invalide";
                            break;
                        }
                        
                        // Vérifier si l'email existe déjà
                        $checkStmt = $conn->prepare("SELECT id FROM admins WHERE email = ?");
                        $checkStmt->execute([$email]);
                        if ($checkStmt->fetch()) {
                            $message = "Cette adresse email est déjà utilisée";
                            break;
                        }
                        
                        // Vérifier si le nom d'utilisateur existe déjà
                        $checkStmt = $conn->prepare("SELECT id FROM admins WHERE username = ?");
                        $checkStmt->execute([$username]);
                        if ($checkStmt->fetch()) {
                            $message = "Ce nom d'utilisateur est déjà pris";
                            break;
                        }
                        
                        // Hasher le mot de passe
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Insérer le nouvel admin
                        $insertStmt = $conn->prepare("INSERT INTO admins (username, email, password, role) VALUES (?, ?, ?, ?)");
                        $result = $insertStmt->execute([$username, $email, $hashedPassword, $adminRole]);
                        
                        if ($result) {
                            $message = "Administrateur ajouté avec succès";
                        } else {
                            $message = "Erreur lors de l'insertion en base de données";
                        }
                    } else {
                        $message = "Vous n'avez pas les permissions pour ajouter un administrateur";
                    }
                    break;
                    
                case 'delete':
                    // Vérifier que admin_id existe pour la suppression
                    if (!isset($_POST['admin_id'])) {
                        $message = "ID administrateur manquant";
                        break;
                    }
                    
                    $adminId = (int)$_POST['admin_id'];
                    
                    // Seuls les administrateurs peuvent supprimer des admins
                    if ($role === 'Administrateur') {
                        // Empêcher la suppression de soi-même
                        if ($adminId === $_SESSION['admin']['id']) {
                            $message = "Vous ne pouvez pas supprimer votre propre compte";
                            break;
                        }
                        
                        $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
                        $stmt->execute([$adminId]);
                        $message = "Administrateur supprimé avec succès";
                    } else {
                        $message = "Vous n'avez pas les permissions pour supprimer un administrateur";
                    }
                    break;
                    
                case 'change_role':
                    // Vérifier que admin_id existe
                    if (!isset($_POST['admin_id'])) {
                        $message = "ID administrateur manquant";
                        break;
                    }
                    
                    $adminId = (int)$_POST['admin_id'];
                    $newRole = $_POST['new_role'];
                    
                    // Seuls les administrateurs peuvent changer les rôles
                    if ($role === 'Administrateur') {
                        if (!in_array($newRole, ['Administrateur', 'Modérateur'])) {
                            $message = "Rôle invalide";
                            break;
                        }
                        
                        // Empêcher le changement de son propre rôle
                        if ($adminId === $_SESSION['admin']['id']) {
                            $message = "Vous ne pouvez pas modifier votre propre rôle";
                            break;
                        }
                        
                        $stmt = $conn->prepare("UPDATE admins SET role = ? WHERE id = ?");
                        $stmt->execute([$newRole, $adminId]);
                        $message = "Rôle modifié avec succès";
                    } else {
                        $message = "Vous n'avez pas les permissions pour modifier les rôles";
                    }
                    break;
            }
        } catch (PDOException $e) {
            $message = "Erreur lors de l'opération: " . $e->getMessage();
        }
    }
}
?>

<link rel="stylesheet" href="/assets/css/back-office/admins.css">
<div class="main-content">
    <div class="dashboard-container">
        <div class="header">
            <h1>Gestion des Administrateurs</h1>
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
                        <input type="text" name="search" placeholder="Rechercher par nom, email ou rôle..." 
                               value="<?= htmlspecialchars($search) ?>" class="search-input">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <?php if ($role === 'Administrateur'): ?>
                    <button class="btn btn-primary" onclick="showAddAdminModal()">
                        <i class="fas fa-plus"></i>
                        Ajouter un administrateur
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Administrateurs</h3>
                    <div class="stat-value"><?= $totalAdmins ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-crown"></i>
                </div>
                <div class="stat-content">
                    <h3>Administrateurs</h3>
                    <div class="stat-value">
                        <?php
                        $adminStmt = $conn->query("SELECT COUNT(*) FROM admins WHERE role = 'Administrateur'");
                        echo $adminStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-cog"></i>
                </div>
                <div class="stat-content">
                    <h3>Modérateurs</h3>
                    <div class="stat-value">
                        <?php
                        $modStmt = $conn->query("SELECT COUNT(*) FROM admins WHERE role = 'Modérateur'");
                        echo $modStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des administrateurs -->
        <div class="users-table-container">
            <div class="table-header">
                <h2>Liste des Administrateurs</h2>
                <div class="table-actions">
                    <span class="results-count"><?= $totalAdmins ?> administrateur(s) trouvé(s)</span>
                </div>
            </div>

            <div class="users-grid">
                <?php foreach ($admins as $admin): ?>
                    <div class="user-card" data-admin-id="<?= $admin['id'] ?>">
                        <div class="user-header">
                            <div class="user-avatar">
                                <i class="fas <?= $admin['role'] === 'Administrateur' ? 'fa-crown' : 'fa-user-cog' ?>" style="font-size: 2rem; color: <?= $admin['role'] === 'Administrateur' ? '#fbbf24' : '#6366f1' ?>;"></i>
                            </div>
                            <div class="user-info">
                                <h3><?= htmlspecialchars($admin['username']) ?></h3>
                                <p class="user-email"><?= htmlspecialchars($admin['email']) ?></p>
                                <span class="role-badge <?= $admin['role'] === 'Administrateur' ? 'role-admin' : 'role-moderator' ?>">
                                    <?= htmlspecialchars($admin['role']) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="user-meta">
                            <span class="join-date">
                                <i class="fas fa-calendar"></i>
                                Créé le <?= date('d/m/Y', strtotime($admin['created_at'])) ?>
                            </span>
                        </div>
                        
                        <div class="user-actions">
                            <button class="btn btn-info btn-sm" onclick="viewAdmin(<?= $admin['id'] ?>)">
                                <i class="fas fa-eye"></i>
                                Voir
                            </button>
                            
                            <?php if ($role === 'Administrateur' && $admin['id'] !== $_SESSION['admin']['id']): ?>
                                <button class="btn btn-warning btn-sm" onclick="showChangeRoleModal(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username']) ?>', '<?= htmlspecialchars($admin['role']) ?>')">
                                    <i class="fas fa-edit"></i>
                                    Modifier rôle
                                </button>
                                
                                <button type="button" class="btn btn-danger btn-sm" onclick="deleteAdmin(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username']) ?>')">
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

<!-- Modal pour ajouter un administrateur -->
<div id="addAdminModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Ajouter un nouvel administrateur</h2>
            <span class="close" onclick="closeAddAdminModal()">&times;</span>
        </div>
        <form id="addAdminForm" method="POST">
            <input type="hidden" name="action" value="add_admin">
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
                <label for="role">Rôle *</label>
                <select id="role" name="role" required>
                    <option value="">Sélectionner un rôle</option>
                    <option value="Modérateur">Modérateur</option>
                    <option value="Administrateur">Administrateur</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="button" onclick="closeAddAdminModal()" class="btn btn-secondary">Annuler</button>
                <button type="submit" class="btn btn-primary">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal pour modifier le rôle -->
<div id="changeRoleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Modifier le rôle</h2>
            <span class="close" onclick="closeChangeRoleModal()">&times;</span>
        </div>
        <form id="changeRoleForm" method="POST">
            <input type="hidden" name="action" value="change_role">
            <input type="hidden" id="changeRoleAdminId" name="admin_id">
            <div class="form-group">
                <label for="newRole">Nouveau rôle *</label>
                <select id="newRole" name="new_role" required>
                    <option value="">Sélectionner un rôle</option>
                    <option value="Modérateur">Modérateur</option>
                    <option value="Administrateur">Administrateur</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="button" onclick="closeChangeRoleModal()" class="btn btn-secondary">Annuler</button>
                <button type="submit" class="btn btn-warning">Modifier</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal pour voir les détails d'un administrateur -->
<div id="adminDetailModal" class="modal">
    <div class="modal-content large">
        <div class="modal-header">
            <h2>Détails de l'administrateur</h2>
            <span class="close" onclick="closeAdminDetailModal()">&times;</span>
        </div>
        <div id="adminDetailContent">
            <!-- Le contenu sera chargé dynamiquement -->
        </div>
    </div>
</div>

<script>
// Fonctions pour les modals
function showAddAdminModal() {
    document.getElementById('addAdminModal').style.display = 'block';
}

function closeAddAdminModal() {
    document.getElementById('addAdminModal').style.display = 'none';
    document.getElementById('addAdminForm').reset();
}

function showChangeRoleModal(adminId, username, currentRole) {
    document.getElementById('changeRoleAdminId').value = adminId;
    document.getElementById('newRole').value = currentRole;
    document.getElementById('changeRoleModal').style.display = 'block';
}

function closeChangeRoleModal() {
    document.getElementById('changeRoleModal').style.display = 'none';
    document.getElementById('changeRoleForm').reset();
}

function showAdminDetailModal() {
    document.getElementById('adminDetailModal').style.display = 'block';
}

function closeAdminDetailModal() {
    document.getElementById('adminDetailModal').style.display = 'none';
}

// Fonction pour voir les détails d'un administrateur
function viewAdmin(adminId) {
    fetch(`admin_detail.php?id=${adminId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('adminDetailContent').innerHTML = html;
            showAdminDetailModal();
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors du chargement des détails');
        });
}

// Fonction pour supprimer un administrateur
function deleteAdmin(adminId, username) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer l'administrateur "${username}" ?\n\nCette action est irréversible.`)) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('admin_id', adminId);
        
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
                
                // Si succès, supprimer la carte admin de l'interface
                if (isSuccess) {
                    const adminCard = document.querySelector(`[data-admin-id="${adminId}"]`);
                    if (adminCard) {
                        adminCard.classList.add('fade-out');
                        setTimeout(() => {
                            adminCard.remove();
                            updateAdminCount();
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

// Fonction pour mettre à jour le compteur d'administrateurs
function updateAdminCount() {
    const adminCards = document.querySelectorAll('.user-card');
    const totalAdmins = adminCards.length;
    
    // Mettre à jour les statistiques
    const statValues = document.querySelectorAll('.stat-value');
    if (statValues.length >= 1) {
        statValues[0].textContent = totalAdmins; // Total administrateurs
    }
    
    // Mettre à jour le compteur de résultats
    const resultsCount = document.querySelector('.results-count');
    if (resultsCount) {
        resultsCount.textContent = `${totalAdmins} administrateur(s) trouvé(s)`;
    }
}

// Validation du formulaire d'ajout d'administrateur
document.addEventListener('DOMContentLoaded', function() {
    const addAdminForm = document.getElementById('addAdminForm');
    if (addAdminForm) {
        addAdminForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const role = document.getElementById('role').value;
            
            if (!username || !email || !password || !role) {
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
            formData.append('action', 'add_admin');
            formData.append('username', username);
            formData.append('email', email);
            formData.append('password', password);
            formData.append('role', role);
            
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
                        closeAddAdminModal();
                        // Rafraîchir la page pour afficher le nouvel admin
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showMessage('Erreur lors de l\'ajout de l\'administrateur', false);
            });
        });
    }
    
    // Validation du formulaire de changement de rôle
    const changeRoleForm = document.getElementById('changeRoleForm');
    if (changeRoleForm) {
        changeRoleForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const newRole = document.getElementById('newRole').value;
            
            if (!newRole) {
                showMessage('Veuillez sélectionner un rôle', false);
                return false;
            }
            
            // Envoyer les données en AJAX
            const formData = new FormData(this);
            
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
                    
                    // Si succès, fermer le modal et rafraîchir la page
                    if (isSuccess) {
                        closeChangeRoleModal();
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showMessage('Erreur lors de la modification du rôle', false);
            });
        });
    }
});

// Fermer les modals en cliquant à l'extérieur
window.onclick = function(event) {
    const addModal = document.getElementById('addAdminModal');
    const changeRoleModal = document.getElementById('changeRoleModal');
    const detailModal = document.getElementById('adminDetailModal');
    
    if (event.target === addModal) {
        closeAddAdminModal();
    }
    if (event.target === changeRoleModal) {
        closeChangeRoleModal();
    }
    if (event.target === detailModal) {
        closeAdminDetailModal();
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