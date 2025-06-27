<?php
require_once "menu.php";

// Gestion de la période sélectionnée
$period = isset($_GET['period']) ? $_GET['period'] : '7days';
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
    
    $users_labels[] = $day_name;
    $posts_labels[] = $day_name;
    $featured_labels[] = $day_name;
    
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
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Linknet - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-container {
            max-width: 1500px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .header h1 {
            color: #2563eb;
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 24px;
        }
        
        .period-selector, .menu-categories {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            overflow-x: auto;
        }
        
        .period-btn, .menu-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            background-color: #e9ecef;
            color: #2563eb;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 16px;
            white-space: nowrap;
        }
        
        .period-btn.active, .period-btn:hover,
        .menu-btn.active, .menu-btn:hover {
            background-color: #2563eb;
            color: #fff;
        }
        
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px 20px;
            box-shadow: 0 4px 12px rgba(37,99,235,0.07);
            border-left: 6px solid #2563eb;
            transition: box-shadow 0.3s;
        }
        
        .stat-card h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 17px;
            color: #2563eb;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .charts-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-section, .chart-circle {
            flex: 1;
            min-width: 300px;
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(37,99,235,0.07);
        }
        
        .chart-title {
            font-size: 18px;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 15px;
        }
        
        @media (max-width: 1200px) {
            .analytics-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }
        
        @media (max-width: 900px) {
            .dashboard-container {
                padding: 0 15px;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }
            
            .analytics-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 15px;
            }
            
            .stat-card {
                padding: 20px 15px;
            }
            
            .stat-value {
                font-size: 28px;
            }
            
            .charts-row {
                flex-direction: column;
                gap: 15px;
            }
            
            .chart-section, .chart-circle {
                min-width: 100%;
            }
        }
        
        @media (max-width: 600px) {
            .dashboard-container {
                padding: 0 10px;
            }
            
            .header h1 {
                font-size: 1.5rem;
                margin-bottom: 20px;
            }
            
            .period-selector, .menu-categories {
                flex-wrap: nowrap;
                overflow-x: auto;
                gap: 6px;
                margin-bottom: 18px;
            }
            
            .period-btn, .menu-btn {
                font-size: 14px;
                padding: 7px 10px;
                min-width: 90px;
                text-align: center;
            }
            
            .analytics-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .stat-card {
                padding: 18px 12px;
            }
            
            .stat-value {
                font-size: 24px;
            }
            
            .chart-section, .chart-circle {
                padding: 15px;
            }
            
            .chart-title {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="dashboard-container">
            <div class="header">
                <h1>Linknet - Dashboard Statistiques</h1>
            </div>
            
            <div class="period-selector">
                <a href="?period=today" class="period-btn<?= $period === 'today' ? ' active' : '' ?>">Aujourd'hui</a>
                <a href="?period=yesterday" class="period-btn<?= $period === 'yesterday' ? ' active' : '' ?>">Hier</a>
                <a href="?period=7days" class="period-btn<?= $period === '7days' ? ' active' : '' ?>">7 jours</a>
                <a href="?period=30days" class="period-btn<?= $period === '30days' ? ' active' : '' ?>">30 jours</a>
                <a href="?period=90days" class="period-btn<?= $period === '90days' ? ' active' : '' ?>">90 jours</a>
                <a href="?period=year" class="period-btn<?= $period === 'year' ? ' active' : '' ?>">1 an</a>
            </div>
            
            <div class="menu-categories">
                <button class="menu-btn active" data-cat="all">Tout</button>
                <button class="menu-btn" data-cat="users">Utilisateurs</button>
                <button class="menu-btn" data-cat="posts">Posts</button>
                <button class="menu-btn" data-cat="interactions">Interactions</button>
                <button class="menu-btn" data-cat="admin">Admin</button>
                <button class="menu-btn" data-cat="reports">Signalements</button>
            </div>
            
            <div class="analytics-grid">
                <div class="stat-card" data-cat="users">
                    <h3>Utilisateurs</h3>
                    <div class="stat-value"><?= $global_stats['total_users'] ?></div>
                    <div>Nouveaux (<?= $period_label ?>): <b><?= $time_stats['new_users'] ?></b></div>
                </div>
                
                <div class="stat-card" data-cat="posts">
                    <h3>Posts</h3>
                    <div class="stat-value"><?= $global_stats['total_posts'] ?></div>
                    <div>Nouveaux (<?= $period_label ?>): <b><?= $time_stats['new_posts'] ?></b></div>
                </div>
                
                <div class="stat-card" data-cat="interactions">
                    <h3>Commentaires</h3>
                    <div class="stat-value"><?= $global_stats['total_comments'] ?></div>
                    <div>Nouveaux (<?= $period_label ?>): <b><?= $time_stats['new_comments'] ?></b></div>
                </div>
                
                <div class="stat-card" data-cat="interactions">
                    <h3>Likes</h3>
                    <div class="stat-value"><?= $global_stats['total_likes'] ?></div>
                    <div>Nouveaux (<?= $period_label ?>): <b><?= $time_stats['new_likes'] ?></b></div>
                </div>
                
                <div class="stat-card" data-cat="interactions">
                    <h3>Messages</h3>
                    <div class="stat-value"><?= $global_stats['total_messages'] ?></div>
                    <div>Nouveaux (<?= $period_label ?>): <b><?= $time_stats['new_messages'] ?></b></div>
                </div>
                
                <div class="stat-card" data-cat="interactions">
                    <h3>Amitiés</h3>
                    <div class="stat-value"><?= $global_stats['total_friendships'] ?></div>
                    <div>Nouvelles (<?= $period_label ?>): <b><?= $time_stats['new_friendships'] ?></b></div>
                </div>
                
                <div class="stat-card" data-cat="interactions">
                    <h3>Abonnés</h3>
                    <div class="stat-value"><?= $global_stats['total_followers'] ?></div>
                    <div>Nouveaux (<?= $period_label ?>): <b><?= $time_stats['new_followers'] ?></b></div>
                </div>
                
                <div class="stat-card" data-cat="posts">
                    <h3>Posts en vedette</h3>
                    <div class="stat-value"><?= $global_stats['total_featured'] ?></div>
                    <div>Nouveaux (<?= $period_label ?>): <b><?= $time_stats['new_featured'] ?></b></div>
                </div>
                
                <div class="stat-card" data-cat="posts">
                    <h3>Hashtags</h3>
                    <div class="stat-value"><?= $global_stats['total_hashtags'] ?></div>
                    <div>Nouveaux (<?= $period_label ?>): <b><?= $time_stats['new_hashtags'] ?></b></div>
                </div>
                
                <div class="stat-card" data-cat="admin">
                    <h3>Admins</h3>
                    <div class="stat-value"><?= $global_stats['total_admins'] ?></div>
                </div>
                
                <div class="stat-card" data-cat="reports">
                    <h3>Signalements</h3>
                    <div class="stat-value"><?= $global_stats['total_reports'] ?></div>
                    <div>Nouveaux (<?= $period_label ?>): <b><?= $time_stats['new_reports'] ?></b></div>
                </div>
                
                <div class="stat-card" data-cat="interactions">
                    <h3>Notifications</h3>
                    <div class="stat-value"><?= $global_stats['total_notifications'] ?></div>
                    <div>Nouvelles (<?= $period_label ?>): <b><?= $time_stats['new_notifications'] ?></b></div>
                </div>
            </div>
            
            <div class="charts-row" data-cat="users">
                <div class="chart-section" data-cat="users">
                    <div class="chart-title">Évolution des Inscriptions</div>
                    <canvas id="usersChart"></canvas>
                </div>
                <div class="chart-circle" data-cat="users">
                    <div class="chart-title">Répartition Admin/Modo</div>
                    <canvas id="adminChart"></canvas>
                </div>
            </div>
            
            <div class="charts-row" data-cat="posts">
                <div class="chart-section" data-cat="posts">
                    <div class="chart-title">Répartition des Posts par Jour</div>
                    <canvas id="postsChart"></canvas>
                </div>
                <div class="chart-circle" data-cat="posts">
                    <div class="chart-title">Posts en Vedette</div>
                    <canvas id="featuredPostsChart"></canvas>
                </div>
            </div>
            
            <div class="charts-row" data-cat="interactions">
                <div class="chart-section" data-cat="interactions">
                    <div class="chart-title">Répartition des Interactions</div>
                    <canvas id="interactionsChart"></canvas>
                </div>
                <div class="chart-circle" data-cat="interactions">
                    <div class="chart-title">Types d'Interactions</div>
                    <canvas id="interactionsTypeChart"></canvas>
                </div>
            </div>
            
            <div class="charts-row" data-cat="admin">
                <div class="chart-section" data-cat="admin">
                    <div class="chart-title">Admins</div>
                    <canvas id="adminChart2"></canvas>
                </div>
                <div class="chart-circle" data-cat="admin">
                    <div class="chart-title">Autre Stat Admin</div>
                    <canvas id="adminOtherChart"></canvas>
                </div>
            </div>
            
            <div class="charts-row" data-cat="reports">
                <div class="chart-section" data-cat="reports">
                    <div class="chart-title">Signalements par Type</div>
                    <canvas id="reportsChart"></canvas>
                </div>
                <div class="chart-circle" data-cat="reports">
                    <div class="chart-title">Statut des Signalements</div>
                    <canvas id="reportsStatusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
    const menuBtns = document.querySelectorAll('.menu-btn');
    const statCards = document.querySelectorAll('.stat-card');
    const chartsRows = document.querySelectorAll('.charts-row');

    menuBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            menuBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const cat = btn.getAttribute('data-cat');
            
            statCards.forEach(card => {
                card.style.display = (cat === 'all' || card.getAttribute('data-cat') === cat) ? '' : 'none';
            });
            
            chartsRows.forEach(row => {
                row.style.display = (cat === 'all' || row.getAttribute('data-cat') === cat) ? '' : 'none';
            });
        });
    });

    // Chart.js avec données réelles
    new Chart(document.getElementById('usersChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode($users_labels) ?>,
            datasets: [{
                label: 'Inscriptions',
                data: <?= json_encode($users_values) ?>,
                backgroundColor: 'rgba(37,99,235,0.1)',
                borderColor: 'rgba(37,99,235,1)',
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
                backgroundColor: 'rgba(59,130,246,0.7)',
                borderColor: 'rgba(59,130,246,1)',
                borderWidth: 1
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById('interactionsChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Commentaires', 'Likes', 'Messages', 'Amitiés', 'Abonnés'],
            datasets: [{
                data: [<?= $global_stats['total_comments'] ?>, <?= $global_stats['total_likes'] ?>, <?= $global_stats['total_messages'] ?>, <?= $global_stats['total_friendships'] ?>, <?= $global_stats['total_followers'] ?>],
                backgroundColor: [
                    'rgba(34,197,94,0.7)',
                    'rgba(14,165,233,0.7)',
                    'rgba(37,99,235,0.7)',
                    'rgba(245,158,66,0.7)',
                    'rgba(59,130,246,0.7)'
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
                    'rgba(37,99,235,0.7)'
                ]
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('adminChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($admin_labels) ?>,
            datasets: [{
                data: <?= json_encode($admin_values) ?>,
                backgroundColor: [
                    'rgba(37,99,235,0.7)',
                    'rgba(30,41,59,0.7)'
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
                backgroundColor: 'rgba(59,130,246,0.7)',
                borderColor: 'rgba(59,130,246,1)',
                borderWidth: 1
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById('interactionsTypeChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Commentaires', 'Likes', 'Messages', 'Amitiés', 'Abonnés'],
            datasets: [{
                data: [<?= $global_stats['total_comments'] ?>, <?= $global_stats['total_likes'] ?>, <?= $global_stats['total_messages'] ?>, <?= $global_stats['total_friendships'] ?>, <?= $global_stats['total_followers'] ?>],
                backgroundColor: [
                    'rgba(34,197,94,0.7)',
                    'rgba(14,165,233,0.7)',
                    'rgba(37,99,235,0.7)',
                    'rgba(245,158,66,0.7)',
                    'rgba(59,130,246,0.7)'
                ]
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('reportsStatusChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($reports_status_labels) ?>,
            datasets: [{
                data: <?= json_encode($reports_status_values) ?>,
                backgroundColor: [
                    'rgba(239,68,68,0.7)',
                    'rgba(37,99,235,0.7)'
                ]
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
    </script>
</body>
</html>