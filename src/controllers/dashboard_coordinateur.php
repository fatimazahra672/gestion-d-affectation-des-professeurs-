<?php
session_start();

// Vérifier si l'utilisateur est connecté et est un coordinateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'coordinateur') {
    header("Location: login_coordinateur.php");
    exit;
}

// Données fictives pour le tableau de bord
$vacataires = 2;
$unites_enseignement = 7;
$affectations = 5;
$creneaux = 1;

// Inclure le script d'enregistrement des visites
require_once 'record_page_visit.php';

// Enregistrer la visite de la page
recordPageVisit('dashborde_coordinateur.php', 'coordinateur');

// Récupérer les statistiques de visite mensuelles pour l'année en cours
$visites = getMonthlyVisitStats('dashborde_coordinateur.php', date('Y'), 'coordinateur');

// Noms des mois en français
$mois = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];

// Informations personnelles
$email = $_SESSION['email'] ?? 'coordinateur1@example.com';
$filiere = $_SESSION['filiere'] ?? 'Informatique';
$annee_scolaire = $_SESSION['annee_scolaire'] ?? '2024-2025';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Coordinateur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #6a0dad;
            --secondary-color: #8a2be2;
            --light-purple: #e6e6fa;
            --dark-purple: #4b0082;
            --accent-color: #00bfff;
            --teacher-color: #3498db;
            --teacher-dark: #2980b9;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fc;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            color: white;
            padding: 10px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-logo {
            height: 50px;
            display: flex;
            align-items: center;
        }

        .header-logo img {
            height: 100%;
            filter: drop-shadow(0 0 5px rgba(255, 255, 255, 0.5));
        }

        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            padding-left: 15px;
            border-left: 1px solid rgba(255, 255, 255, 0.3);
        }

        .header-right {
            display: flex;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.15);
            padding: 8px 15px;
            border-radius: 30px;
            backdrop-filter: blur(5px);
        }

        .user-info i {
            color: rgba(255, 255, 255, 0.9);
        }

        .user-info-label {
            font-weight: 600;
            color: rgba(255, 255, 255, 0.8);
        }

        .user-info-value {
            color: white;
            font-weight: 500;
        }

        /* Conteneur principal */
        .main-container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        /* ================ SIDEBAR MODERNE ================ */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--dark-purple) 0%, var(--primary-color) 100%);
            height: 100%;
            padding: 20px 0;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 15px;
        }

        .sidebar-header img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: 10px;
            border: 2px solid white;
            padding: 5px;
        }

        .sidebar-header h3 {
            color: white;
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }

        .sidebar-menu {
            flex: 1;
            overflow-y: auto;
            padding: 0 15px;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            color: white;
            padding: 12px 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            text-decoration: none;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .nav-link:hover::before {
            left: 100%;
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            border-left: 4px solid var(--accent-color);
        }

        .nav-link i {
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
        }

        .nav-link span {
            flex: 1;
        }

        .logout-btn {
            background: rgba(255, 71, 87, 0.2);
            margin-top: 10px;
        }

        .logout-btn:hover {
            background: rgba(255, 71, 87, 0.3);
        }

        /* Sections du sidebar */
        .section-title {
            padding: 12px 15px;
            font-weight: 600;
            color: white;
            background: rgba(0, 0, 0, 0.15);
            border-radius: 5px;
            margin: 15px 0 5px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .section-title:hover {
            background: rgba(0, 0, 0, 0.25);
        }

        .section-title.coordinateur {
            background: rgba(0, 0, 0, 0.2);
            border-left: 4px solid var(--accent-color);
        }

        .section-title.enseignant {
            background: rgba(0, 0, 0, 0.2);
            border-left: 4px solid var(--teacher-color);
        }

        .section-title .arrow {
            margin-left: auto;
            transition: transform 0.3s;
        }

        .section-title .arrow.rotated {
            transform: rotate(180deg);
        }

        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .submenu.open {
            max-height: 500px;
        }

        /* Contenu principal */
        .main-content {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            background-color: #f8f9fc;
        }

        /* Cartes de statistiques */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 24px;
        }

        .vacataires-icon {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }

        .ue-icon {
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
        }

        .affectations-icon {
            background: linear-gradient(135deg, var(--accent-color) 0%, #0077b6 100%);
        }

        .creneaux-icon {
            background: linear-gradient(135deg, #20c997 0%, #0d6efd 100%);
        }

        .stat-info {
            display: flex;
            flex-direction: column;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            line-height: 1.2;
        }

        .stat-label {
            font-size: 14px;
            color: #777;
            font-weight: 500;
        }

        /* Graphiques */
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            height: 350px;
            display: flex;
            flex-direction: column;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .chart-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-purple);
            margin: 0;
            display: flex;
            align-items: center;
        }

        .chart-title i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .chart-tabs {
            display: flex;
            gap: 10px;
        }

        .chart-tab {
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            background-color: #f0f0f5;
            font-size: 14px;
        }

        .chart-tab.active {
            background-color: var(--primary-color);
            color: white;
        }

        .chart-wrapper {
            flex: 1;
            position: relative;
        }

        /* Calendrier */
        .calendar-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            height: 100%;
        }

        .calendar-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .calendar-title {
            font-weight: 600;
        }

        .calendar-body {
            padding: 15px;
        }

        .calendar-month {
            text-align: center;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }

        .calendar-day {
            text-align: center;
            padding: 8px;
            border-radius: 5px;
            font-size: 14px;
        }

        .calendar-day.header {
            font-weight: bold;
            background-color: transparent;
            color: #777;
        }

        .calendar-day.active {
            background-color: var(--primary-color);
            color: white;
            font-weight: bold;
        }

        .calendar-day.today {
            background-color: var(--accent-color);
            color: white;
        }

        .content-row {
            display: flex;
            gap: 25px;
            margin-bottom: 25px;
        }

        .content-col {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .content-col-2-3 {
            flex: 2;
        }

        .content-col-1-3 {
            flex: 1;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 220px;
            }
            
            .content-row {
                flex-direction: column;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header-left {
                flex-direction: column;
                width: 100%;
                justify-content: center;
            }
            
            .header h1 {
                border-left: none;
                border-top: 1px solid rgba(255, 255, 255, 0.3);
                padding-left: 0;
                padding-top: 10px;
            }
            
            .header-right {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .main-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                padding: 15px 0;
            }
            
            .sidebar-menu {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
            }
            
            .nav-item {
                margin-bottom: 0;
            }
            
            .nav-link {
                padding: 8px 12px;
            }
            
            .stats-cards {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- En-tête avec logo ENSAH -->
    <div class="header">
        <div class="header-left">
            <div class="header-logo">
                <img src="https://upload.wikimedia.org/wikipedia/fr/thumb/5/5f/Logo_ENSAH.svg/1200px-Logo_ENSAH.svg.png" alt="Logo ENSAH">
            </div>
            <h1>Tableau de Bord Coordinateur</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-calendar-alt"></i>
                <span class="user-info-label">Année :</span>
                <span class="user-info-value"><?= htmlspecialchars($annee_scolaire) ?></span>
            </div>
        </div>
    </div>

    <!-- Conteneur principal -->
    <div class="main-container">
        <!-- Sidebar Moderne avec sections dépliables -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <img src="https://ui-avatars.com/api/?name=Coordinateur&background=8a2be2&color=fff" alt="Coordinateur">
                <h3>Coordinateur & Enseignant</h3>
            </div>
            
            <div class="sidebar-menu">
                <!-- Tableau de bord -->
                <div class="nav-item">
                    <a href="#" class="nav-link active">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Tableau de Bord</span>
                    </a>
                </div>
                
                <!-- Section Coordinateur -->
                <div class="section-title coordinateur" id="coord-section">
                    <i class="fas fa-user-tie"></i>
                    <span>Coordinateur</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </div>

                        <div class="nav-item">
                        <a href="gestion_unites_enseignements.php" class="nav-link">
                            <i class="fas fa-book"></i>
                            <span>Unités d'enseignement</span>
                        </a>
                    </div>
                
                <div class="submenu" id="coord-menu">
                    <div class="nav-item">
                        <a href="gerer_groupes.php" class="nav-link">
                            <i class="fas fa-users"></i>
                            <span>Gérer les groupes</span>
                        </a>
                    </div>

                   

                    <div class="nav-item">
                        <a href="affectation_vacataire.php" class="nav-link">
                            <i class="fas fa-user-tie"></i>
                            <span>Affectation vacataires</span>
                        </a>
                    </div>

                     <div class="nav-item">
                        <a href="creer_vacataire.php" class="nav-link">
                            <i class="fas fa-book"></i>
                            <span>créer compet vacataire</span>
                        </a>
                    </div>
                    



                     <div class="nav-item">
                        <a href="Export_Exel.php" class="nav-link">
                            <i class="fas fa-file-excel"></i>
                            <span>Extraire en Excel</span>
                        </a>
                    </div>

                         <div class="nav-item">
                        <a href="historique.php" class="nav-link">
                            <i class="fas fa-comments"></i>
                            <span>Historique</span>
                        </a>
                     </div>
                    <div class="nav-item">
                        <a href="gerer_emplois_temps.php" class="nav-link">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Emplois du temps</span>
                        </a>
                    </div>
                    
                </div>
                
                <!-- Section Enseignant -->
                <div class="section-title enseignant" id="teacher-section">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Enseignant</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </div>
                
                <div class="submenu" id="teacher-menu">
                    <div class="nav-item">
                        <a href="Affichage_liste_UE.php" class="nav-link">
                            <i class="fas fa-book-reader"></i>
                            <span>Listes UE</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="souhaits_enseignants.php" class="nav-link">
                            <i class="fas fa-calendar-check"></i>
                            <span>Souhaits enseignants</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="Calcul_automatique_charge_horaire.php" class="nav-link">
                            <i class="fas fa-file-signature"></i>
                            <span>Charge horaire</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="Notification.php" class="nav-link">
                            <i class="fas fa-tasks"></i>
                            <span>Notifications</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="Consulter_modules.php" class="nav-link">
                            <i class="fas fa-comments"></i>
                            <span>Modules assurés</span>
                        </a>
                    </div>

                     <div class="nav-item">
                        <a href="Uploader_notes.php" class="nav-link">
                            <i class="fas fa-comments"></i>
                            <span>Upload notes</span>
                        </a>
                    </div>

                     
                </div>
                
                <!-- Déconnexion -->
                <div class="nav-item">
                    <a href="logout.php" class="nav-link logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Déconnexion</span>
                    </a>
                </div>
            </div>
        </nav>

        <!-- Contenu principal -->
        <div class="main-content">
            <!-- Cartes de statistiques -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon vacataires-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?= $vacataires ?></div>
                        <div class="stat-label">Vacataires</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon ue-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?= $unites_enseignement ?></div>
                        <div class="stat-label">Unités d'enseignement</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon affectations-icon">
                        <i class="fas fa-link"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?= $affectations ?></div>
                        <div class="stat-label">Affectations</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon creneaux-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?= $creneaux ?></div>
                        <div class="stat-label">Créneaux horaires</div>
                    </div>
                </div>
            </div>

            <!-- Contenu en deux colonnes -->
            <div class="content-row">
                <!-- Colonne principale (2/3) -->
                <div class="content-col content-col-2-3">
                    <!-- Graphique -->
                    <div class="chart-container">
                        <div class="chart-header">
                            <div class="chart-title">
                                <i class="fas fa-chart-line"></i> Statistiques de visites mensuelles (<?= date('Y') ?>)
                            </div>
                            <div class="chart-tabs">
                                <div class="chart-tab active">Lignes</div>
                                <div class="chart-tab">Colonnes</div>
                            </div>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="visitsChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Colonne secondaire (1/3) -->
                <div class="content-col content-col-1-3">
                    <!-- Calendrier -->
                    <div class="calendar-card">
                        <div class="calendar-header">
                            <div class="calendar-title">Calendrier</div>
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <div class="calendar-body">
                            <div class="calendar-month">Juin 2024</div>
                            <div class="calendar-grid">
                                <div class="calendar-day header">Di</div>
                                <div class="calendar-day header">Lu</div>
                                <div class="calendar-day header">Ma</div>
                                <div class="calendar-day header">Me</div>
                                <div class="calendar-day header">Je</div>
                                <div class="calendar-day header">Ve</div>
                                <div class="calendar-day header">Sa</div>

                                <!-- Jours du mois -->
                                <div class="calendar-day">27</div>
                                <div class="calendar-day">28</div>
                                <div class="calendar-day">29</div>
                                <div class="calendar-day">30</div>
                                <div class="calendar-day">31</div>
                                <div class="calendar-day">1</div>
                                <div class="calendar-day">2</div>

                                <div class="calendar-day">3</div>
                                <div class="calendar-day">4</div>
                                <div class="calendar-day">5</div>
                                <div class="calendar-day">6</div>
                                <div class="calendar-day">7</div>
                                <div class="calendar-day">8</div>
                                <div class="calendar-day">9</div>

                                <div class="calendar-day">10</div>
                                <div class="calendar-day">11</div>
                                <div class="calendar-day">12</div>
                                <div class="calendar-day">13</div>
                                <div class="calendar-day">14</div>
                                <div class="calendar-day">15</div>
                                <div class="calendar-day">16</div>

                                <div class="calendar-day">17</div>
                                <div class="calendar-day">18</div>
                                <div class="calendar-day">19</div>
                                <div class="calendar-day">20</div>
                                <div class="calendar-day today">21</div>
                                <div class="calendar-day">22</div>
                                <div class="calendar-day">23</div>

                                <div class="calendar-day">24</div>
                                <div class="calendar-day active">25</div>
                                <div class="calendar-day">26</div>
                                <div class="calendar-day">27</div>
                                <div class="calendar-day">28</div>
                                <div class="calendar-day">29</div>
                                <div class="calendar-day">30</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialisation du graphique
        const ctx = document.getElementById('visitsChart').getContext('2d');
        const visitsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($mois) ?>,
                datasets: [{
                    label: 'Nombre de visites',
                    data: <?= json_encode($visites) ?>,
                    backgroundColor: 'rgba(106, 13, 173, 0.2)',
                    borderColor: 'rgba(106, 13, 173, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'white',
                    pointBorderColor: 'rgba(106, 13, 173, 1)',
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            font: {
                                size: 10
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 11
                            },
                            color: '#333',
                            padding: 5,
                            autoSkip: false,
                            maxRotation: 0
                        },
                        display: true
                    }
                }
            }
        });

        // Gestion des onglets du graphique
        document.querySelectorAll('.chart-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelector('.chart-tab.active').classList.remove('active');
                this.classList.add('active');

                // Changer le type de graphique
                if (this.textContent === 'Colonnes') {
                    visitsChart.config.type = 'bar';
                } else {
                    visitsChart.config.type = 'line';
                }
                visitsChart.update();
            });
        });
        
        // Gestion des sections dépliables
        document.querySelectorAll('.section-title').forEach(section => {
            section.addEventListener('click', function() {
                const sectionId = this.id;
                const menuId = sectionId.replace('section', 'menu');
                const menu = document.getElementById(menuId);
                const arrow = this.querySelector('.arrow');
                
                // Toggle menu
                menu.classList.toggle('open');
                
                // Toggle arrow rotation
                arrow.classList.toggle('rotated');
            });
        });
        
        // Ouvrir la section Coordinateur par défaut
        document.getElementById('coord-menu').classList.add('open');
        document.querySelector('#coord-section .arrow').classList.add('rotated');
        
        // Fermer la section Enseignant par défaut
        document.getElementById('teacher-menu').classList.remove('open');
        
        // Ajustement automatique de la taille du graphique
        window.addEventListener('resize', function() {
            visitsChart.resize();
        });
    </script>
</body>
</html>