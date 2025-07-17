<?php
require_once "../menu.php";

if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../../config/database.php";

if (!isset($_SESSION["admin"])) {
    header("Location: login.php");
    exit();
}

$adminId = $_SESSION["admin"]["id"] ?? null;
$role = $_SESSION["admin"]["role"] ?? 'Modérateur';

if (!$adminId) {
    header("Location: login.php");
    exit();
}

// Initialisation
$error = $success = '';

// Récupération des données admin
try {
    $stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        header("Location: login.php");
        exit();
    }
} catch (PDOException $e) {
    $error = "Erreur de base de données";
}

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mise à jour des infos
    if (isset($_POST['update_info'])) {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($username) || empty($email)) {
            $error = "Tous les champs sont requis";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Email invalide";
        } else {
            try {
                $stmt = $conn->prepare("UPDATE admins SET username = ?, email = ? WHERE id = ?");
                $stmt->execute([$username, $email, $adminId]);
                
                $_SESSION['admin']['username'] = $username;
                $_SESSION['admin']['email'] = $email;
                $admin['username'] = $username;
                $admin['email'] = $email;
                
                $success = "Informations mises à jour avec succès";
            } catch (PDOException $e) {
                $error = "Erreur lors de la mise à jour";
            }
        }
    }
    
    // Changement de mot de passe
    if (isset($_POST['update_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if (empty($current) || empty($new) || empty($confirm)) {
            $error = "Tous les champs sont requis";
        } elseif (!password_verify($current, $admin['password'])) {
            $error = "Mot de passe actuel incorrect";
        } elseif ($new !== $confirm) {
            $error = "Les mots de passe ne correspondent pas";
        } elseif (strlen($new) < 8) {
            $error = "Le mot de passe doit contenir au moins 8 caractères";
        } else {
            try {
                $hash = password_hash($new, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
                $stmt->execute([$hash, $adminId]);
                $success = "Mot de passe mis à jour avec succès";
            } catch (PDOException $e) {
                $error = "Erreur lors de la mise à jour du mot de passe";
            }
        }
    }
    
    // Suppression de compte (seulement pour les administrateurs)
    if (isset($_POST['delete_account']) && $role === 'Administrateur') {
        $password = $_POST['delete_password'] ?? '';
        
        if (empty($password)) {
            $error = "Veuillez entrer votre mot de passe";
        } elseif (!password_verify($password, $admin['password'])) {
            $error = "Mot de passe incorrect";
        } else {
            try {
                $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
                $stmt->execute([$adminId]);
                session_destroy();
                header("Location: login.php");
                exit();
            } catch (PDOException $e) {
                $error = "Erreur lors de la suppression";
            }
        }
    }
}

function calculateTimeSince($date) {
    $now = new DateTime();
    $created = new DateTime($date);
    $interval = $now->diff($created);
    
    if ($interval->y > 0) {
        return $interval->y . ' an' . ($interval->y > 1 ? 's' : '');
    } elseif ($interval->m > 0) {
        return $interval->m . ' mois';
    } else {
        return $interval->d . ' jour' . ($interval->d > 1 ? 's' : '');
    }
}
?>
<link rel="stylesheet" href="/assets/css/back-office/auth/profile.css">
<div class="main-content">
    <div class="profile-container">
        <div class="header">
            <h1>Profil Administrateur</h1>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Carte de profil -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="profile-info">
                    <h2><?= htmlspecialchars($admin['username']) ?></h2>
                    <div class="profile-meta">
                        <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($admin['email']) ?></span>
                        <span><i class="fas fa-user-tag"></i> <?= htmlspecialchars($admin['role']) ?></span>
                        <span><i class="fas fa-calendar-alt"></i> Membre depuis <?= calculateTimeSince($admin['created_at']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informations personnelles -->
        <div class="profile-section">
            <div class="section-title">
                <i class="fas fa-user-cog"></i>
                Informations personnelles
            </div>
            <form method="POST" class="profile-form">
                <input type="hidden" name="update_info" value="1">
                <div class="form-group">
                    <label class="form-label">Nom d'utilisateur</label>
                    <div class="input-wrapper">
                        <input type="text" name="username" class="form-input" value="<?= htmlspecialchars($admin['username']) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <div class="input-wrapper">
                        <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($admin['email']) ?>" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Mettre à jour
                </button>
            </form>
        </div>

        <!-- Sécurité du compte -->
        <div class="profile-section">
            <div class="section-title">
                <i class="fas fa-key"></i>
                Sécurité du compte
            </div>
            <form method="POST" class="profile-form">
                <input type="hidden" name="update_password" value="1">
                <div class="form-group">
                    <label class="form-label">Mot de passe actuel</label>
                    <div class="input-wrapper password-wrapper">
                        <input type="password" name="current_password" id="current_password" class="form-input" required>
                        <i class="fas fa-eye password-toggle" data-target="current_password"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Nouveau mot de passe</label>
                    <div class="input-wrapper password-wrapper">
                        <input type="password" name="new_password" id="new_password" class="form-input" required minlength="8">
                        <i class="fas fa-eye password-toggle" data-target="new_password"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirmation</label>
                    <div class="input-wrapper password-wrapper">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-input" required minlength="8">
                        <i class="fas fa-eye password-toggle" data-target="confirm_password"></i>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-key"></i>
                    Changer le mot de passe
                </button>
            </form>
        </div>

        <!-- Zone dangereuse (seulement pour les administrateurs) -->
        <?php if ($role === 'Administrateur'): ?>
        <div class="profile-section danger-zone">
            <div class="section-title">
                <i class="fas fa-exclamation-triangle"></i>
                Zone dangereuse
            </div>
            <div class="warning-box">
                <p>
                    <i class="fas fa-warning"></i>
                    La suppression de compte est irréversible. Toutes vos données seront perdues définitivement.
                </p>
            </div>
            <button id="deleteBtn" class="btn btn-danger">
                <i class="fas fa-trash"></i>
                Supprimer mon compte
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de suppression (seulement pour les administrateurs) -->
<?php if ($role === 'Administrateur'): ?>
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h3>Confirmation de suppression</h3>
        <p>Pour confirmer la suppression de votre compte, veuillez entrer votre mot de passe :</p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="delete_account" value="1">
            <div class="form-group">
                <div class="input-wrapper password-wrapper">
                    <input type="password" name="delete_password" id="delete_password" class="form-input" required>
                    <i class="fas fa-eye password-toggle" data-target="delete_password"></i>
                </div>
            </div>
            <button type="submit" class="btn btn-danger">
                <i class="fas fa-trash"></i>
                Confirmer la suppression
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// Toggle password visibility
document.querySelectorAll('.password-toggle').forEach(icon => {
    icon.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });
});

// Auto-hide success messages after 3 seconds
document.addEventListener('DOMContentLoaded', function() {
    const successMessages = document.querySelectorAll('.alert-success');
    const errorMessages = document.querySelectorAll('.alert-danger');
    
    // Auto-hide success messages
    successMessages.forEach(function(message) {
        setTimeout(function() {
            message.style.transition = 'opacity 0.5s ease-out';
            message.style.opacity = '0';
            setTimeout(function() {
                message.remove();
            }, 500);
        }, 3000);
    });
    
    // Auto-hide error messages
    errorMessages.forEach(function(message) {
        setTimeout(function() {
            message.style.transition = 'opacity 0.5s ease-out';
            message.style.opacity = '0';
            setTimeout(function() {
                message.remove();
            }, 500);
        }, 3000);
    });
});

// Gestion de la suppression (seulement pour les administrateurs)
<?php if ($role === 'Administrateur'): ?>
const deleteBtn = document.getElementById('deleteBtn');
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');

if (deleteBtn && deleteModal) {
    deleteBtn.addEventListener('click', () => {
        deleteModal.style.display = 'block';
    });

    document.querySelector('.close-modal').addEventListener('click', () => {
        deleteModal.style.display = 'none';
    });

    window.addEventListener('click', (event) => {
        if (event.target === deleteModal) {
            deleteModal.style.display = 'none';
        }
    });
}
<?php endif; ?>
</script> 