<!-- partials/sidebar.php -->
<div class="sidebar">
    <div class="sidebar-brand">
        <div class="logo-container">
            <img src="../assets/images/bu-logo.png" alt="BU Logo" class="sidebar-logo">
            <img src="../assets/images/polangui-logo.png" alt="Polangui Logo" class="sidebar-logo">
        </div>
        <h4 class="admin-portal-text">Admin Portal</h4>
    </div>

    <div class="sidebar-menu">
        <div class="sidebar-item">
            <a href="dashboard.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt me-2"></i>
                <span>Dashboard</span>
            </a>
        </div>

        <div class="sidebar-item">
            <a href="manage-users.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'manage-users.php' ? 'active' : '' ?>">
                <i class="fas fa-users me-2"></i>
                <span>Manage Users</span>
            </a>
        </div>

        <div class="sidebar-item">
            <a href="manage-attendance.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'manage-attendance.php' ? 'active' : '' ?>">
                <i class="fas fa-clipboard-check me-2"></i>
                <span>Attendance</span>
            </a>
        </div>

        <div class="sidebar-item">
            <a href="maps.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'maps.php' ? 'active' : '' ?>">
                <i class="fas fa-map-marked-alt me-2"></i>
                <span>Check-in Map</span>
            </a>
        </div>

        <div class="sidebar-item">
            <a href="reports.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar me-2"></i>
                <span>Reports</span>
            </a>
        </div>

        <div class="sidebar-item mt-3">
            <a href="admin-logout.php" class="sidebar-link">
                <i class="fas fa-sign-out-alt me-2"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>