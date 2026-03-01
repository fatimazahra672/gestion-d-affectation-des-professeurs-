</html>
<?php
session_start();

// Vérification de la session
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'enseignant') {
    header('Location: login_coordinateur.php');
    exit();
}

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=gestion_coordinteur;charset=utf8",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupération des informations
$id_utilisateur = $_SESSION['user_id'] ?? $_SESSION['id_utilisateur'] ?? $_SESSION['id'] ?? null;

if (!$id_utilisateur) {
    $_SESSION['error'] = "Session invalide - ID utilisateur non trouvé";
    header('Location: login_coordinateur.php');
    exit();
}

$info = null;

try {
    $stmt = $pdo->prepare("
        SELECT u.*, s.nom_specialite
        FROM utilisateurs u
        LEFT JOIN specialite s ON u.id_specialite = s.id_specialite
        WHERE u.id = ? AND u.type_utilisateur = 'enseignant'
    ");
    $stmt->execute([$id_utilisateur]);
    $info = $stmt->fetch();

    if (!$info) {
        $_SESSION['error'] = "Profil introuvable";
        header('Location: login_coordinateur.php');
        exit();
    }

} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}

// Récupération des données complémentaires
try {
    // Matières de l'enseignant connecté
    $matieres_stmt = $pdo->prepare("
        SELECT m.code, m.nom, m.credit
        FROM matieres m
        WHERE m.id_utilisateur = ?
        ORDER BY m.code
    ");
    $matieres_stmt->execute([$id_utilisateur]);
    $matieres = $matieres_stmt->fetchAll();

    // Groupes encadrés
    $groupes = []; 
    
    // Emploi du temps  
    $emploi = [];

    // Génération de données dynamiques pour le graphique
    $noms_mois = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
    $mois_actuel = (int)date('n') - 1; // Mois actuel (0-indexé)
    $labels_mois = [];
    $donnees_mois = [];
    
    // On veut les 6 derniers mois (du plus ancien au plus récent)
    for ($i = 5; $i >= 0; $i--) {
        $index_mois = ($mois_actuel - $i + 12) % 12;
        $labels_mois[] = $noms_mois[$index_mois];
        // Génération de données aléatoires pour la démo
        $donnees_mois[] = rand(20, 100);
    }

} catch (PDOException $e) {
    die("Erreur de chargement : " . $e->getMessage());
}

// Déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login_coordinateur.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord enseignant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #6a0dad;
            --secondary-color: #8a2be2;
            --light-purple: #e6e6fa;
            --dark-purple: #4b0082;
            --accent-color: #00bfff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f0f5;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            color: white;
            padding: 15px 25px;
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

        .header img {
            width: 45px;
            height: 45px;
            filter: drop-shadow(0 0 5px rgba(255, 255, 255, 0.5));
        }

        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
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
            width: 250px;
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

        .profile-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            margin: 0 auto 10px;
            border: 3px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            color: white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
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

        /* Contenu principal */
        .main-content {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            background-color: #f8f9fc;
        }

        /* Section de gestion */
        .dashboard-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-purple);
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px 15px;
            background: #f8f9fc;
            color: #555;
            font-weight: 600;
            border-bottom: 2px solid #eee;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            color: #333;
        }

        tr:hover td {
            background: #f8f9ff;
        }

        .action-btn {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background: #e6f7ee;
            color: #10b981;
        }

        .status-inactive {
            background: #fff4e6;
            color: #f59e0b;
        }

        /* Styles pour le graphique et calendrier */
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }

        .stats-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .stats-numbers {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .stat-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .calendar {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .calendar th, .calendar td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }

        .calendar th {
            background-color: #f2f2f2;
        }

        .calendar .today {
            background-color: #e6f7ff;
            font-weight: bold;
        }

        .search-box {
            margin-top: 20px;
        }

        .search-box input {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        /* Nouveau style pour la partie gauche */
        .left-section {
            flex: 0 0 30%;
            padding-right: 20px;
        }

        .right-section {
            flex: 1;
        }

        .content-row {
            display: flex;
            gap: 20px;
        }

        /* Styles ajoutés pour le nouveau graphique */
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

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 220px;
            }
            
            .content-row {
                flex-direction: column;
            }
            
            .left-section, .right-section {
                width: 100%;
                padding-right: 0;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header-left, .header-right {
                width: 100%;
                justify-content: center;
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
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-left">
            <img src="images/logo.png" alt="Logo">
            <h1>Tableau de bord enseignant</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <div>
                    <div class="user-info-label">Enseignant</div>
                    <div class="user-info-value"><?= htmlspecialchars($info['prenom'] . ' ' . $info['nom']) ?></div>
                </div>
            </div>
        </div>
    </header>

    <div class="main-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="profile-circle">EN</div>
                <h3><?= htmlspecialchars($info['prenom'] . ' ' . $info['nom']) ?></h3>
                <small class="text-white-50"><?= htmlspecialchars($info['nom_specialite'] ?? 'Non spécifiée') ?></small>
            </div>
            <nav class="sidebar-menu">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="#" class="nav-link active">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Tableau de bord</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="Affichage_liste_UE.php" class="nav-link">
                            <i class="fas fa-list-ul"></i>
                            <span>Liste des UE</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="souhaits_enseignants.php" class="nav-link">
                            <i class="fas fa-hand-paper"></i>
                            <span>Souhaits enseignants</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="Calcul_automatique_charge_horaire.php" class="nav-link">
                            <i class="fas fa-calculator"></i>
                            <span>Charge horaire</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="Notification.php" class="nav-link">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="Consulter_modules.php" class="nav-link">
                            <i class="fas fa-book-open"></i>
                            <span>Modules assurés</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="Uploader_notes.php" class="nav-link">
                            <i class="fas fa-upload"></i>
                            <span>Upload notes</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="historique.php" class="nav-link">
                            <i class="fas fa-history"></i>
                            <span>Historique</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?logout=true" class="nav-link logout-btn">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Déconnexion</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="content-row">
                <div class="left-section">
                    <section class="dashboard-section">
                        <div class="section-header">
                            <h2 class="section-title">Statistiques de visites</h2>
                            <div class="chart-tabs">
                                <div class="chart-tab active">Lignes</div>
                                <div class="chart-tab">Colonnes</div>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="visitsChart"></canvas>
                        </div>
                        <div class="stats-container">
                            <div class="stats-numbers">
                                <div class="stat-number"><?= count($matieres) ?> matières</div>
                                <div class="stat-number"><?= count($groupes) ?> groupes</div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="right-section">
                    <section class="dashboard-section">
                        <div class="section-header">
                            <h2 class="section-title">Matières enseignées</h2>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nom de la matière</th>
                                        <th>Code</th>
                                        <th>Crédits</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($matieres as $matiere): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($matiere['nom']) ?></td>
                                        <td><?= htmlspecialchars($matiere['code']) ?></td>
                                        <td><?= $matiere['credit'] ?></td>
                                        <td>
                                            <button class="action-btn btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> Voir
                                            </button>
                                            <button class="action-btn btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-edit"></i> Modifier
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($matieres)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Aucune matière assignée</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="dashboard-section">
                        <div class="section-header">
                            <h2 class="section-title">Emploi du temps</h2>
                        </div>
                        <?php if (!empty($emploi)): ?>
                        <table class="calendar">
                            <thead>
                                <tr>
                                    <th>Jour</th>
                                    <th>Horaire</th>
                                    <th>Groupe</th>
                                    <th>Salle</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($emploi as $cours): ?>
                                <tr>
                                    <td><?= htmlspecialchars($cours['jour']) ?></td>
                                    <td><?= $cours['debut'] ?> - <?= $cours['fin'] ?></td>
                                    <td><?= htmlspecialchars($cours['groupe']) ?></td>
                                    <td><?= htmlspecialchars($cours['salle']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="alert alert-info">Aucun cours programmé cette semaine</div>
                        <?php endif; ?>
                        <div class="search-box">
                            <input type="text" placeholder="Rechercher...">
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Graphique des visites dynamique
        const ctx = document.getElementById('visitsChart').getContext('2d');
        const visitsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels_mois); ?>,
                datasets: [{
                    label: 'Nombre de visites',
                    data: <?php echo json_encode($donnees_mois); ?>,
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
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255, 255, 255, 0.9)',
                        titleColor: '#333',
                        bodyColor: '#333',
                        borderColor: 'rgba(106, 13, 173, 0.5)',
                        borderWidth: 1,
                        padding: 10
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
                    visitsChart.config.data.datasets[0].backgroundColor = 'rgba(106, 13, 173, 0.7)';
                } else {
                    visitsChart.config.type = 'line';
                    visitsChart.config.data.datasets[0].backgroundColor = 'rgba(106, 13, 173, 0.2)';
                }
                visitsChart.update();
            });
        });

        // Mise à jour dynamique des données chaque minute
        setInterval(() => {
            // Simuler de nouvelles données
            const newData = visitsChart.data.datasets[0].data.map(value => {
                // Variation de ±5
                const variation = Math.floor(Math.random() * 10) - 5;
                return Math.max(0, value + variation);
            });

            // Mettre à jour le graphique
            visitsChart.data.datasets[0].data = newData;
            visitsChart.update();
        }, 60000); // Mise à jour toutes les minutes
    </script>
</body>
</html>