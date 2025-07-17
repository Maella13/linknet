
<?php
require_once "../menu.php";

// Gestion de la période sélectionnée
$period = isset($_GET['period']) ? $_GET['period'] : 'today';
$date_filter = "";
$period_label = "";

switch ($period) {
    case 'today':
        $date_filter = "DATE(created_at) = CURDATE()";
        $period_label = "Aujourd'hui";
        break;
    case 'yesterday':
        $date_filter = "DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $period_label = "Hier";
        break;
    case '7days':
        $date_filter = "created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        $period_label = "7 derniers jours";
        break;
    case '30days':
        $date_filter = "created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $period_label = "30 derniers jours";
        break;
    case '90days':
        $date_filter = "created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
        $period_label = "90 derniers jours";
        break;
    case 'year':
        $date_filter = "created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        $period_label = "1 an";
        break;
    default:
        $date_filter = "created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        $period_label = "7 derniers jours";
}

// Après la définition de $date_filter et $period_label
switch ($period) {
    case 'today':
        $comments_date_filter = "DATE(created_at) = CURDATE()";
        break;
    case 'yesterday':
        $comments_date_filter = "DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        break;
    case '7days':
        $comments_date_filter = "created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case '30days':
        $comments_date_filter = "created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
    case '90days':
        $comments_date_filter = "created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
        break;
    case 'year':
        $comments_date_filter = "created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        break;
    default:
        $comments_date_filter = "created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
}
$new_comments = $conn->query("SELECT COUNT(*) FROM comments WHERE $comments_date_filter")->fetchColumn();

// Widget commentaires : requête dédiée
$comments_total = $conn->query("SELECT COUNT(*) FROM comments")->fetchColumn();

switch ($period) {
    case 'today':
        $comments_new = $conn->query("SELECT COUNT(*) FROM comments WHERE DATE(created_at) = CURDATE()")->fetchColumn();
        break;
    case 'yesterday':
        $comments_new = $conn->query("SELECT COUNT(*) FROM comments WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();
        break;
    case '7days':
        $comments_new = $conn->query("SELECT COUNT(*) FROM comments WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
        break;
    case '30days':
        $comments_new = $conn->query("SELECT COUNT(*) FROM comments WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
        break;
    case '90days':
        $comments_new = $conn->query("SELECT COUNT(*) FROM comments WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)")->fetchColumn();
        break;
    case 'year':
        $comments_new = $conn->query("SELECT COUNT(*) FROM comments WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)")->fetchColumn();
        break;
    default:
        $comments_new = $conn->query("SELECT COUNT(*) FROM comments WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
        break;
}

// Statistiques globales
$global_stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM posts) as total_posts,
        (SELECT COUNT(*) FROM comments) as total_comments,
        (SELECT COUNT(*) FROM likes) as total_likes,
        (SELECT COUNT(*) FROM messages) as total_messages,
        (SELECT COUNT(*) FROM friends WHERE status = 'accepted') as total_friendships,
        (SELECT COUNT(*) FROM followers) as total_followers,
        (SELECT COUNT(*) FROM featured_posts) as total_featured,
        (SELECT COUNT(*) FROM hashtags) as total_hashtags,
        (SELECT COUNT(*) FROM notifications) as total_notifications,
        (SELECT COUNT(*) FROM reports) as total_reports,
        (SELECT COUNT(*) FROM admins) as total_admins
")->fetch(PDO::FETCH_ASSOC);

// Statistiques temporelles
$time_stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE $date_filter) as new_users,
        (SELECT COUNT(*) FROM posts WHERE $date_filter) as new_posts,
        (SELECT COUNT(*) FROM comments WHERE $date_filter) as new_comments,
        (SELECT COUNT(*) FROM likes WHERE $date_filter) as new_likes,
        (SELECT COUNT(*) FROM messages WHERE $date_filter) as new_messages,
        (SELECT COUNT(*) FROM friends WHERE status = 'accepted' AND $date_filter) as new_friendships,
        (SELECT COUNT(*) FROM followers WHERE $date_filter) as new_followers,
        (SELECT COUNT(*) FROM featured_posts WHERE $date_filter) as new_featured,
        (SELECT COUNT(*) FROM hashtags WHERE $date_filter) as new_hashtags,
        (SELECT COUNT(*) FROM notifications WHERE $date_filter) as new_notifications,
        (SELECT COUNT(*) FROM reports WHERE $date_filter) as new_reports
")->fetch(PDO::FETCH_ASSOC);

// Données pour les graphiques - Inscriptions par jour (7 derniers jours)
$users_chart_data = $conn->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
    FROM users 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date
")->fetchAll(PDO::FETCH_ASSOC);

// Données pour les graphiques - Posts par jour (7 derniers jours)
$posts_chart_data = $conn->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
    FROM posts 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date
")->fetchAll(PDO::FETCH_ASSOC);

// Données pour les graphiques - Posts en vedette par jour (7 derniers jours)
$featured_posts_chart_data = $conn->query("
    SELECT 
        DATE(fp.created_at) as date,
        COUNT(*) as count
    FROM featured_posts fp
    WHERE fp.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(fp.created_at)
    ORDER BY date
")->fetchAll(PDO::FETCH_ASSOC);

// Données pour les graphiques - Répartition Admin/Modo
$admin_role_data = $conn->query("
    SELECT 
        role,
        COUNT(*) as count
    FROM admins 
    GROUP BY role
")->fetchAll(PDO::FETCH_ASSOC);

// Données pour les graphiques - Signalements par type
$reports_type_data = $conn->query("
    SELECT 
        report_type,
        COUNT(*) as count
    FROM reports 
    GROUP BY report_type
")->fetchAll(PDO::FETCH_ASSOC);

// Données pour les graphiques - Statut des signalements
$reports_status_data = $conn->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM reports 
    GROUP BY status
")->fetchAll(PDO::FETCH_ASSOC);

// Données pour le camembert : répartition des commentaires par post (top 5)
$comments_by_post = $conn->query("
    SELECT p.id, p.content, COUNT(c.id) as nb_comments
    FROM comments c
    JOIN posts p ON c.post_id = p.id
    GROUP BY p.id, p.content
    ORDER BY nb_comments DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$top_post_ids = [];
$pie_labels = [];
$pie_values = [];
foreach ($comments_by_post as $row) {
    $label = mb_strimwidth(strip_tags($row['content']), 0, 20, '...');
    $pie_labels[] = $label ?: 'Post #' . $row['id'];
    $pie_values[] = (int)$row['nb_comments'];
    $top_post_ids[] = (int)$row['id'];
}

// 2. Calculer les autres commentaires (posts hors top 5)
if (count($top_post_ids) > 0) {
    $in = implode(',', $top_post_ids);
    $other_comments = $conn->query("SELECT COUNT(*) FROM comments WHERE post_id NOT IN ($in)")->fetchColumn();
    if ($other_comments > 0) {
        $pie_labels[] = 'Autres';
        $pie_values[] = (int)$other_comments;
    }
}

// Données pour le bar chart : commentaires par jour (7 jours)
$comments_chart_data = $conn->query("
    SELECT 
        DATE(c.created_at) as date,
        COUNT(*) as count
    FROM comments c
    WHERE c.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(c.created_at)
    ORDER BY date
")->fetchAll(PDO::FETCH_ASSOC);

// Données pour les commentaires de la période sélectionnée
$period_comments = $conn->query("
    SELECT 
        c.id,
        c.comment_text,
        c.created_at,
        u.username,
        u.profile_picture,
        p.content as post_content,
        p.id as post_id
    FROM comments c
    JOIN users u ON c.user_id = u.id
    JOIN posts p ON c.post_id = p.id
    WHERE " . 
        ($period === 'today' ? 'DATE(c.created_at) = CURDATE()' : 
         ($period === 'yesterday' ? 'DATE(c.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)' : 
          'c.created_at >= DATE_SUB(CURDATE(), INTERVAL ' . 
            ($period === '7days' ? '7' : 
             ($period === '30days' ? '30' : 
              ($period === '90days' ? '90' : 
               ($period === 'year' ? '365' : '7')))) . ' DAY)')) . "
    ORDER BY c.created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Posts avec le plus de commentaires
$top_posts_comments = $conn->query("
    SELECT 
        p.id,
        p.content,
        p.created_at,
        u.username,
        COUNT(c.id) as comment_count
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN comments c ON p.id = c.post_id
    GROUP BY p.id, p.content, p.created_at, u.username
    HAVING comment_count > 0
    ORDER BY comment_count DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Utilisateurs qui commentent le plus
$top_commenters = $conn->query("
    SELECT 
        u.id,
        u.username,
        u.profile_picture,
        COUNT(c.id) as comment_count
    FROM users u
    JOIN comments c ON u.id = c.user_id
    GROUP BY u.id, u.username, u.profile_picture
    ORDER BY comment_count DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$comments_labels = [];
$comments_values = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $day_name = date('D', strtotime($date));
    // Traduire le jour en français
    $french_day_name = '';
    switch ($day_name) {
        case 'Mon':
            $french_day_name = 'Lun';
            break;
        case 'Tue':
            $french_day_name = 'Mar';
            break;
        case 'Wed':
            $french_day_name = 'Mer';
            break;
        case 'Thu':
            $french_day_name = 'Jeu';
            break;
        case 'Fri':
            $french_day_name = 'Ven';
            break;
        case 'Sat':
            $french_day_name = 'Sam';
            break;
        case 'Sun':
            $french_day_name = 'Dim';
            break;
        default:
            $french_day_name = $day_name;
            break;
    }
    $comments_labels[] = $french_day_name;
    $count = 0;
    foreach ($comments_chart_data as $data) {
        if ($data['date'] == $date) {
            $count = $data['count'];
            break;
        }
    }
    $comments_values[] = $count;
}

// Préparer les données pour Chart.js
$users_labels = [];
$users_values = [];
$posts_labels = [];
$posts_values = [];
$featured_labels = [];
$featured_values = [];

// Générer les 7 derniers jours
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $day_name = date('D', strtotime($date));
    
    // Traduire le jour en français
    $french_day_name = '';
    switch ($day_name) {
        case 'Mon':
            $french_day_name = 'Lun';
            break;
        case 'Tue':
            $french_day_name = 'Mar';
            break;
        case 'Wed':
            $french_day_name = 'Mer';
            break;
        case 'Thu':
            $french_day_name = 'Jeu';
            break;
        case 'Fri':
            $french_day_name = 'Ven';
            break;
        case 'Sat':
            $french_day_name = 'Sam';
            break;
        case 'Sun':
            $french_day_name = 'Dim';
            break;
        default:
            $french_day_name = $day_name;
            break;
    }
    
    $users_labels[] = $french_day_name;
    $posts_labels[] = $french_day_name;
    $featured_labels[] = $french_day_name;
    
    // Chercher les données pour cette date
    $users_count = 0;
    $posts_count = 0;
    $featured_count = 0;
    
    foreach ($users_chart_data as $data) {
        if ($data['date'] == $date) {
            $users_count = $data['count'];
            break;
        }
    }
    
    foreach ($posts_chart_data as $data) {
        if ($data['date'] == $date) {
            $posts_count = $data['count'];
            break;
        }
    }
    
    foreach ($featured_posts_chart_data as $data) {
        if ($data['date'] == $date) {
            $featured_count = $data['count'];
            break;
        }
    }
    
    $users_values[] = $users_count;
    $posts_values[] = $posts_count;
    $featured_values[] = $featured_count;
}

// Préparer les données pour les graphiques circulaires
$admin_labels = [];
$admin_values = [];
foreach ($admin_role_data as $data) {
    $admin_labels[] = $data['role'];
    $admin_values[] = $data['count'];
}

$reports_type_labels = [];
$reports_type_values = [];
foreach ($reports_type_data as $data) {
    $reports_type_labels[] = ucfirst($data['report_type']);
    $reports_type_values[] = $data['count'];
}

$reports_status_labels = [];
$reports_status_values = [];
foreach ($reports_status_data as $data) {
    $reports_status_labels[] = ucfirst($data['status']);
    $reports_status_values[] = $data['count'];
}
?>
<link rel="stylesheet" href="/assets/css/back-office/dashboard.css">

<div class="main-content">
    <div class="dashboard-container">
        <div class="header">
            <h1>Linknet - Dashboard Statistiques</h1>
        </div>
        <div class="period-selector">
            <button class="period-btn <?= $period === 'today' ? 'active' : '' ?>" data-period="today">Aujourd'hui</button>
            <button class="period-btn <?= $period === 'yesterday' ? 'active' : '' ?>" data-period="yesterday">Hier</button>
            <button class="period-btn <?= $period === '7days' ? 'active' : '' ?>" data-period="7days">7 jours</button>
            <button class="period-btn <?= $period === '30days' ? 'active' : '' ?>" data-period="30days">30 jours</button>
            <button class="period-btn <?= $period === '90days' ? 'active' : '' ?>" data-period="90days">90 jours</button>
            <button class="period-btn <?= $period === 'year' ? 'active' : '' ?>" data-period="year">1 an</button>
        </div>
        <div class="menu-categories">
            <button class="menu-btn active" data-cat="all">Tout</button>
            <button class="menu-btn" data-cat="users">Utilisateurs</button>
            <button class="menu-btn" data-cat="posts">Posts</button>
            <button class="menu-btn" data-cat="interactions">Interactions</button>
            <button class="menu-btn" data-cat="admin">Admin</button>
            <button class="menu-btn" data-cat="reports">Signalements</button>
        </div>
        <!-- Widgets modernes -->
        <div class="widgets-row">
            <div class="widget" data-cat="users">
                <canvas id="usersCircle" class="widget-circular"></canvas>
                <div class="widget-title">Utilisateurs</div>
                <div class="widget-value"><?= $global_stats['total_users'] ?></div>
                <div class="widget-desc">Nouveaux (<?= $period_label ?>): <b><?= $time_stats['new_users'] ?></b></div>
            </div>
            <div class="widget" data-cat="posts">
                <canvas id="postsCircle" class="widget-circular"></canvas>
                <div class="widget-title">Posts</div>
                <div class="widget-value"><?= $global_stats['total_posts'] ?></div>
                <div class="widget-desc">Nouveaux (<?= $period_label ?>): <b><?= $time_stats['new_posts'] ?></b></div>
            </div>
            <div class="widget" data-cat="interactions">
                <canvas id="commentsCircle" class="widget-circular"></canvas>
                <div class="widget-title">Commentaires</div>
                <div class="widget-value"><?= $comments_total ?></div>
                <div class="widget-desc">Nouveaux (<?= $period_label ?>): <b><?= $comments_new ?></b></div>
            </div>
            <div class="widget" data-cat="reports">
                <canvas id="reportsGauge" class="widget-gauge" width="90" height="45"></canvas>
                <div class="widget-title">Signalements</div>
                <div class="widget-value"><?= $global_stats['total_reports'] ?></div>
                <div class="widget-desc">Nouveaux (<?= $period_label ?>): <b><?= $time_stats['new_reports'] ?></b></div>
            </div>
        </div>
        <!-- Grille de graphiques -->
        <div class="charts-grid">
            <div class="chart-card" data-cat="users">
                <div class="chart-title">Évolution des Inscriptions</div>
                <canvas id="usersChart"></canvas>
            </div>
            <div class="chart-card" data-cat="posts">
                <div class="chart-title">Posts par Jour</div>
                <canvas id="postsChart"></canvas>
            </div>
            <div class="chart-card" data-cat="admin">
                <div class="chart-title">Répartition Admin/Modo</div>
                <canvas id="adminChart"></canvas>
            </div>
            <div class="chart-card" data-cat="reports">
                <div class="chart-title">Signalements par Type</div>
                <canvas id="reportsChart"></canvas>
            </div>
            <div class="chart-card" data-cat="interactions">
                <div class="chart-title">Répartition des Interactions</div>
                <canvas id="interactionsChart"></canvas>
            </div>
            <div class="chart-card" data-cat="posts">
                <div class="chart-title">Posts en Vedette</div>
                <canvas id="featuredPostsChart"></canvas>
            </div>
            <div class="chart-card" data-cat="admin">
                <div class="chart-title">Statut des Signalements</div>
                <canvas id="reportsStatusChart"></canvas>
            </div>
            <div class="chart-card" data-cat="interactions">
                <div class="chart-title">Commentaires par jour (7 jours)</div>
                <canvas id="commentsBarChart"></canvas>
            </div>
            <div class="chart-card" data-cat="interactions">
                <div class="chart-title">Répartition des commentaires par post</div>
                <canvas id="commentsPieChart"></canvas>
            </div>
            <div class="chart-card" data-cat="interactions">
                <div class="chart-title">Commentaires récents (<?= $period_label ?>)</div>
                <div class="comments-table-container">
                    <?php if (empty($period_comments)): ?>
                        <p class="no-data">Aucun commentaire pour cette période</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="comments-table">
                                <thead>
                                    <tr>
                                        <th>Utilisateur</th>
                                        <th>Commentaire</th>
                                        <th>Post</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($period_comments as $comment): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $profilePic = $comment['profile_picture'];
                                            if (empty($profilePic) || $profilePic === 'default_profile.jpg') {
                                                $profilePic = '../../uploads/default_profile.jpg';
                                            } else {
                                                $profilePic = '../../uploads/' . $profilePic;
                                            }
                                            ?>
                                            <div class="user-info">
                                                <img src="<?= htmlspecialchars($profilePic) ?>" alt="<?= htmlspecialchars($comment['username']) ?>" class="user-avatar">
                                                <span><?= htmlspecialchars($comment['username']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars(mb_strimwidth($comment['comment_text'], 0, 50, '...')) ?></td>
                                        <td><?= htmlspecialchars(mb_strimwidth($comment['post_content'], 0, 30, '...')) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="chart-card" data-cat="interactions">
                <div class="chart-title">Posts avec le plus de commentaires</div>
                <div class="comments-table-container">
                    <?php if (empty($top_posts_comments)): ?>
                        <p class="no-data">Aucun post avec des commentaires</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="comments-table">
                                <thead>
                                    <tr>
                                        <th>Post</th>
                                        <th>Auteur</th>
                                        <th>Commentaires</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_posts_comments as $post): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(mb_strimwidth($post['content'], 0, 40, '...')) ?></td>
                                        <td><?= htmlspecialchars($post['username']) ?></td>
                                        <td><span class="badge"><?= $post['comment_count'] ?></span></td>
                                        <td><?= date('d/m/Y', strtotime($post['created_at'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="chart-card" data-cat="interactions">
                <div class="chart-title">Top commentateurs</div>
                <div class="comments-table-container">
                    <?php if (empty($top_commenters)): ?>
                        <p class="no-data">Aucun commentateur</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="comments-table">
                                <thead>
                                    <tr>
                                        <th>Utilisateur</th>
                                        <th>Commentaires</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_commenters as $commenter): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $profilePic = $commenter['profile_picture'];
                                            if (empty($profilePic) || $profilePic === 'default_profile.jpg') {
                                                $profilePic = '../../uploads/default_profile.jpg';
                                            } else {
                                                $profilePic = '../../uploads/' . $profilePic;
                                            }
                                            ?>
                                            <div class="user-info">
                                                <img src="<?= htmlspecialchars($profilePic) ?>" alt="<?= htmlspecialchars($commenter['username']) ?>" class="user-avatar">
                                                <span><?= htmlspecialchars($commenter['username']) ?></span>
                                            </div>
                                        </td>
                                        <td><span class="badge"><?= $commenter['comment_count'] ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Filtres catégories (affichage widgets et graphiques)
const menuBtns = document.querySelectorAll('.menu-btn');
const widgets = document.querySelectorAll('.widget');
const chartCards = document.querySelectorAll('.chart-card');
menuBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        menuBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const cat = btn.getAttribute('data-cat');
        widgets.forEach(w => {
            w.style.display = (cat === 'all' || w.getAttribute('data-cat') === cat) ? '' : 'none';
        });
        chartCards.forEach(c => {
            c.style.display = (cat === 'all' || c.getAttribute('data-cat') === cat) ? '' : 'none';
        });
    });
});

// Gestion des changements de période via AJAX
const periodBtns = document.querySelectorAll('.period-btn');
let currentPeriod = '<?= $period ?>';

periodBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        const period = btn.getAttribute('data-period');
        if (period === currentPeriod) return;
        
        // Mettre à jour l'état actif des boutons
        periodBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentPeriod = period;
        
        // Mettre à jour l'URL sans recharger la page
        const url = new URL(window.location);
        url.searchParams.set('period', period);
        window.history.pushState({}, '', url);
        
        // Recharger la page pour obtenir les nouvelles données
        window.location.reload();
    });
});

// Widgets circulaires (Chart.js)
new Chart(document.getElementById('usersCircle'), {
    type: 'doughnut',
    data: {
        labels: ['Utilisateurs', ''],
        datasets: [{
            data: [<?= $global_stats['total_users'] ?>, Math.max(1, <?= $global_stats['total_users'] ?> * 0.2)],
            backgroundColor: ['#7c3aed', '#ede9fe'],
            borderWidth: 0
        }]
    },
    options: {
        cutout: '75%',
        plugins: { legend: { display: false } }
    }
});
new Chart(document.getElementById('postsCircle'), {
    type: 'doughnut',
    data: {
        labels: ['Posts', ''],
        datasets: [{
            data: [<?= $global_stats['total_posts'] ?>, Math.max(1, <?= $global_stats['total_posts'] ?> * 0.2)],
            backgroundColor: ['#a78bfa', '#ede9fe'],
            borderWidth: 0
        }]
    },
    options: {
        cutout: '75%',
        plugins: { legend: { display: false } }
    }
});
new Chart(document.getElementById('commentsCircle'), {
    type: 'doughnut',
    data: {
        labels: ['Commentaires', ''],
        datasets: [{
            data: [<?= $comments_total ?>, Math.max(1, <?= $comments_total ?> * 0.2)],
            backgroundColor: ['#8b5cf6', '#ede9fe'],
            borderWidth: 0
        }]
    },
    options: {
        cutout: '75%',
        plugins: { legend: { display: false } }
    }
});
// Widget jauge (Chart.js 3+)
new Chart(document.getElementById('reportsGauge'), {
    type: 'doughnut',
    data: {
        labels: ['Signalements', ''],
        datasets: [{
            data: [<?= $global_stats['total_reports'] ?>, Math.max(1, <?= $global_stats['total_reports'] ?> * 0.5)],
            backgroundColor: ['#f472b6', '#ede9fe'],
            borderWidth: 0
        }]
    },
    options: {
        rotation: -90,
        circumference: 180,
        cutout: '80%',
        plugins: { legend: { display: false } }
    }
});
// Graphiques principaux
new Chart(document.getElementById('usersChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: <?= json_encode($users_labels) ?>,
        datasets: [{
            label: 'Inscriptions',
            data: <?= json_encode($users_values) ?>,
            backgroundColor: 'rgba(124,58,237,0.1)',
            borderColor: 'rgba(124,58,237,1)',
            borderWidth: 2,
            tension: 0.3,
            fill: true
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});
new Chart(document.getElementById('postsChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($posts_labels) ?>,
        datasets: [{
            label: 'Posts',
            data: <?= json_encode($posts_values) ?>,
            backgroundColor: 'rgba(167,139,250,0.7)',
            borderColor: 'rgba(167,139,250,1)',
            borderWidth: 1
        }]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: true,
        aspectRatio: 2,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
new Chart(document.getElementById('adminChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($admin_labels) ?>,
        datasets: [{
            data: <?= json_encode($admin_values) ?>,
            backgroundColor: [
                'rgba(124,58,237,0.7)',
                'rgba(167,139,250,0.7)'
            ]
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
new Chart(document.getElementById('reportsChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($reports_type_labels) ?>,
        datasets: [{
            data: <?= json_encode($reports_type_values) ?>,
            backgroundColor: [
                'rgba(239,68,68,0.7)',
                'rgba(124,58,237,0.7)'
            ]
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
new Chart(document.getElementById('interactionsChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: ['Commentaires', 'Likes', 'Messages', 'Amitiés', 'Abonnés'],
        datasets: [{
            data: [<?= $global_stats['total_comments'] ?>, <?= $global_stats['total_likes'] ?>, <?= $global_stats['total_messages'] ?>, <?= $global_stats['total_friendships'] ?>, <?= $global_stats['total_followers'] ?>],
            backgroundColor: [
                'rgba(139,92,246,0.7)',
                'rgba(167,139,250,0.7)',
                'rgba(124,58,237,0.7)',
                'rgba(244,114,182,0.7)',
                'rgba(233,213,255,0.7)'
            ]
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
new Chart(document.getElementById('featuredPostsChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($featured_labels) ?>,
        datasets: [{
            label: 'Posts en vedette',
            data: <?= json_encode($featured_values) ?>,
            backgroundColor: 'rgba(139,92,246,0.7)',
            borderColor: 'rgba(124,58,237,1)',
            borderWidth: 1
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});
new Chart(document.getElementById('reportsStatusChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($reports_status_labels) ?>,
        datasets: [{
            data: <?= json_encode($reports_status_values) ?>,
            backgroundColor: [
                'rgba(239,68,68,0.7)',
                'rgba(124,58,237,0.7)'
            ]
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
// Bar chart commentaires par jour
new Chart(document.getElementById('commentsBarChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($comments_labels) ?>,
        datasets: [{
            label: 'Commentaires',
            data: <?= json_encode($comments_values) ?>,
            backgroundColor: 'rgba(139,92,246,0.7)',
            borderColor: 'rgba(124,58,237,1)',
            borderWidth: 1
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});
// Pie chart répartition par post
new Chart(document.getElementById('commentsPieChart').getContext('2d'), {
    type: 'pie',
    data: {
        labels: <?= json_encode($pie_labels) ?>,
        datasets: [{
            data: <?= json_encode($pie_values) ?>,
            backgroundColor: [
                '#7c3aed','#a78bfa','#8b5cf6','#f472b6','#ede9fe','#f3e8ff'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>
</body>
</html>