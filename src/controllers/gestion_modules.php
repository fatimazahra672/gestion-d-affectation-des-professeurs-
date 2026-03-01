<?php
// Inclure le fichier de configuration
require_once 'config.php';

// Gestion de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=session_invalide");
    exit();
}

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
    
    // Récupérer l'email de l'utilisateur
    $emailQuery = $pdo->prepare("SELECT email FROM utilisateurs WHERE id = ?");
    $emailQuery->execute([$_SESSION['user_id']]);
    $user_email = $emailQuery->fetchColumn();
    
    // Récupérer les infos du département
    $deptQuery = $pdo->prepare("SELECT nom_departement FROM departement WHERE id_departement = ?");
    $deptQuery->execute([$_SESSION['id_departement']]);
    $departement_nom = $deptQuery->fetchColumn() ?? $_SESSION['departement_nom'] ?? 'Département';
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer toutes les matières avec leur type d'enseignement
function getMatieresAvecType() {
    global $pdo;

    try {
        // Requête combinant les tables matières et unités d'enseignement
        $query = "SELECT m.*, GROUP_CONCAT(DISTINCT ue.type_enseignement SEPARATOR ', ') AS types_enseignement
                  FROM matieres m
                  LEFT JOIN unites_enseignements ue ON m.id_matiere = ue.id_matiere
                  GROUP BY m.id_matiere";
        $stmt = $pdo->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // En cas d'erreur, retourner un tableau vide
        error_log("Erreur dans getMatieresAvecType: " . $e->getMessage());
        return [];
    }
}

$matieres = getMatieresAvecType();

// Calculer les statistiques
$nombreMatieres = count($matieres);
$matieresAffectees = count(array_filter($matieres, function($m) { return $m['id_utilisateur'] !== null; }));
$matieresNonAffectees = $nombreMatieres - $matieresAffectees;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Matières - Chef Département</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
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
        
        /* Titre de page */
        .page-title {
            color: var(--dark-purple);
            padding-bottom: 15px;
            margin-bottom: 25px;
            border-bottom: 2px solid rgba(65, 105, 225, 0.3);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-title i {
            font-size: 2rem;
            color: var(--primary-color);
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
        
        /* Boutons d'export */
        .export-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .export-btn {
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .export-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
        }
        
        .export-excel {
            background: #1D6F42;
            color: white;
            border: none;
        }
        
        .export-print {
            background: var(--primary-color);
            color: white;
            border: none;
        }
        
        /* Badges */
        .badge-semestre {
            background-color: #2e8b57;
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            font-size: 0.85rem;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-assigned { background-color: #2ecc71; color: white; }
        .status-unassigned { background-color: #e74c3c; color: white; }

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
        }
        
        /* Message vide */
        .no-data {
            padding: 50px;
            text-align: center;
            background-color: rgba(255,255,255,0.7);
            border-radius: 10px;
            margin: 20px 0;
        }

        .no-data i {
            color: var(--primary-color);
            font-size: 60px;
            margin-bottom: 20px;
            opacity: 0.8;
        }
        
        /* Statistiques */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-item {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }
        
        .stat-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon-container {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 24px;
            flex-shrink: 0;
        }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-value {
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
        
        /* Boutons d'action */
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-action {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-edit {
            background-color: #3498db;
            color: white;
            border: none;
        }
        
        .btn-delete {
            background-color: #e74c3c;
            color: white;
            border: none;
        }
        
        .btn-action:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        /* Bouton Ajouter */
        .btn-add {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-add:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
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
            <h1>Gestion des Matières</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-envelope"></i>
                <span class="user-info-value"><?= htmlspecialchars($user_email ?? 'email@exemple.com') ?></span>
            </div>
            <div class="user-info">
                <i class="fas fa-building"></i>
                <span class="user-info-value"><?= htmlspecialchars($departement_nom) ?></span>
            </div>
        </div>
    </div>

    <!-- Conteneur principal -->
    <div class="main-container">
        <!-- Sidebar Moderne avec sections dépliables -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($departement_nom) ?>&background=8a2be2&color=fff" alt="Chef Département">
                <h3><?= htmlspecialchars($departement_nom) ?></h3>
            </div>
            
            <div class="sidebar-menu">
                <!-- Tableau de bord -->
                <div class="nav-item">
                    <a href="chef_dashboard.php" class="nav-link">
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
                        <a href="gestion_modules.php" class="nav-link active">
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
                        <a href="ajouter_gcharge_horaire.php" class="nav-link">
                            <i class="fas fa-chart-pie"></i>
                            <span>Charge Horaire</span>
                        </a>
                    </div>
                     <div class="nav-item">
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
                
                <!-- Section Enseignant -->
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
                  
                    
                    <div class="nav-item">
                        <a href="import_export.php" class="nav-link">
                            <i class="fas fa-file-excel"></i>
                            <span>Import/Export</span>
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
            <h1 class="page-title">
                <i class="fas fa-book"></i>
                Liste des Matières avec Types d'Enseignement
            </h1>
            
            <!-- Statistiques -->
            <div class="stats-row">
                <div class="stat-item">
                    <div class="stat-icon-container" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $nombreMatieres ?></div>
                        <div class="stat-label">Matières</div>
                    </div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-icon-container" style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $matieresAffectees ?></div>
                        <div class="stat-label">Matières Affectées</div>
                    </div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-icon-container" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
                        <i class="fas fa-user-times"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $matieresNonAffectees ?></div>
                        <div class="stat-label">Matières Non Affectées</div>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="export-buttons">
                    <button id="export-excel" class="btn export-btn export-excel">
                        <i class="fas fa-file-excel"></i> Exporter vers Excel
                    </button>
                    <button id="export-print" class="btn export-btn export-print">
                        <i class="fas fa-print"></i> Imprimer
                    </button>
                </div>
                <a href="ajouter_matiere.php" class="btn btn-add">
                    <i class="fas fa-plus"></i> Ajouter une matière
                </a>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-list"></i> Matières disponibles avec types d'enseignement
                    </div>
                </div>
                
                <?php if (count($matieres) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="matieresTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Code</th>
                                    <th>Nom</th>
                                    <th>Types d'enseignement</th>
                                    <th>Crédits</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($matieres as $matiere): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($matiere['id_matiere'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($matiere['code'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($matiere['nom'] ?? 'Non spécifié') ?></td>
                                        <td>
                                            <?php if (!empty($matiere['types_enseignement'])): ?>
                                                <?= htmlspecialchars($matiere['types_enseignement']) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Aucun type défini</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($matiere['credit'] ?? '0') ?></td>
                                        <td>
                                            <?php if ($matiere['id_utilisateur']): ?>
                                                <span class="status-badge status-assigned">Affectée</span>
                                            <?php else: ?>
                                                <span class="status-badge status-unassigned">Non affectée</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="editer_matiere.php?id=<?= $matiere['id_matiere'] ?>" class="btn btn-action btn-edit">
                                                    <i class="fas fa-edit"></i> Éditer
                                                </a>
                                                <a href="supprimer_matiere.php?id=<?= $matiere['id_matiere'] ?>" class="btn btn-action btn-delete" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette matière?');">
                                                    <i class="fas fa-trash"></i> Supprimer
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-book-open fa-3x mb-3"></i>
                        <h3>Aucune matière trouvée</h3>
                        <p class="mb-0">La liste des matières est vide</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <!-- Scripts pour l'export -->
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialisation de DataTables avec les boutons d'export
            var table = $('#matieresTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                },
                responsive: true,
                pageLength: 10,
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        title: 'Liste des Matières avec Types d\'Enseignement',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5] // Exclure la colonne Actions
                        },
                        className: 'btn-excel'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Imprimer',
                        title: 'Liste des Matières avec Types d\'Enseignement',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5] // Exclure la colonne Actions
                        },
                        className: 'btn-print'
                    }
                ],
                initComplete: function() {
                    $('.dataTables_filter input').addClass('form-control');
                    $('.dataTables_length select').addClass('form-select');
                }
            });

            // Lier les boutons personnalisés aux fonctions d'export
            $('#export-excel').on('click', function() {
                table.button('.buttons-excel').trigger();
            });

            $('#export-print').on('click', function() {
                table.button('.buttons-print').trigger();
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
        });
    </script>
</body>
</html>