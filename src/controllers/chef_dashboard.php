<?php
require_once 'config.php';

// Gestion de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    // Si l'utilisateur n'est pas connecté, on le redirige vers la page de connexion
    header("Location: login.php?error=session_invalide");
    exit();
}

// Pour le débogage
error_log("Session user_id: " . $_SESSION['user_id']);
error_log("Session role: " . ($_SESSION['role'] ?? 'non défini'));
error_log("Session user_type: " . ($_SESSION['user_type'] ?? 'non défini'));

// Forcer le type d'utilisateur à chef_departement pour cette page
$_SESSION['user_type'] = 'chef_departement';
$_SESSION['role'] = 'chef_departement';

try {
    // Connexion à la base de données
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Récupération des infos du département - avec gestion des erreurs
    try {
        // Essayer d'abord avec la structure 'departement' (singulier)
        $stmt = $pdo->prepare("
            SELECT d.id_departement, d.nom_departement
            FROM departement d
            JOIN utilisateurs u ON d.id_departement = u.id_departement
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $departement = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Erreur requête departement: " . $e->getMessage());
        $departement = false;
    }

    // Si la première requête échoue, essayer avec la structure 'departements' (pluriel)
    if (!$departement) {
        try {
            $stmt = $pdo->prepare("
                SELECT d.departement_id as id_departement, d.nom_departement
                FROM departements d
                JOIN utilisateurs u ON d.departement_id = u.id_departement
                WHERE u.id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $departement = $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Erreur requête departements: " . $e->getMessage());
            $departement = false;
        }
    }

    // Si toujours pas de département, utiliser les valeurs de session ou des valeurs par défaut
    if (!$departement) {
        error_log("Aucun département trouvé pour l'utilisateur " . $_SESSION['user_id']);

        // Utiliser les valeurs de session si elles existent
        if (isset($_SESSION['id_departement']) || isset($_SESSION['departement_id'])) {
            $dept_id = $_SESSION['id_departement'] ?? $_SESSION['departement_id'] ?? 1;
            $dept_nom = $_SESSION['departement_nom'] ?? 'Département par défaut';

            $departement = [
                'id_departement' => $dept_id,
                'nom_departement' => $dept_nom
            ];

            error_log("Utilisation des valeurs de session pour le département: ID=$dept_id, Nom=$dept_nom");
        } else {
            // Valeurs par défaut
            $departement = [
                'id_departement' => 1,
                'nom_departement' => 'Département par défaut'
            ];
            error_log("Utilisation des valeurs par défaut pour le département");
        }
    }

    // Mettre à jour les variables de session avec les deux formats pour assurer la compatibilité
    $_SESSION['id_departement'] = $departement['id_departement'];
    $_SESSION['departement_id'] = $departement['id_departement'];
    $_SESSION['departement_nom'] = $departement['nom_departement'];

    // Statistiques
    $statsQuery = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM utilisateurs WHERE id_departement = ? AND type_utilisateur = 'enseignant') AS total_professeurs,
            (SELECT COUNT(*) FROM utilisateurs WHERE id_departement = ? AND type_utilisateur = 'vacataire') AS total_vacataires,
            (SELECT COUNT(*) FROM specialite WHERE id_departement = ?) AS total_specialites,
            (SELECT COUNT(*) FROM affectations_vacataires WHERE id_vacataire IN
                (SELECT id FROM utilisateurs WHERE id_departement = ? AND type_utilisateur = 'vacataire')) AS total_affectations,
            (SELECT COUNT(*) FROM unites_enseignements WHERE filiere IN
                (SELECT nom_filiere FROM filiere WHERE id_departement = ?)) AS total_modules,
            (SELECT COUNT(*) FROM affectations_vacataires WHERE id_vacataire IN
                (SELECT id FROM utilisateurs WHERE id_departement = ? AND type_utilisateur = 'vacataire')
                AND date_affectation >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS total_recentes
    ");
    $statsQuery->execute([
        $departement['id_departement'],
        $departement['id_departement'],
        $departement['id_departement'],
        $departement['id_departement'],
        $departement['id_departement'],
        $departement['id_departement']
    ]);
    $stats = $statsQuery->fetch();

    // Dernières affectations
    $affectationsQuery = $pdo->prepare("
        SELECT
            u.nom,
            u.prenom,
            m.nom AS matiere,
            DATE_FORMAT(av.date_affectation, '%d/%m/%Y %H:%i') AS date_affectation
        FROM affectations_vacataires av
        JOIN utilisateurs u ON av.id_vacataire = u.id
        JOIN matieres m ON av.id_matiere = m.id_matiere
        WHERE u.id_departement = ?
        ORDER BY av.date_affectation DESC
        LIMIT 5
    ");
    $affectationsQuery->execute([$departement['id_departement']]);
    $affectations = $affectationsQuery->fetchAll();

    // Dernières validations (simulé car pas de table choix_professeurs dans votre BD)
    $validations = [];

    // Données pour le graphique
    $chartQuery = $pdo->prepare("
        SELECT
            DAYNAME(date_affectation) AS jour,
            COUNT(*) AS total
        FROM affectations_vacataires
        WHERE id_vacataire IN (SELECT id FROM utilisateurs WHERE id_departement = ?)
        AND date_affectation >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
        GROUP BY jour
        ORDER BY FIELD(jour, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche')
    ");
    $chartQuery->execute([$departement['id_departement']]);
    $chart_data = $chartQuery->fetchAll();

    // Si pas de données pour le graphique, créer des données vides
    if (empty($chart_data)) {
        $jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        foreach ($jours as $jour) {
            $chart_data[] = ['jour' => $jour, 'total' => 0];
        }
    }

    // Récupérer l'email de l'utilisateur
    $emailQuery = $pdo->prepare("SELECT email FROM utilisateurs WHERE id = ?");
    $emailQuery->execute([$_SESSION['user_id']]);
    $user_email = $emailQuery->fetchColumn();

} catch(PDOException $e) {
    die("Erreur système : " . htmlspecialchars($e->getMessage()));
}

function sanitize($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Chef Département - <?= sanitize($_SESSION['departement_nom']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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

        .professeurs-icon {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        }

        .vacataires-icon {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }

        .specialites-icon {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
        }

        .affectations-icon {
            background: linear-gradient(135deg, var(--accent-color) 0%, #0077b6 100%);
        }

        .matieres-icon {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
        }

        .recentes-icon {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
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

        /* Tableaux */
        .table-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .table-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-purple);
            margin: 0;
            display: flex;
            align-items: center;
        }

        .table-title i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        /* Graphique */
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

        .chart-wrapper {
            flex: 1;
            position: relative;
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
                <img src="images/logo.png" alt="Logo ENSAH">
            </div>
            <h1>Tableau de Bord Chef Département</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-envelope"></i>
                <span class="user-info-value"><?= sanitize($user_email ?? 'email@exemple.com') ?></span>
            </div>
            <div class="user-info">
                <i class="fas fa-building"></i>
                <span class="user-info-value"><?= sanitize($_SESSION['departement_nom']) ?></span>
            </div>
        </div>
    </div>

    <!-- Conteneur principal -->
    <div class="main-container">
        <!-- Sidebar Moderne avec sections dépliables -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['departement_nom'] ?? 'Chef') ?>&background=8a2be2&color=fff" alt="Chef Département">
                <h3><?= sanitize($_SESSION['departement_nom']) ?></h3>
            </div>
            
            <div class="sidebar-menu">
                <!-- Tableau de bord -->
                <div class="nav-item">
                    <a href="chef_dashboard.php" class="nav-link active">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Tableau de Bord</span>
                    </a>
                </div>
                
                <!-- Section Chef Département -->
                <div class="section-title coordinateur" id="chef-section">
                    <i class="fas fa-user-tie"></i>
                    <span>Chef Département</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </div>
                
                <div class="submenu" id="chef-menu">
                    <div class="nav-item">
                        <a href="gestion_modules.php" class="nav-link">
                            <i class="fas fa-book"></i>
                            <span>Unités d'enseignement</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="gestion_professeurs.php" class="nav-link">
                            <i class="fas fa-users-cog"></i>
                            <span>Gestion Professeurs</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="affectation_ue.php" class="nav-link">
                            <i class="fas fa-tasks"></i>
                            <span>Affectation UE</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="gestion_choix.php" class="nav-link">
                            <i class="fas fa-check-double"></i>
                            <span>Validation Choix</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="ajouter_charge_horaire.php" class="nav-link">
                            <i class="fas fa-chart-pie"></i>
                            <span>Charge Horaire</span>
                        </a>
                    </div>
                     <a href="ue_vacantes.php" class="nav-link">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>UE Vacantes</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="historique.php" class="nav-link">
                            <i class="fas fa-archive"></i>
                            <span>Historique</span>
                        </a>
                </div>
                
                <!-- Section Enseignant (anciennement Administration) -->
                <div class="section-title enseignant" id="enseignant-section">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Enseignant</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </div>
                
                <div class="submenu" id="enseignant-menu">
                    <li class="nav-item">
                        <a href="dashboard_enseignant.php" class="nav-link active">
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
                    <div class="stat-icon professeurs-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?= $stats['total_professeurs'] ?? 0 ?></div>
                        <div class="stat-label">Enseignants</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon vacataires-icon">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?= $stats['total_vacataires'] ?? 0 ?></div>
                        <div class="stat-label">Vacataires</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon specialites-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?= $stats['total_specialites'] ?? 0 ?></div>
                        <div class="stat-label">Spécialités</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon affectations-icon">
                        <i class="fas fa-link"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?= $stats['total_affectations'] ?? 0 ?></div>
                        <div class="stat-label">Affectations</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon matieres-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?= $stats['total_modules'] ?? 0 ?></div>
                        <div class="stat-label">Matières</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon recentes-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?= $stats['total_recentes'] ?? 0 ?></div>
                        <div class="stat-label">Affectations récentes</div>
                    </div>
                </div>
            </div>

            <!-- Contenu en deux colonnes -->
            <div class="row g-4 mb-4">
                <!-- Dernières affectations -->
                <div class="col-md-6">
                    <div class="table-card">
                        <div class="table-header">
                            <div class="table-title">
                                <i class="fas fa-history"></i> Dernières affectations
                            </div>
                        </div>
                        <div class="table-responsive">
                            <?php if (!empty($affectations)): ?>
                            <table class="table table-hover" id="affectationsTable">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Prénom</th>
                                        <th>Matière</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($affectations as $affectation): ?>
                                    <tr>
                                        <td><?= sanitize($affectation['nom']) ?></td>
                                        <td><?= sanitize($affectation['prenom']) ?></td>
                                        <td><?= sanitize($affectation['matiere']) ?></td>
                                        <td><?= sanitize($affectation['date_affectation']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="alert alert-info">Aucune affectation récente</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Activité récente -->
                <div class="col-md-6">
                    <div class="table-card">
                        <div class="table-header">
                            <div class="table-title">
                                <i class="fas fa-check-circle"></i> Activité récente
                            </div>
                        </div>
                        <div class="table-responsive">
                            <?php if (!empty($validations)): ?>
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Vacataire</th>
                                        <th>Action</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($validations as $validation): ?>
                                    <tr>
                                        <td><?= sanitize($validation['vacataire']) ?></td>
                                        <td><?= sanitize($validation['action']) ?></td>
                                        <td><?= sanitize($validation['date']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="alert alert-info">Aucune activité récente</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Graphique -->
            <div class="chart-container">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="fas fa-chart-line"></i> Activité hebdomadaire
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Initialisation du graphique
        const ctx = document.getElementById('activityChart').getContext('2d');
        const activityChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($chart_data, 'jour')) ?>,
                datasets: [{
                    label: 'Affectations par jour',
                    data: <?= json_encode(array_column($chart_data, 'total')) ?>,
                    backgroundColor: 'rgba(106, 13, 173, 0.7)',
                    borderColor: 'rgba(106, 13, 173, 1)',
                    borderWidth: 2,
                    borderRadius: 5,
                    barThickness: 30
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
                                size: 14
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
                            stepSize: 1,
                            font: {
                                size: 12
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 12
                            },
                            color: '#333',
                            padding: 5
                        }
                    }
                }
            }
        });

        // Configuration DataTable
        $('#affectationsTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
            },
            searching: false,
            paging: false,
            info: false
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
        
        // Ouvrir la section Chef Département par défaut
        document.getElementById('chef-menu').classList.add('open');
        document.querySelector('#chef-section .arrow').classList.add('rotated');
        
        // Fermer la section Enseignant par défaut
        document.getElementById('enseignant-menu').classList.remove('open');
        
        // Ajustement automatique de la taille du graphique
        window.addEventListener('resize', function() {
            activityChart.resize();
        });
    </script>
</body>
</html>