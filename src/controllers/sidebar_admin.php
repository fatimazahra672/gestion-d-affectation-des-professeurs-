<?php
// Vérification de la session
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login_coordinateur.php");
    exit();
}

// Déterminer la page active
$current_page = basename($_SERVER['PHP_SELF']);
$current_type = isset($_GET['type']) ? $_GET['type'] : '';
?>

<!-- Styles pour le menu déroulant -->
<style>
    .sidebar .nav-link.dropdown-toggle::after {
        display: inline-block;
        margin-left: 0.5em;
        vertical-align: 0.15em;
        content: "";
        border-top: 0.3em solid;
        border-right: 0.3em solid transparent;
        border-bottom: 0;
        border-left: 0.3em solid transparent;
        transition: transform 0.3s ease;
    }

    .sidebar .nav-link.dropdown-toggle[aria-expanded="true"]::after {
        transform: rotate(180deg);
    }

    .sidebar #userSubmenu {
        padding-left: 0;
        list-style: none;
        transition: all 0.3s ease;
    }

    .sidebar #userSubmenu .nav-link {
        padding-left: 2.5rem;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        margin: 4px 0;
    }

    .sidebar #userSubmenu .nav-link:hover {
        background-color: rgba(30, 144, 255, 0.2);
    }

    .sidebar #userSubmenu .nav-link.active {
        background-color: rgba(30, 144, 255, 0.3);
        border-left: 3px solid var(--primary-blue);
    }
</style>

<!-- Sidebar -->
<nav class="col-md-3 col-lg-2 sidebar">
    <div class="text-center mb-4">
        <img src="images/logo.png" alt="ENSAH" class="img-fluid mb-3" style="filter: drop-shadow(0 0 5px var(--primary-blue));">
        <h5 class="text-white" style="text-shadow: 0 0 10px var(--primary-blue);">Administration ENSAH</h5>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= $current_page === 'admin_dashboard.php' ? 'active' : '' ?>" href="admin_dashboard.php">
                <i class="fas fa-chart-line me-2"></i>Tableau de bord
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link dropdown-toggle <?= $current_page === 'gestion_utilisateurs.php' || $current_page === 'gestion_chef_departement.php' ? 'active' : '' ?>"
               href="#userSubmenu"
               data-bs-toggle="collapse"
               aria-expanded="<?= $current_page === 'gestion_utilisateurs.php' || $current_page === 'gestion_chef_departement.php' ? 'true' : 'false' ?>">
                <i class="fas fa-users-cog me-2"></i>Gestion Utilisateurs
            </a>
            <ul class="collapse <?= $current_page === 'gestion_utilisateurs.php' || $current_page === 'gestion_chef_departement.php' ? 'show' : '' ?>" id="userSubmenu">
                <li class="nav-item">
                    <a class="nav-link ms-3 <?= $current_page === 'gestion_chef_departement.php' ? 'active' : ($current_type === 'chef_departement' ? 'active' : '') ?>" href="gestion_chef_departement.php">
                        <i class="fas fa-user-tie me-2"></i> Chefs de département
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link ms-3 <?= $current_type === 'coordinateur' ? 'active' : '' ?>" href="gestion_utilisateurs.php?type=coordinateur">
                        <i class="fas fa-user-cog me-2"></i> Coordinateurs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link ms-3 <?= $current_type === 'enseignant' ? 'active' : '' ?>" href="gestion_utilisateurs.php?type=enseignant">
                        <i class="fas fa-chalkboard-teacher me-2"></i> Enseignants
                    </a>
                </li>
            </ul>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_page === 'gestion_departements.php' ? 'active' : '' ?>" href="gestion_departements.php">
                <i class="fas fa-building me-2"></i>Départements
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_page === 'gestion_specialites.php' ? 'active' : '' ?>" href="gestion_specialites.php">
                <i class="fas fa-graduation-cap me-2"></i>Spécialités
            </a>
        </li>
        <li class="nav-item mt-4">
            <a class="nav-link text-danger" href="logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
            </a>
        </li>
    </ul>
</nav>

<!-- Script pour le menu déroulant -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Gestion du clic sur les liens du sous-menu
        const subMenuLinks = document.querySelectorAll('#userSubmenu .nav-link');
        subMenuLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // Empêcher la fermeture du menu déroulant lors du clic sur un sous-élément
                e.stopPropagation();
            });
        });

        // Gestion de l'animation de la flèche du menu déroulant
        const dropdownToggle = document.querySelector('.nav-link.dropdown-toggle');
        if (dropdownToggle) {
            dropdownToggle.addEventListener('click', function() {
                const isExpanded = this.getAttribute('aria-expanded') === 'true';
                this.setAttribute('aria-expanded', !isExpanded);
            });
        }
    });
</script>
