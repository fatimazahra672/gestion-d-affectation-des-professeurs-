<?php
// Vérification de la session
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Déterminer la page active
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Styles pour le menu déroulant -->
<style>
    #sidebar .nav-link.dropdown-toggle::after {
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

    #sidebar .nav-link.dropdown-toggle[aria-expanded="true"]::after {
        transform: rotate(180deg);
    }

    #sidebar #userSubmenu {
        padding-left: 0;
        transition: all 0.3s ease;
    }

    #sidebar #userSubmenu .nav-link {
        padding-left: 2.5rem;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    #sidebar #userSubmenu .nav-link:hover {
        background-color: rgba(30, 144, 255, 0.2);
    }

    #sidebar #userSubmenu .nav-link.active {
        background-color: rgba(30, 144, 255, 0.3);
        border-left: 3px solid var(--primary-blue);
    }
</style>

<!-- Sidebar -->
<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block sidebar">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4">
            <img src="images/logo.png" alt="Logo" class="img-fluid mb-3" style="max-width: 120px; filter: drop-shadow(0 0 5px var(--primary-blue));">
            <h5 class="text-white" style="text-shadow: 0 0 10px var(--primary-blue);">
                Gestion Pédagogique
            </h5>
        </div>

        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'chef_dashboard.php' ? 'active' : '' ?>" href="chef_dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i> Tableau de bord
                </a>
            </li>

            <!-- Menu déroulant pour la gestion des utilisateurs -->
            <li class="nav-item">
                <a class="nav-link dropdown-toggle <?= in_array($current_page, ['gestion_chef_departement.php', 'gestion_coordinateur.php', 'gestion_professeurs.php']) ? 'active' : '' ?>"
                   href="#userSubmenu"
                   data-bs-toggle="collapse"
                   aria-expanded="<?= in_array($current_page, ['gestion_chef_departement.php', 'gestion_coordinateur.php', 'gestion_professeurs.php']) ? 'true' : 'false' ?>">
                    <i class="fas fa-users me-2"></i> Gestion Utilisateurs
                </a>
                <ul class="collapse <?= in_array($current_page, ['gestion_chef_departement.php', 'gestion_coordinateur.php', 'gestion_professeurs.php']) ? 'show' : '' ?>" id="userSubmenu">
                    <li class="nav-item">
                        <a class="nav-link ms-3 <?= $current_page === 'gestion_chef_departement.php' ? 'active' : '' ?>" href="gestion_chef_departement.php">
                            <i class="fas fa-user-tie me-2"></i> Chefs de département
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link ms-3 <?= $current_page === 'gestion_coordinateur.php' ? 'active' : '' ?>" href="gestion_coordinateur.php">
                            <i class="fas fa-user-cog me-2"></i> Coordinateurs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link ms-3 <?= $current_page === 'gestion_professeurs.php' ? 'active' : '' ?>" href="gestion_professeurs.php">
                            <i class="fas fa-chalkboard-teacher me-2"></i> Professeurs
                        </a>
                    </li>
                </ul>
            </li>

            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'gestion_modules.php' ? 'active' : '' ?>" href="gestion_modules.php">
                    <i class="fas fa-book-open me-2"></i> Modules
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'affectation_ue.php' ? 'active' : '' ?>" href="affectation_ue.php">
                    <i class="fas fa-tasks me-2"></i> Affectations
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'gestion_choix.php' ? 'active' : '' ?>" href="gestion_choix.php">
                    <i class="fas fa-check-circle me-2"></i> Validation Choix
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'charge_horaire.php' ? 'active' : '' ?>" href="charge_horaire.php">
                    <i class="fas fa-chart-pie me-2"></i> Charges Horaires
                </a>
            </li>
            <li class="nav-item mt-3">
                <a class="nav-link text-danger" href="logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i> Déconnexion
                </a>
            </li>
        </ul>
    </div>
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
