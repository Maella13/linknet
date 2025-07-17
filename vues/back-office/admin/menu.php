<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../../config/database.php";
if (!isset($_SESSION["admin"])) {
    header("Location: /vues/back-office/admin/auth/login.php");
    exit();
}

$role = $_SESSION["admin"]["role"] ?? 'Modérateur';

// Table => [fichier, icône FontAwesome]
$tables = [
    'Utilisateurs' => ['../users/users.php', 'fa-user'],
    'Posts' => ['../posts/posts.php', 'fa-pen-nib'],
    'Commentaires' => ['../comments/comments.php', 'fa-comment-dots'],
    'Likes' => ['../likes/likes.php', 'fa-heart'],
    'Messages' => ['../messages/messages.php', 'fa-envelope'],
    'Amitiés' => ['../friends/friends.php', 'fa-user-friends'],
    'Abonnés' => ['../followers/followers.php', 'fa-users'],
    'Posts en vedette' => ['../featured_posts/featured_posts.php', 'fa-star'],
    'Hashtags' => ['../hashtags/hashtags.php', 'fa-hashtag'],
    'Notifications' => ['../notifications/notifications.php', 'fa-bell'],
    'Signalements' => ['../reports/reports.php', 'fa-flag'],
    'Admins' => ['../admins/admins.php', 'fa-user-shield'],
    'Requêtes d\'amis' => ['../friend_requests/friend_requests.php', 'fa-user-plus']
];

$mod_links =  array_keys($tables);
$admin_links = array_keys($tables);
$accessible = ($role === 'Administrateur') ? $admin_links : $mod_links;

// Couleurs de survol par index
$hover_colors = [
    '#2563eb', // bleu
    '#a16207', // marron/doré
    '#22c55e', // vert
    '#f59e42', // orange
    '#8b5cf6', // violet
    '#0ea5e9', // bleu clair
    '#f43f5e', // rose/rouge
    '#fbbf24', // jaune
    '#14b8a6', // turquoise
    '#6366f1', // indigo
    '#e11d48', // rouge foncé
    '#7c3aed', // violet foncé
    '#475569', // gris foncé
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="shortcut icon" href="/assets/images/linknet_logo.webp" type="image/x-icon">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="/assets/css/back-office/menu.css">
</head>
<body>
    <div class="menu-sidebar-pro" id="menuSidebarPro">
        <div class="menu-header">
            <div class="menu-header-title">
                <img src="/assets/images/linknet_logo.webp" alt="Linknet Logo" class="menu-logo">
                <span class="menu-title">Admin Panel</span>
            </div>
            <button class="menu-toggle-btn" id="menuSidebarProToggle" title="Fermer le menu">
            <i class="fas fa-times"></i> </button>
        </div>

        <ul class="menu-sidebar-pro-list">
            <li>
                <a class="menu-sidebar-pro-link menu-hover-0" href="../dashboard/dashboard.php">
                    <span class="ms-icon"><i class="fas fa-tachometer-alt"></i></span>
                    <span class="ms-label">Dashboard</span>
                </a>
            </li>

            <?php $i = 1; foreach ($tables as $label => $arr): ?>
                <?php if (in_array($label, $accessible)): ?>
                    <li>
                        <a class="menu-sidebar-pro-link menu-hover-<?= $i ?>" href="<?= htmlspecialchars($arr[0]) ?>">
                            <span class="ms-icon"><i class="fas <?= $arr[1] ?>"></i></span>
                            <span class="ms-label"><?= htmlspecialchars($label) ?></span>
                        </a>
                    </li>
                    <?php $i++; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>

        <div class="menu-footer">
            <a class="menu-sidebar-pro-profile" href="/vues/back-office/admin/auth/profile.php">
                <span class="ms-icon"><i class="fas fa-user-circle"></i></span>
                <span class="ms-label">Profil</span>
            </a>
            <a class="menu-sidebar-pro-logout" href="/vues/back-office/admin/auth/logout.php">
                <span class="ms-icon"><i class="fas fa-sign-out-alt"></i></span>
                <span class="ms-label">Déconnexion</span>
            </a>
        </div>
    </div>

    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <script>
    const sidebar = document.getElementById('menuSidebarPro');
    const toggleBtn = document.getElementById('menuSidebarProToggle');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');

    // Function to check if the screen is considered mobile
    function isMobileScreen() {
        return window.innerWidth <= 750 || (window.innerHeight <= 750 && window.innerWidth <= 360);
    }

    // Function to close the mobile menu
    function closeMobileMenu() {
        sidebar.classList.remove('active');
        document.body.classList.remove('menu-active');
        // Ensure the toggle button icon is a hamburger when the menu is closed on mobile
        if (isMobileScreen()) {
            toggleBtn.querySelector('i').classList.remove('fa-times', 'fa-chevron-left', 'fa-chevron-right');
            toggleBtn.querySelector('i').classList.add('fa-bars');
        }
    }

    // Handle menu toggle for large screens (desktop)
    toggleBtn.addEventListener('click', function() {
        if (isMobileScreen()) {
            // On mobile, this is the close button
            closeMobileMenu(); // Call the function to close
        } else {
            // On desktop, this is the collapse/expand button
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('menuCollapsed', sidebar.classList.contains('collapsed'));
            // Change the toggle button icon on desktop
            if (sidebar.classList.contains('collapsed')) {
                toggleBtn.querySelector('i').classList.remove('fa-chevron-left');
                toggleBtn.querySelector('i').classList.add('fa-chevron-right');
            } else {
                toggleBtn.querySelector('i').classList.remove('fa-chevron-right');
                toggleBtn.querySelector('i').classList.add('fa-chevron-left');
            }
        }
    });

    // Event listener for the mobile toggle button (hamburger icon)
    mobileMenuToggle.addEventListener('click', function() {
        sidebar.classList.add('active'); // Open the menu
        sidebar.classList.remove('collapsed'); // Ensure the menu is always expanded on mobile
        document.body.classList.add('menu-active'); // Hide the mobile button (hamburger)
        // The close button icon should always be a cross on mobile when open
        toggleBtn.querySelector('i').classList.remove('fa-chevron-left', 'fa-chevron-right', 'fa-bars');
        toggleBtn.querySelector('i').classList.add('fa-times');
    });

    // Handle active state of menu links
    // Select ALL relevant links, including those in the footer
    const menuLinks = document.querySelectorAll(
        '.menu-sidebar-pro-list .menu-sidebar-pro-link, ' +
        '.menu-footer .menu-sidebar-pro-profile, ' +
        '.menu-footer .menu-sidebar-pro-logout'
    );

    function setActiveLink(clickedLink) {
        menuLinks.forEach(link => {
            link.classList.remove('active');
        });
        clickedLink.classList.add('active');
    }

    menuLinks.forEach(link => {
        link.addEventListener('click', function() {
            setActiveLink(this);
            // Close the mobile menu after clicking a link if it's a mobile screen
            if (isMobileScreen()) {
                closeMobileMenu(); // Call the function to close
            }
        });
    });

    // Set the active link based on the current page on load
    const currentPage = window.location.pathname.split('/').pop();
    menuLinks.forEach(link => {
        const href = link.getAttribute('href');
        // Check if the href contains the current page name
        if (href.includes(currentPage) && currentPage !== '') {
            setActiveLink(link);
        } else if (currentPage === '' && href.includes('dashboard.php')) {
            // If the URL is the root (e.g., /admin/) and the link is to the dashboard, make it active
            // This is often the case if dashboard.php is the default file in a folder
            setActiveLink(link);
        }
        // Specific case for profile and logout links:
        // The link "/vues/back-office/admin/auth/profile.php" should activate "Profil"
        // The link "/vues/back-office/admin/auth/logout.php" should activate "Déconnexion"
        // Ensure the current page exactly matches the href
        if (window.location.pathname === href) {
            setActiveLink(link);
        }
    });

    // Adjust menu state on window resize
    window.addEventListener('resize', function() {
        if (!isMobileScreen()) {
            // If not on a mobile screen, disable mobile classes
            sidebar.classList.remove('active');
            document.body.classList.remove('menu-active');
            mobileMenuToggle.style.display = 'none'; // Hide the hamburger button
            // Restore "collapsed" state if saved for large screens
            if (localStorage.getItem('menuCollapsed') === 'true') {
                sidebar.classList.add('collapsed');
                toggleBtn.querySelector('i').classList.remove('fa-times', 'fa-chevron-left', 'fa-bars');
                toggleBtn.querySelector('i').classList.add('fa-chevron-right');
            } else {
                toggleBtn.querySelector('i').classList.remove('fa-times', 'fa-chevron-right', 'fa-bars');
                toggleBtn.querySelector('i').classList.add('fa-chevron-left');
            }
        } else {
            // If on a mobile screen, ensure the menu is not "collapsed"
            sidebar.classList.remove('collapsed');
            mobileMenuToggle.style.display = 'flex'; // Show the hamburger button
            // The close icon should always be a cross on mobile if the menu is open
            if (sidebar.classList.contains('active')) {
                 toggleBtn.querySelector('i').classList.remove('fa-chevron-left', 'fa-chevron-right', 'fa-bars');
                 toggleBtn.querySelector('i').classList.add('fa-times');
            } else {
                // Otherwise, it's the hamburger icon
                toggleBtn.querySelector('i').classList.remove('fa-chevron-left', 'fa-chevron-right', 'fa-times');
                toggleBtn.querySelector('i').classList.add('fa-bars');
            }
            // If the menu is open on mobile during resize, hide the mobile button
            if (sidebar.classList.contains('active')) {
                document.body.classList.add('menu-active');
            } else {
                document.body.classList.remove('menu-active');
            }
        }
    });

    // Initial call to set correct state on load
    window.dispatchEvent(new Event('resize'));
    </script>
</body>
</html>