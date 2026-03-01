<?php
// Déterminer la page actuelle
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="admin_dashboard.php">
            <img src="images/logo.png" alt="ENSAH" height="40">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'admin_dashboard.php' ? 'active' : '' ?>" href="admin_dashboard.php">
                        <i class="fas fa-tachometer-alt me-1"></i> Tableau de bord
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_page, ['gestion_chef_departement.php', 'gestion_coordinateur.php', 'gestion_enseignant.php']) ? 'active' : '' ?>" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-users me-1"></i> Gestion Utilisateurs
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                        <li>
                            <a class="dropdown-item <?= $current_page === 'gestion_chef_departement.php' ? 'active' : '' ?>" href="gestion_chef_departement.php">
                                <i class="fas fa-user-tie me-1"></i> Chefs de département
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= $current_page === 'gestion_coordinateur.php' ? 'active' : '' ?>" href="gestion_coordinateur.php">
                                <i class="fas fa-user-cog me-1"></i> Coordinateurs
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= $current_page === 'gestion_enseignant.php' ? 'active' : '' ?>" href="gestion_enseignant.php">
                                <i class="fas fa-chalkboard-teacher me-1"></i> Enseignants
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'gestion_modules.php' ? 'active' : '' ?>" href="gestion_modules.php">
                        <i class="fas fa-book me-1"></i> Modules
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt me-1"></i> Déconnexion
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
