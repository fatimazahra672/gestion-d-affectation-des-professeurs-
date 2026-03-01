<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "gestion_coordinteur");

if ($conn->connect_error) {
    die("Connexion échouée : " . $conn->connect_error);
}

// Récupérer l'ID du vacataire connecté
$id_vacataire = $_SESSION['id_vacataire'] ?? 1; // À adapter à votre système d'authentification

// Récupérer les informations du vacataire
$sql_vacataire = "SELECT * FROM vacataires WHERE id_vacataire = ?";
$stmt_vacataire = $conn->prepare($sql_vacataire);
$stmt_vacataire->bind_param("i", $id_vacataire);
$stmt_vacataire->execute();
$result_vacataire = $stmt_vacataire->get_result();
$vacataire = $result_vacataire->fetch_assoc();

// Récupérer les UEs assignées avec détails complets
$sql_ues = "SELECT 
                ue.id_ue,
                ue.filiere,
                ue.niveau,
                ue.type_enseignement,
                ue.volume_horaire,
                ue.annee_scolaire,
                m.nom AS nom_matiere,
                m.code AS code_matiere,
                m.credit
            FROM vacataires_ues vu
            JOIN unites_enseignements ue ON vu.id_ue = ue.id_ue
            JOIN matieres m ON ue.id_matiere = m.id_matiere
            WHERE vu.id_vacataire = ?
            ORDER BY ue.annee_scolaire DESC, ue.filiere";
$stmt_ues = $conn->prepare($sql_ues);
$stmt_ues->bind_param("i", $id_vacataire);
$stmt_ues->execute();
$result_ues = $stmt_ues->get_result();
$ues = $result_ues->fetch_all(MYSQLI_ASSOC);

// Calculs statistiques
$ue_count = count($ues);
$total_credits = array_sum(array_column($ues, 'credit'));
$types_enseignement = array_count_values(array_column($ues, 'type_enseignement'));
$annee_scolaire = "2023-2024"; // À adapter selon vos besoins
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Unités d'Enseignement</title>
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
            --danger-color: #ff4757;
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

        /* Sidebar */
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
            margin-top: auto;
            color: white;
            border: none;
            padding: 12px 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            width: calc(100% - 30px);
            margin: 20px 15px 0;
            text-decoration: none;
        }

        .logout-btn:hover {
            background: rgba(255, 71, 87, 0.3);
            transform: translateX(5px);
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

        .ue-icon {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }

        .notes-icon {
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
        }

        .annee-icon {
            background: linear-gradient(135deg, var(--accent-color) 0%, #0077b6 100%);
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

        /* Section informations */
        .info-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .info-header {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-purple);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .info-header i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-label {
            font-weight: 600;
            color: #555;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .info-value {
            color: #333;
            font-size: 16px;
            padding: 8px 12px;
            background-color: #f8f9fa;
            border-radius: 6px;
        }

        /* Tableau */
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .table-header {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-purple);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .table-header i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table thead th {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 15px;
            text-align: left;
        }

        .table tbody td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }

        .table tbody tr:hover {
            background-color: #f5f5f5;
        }

        .badge {
            padding: 6px 10px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 12px;
        }

        .badge-info {
            background-color: var(--accent-color);
            color: white;
        }

        /* Responsive */
        @media (max-width: 992px) {
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
    <!-- En-tête avec logo -->
    <div class="header">
        <div class="header-left">
            <div class="header-logo">
                <img src="image copy 8.png" alt="Logo">
            </div>
            <h1>Mes Unités d'Enseignement</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-calendar-alt"></i>
                <span class="user-info-label">Année :</span>
                <span class="user-info-value"><?= htmlspecialchars($annee_scolaire) ?></span>
            </div>
            <div class="user-info">
                <i class="fas fa-user"></i>
                <span class="user-info-label">Connecté en tant que :</span>
                <span class="user-info-value"><?= isset($vacataire) ? htmlspecialchars($vacataire['prenom'] . ' ' . $vacataire['nom']) : 'Vacataire' ?></span>
            </div>
        </div>
    </div>

    <!-- Conteneur principal -->
    <div class="main-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <img src="https://ui-avatars.com/api/?name=<?= isset($vacataire) ? urlencode($vacataire['prenom'] . '+' . $vacataire['nom']) : 'Vacataire' ?>&background=8a2be2&color=fff" alt="Vacataire">
                <h3><?= isset($vacataire) ? htmlspecialchars($vacataire['prenom'] . ' ' . $vacataire['nom']) : 'Vacataire' ?></h3>
            </div>
            
            <div class="sidebar-menu">
                <!-- Tableau de bord -->
                <div class="nav-item">
                    <a href="dashboard_vacataire.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Tableau de Bord</span>
                    </a>
                </div>
                
                <!-- Mes UEs -->
                <div class="nav-item">
                    <a href="mes_ues.php" class="nav-link active">
                        <i class="fas fa-book"></i>
                        <span>Mes UEs</span>
                    </a>
                </div>
                
                <!-- Uploader notes -->
                <div class="nav-item">
                    <a href="aploader_vacataire.php" class="nav-link">
                        <i class="fas fa-file-upload"></i>
                        <span>Uploader notes</span>
                    </a>
                </div>
                
                <!-- Déconnexion -->
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </nav>

        <!-- Contenu principal -->
        <div class="main-content">
            <!-- Cartes de statistiques -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon ue-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?= $ue_count ?></div>
                        <div class="stat-label">Unités d'enseignement</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon notes-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?= array_sum(array_column($ues, 'volume_horaire')) ?>h</div>
                        <div class="stat-label">Volume horaire total</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon annee-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?= $total_credits ?></div>
                        <div class="stat-label">Crédits académiques</div>
                    </div>
                </div>
            </div>

            <!-- Tableau des UEs -->
            <div class="table-container">
                <h2 class="table-header">
                    <i class="fas fa-chalkboard-teacher"></i> Détail des UEs assignées
                </h2>
                
                <?php if ($ue_count > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Matière</th>
                                    <th>Type</th>
                                    <th>Filière/Niveau</th>
                                    <th>Volume Horaire</th>
                                    <th>Crédits</th>
                                    <th>Année Scolaire</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ues as $ue): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($ue['code_matiere']) ?></td>
                                        <td><?= htmlspecialchars($ue['nom_matiere']) ?></td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?= htmlspecialchars($ue['type_enseignement']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($ue['filiere']) ?>
                                            <small class="text-muted d-block">Niveau <?= $ue['niveau'] ?></small>
                                        </td>
                                        <td><?= $ue['volume_horaire'] ?>h</td>
                                        <td><?= $ue['credit'] ?></td>
                                        <td><?= $ue['annee_scolaire'] ?></td>
                                        <td>
                                            <a href="details_ue.php?id=<?= $ue['id_ue'] ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-search"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        Aucune unité d'enseignement ne vous est actuellement assignée.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Section types d'enseignement -->
            <div class="info-section">
                <h2 class="info-header">
                    <i class="fas fa-chart-pie"></i> Répartition par type d'enseignement
                </h2>
                <div class="info-grid">
                    <?php foreach ($types_enseignement as $type => $count): ?>
                        <div class="info-item">
                            <div class="info-label"><?= $type ?></div>
                            <div class="info-value"><?= $count ?> UE(s)</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>