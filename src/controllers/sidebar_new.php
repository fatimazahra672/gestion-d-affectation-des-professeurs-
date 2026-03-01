<?php
// Déterminer la page actuelle
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
    :root {
        --primary-blue: #1e90ff;
        --primary-magenta: #9c27b0;
        --blue-transparent: rgba(30, 144, 255, 0.3);
        --magenta-transparent: rgba(156, 39, 176, 0.3);
    }

    .sidebar {
        background: rgba(44, 62, 80, 0.95);
        backdrop-filter: blur(5px);
        border-right: 2px solid var(--primary-blue);
        box-shadow: 4px 0 15px var(--blue-transparent);
        animation: borderGlow 8s infinite alternate;
        height: 100vh;
        position: fixed;
        width: 280px;
        z-index: 1000;
    }

    @keyframes borderGlow {
        0% { border-color: var(--primary-blue); }
        50% { border-color: var(--primary-magenta); }
        100% { border-color: var(--primary-blue); }
    }

    .sidebar .nav-link {
        color: white;
        padding: 12px 15px;
        margin: 8px 0;
        border-radius: 8px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .sidebar .nav-link:hover {
        background-color: rgba(30, 144, 255, 0.2);
        transform: translateX(5px);
    }

    .sidebar .nav-link.active {
        background-color: rgba(30, 144, 255, 0.3);
        border-left: 4px solid var(--primary-blue);
        transform: translateX(8px);
    }

    .sidebar .nav-link i {
        margin-right: 10px;
        width: 20px;
        text-align: center;
    }

    #sidebar #userSubmenu {
        background-color: rgba(0, 0, 0, 0.2);
        border-radius: 8px;
        padding: 5px 0;
        margin-left: 15px;
    }

    #sidebar #userSubmenu .nav-link {
        padding: 8px 15px;
        margin: 5px 0;
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
                <a class="nav-link dropdown-toggle <?= in_array($current_page, ['gestion_chef_departement.php', 'gestion_coordinateur.php', 'gestion_enseignant.php']) ? 'active' : '' ?>"
                   href="#userSubmenu"
                   data-bs-toggle="collapse"
                   aria-expanded="<?= in_array($current_page, ['gestion_chef_departement.php', 'gestion_coordinateur.php', 'gestion_enseignant.php']) ? 'true' : 'false' ?>">
                    <i class="fas fa-users me-2"></i> Gestion Utilisateurs
                </a>
                <ul class="collapse <?= in_array($current_page, ['gestion_chef_departement.php', 'gestion_coordinateur.php', 'gestion_enseignant.php']) ? 'show' : '' ?>" id="userSubmenu">
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
                        <a class="nav-link ms-3 <?= $current_page === 'gestion_enseignant.php' ? 'active' : '' ?>" href="gestion_enseignant.php">
                            <i class="fas fa-chalkboard-teacher me-2"></i> Enseignants
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
            <li class="nav-item mt-4">
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
