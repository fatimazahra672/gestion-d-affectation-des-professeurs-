<?php
require_once 'config.php';
session_start();

// Vérification de la session admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login_coordinateur.php");
    exit;
}

// Récupération de l'email de l'admin depuis la session
$admin_email = $_SESSION['email'] ?? 'admin@ensah.ma';

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Statistiques globales
    try {
        $stats = $pdo->query("
            SELECT
                (SELECT COUNT(*) FROM enseignants) AS total_professeurs,
                (SELECT COUNT(*) FROM vacataires) AS total_vacataires,
                (SELECT COUNT(*) FROM unites_enseignements) AS total_departements,
                (SELECT COUNT(*) FROM matieres) AS total_specialites,
                (SELECT COUNT(*) FROM affectations_vacataires) AS total_affectations
        ")->fetch();
    } catch (PDOException $e) {
        $stats = [
            'total_professeurs' => 0,
            'total_vacataires' => 0,
            'total_departements' => 0,
            'total_specialites' => 0,
            'total_affectations' => 0
        ];
    }

    // Dernières affectations
    try {
        $check_column = $pdo->query("SHOW COLUMNS FROM affectations_vacataires LIKE 'date_affectation'");
        $column_exists = $check_column->rowCount() > 0;

        if ($column_exists) {
            $affectations = $pdo->query("
                SELECT
                    v.nom,
                    v.prenom,
                    m.nom AS specialite,
                    ue.nom_ue AS departement,
                    av.date_affectation
                FROM affectations_vacataires av
                JOIN vacataires v ON av.id_vacataire = v.id_vacataire
                JOIN matieres m ON av.id_matiere = m.id_matiere
                JOIN unites_enseignements ue ON m.id_ue = ue.id_ue
                ORDER BY av.date_affectation DESC
                LIMIT 5
            ")->fetchAll();
        } else {
            $affectations = $pdo->query("
                SELECT
                    v.nom,
                    v.prenom,
                    m.nom AS specialite,
                    ue.nom_ue AS departement,
                    NOW() AS date_affectation
                FROM affectations_vacataires av
                JOIN vacataires v ON av.id_vacataire = v.id_vacataire
                JOIN matieres m ON av.id_matiere = m.id_matiere
                JOIN unites_enseignements ue ON m.id_ue = ue.id_ue
                LIMIT 5
            ")->fetchAll();
        }
    } catch (PDOException $e) {
        $affectations = [];
    }

    // Données pour les graphiques
    try {
        // Affectations par jour
        $affectationsParJour = $pdo->query("
            SELECT
                DAYNAME(date_affectation) AS jour,
                COUNT(*) AS total
            FROM affectations_vacataires
            WHERE date_affectation >= CURDATE() - INTERVAL 7 DAY
            GROUP BY jour, DAYOFWEEK(date_affectation)
            ORDER BY DAYOFWEEK(date_affectation)
        ")->fetchAll();

        // Répartition par spécialité
        $repartitionSpecialites = $pdo->query("
            SELECT 
                m.nom AS specialite,
                COUNT(av.id_affectation) AS total
            FROM affectations_vacataires av
            JOIN matieres m ON av.id_matiere = m.id_matiere
            GROUP BY m.nom
            ORDER BY total DESC
            LIMIT 5
        ")->fetchAll();

        // Préparer les données pour les graphiques
        $jours = ['Monday' => 'Lundi', 'Tuesday' => 'Mardi', 'Wednesday' => 'Mercredi', 
                 'Thursday' => 'Jeudi', 'Friday' => 'Vendredi', 'Saturday' => 'Samedi', 
                 'Sunday' => 'Dimanche'];
        $affectationsData = array_fill(0, 7, 0);
        
        foreach ($affectationsParJour as $row) {
            if (isset($jours[$row['jour']])) {
                $index = array_search($jours[$row['jour']], array_values($jours));
                if ($index !== false) {
                    $affectationsData[$index] = $row['total'];
                }
            }
        }
        
        $specialitesLabels = [];
        $specialitesData = [];
        foreach ($repartitionSpecialites as $row) {
            $specialitesLabels[] = $row['specialite'];
            $specialitesData[] = $row['total'];
        }

        $chart_data = [
            'affectations' => [
                'labels' => array_values($jours),
                'data' => $affectationsData
            ],
            'specialites' => [
                'labels' => $specialitesLabels,
                'data' => $specialitesData
            ]
        ];
    } catch (PDOException $e) {
        $chart_data = [
            'affectations' => [
                'labels' => ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'],
                'data' => [12, 19, 3, 5, 2]
            ],
            'specialites' => [
                'labels' => ['Informatique', 'Génie Civil', 'Électronique', 'Mécanique', 'Autres'],
                'data' => [30, 20, 15, 25, 10]
            ]
        ];
    }

} catch(PDOException $e) {
    die("Erreur de base de données : " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - ENSAH</title>
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
            display: flex;
            flex-direction: column;
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

        /* MODIFICATION: Bouton de déconnexion en bas de sidebar */
        .sidebar-footer {
            margin-top: auto;
            padding: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 10px 15px;
            background: rgba(255, 71, 87, 0.1);
            color: white;
            border: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
            cursor: pointer;
        }
        
        .logout-btn:hover {
            background: rgba(255, 71, 87, 0.2);
            transform: translateY(-2px);
        }

        /* Menu déroulant amélioré */
        .dropdown-menu {
            display: none;
            padding: 5px 0;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            margin: 5px 0 10px 15px;
            border-left: 2px solid var(--accent-color);
        }

        .dropdown-menu.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .dropdown-toggle {
            position: relative;
        }

        .dropdown-toggle::after {
            display: inline-block;
            margin-left: auto;
            vertical-align: 0.15em;
            content: "";
            border-top: 0.3em solid;
            border-right: 0.3em solid transparent;
            border-bottom: 0;
            border-left: 0.3em solid transparent;
            transition: transform 0.3s ease;
            color: rgba(255, 255, 255, 0.7);
        }

        .dropdown-toggle[aria-expanded="true"]::after {
            transform: rotate(180deg);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        /* ================ FIN DU SIDEBAR MODERNE ================ */

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

        .professeurs-icon {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }

        .vacataires-icon {
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
        }

        .departements-icon {
            background: linear-gradient(135deg, var(--accent-color) 0%, #0077b6 100%);
        }

        .specialites-icon {
            background: linear-gradient(135deg, #20c997 0%, #0d6efd 100%);
        }

        .affectations-icon {
            background: linear-gradient(135deg, #ff6b6b 0%, #ff8e8e 100%);
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
        .charts-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-purple);
            margin: 0;
        }

        .chart-actions i {
            color: #777;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .chart-actions i:hover {
            color: var(--primary-color);
        }

        .chart-wrapper {
            height: 250px;
            position: relative;
        }

        /* Dernières affectations */
        .recent-table {
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

        .view-all {
            color: var(--primary-color);
            font-weight: 500;
            text-decoration: none;
            transition: opacity 0.3s ease;
        }

        .view-all:hover {
            opacity: 0.8;
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

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-completed {
            background: #e6f7ee;
            color: #10b981;
        }

        .status-pending {
            background: #fff4e6;
            color: #f59e0b;
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #777;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }

        /* Barre de recherche */
        .search-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .search-box {
            display: flex;
            align-items: center;
            background: #f8f9fc;
            border-radius: 50px;
            padding: 10px 20px;
            border: 1px solid #eee;
        }

        .search-box i {
            color: #777;
            margin-right: 10px;
        }

        .search-box input {
            flex: 1;
            border: none;
            background: transparent;
            outline: none;
            font-size: 16px;
            color: #333;
        }

        .search-box input::placeholder {
            color: #aaa;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .charts-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                width: 220px;
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
            
            .sidebar-footer {
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- En-tête -->
    <div class="header">
        <div class="header-left">
            <img src="images/Logo.png" alt="ENSAH Logo">
            <h1>Tableau de Bord Administrateur</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-envelope"></i>
                <span class="user-info-value"><?= htmlspecialchars($admin_email) ?></span>
            </div>
            <div class="user-info">
                <i class="fas fa-calendar-alt"></i>
                <span class="user-info-label">Année :</span>
                <span class="user-info-value"><?= date('Y') ?>-<?= date('Y')+1 ?></span>
            </div>
        </div>
    </div>

    <!-- Conteneur principal -->
    <div class="main-container">
        <!-- Sidebar Moderne -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <img src="https://ui-avatars.com/api/?name=Admin+ENSAH&background=8a2be2&color=fff" alt="Admin">
                <h3>Administrateur ENSAH</h3>
            </div>
            
            <div class="sidebar-menu">
                <div class="nav-item">
                    <a href="#" class="nav-link active">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Tableau de Bord</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="#" class="nav-link dropdown-toggle" id="userDropdown" data-bs-toggle="collapse" data-bs-target="#userSubmenu" aria-expanded="false">
                        <i class="fas fa-users-cog"></i>
                        <span>Gestion Utilisateurs</span>
                        <i class="fas fa-chevron-down ms-auto"></i>
                    </a>
                    <div class="dropdown-menu collapse" id="userSubmenu">
                        <a href="gestion_chef_departement.php" class="nav-link">
                            <i class="fas fa-user-tie"></i>
                            <span>Chefs de département</span>
                        </a>
                        <a href="gestion_coordinateur.php" class="nav-link">
                            <i class="fas fa-user-cog"></i>
                            <span>Coordinateurs</span>
                        </a>
                        <a href="gestion_enseignant.php" class="nav-link">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span>Enseignants</span>
                        </a>
                    </div>
                </div>
                
                <!-- MODIFICATION: Bouton de déconnexion en bas de sidebar -->
                <div class="sidebar-footer">
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Déconnexion</span>
                    </a>
                </div>
            </div>
        </nav>

        <!-- Contenu principal -->
        <div class="main-content">
            <!-- Barre de recherche -->
            <div class="search-container">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Taper ici pour rechercher...">
                </div>
            </div>
            
            <!-- Cartes de statistiques -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon professeurs-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?= $stats['total_professeurs'] ?? '0' ?></div>
                        <div class="stat-label">Professeurs</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon vacataires-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?= $stats['total_vacataires'] ?? '0' ?></div>
                        <div class="stat-label">Vacataires</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon departements-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?= $stats['total_departements'] ?? '0' ?></div>
                        <div class="stat-label">Départements</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon specialites-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?= $stats['total_specialites'] ?? '0' ?></div>
                        <div class="stat-label">Spécialités</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon affectations-icon">
                        <i class="fas fa-link"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?= $stats['total_affectations'] ?? '0' ?></div>
                        <div class="stat-label">Affectations</div>
                    </div>
                </div>
            </div>
            
            <!-- Graphiques -->
            <div class="charts-container">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Affectations par jour</h3>
                        <div class="chart-actions">
                            <i class="fas fa-sync-alt" title="Actualiser"></i>
                        </div>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="affectationsChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Répartition par spécialité</h3>
                        <div class="chart-actions">
                            <i class="fas fa-sync-alt" title="Actualiser"></i>
                        </div>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="specialitesChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Dernières affectations -->
            <div class="recent-table">
                <div class="section-header">
                    <h3 class="section-title">Dernières Affectations</h3>
                    <a href="gestion_affectations.php" class="view-all">Voir tout</a>
                </div>
                
                <?php if (count($affectations) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Vacataire</th>
                                <th>Spécialité</th>
                                <th>Département</th>
                                <th>Date</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($affectations as $affectation): ?>
                                <tr>
                                    <td><?= htmlspecialchars($affectation['prenom'] . ' ' . htmlspecialchars($affectation['nom'])) ?></td>
                                    <td><?= htmlspecialchars($affectation['specialite']) ?></td>
                                    <td><?= htmlspecialchars($affectation['departement']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($affectation['date_affectation'])) ?></td>
                                    <td><span class="status-badge status-completed">Active</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h4>Aucune affectation récente</h4>
                        <p>Aucune affectation n'a été enregistrée pour le moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialisation des graphiques
        document.addEventListener('DOMContentLoaded', function() {
            // Graphique des affectations
            var affectationsCtx = document.getElementById('affectationsChart').getContext('2d');
            var affectationsChart = new Chart(affectationsCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($chart_data['affectations']['labels']) ?>,
                    datasets: [{
                        label: 'Nombre d\'affectations',
                        data: <?= json_encode($chart_data['affectations']['data']) ?>,
                        backgroundColor: 'rgba(106, 13, 173, 0.7)',
                        borderColor: 'rgba(106, 13, 173, 1)',
                        borderWidth: 1,
                        borderRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                                },
                            ticks: {
                                stepSize: 5
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(255, 255, 255, 0.9)',
                            titleColor: '#333',
                            bodyColor: '#555',
                            borderColor: '#ddd',
                            borderWidth: 1,
                            padding: 12,
                            displayColors: false
                        }
                    }
                }
            });

            // Graphique des spécialités
            var specialitesCtx = document.getElementById('specialitesChart').getContext('2d');
            var specialitesChart = new Chart(specialitesCtx, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($chart_data['specialites']['labels']) ?>,
                    datasets: [{
                        data: <?= json_encode($chart_data['specialites']['data']) ?>,
                        backgroundColor: [
                            'rgba(106, 13, 173, 0.8)',
                            'rgba(138, 43, 226, 0.8)',
                            'rgba(0, 191, 255, 0.8)',
                            'rgba(255, 107, 107, 0.8)',
                            'rgba(100, 149, 237, 0.8)',
                            'rgba(32, 201, 151, 0.8)',
                            'rgba(255, 193, 7, 0.8)'
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(255, 255, 255, 0.9)',
                            titleColor: '#333',
                            bodyColor: '#555',
                            borderColor: '#ddd',
                            borderWidth: 1,
                            padding: 12,
                            displayColors: true
                        }
                    },
                    cutout: '65%'
                }
            });
            
            // Gestion du menu déroulant
            const dropdownToggle = document.querySelector('.dropdown-toggle');
            if (dropdownToggle) {
                dropdownToggle.addEventListener('click', function() {
                    const submenu = document.querySelector(this.getAttribute('data-bs-target'));
                    submenu.classList.toggle('show');
                    const isExpanded = this.getAttribute('aria-expanded') === 'true';
                    this.setAttribute('aria-expanded', !isExpanded);
                });
            }
        });
    </script>
</body>
</html>