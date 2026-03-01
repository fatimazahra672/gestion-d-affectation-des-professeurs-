<?php
// Vérification de la session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chef_departement') {
    header("Location: login.php");
    exit();
}

// Déterminer la page active
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard_chef.php">
            <img src="images/logo.png" alt="ENSAH" height="40" class="d-inline-block align-text-top me-2">
            ENSAH
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'dashboard_chef.php' ? 'active' : '' ?>" href="dashboard_chef.php">
                        <i class="fas fa-chart-line me-1"></i> Tableau de bord
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'ue_vacantes.php' ? 'active' : '' ?>" href="ue_vacantes.php">
                        <i class="fas fa-book-open me-1"></i> UE Vacantes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'gestion_enseignants.php' ? 'active' : '' ?>" href="gestion_enseignants.php">
                        <i class="fas fa-chalkboard-teacher me-1"></i> Enseignants
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'affectations.php' ? 'active' : '' ?>" href="affectations.php">
                        <i class="fas fa-tasks me-1"></i> Affectations
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($_SESSION['nom'] . ' ' . $_SESSION['prenom']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card me-1"></i> Mon profil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i> Déconnexion</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
