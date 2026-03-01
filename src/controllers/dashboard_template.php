<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Coordinateur</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard_style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-logo">
            <img src="images/logo.png" alt="Logo">
        </div>
        <div class="header-title">
            Bienvenue Coordinateur
        </div>
    </header>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>Menu Principal</h3>
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="dashborde_coordinateur.php" class="active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>
            <li>
                <a href="gestion_groupes.php">
                    <i class="fas fa-users"></i>
                    <span>Gérer les groupes</span>
                </a>
            </li>
            <li>
                <a href="emplois_du_temps.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Emplois du temps</span>
                </a>
            </li>
            <li>
                <a href="affectation_vacataires.php">
                    <i class="fas fa-user-tie"></i>
                    <span>Affectation des vacataires</span>
                </a>
            </li>
            <li>
                <a href="unites_enseignement.php">
                    <i class="fas fa-book"></i>
                    <span>Unités d'enseignement</span>
                </a>
            </li>
            <li>
                <a href="export_excel.php">
                    <i class="fas fa-file-excel"></i>
                    <span>Extraire en Excel</span>
                </a>
            </li>
            <li>
                <a href="logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Dashboard Cards -->
        <div class="dashboard-cards">
            <div class="card">
                <div class="card-icon purple">
                    <i class="fas fa-users"></i>
                </div>
                <div class="card-number">2</div>
                <div class="card-title">Vacataires</div>
            </div>
            
            <div class="card">
                <div class="card-icon blue">
                    <i class="fas fa-book"></i>
                </div>
                <div class="card-number">7</div>
                <div class="card-title">Unités d'enseignement</div>
            </div>
            
            <div class="card">
                <div class="card-icon blue">
                    <i class="fas fa-link"></i>
                </div>
                <div class="card-number">5</div>
                <div class="card-title">Affectations</div>
            </div>
            
            <div class="card">
                <div class="card-icon pink">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="card-number">1</div>
                <div class="card-title">Emplois du temps</div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <!-- Statistics Chart -->
                <div class="card">
                    <h3>Statistiques de visites mensuelles (2025)</h3>
                    <div>
                        <canvas id="visitsChart" height="250"></canvas>
                    </div>
                    <div class="chart-legend">
                        <button class="btn btn-primary" id="viewLines">Lignes</button>
                        <button class="btn btn-secondary" id="viewColumns">Colonnes</button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Personal Information -->
                <div class="info-card">
                    <h3>Informations personnelles</h3>
                    <div class="info-item">
                        <span class="info-label">Email :</span>
                        <span><?php echo $_SESSION['email'] ?? 'min@ensah.ma'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Filière :</span>
                        <span><?php echo $_SESSION['filiere'] ?? 'Informatique'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Année scolaire :</span>
                        <span><?php echo $_SESSION['annee_scolaire'] ?? '2024-2025'; ?></span>
                    </div>
                </div>
                
                <!-- Calendar -->
                <div class="calendar-card">
                    <div class="calendar-header">
                        <h3>Calendrier</h3>
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div id="calendar">
                        <h4 class="text-center">Juin 2024</h4>
                        <div class="calendar-grid">
                            <div class="calendar-day header">Di</div>
                            <div class="calendar-day header">Lu</div>
                            <div class="calendar-day header">Ma</div>
                            <div class="calendar-day header">Me</div>
                            <div class="calendar-day header">Je</div>
                            <div class="calendar-day header">Ve</div>
                            <div class="calendar-day header">Sa</div>
                            
                            <!-- Calendar days -->
                            <?php
                            // Generate calendar days
                            $days = [27, 28, 29, 30, 31, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23];
                            foreach ($days as $day) {
                                $class = ($day == 21) ? 'calendar-day today' : 'calendar-day';
                                echo "<div class='$class'>$day</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Links -->
        <div class="quick-links">
            <h3>Liens rapides</h3>
            <ul class="quick-links-list">
                <li>
                    <a href="gestion_groupes.php">
                        <i class="fas fa-users"></i>
                        Gérer les groupes
                    </a>
                </li>
                <li>
                    <a href="emplois_du_temps.php">
                        <i class="fas fa-calendar-alt"></i>
                        Gérer les emplois du temps
                    </a>
                </li>
                <li>
                    <a href="affectation_vacataires.php">
                        <i class="fas fa-user-tie"></i>
                        Affectation des vacataires
                    </a>
                </li>
                <li>
                    <a href="unites_enseignement.php">
                        <i class="fas fa-book"></i>
                        Unités d'enseignement
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <script>
        // Chart initialization
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('visitsChart').getContext('2d');
            var chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
                    datasets: [{
                        label: 'Nombre de visites',
                        data: [5, 10, 15, 8, 12, 18, 10, 5, 15, 35, 15, 10],
                        backgroundColor: 'rgba(142, 36, 170, 0.2)',
                        borderColor: '#8e24aa',
                        borderWidth: 2,
                        pointBackgroundColor: '#8e24aa',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // Toggle chart type
            document.getElementById('viewLines').addEventListener('click', function() {
                chart.config.type = 'line';
                chart.update();
            });
            
            document.getElementById('viewColumns').addEventListener('click', function() {
                chart.config.type = 'bar';
                chart.update();
            });
        });
    </script>
</body>
</html>
