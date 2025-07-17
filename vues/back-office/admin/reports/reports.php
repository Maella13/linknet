<?php
require_once "../menu.php";

// Gestion des actions POST
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action) {
        try {
            switch ($action) {
                case 'add_report':
                    if ($role === 'Administrateur') {
                        $reporter_id = (int)($_POST['reporter_id'] ?? 0);
                        $report_type = $_POST['report_type'] ?? '';
                        $reason = trim($_POST['reason'] ?? '');
                        
                        if ($reporter_id <= 0) {
                            $message = "Veuillez sélectionner un utilisateur signalant";
                            break;
                        }
                        
                        if (!in_array($report_type, ['user', 'post'])) {
                            $message = "Type de signalement invalide";
                            break;
                        }
                        
                        if (empty($reason)) {
                            $message = "La raison est obligatoire";
                            break;
                        }
                        
                        // Récupérer l'ID signalé selon le type
                        $reported_id = 0;
                        if ($report_type === 'user') {
                            $reported_id = (int)($_POST['reported_user_id'] ?? 0);
                            if ($reported_id <= 0) {
                                $message = "Veuillez sélectionner un utilisateur signalé";
                                break;
                            }
                        } else if ($report_type === 'post') {
                            $reported_id = (int)($_POST['reported_post_id'] ?? 0);
                            if ($reported_id <= 0) {
                                $message = "Veuillez sélectionner un post signalé";
                                break;
                            }
                        }
                        
                        // Vérifier si l'utilisateur signalant existe
                        $checkReporterStmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
                        $checkReporterStmt->execute([$reporter_id]);
                        if (!$checkReporterStmt->fetch()) {
                            $message = "Utilisateur signalant introuvable";
                            break;
                        }
                        
                        // Vérifier si l'élément signalé existe
                        if ($report_type === 'user') {
                            $checkReportedStmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
                            $checkReportedStmt->execute([$reported_id]);
                            if (!$checkReportedStmt->fetch()) {
                                $message = "Utilisateur signalé introuvable";
                                break;
                            }
                        } else if ($report_type === 'post') {
                            $checkReportedStmt = $conn->prepare("SELECT id FROM posts WHERE id = ?");
                            $checkReportedStmt->execute([$reported_id]);
                            if (!$checkReportedStmt->fetch()) {
                                $message = "Post signalé introuvable";
                                break;
                            }
                        }
                        
                        $insertStmt = $conn->prepare("INSERT INTO reports (reporter_id, reported_id, report_type, reason, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
                        if ($insertStmt->execute([$reporter_id, $reported_id, $report_type, $reason])) {
                            $message = "Signalement ajouté avec succès";
                        } else {
                            $message = "Erreur lors de l'ajout";
                        }
                    } else {
                        $message = "Vous n'avez pas les permissions pour ajouter un signalement";
                    }
                    break;
                    
                case 'update_status':
                    if (!isset($_POST['report_id']) || !isset($_POST['new_status'])) {
                        $message = "Données manquantes";
                        break;
                    }
                    $reportId = (int)$_POST['report_id'];
                    $newStatus = $_POST['new_status'];
                    
                    if (!in_array($newStatus, ['pending', 'reviewed'])) {
                        $message = "Statut invalide";
                        break;
                    }
                    
                    if ($role === 'Administrateur' || $role === 'Modérateur') {
                        $stmt = $conn->prepare("UPDATE reports SET status = ? WHERE id = ?");
                        $stmt->execute([$newStatus, $reportId]);
                        $message = "Statut mis à jour avec succès";
                    } else {
                        $message = "Vous n'avez pas les permissions pour modifier un signalement";
                    }
                    break;
                    
                case 'delete':
                    if (!isset($_POST['report_id'])) {
                        $message = "ID signalement manquant";
                        break;
                    }
                    $reportId = (int)$_POST['report_id'];
                    if ($role === 'Administrateur' || $role === 'Modérateur') {
                        $stmt = $conn->prepare("DELETE FROM reports WHERE id = ?");
                        $stmt->execute([$reportId]);
                        $message = "Signalement supprimé avec succès";
                    } else {
                        $message = "Vous n'avez pas les permissions pour supprimer un signalement";
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
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

$whereClause = '';
$params = [];
$conditions = [];

if (!empty($search)) {
    $conditions[] = "(r.reason LIKE ? OR u1.username LIKE ? OR u2.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_type)) {
    $conditions[] = "r.report_type = ?";
    $params[] = $filter_type;
}

if (!empty($filter_status)) {
    $conditions[] = "r.status = ?";
    $params[] = $filter_status;
}

if (!empty($conditions)) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

$countQuery = "SELECT COUNT(*) FROM reports r 
               LEFT JOIN users u1 ON r.reporter_id = u1.id 
               LEFT JOIN users u2 ON r.reported_id = u2.id 
               $whereClause";
$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $limit);

$query = "SELECT r.*, 
                 u1.username as reporter_username, u1.profile_picture as reporter_picture,
                 u2.username as reported_username, u2.profile_picture as reported_picture,
                 p.content as post_content, p.created_at as post_created_at, u3.username as post_author
          FROM reports r 
          LEFT JOIN users u1 ON r.reporter_id = u1.id 
          LEFT JOIN users u2 ON r.reported_id = u2.id AND r.report_type = 'user'
          LEFT JOIN posts p ON r.reported_id = p.id AND r.report_type = 'post'
          LEFT JOIN users u3 ON p.user_id = u3.id
          $whereClause
          ORDER BY r.created_at DESC 
          LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération de tous les utilisateurs pour le select d'ajout
$usersQuery = "SELECT id, username FROM users ORDER BY username";
$usersList = $conn->query($usersQuery)->fetchAll(PDO::FETCH_ASSOC);

// Récupération de tous les posts pour le select d'ajout
$postsQuery = "SELECT p.id, p.content, p.created_at, u.username as author_name 
               FROM posts p 
               LEFT JOIN users u ON p.user_id = u.id 
               ORDER BY p.created_at DESC";
$postsList = $conn->query($postsQuery)->fetchAll(PDO::FETCH_ASSOC);
?>
<link rel="stylesheet" href="/assets/css/back-office/reports.css">

<div class="main-content">
    <div class="dashboard-container">
        <div class="header">
            <h1>Gestion des Signalements</h1>
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
                        <input type="text" name="search" placeholder="Rechercher par raison ou utilisateur..." 
                               value="<?= htmlspecialchars($search) ?>" class="search-input">
                        <select name="type" class="filter-select">
                            <option value="">Tous les types</option>
                            <option value="user" <?= $filter_type === 'user' ? 'selected' : '' ?>>Utilisateur</option>
                            <option value="post" <?= $filter_type === 'post' ? 'selected' : '' ?>>Post</option>
                        </select>
                        <select name="status" class="filter-select">
                            <option value="">Tous les statuts</option>
                            <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>En attente</option>
                            <option value="reviewed" <?= $filter_status === 'reviewed' ? 'selected' : '' ?>>Examiné</option>
                        </select>
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <?php if ($role === 'Administrateur'): ?>
                    <button class="btn btn-primary" onclick="showAddReportModal()">
                        <i class="fas fa-plus"></i>
                        Ajouter un signalement
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-flag"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Signalements</h3>
                    <div class="stat-value"><?= $total ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3>En Attente</h3>
                    <div class="stat-value">
                        <?php
                        $pendingStmt = $conn->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'");
                        echo $pendingStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>Examinés</h3>
                    <div class="stat-value">
                        <?php
                        $reviewedStmt = $conn->query("SELECT COUNT(*) FROM reports WHERE status = 'reviewed'");
                        echo $reviewedStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3>Utilisateurs Signalés</h3>
                    <div class="stat-value">
                        <?php
                        $usersStmt = $conn->query("SELECT COUNT(DISTINCT reported_id) FROM reports");
                        echo $usersStmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des signalements -->
        <div class="reports-table-container">
            <div class="table-header">
                <h2>Liste des Signalements</h2>
                <div class="table-actions">
                    <span class="results-count"><?= $total ?> signalement(s) trouvé(s)</span>
                </div>
            </div>

            <div class="reports-grid">
                <?php foreach ($reports as $report): ?>
                    <div class="report-card <?= $report['status'] === 'pending' ? 'pending' : 'reviewed' ?>" data-report-id="<?= $report['id'] ?>">
                        <div class="report-header">
                            <div class="report-avatar">
                                <img src="<?= !empty($report['reporter_picture']) ? '../../uploads/' . $report['reporter_picture'] : '../../uploads/default_profile.jpg' ?>" 
                                     alt="Avatar" onerror="this.src='../../uploads/default_profile.jpg'">
                            </div>
                            <div class="report-info">
                                <h3><?= htmlspecialchars($report['reporter_username']) ?></h3>
                                <p class="report-date"><?= date('d/m/Y H:i', strtotime($report['created_at'])) ?></p>
                            </div>
                            <div class="report-status <?= $report['status'] ?>">
                                <i class="fas <?= $report['status'] === 'pending' ? 'fa-clock' : 'fa-check-circle' ?>"></i>
                            </div>
                        </div>
                        
                        <div class="report-content">
                            <div class="report-type">
                                <span class="type-badge type-<?= $report['report_type'] ?>">
                                    <?= $report['report_type'] === 'user' ? 'Utilisateur' : 'Post' ?>
                                </span>
                            </div>
                            
                            <div class="report-details">
                                <?php if ($report['report_type'] === 'user'): ?>
                                    <p><strong>Signalé :</strong> <?= htmlspecialchars($report['reported_username']) ?></p>
                                <?php else: ?>
                                    <p><strong>Post signalé :</strong> <?= htmlspecialchars(substr($report['post_content'], 0, 100)) ?>...</p>
                                    <p><strong>Auteur du post :</strong> <?= htmlspecialchars($report['post_author']) ?></p>
                                    <p><strong>Date du post :</strong> <?= date('d/m/Y H:i', strtotime($report['post_created_at'])) ?></p>
                                <?php endif; ?>
                                <p><strong>Raison :</strong> <?= htmlspecialchars($report['reason']) ?></p>
                            </div>
                        </div>
                        
                        <div class="report-actions">
                            <?php if ($report['status'] === 'pending'): ?>
                                <button class="btn btn-success btn-sm" onclick="updateStatus(<?= $report['id'] ?>, 'reviewed')">
                                    <i class="fas fa-check"></i>
                                    Marquer comme examiné
                                </button>
                            <?php else: ?>
                                <button class="btn btn-warning btn-sm" onclick="updateStatus(<?= $report['id'] ?>, 'pending')">
                                    <i class="fas fa-clock"></i>
                                    Remettre en attente
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($role === 'Administrateur' || $role === 'Modérateur'): ?>
                                <button type="button" class="btn btn-danger btn-sm" onclick="deleteReport(<?= $report['id'] ?>)">
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
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($filter_type) ?>&status=<?= urlencode($filter_status) ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                            Précédent
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($filter_type) ?>&status=<?= urlencode($filter_status) ?>" 
                           class="page-link <?= $i === $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($filter_type) ?>&status=<?= urlencode($filter_status) ?>" class="page-link">
                            Suivant
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal d'ajout de signalement -->
<?php if ($role === 'Administrateur' || $role === 'Modérateur'): ?>
<div id="addReportModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Ajouter un Signalement</h2>
            <span class="close" onclick="closeAddReportModal()">&times;</span>
        </div>
        
        <form id="addReportForm" method="POST">
            <input type="hidden" name="action" value="add_report">
            
            <div class="form-group">
                <label for="reporter_id">Utilisateur signalant *</label>
                <select name="reporter_id" id="reporter_id" required>
                    <option value="">Sélectionner un utilisateur</option>
                    <?php foreach ($usersList as $user): ?>
                        <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="report_type">Type de signalement *</label>
                <select name="report_type" id="report_type" required onchange="toggleReportedField()">
                    <option value="">Sélectionner un type</option>
                    <option value="user">Utilisateur</option>
                    <option value="post">Post</option>
                </select>
            </div>
            
            <div class="form-group" id="reported_user_group">
                <label for="reported_user_id">Utilisateur signalé *</label>
                <select name="reported_user_id" id="reported_user_id">
                    <option value="">Sélectionner un utilisateur</option>
                    <?php foreach ($usersList as $user): ?>
                        <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" id="reported_post_group" style="display: none;">
                <label for="reported_post_id">Post signalé *</label>
                <select name="reported_post_id" id="reported_post_id">
                    <option value="">Sélectionner un post</option>
                    <?php foreach ($postsList as $post): ?>
                        <option value="<?= $post['id'] ?>">
                            <?= htmlspecialchars(substr($post['content'], 0, 50)) ?>... 
                            (par <?= htmlspecialchars($post['author_name']) ?> - <?= date('d/m/Y', strtotime($post['created_at'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="reason">Raison *</label>
                <textarea name="reason" id="reason" rows="4" required placeholder="Décrivez la raison du signalement..."></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" onclick="closeAddReportModal()" class="btn btn-secondary">Annuler</button>
                <button type="submit" class="btn btn-primary">Ajouter</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// Fonctions pour les modals
function showAddReportModal() {
    document.getElementById('addReportModal').style.display = 'block';
}

function closeAddReportModal() {
    document.getElementById('addReportModal').style.display = 'none';
    document.getElementById('addReportForm').reset();
}

// Fonction de suppression avec confirmation
function deleteReport(reportId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce signalement ?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="report_id" value="${reportId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Fonction pour mettre à jour le statut
function updateStatus(reportId, newStatus) {
    const statusText = newStatus === 'reviewed' ? 'examiné' : 'en attente';
    if (confirm(`Êtes-vous sûr de vouloir marquer ce signalement comme ${statusText} ?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="report_id" value="${reportId}">
            <input type="hidden" name="new_status" value="${newStatus}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Validation du formulaire d'ajout
document.addEventListener('DOMContentLoaded', function() {
    const addReportForm = document.getElementById('addReportForm');
    if (addReportForm) {
        addReportForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const reporterId = document.getElementById('reporter_id').value;
            const reportType = document.getElementById('report_type').value;
            const reason = document.getElementById('reason').value.trim();
            
            if (!reporterId || !reportType || !reason) {
                showMessage('Tous les champs obligatoires doivent être remplis', false);
                return false;
            }
            
            if (reportType === 'user') {
                const reportedId = document.getElementById('reported_user_id').value;
                if (!reportedId) {
                    showMessage('Veuillez sélectionner un utilisateur signalé', false);
                    return false;
                }
            } else if (reportType === 'post') {
                const reportedPostId = document.getElementById('reported_post_id').value;
                if (!reportedPostId) {
                    showMessage('Veuillez sélectionner un post signalé', false);
                    return false;
                }
            }
            
            if (reason.length < 10) {
                showMessage('La raison doit contenir au moins 10 caractères', false);
                return false;
            }
            
            const formData = new FormData();
            formData.append('action', 'add_report');
            formData.append('reporter_id', reporterId);
            formData.append('report_type', reportType);
            formData.append('reason', reason);
            
            if (reportType === 'user') {
                formData.append('reported_id', reportedId);
            } else if (reportType === 'post') {
                formData.append('reported_id', reportedPostId);
            }
            
            const submitBtn = addReportForm.querySelector('button[type="submit"]');
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
                        closeAddReportModal();
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showMessage('Erreur lors de l\'ajout du signalement', false);
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
    const addModal = document.getElementById('addReportModal');
    
    if (event.target === addModal) {
        closeAddReportModal();
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

function toggleReportedField() {
    const reportType = document.getElementById('report_type').value;
    const reportedUserGroup = document.getElementById('reported_user_group');
    const reportedPostGroup = document.getElementById('reported_post_group');
    
    if (reportType === 'user') {
        reportedUserGroup.style.display = 'block';
        reportedPostGroup.style.display = 'none';
    } else if (reportType === 'post') {
        reportedUserGroup.style.display = 'none';
        reportedPostGroup.style.display = 'block';
    }
}
</script>
