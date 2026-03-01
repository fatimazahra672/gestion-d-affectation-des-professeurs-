<?php
session_start();

// Vérifier si l'utilisateur est connecté et est un coordinateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'coordinateur') {
    header("Location: login_coordinateur.php");
    exit;
}

// Connexion à la base de données
$host = 'localhost';
$dbname = 'gestion_coordinteur';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer toutes les unités d'enseignement avec le responsable
function getUnitesEnseignements() {
    global $pdo;
    $stmt = $pdo->query("
        SELECT ue.*, 
               m.nom AS nom_matiere,
               CONCAT(u.prenom, ' ', u.nom) AS responsable
        FROM unites_enseignements ue
        LEFT JOIN matieres m ON ue.id_matiere = m.id_matiere
        LEFT JOIN utilisateurs u ON m.id_utilisateur = u.id  /* CORRECTION APPLIQUÉE ICI */
        ORDER BY filiere, niveau, type_enseignement
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les matières avec les responsables
function getMatieres() {
    global $pdo;
    try {
        $stmt = $pdo->query("
            SELECT m.id_matiere, 
                   m.nom,
                   CONCAT(u.prenom, ' ', u.nom) AS responsable
            FROM matieres m
            LEFT JOIN utilisateurs u ON m.id_utilisateur = u.id  /* CORRECTION APPLIQUÉE ICI */
            ORDER BY m.nom
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Traitement de l'ajout d'une UE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter'])) {
    try {
        $id_matiere = $_POST['id_matiere'];
        $filiere = $_POST['filiere'];
        $niveau = $_POST['niveau'];
        $annee_scolaire = $_POST['annee_scolaire'];
        $type_enseignement = $_POST['type_enseignement'];
        $volume_horaire = $_POST['volume_horaire'];

        $stmt = $pdo->prepare("
            INSERT INTO unites_enseignements 
            (id_matiere, filiere, niveau, annee_scolaire, type_enseignement, volume_horaire)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$id_matiere, $filiere, $niveau, $annee_scolaire, $type_enseignement, $volume_horaire]);
        
        $message = "Unité d'enseignement ajoutée avec succès";
        $messageType = "success";
    } catch (PDOException $e) {
        $message = "Erreur lors de l'ajout : " . $e->getMessage();
        $messageType = "danger";
    }
}

// Traitement de la modification d'une UE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier'])) {
    try {
        $id_ue = $_POST['id_ue'];
        $id_matiere = $_POST['id_matiere'];
        $filiere = $_POST['filiere'];
        $niveau = $_POST['niveau'];
        $annee_scolaire = $_POST['annee_scolaire'];
        $type_enseignement = $_POST['type_enseignement'];
        $volume_horaire = $_POST['volume_horaire'];

        $stmt = $pdo->prepare("
            UPDATE unites_enseignements 
            SET id_matiere = ?, filiere = ?, niveau = ?, annee_scolaire = ?, 
                type_enseignement = ?, volume_horaire = ?
            WHERE id_ue = ?
        ");
        
        $stmt->execute([$id_matiere, $filiere, $niveau, $annee_scolaire, $type_enseignement, $volume_horaire, $id_ue]);
        
        $message = "Unité d'enseignement modifiée avec succès";
        $messageType = "success";
    } catch (PDOException $e) {
        $message = "Erreur lors de la modification : " . $e->getMessage();
        $messageType = "danger";
    }
}

// Traitement de la suppression d'une UE
if (isset($_GET['supprimer'])) {
    try {
        $id_ue = $_GET['supprimer'];
        
        $stmt = $pdo->prepare("DELETE FROM unites_enseignements WHERE id_ue = ?");
        $stmt->execute([$id_ue]);
        
        $message = "Unité d'enseignement supprimée avec succès";
        $messageType = "success";
    } catch (PDOException $e) {
        $message = "Erreur lors de la suppression : " . $e->getMessage();
        $messageType = "danger";
    }
}

$unites_enseignements = getUnitesEnseignements();
$matieres = getMatieres();

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
    <title>Gestion des Unités d'Enseignement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        /* Styles spécifiques à la gestion des unités d'enseignement */
        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--dark-purple);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }

        .page-title i {
            margin-right: 15px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .page-description {
            color: #666;
            margin-bottom: 25px;
            font-size: 1rem;
        }

        .card-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .table-container {
            overflow-x: auto;
        }

        .table-ue {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-ue thead {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            color: white;
        }

        .table-ue th {
            font-weight: 500;
            padding: 15px;
            border: none;
            font-size: 0.95rem;
            text-align: left;
        }

        .table-ue td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .table-ue tbody tr:hover {
            background-color: rgba(106, 13, 173, 0.05);
        }

        .btn-action {
            padding: 8px 15px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-right: 5px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-edit {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }

        .btn-edit:hover {
            background-color: var(--dark-purple);
        }

        .btn-delete {
            background-color: #dc3545;
            color: white;
            border: none;
        }

        .btn-delete:hover {
            background-color: #bd2130;
        }

        .btn-add {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(65,105,225,0.3);
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(65,105,225,0.4);
            background: linear-gradient(135deg, var(--dark-purple), var(--primary-color));
            color: white;
        }

        .export-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .btn-excel, .btn-print {
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-excel {
            background-color: #1D6F42;
            color: white;
            border: none;
        }

        .btn-excel:hover {
            background-color: #166534;
        }

        .btn-print {
            background-color: #6c757d;
            color: white;
            border: none;
        }

        .btn-print:hover {
            background-color: #5a6268;
        }

        /* Modals */
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            color: white;
        }

        .modal-title {
            font-weight: 600;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(106, 13, 173, 0.25);
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
            <h1>Gestion des Unités d'Enseignement</h1>
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
                    <a href="dashboard_coordinateur.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Tableau de Bord</span>
                    </a>
                </div>
                
                <!-- Section Coordinateur -->
                <div class="section-title coordinateur" id="coord-section">
                    <i class="fas fa-user-tie"></i>
                    <span>Coordinateur</span>
                    <i class="fas fa-chevron-down arrow rotated"></i>
                </div>

                <div class="submenu open" id="coord-menu">
                    <div class="nav-item">
                        <a href="gestion_unites_enseignements.php" class="nav-link active">
                            <i class="fas fa-book"></i>
                            <span>Unités d'enseignement</span>
                        </a>
                    </div>
                
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
                            <span>Créer compétence vacataire</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="historique.php" class="nav-link">
                            <i class="fas fa-history"></i>
                            <span>Historique des années</span>
                        </a>
                    </div>

                    <div class="nav-item">
                        <a href="Export_Exel.php" class="nav-link">
                            <i class="fas fa-file-excel"></i>
                            <span>Extraire en Excel</span>
                        </a>
                    </div>

                    <div class="nav-item">
                        <a href="#" class="nav-link">
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="page-title"><i class="fas fa-book"></i> Gestion des Unités d'Enseignement</h2>
                    <p class="page-description">Gérez les unités d'enseignement de l'établissement</p>
                </div>
                <div>
                    <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#ajoutModal">
                        <i class="fas fa-plus-circle"></i> Ajouter une UE
                    </button>
                </div>
            </div>

            <?php if (isset($message)): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card-container">
                <div class="export-buttons">
                    <button id="export-excel" class="btn btn-excel">
                        <i class="fas fa-file-excel"></i> Exporter Excel
                    </button>
                    <button id="print-table" class="btn btn-print">
                        <i class="fas fa-print"></i> Imprimer
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-ue" id="table-ue">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Matière</th>
                                <th>Responsable</th>
                                <th>Filière</th>
                                <th>Niveau</th>
                                <th>Année Scolaire</th>
                                <th>Type</th>
                                <th>Volume Horaire</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unites_enseignements as $ue): ?>
                                <tr>
                                    <td><?= $ue['id_ue'] ?></td>
                                    <td><?= htmlspecialchars($ue['nom_matiere'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if (!empty($ue['responsable'])): ?>
                                            <?= htmlspecialchars($ue['responsable']) ?>
                                        <?php else: ?>
                                            Non assigné
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($ue['filiere']) ?></td>
                                    <td><?= htmlspecialchars($ue['niveau']) ?></td>
                                    <td><?= htmlspecialchars($ue['annee_scolaire']) ?></td>
                                    <td><?= htmlspecialchars($ue['type_enseignement']) ?></td>
                                    <td><?= $ue['volume_horaire'] ?> h</td>
                                    <td>
                                        <button class="btn btn-action btn-edit" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modifierModal"
                                                data-id="<?= $ue['id_ue'] ?>"
                                                data-matiere="<?= $ue['id_matiere'] ?>"
                                                data-filiere="<?= htmlspecialchars($ue['filiere']) ?>"
                                                data-niveau="<?= htmlspecialchars($ue['niveau']) ?>"
                                                data-annee="<?= htmlspecialchars($ue['annee_scolaire']) ?>"
                                                data-type="<?= htmlspecialchars($ue['type_enseignement']) ?>"
                                                data-volume="<?= $ue['volume_horaire'] ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?supprimer=<?= $ue['id_ue'] ?>" class="btn btn-action btn-delete" 
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette UE ?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ajout -->
    <div class="modal fade" id="ajoutModal" tabindex="-1" aria-labelledby="ajoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ajoutModalLabel">Ajouter une Unité d'Enseignement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="" method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="id_matiere" class="form-label">Matière</label>
                                <select class="form-select" id="id_matiere" name="id_matiere" required>
                                    <option value="">Sélectionner une matière</option>
                                    <?php foreach ($matieres as $matiere): ?>
                                        <option value="<?= $matiere['id_matiere'] ?>">
                                            <?= htmlspecialchars($matiere['nom']) ?>
                                            <?php if (!empty($matiere['responsable'])): ?>
                                                (Responsable: <?= htmlspecialchars($matiere['responsable']) ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="filiere" class="form-label">Filière</label>
                                <input type="text" class="form-control" id="filiere" name="filiere" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="niveau" class="form-label">Niveau</label>
                                <input type="text" class="form-control" id="niveau" name="niveau" required>
                            </div>
                            <div class="col-md-6">
                                <label for="annee_scolaire" class="form-label">Année Scolaire</label>
                                <input type="text" class="form-control" id="annee_scolaire" name="annee_scolaire" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="type_enseignement" class="form-label">Type d'Enseignement</label>
                                <select class="form-select" id="type_enseignement" name="type_enseignement" required>
                                    <option value="">Sélectionner un type</option>
                                    <option value="Cours">Cours</option>
                                    <option value="TD">TD</option>
                                    <option value="TP">TP</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="volume_horaire" class="form-label">Volume Horaire (heures)</label>
                                <input type="number" class="form-control" id="volume_horaire" name="volume_horaire" required>
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" name="ajouter" class="btn btn-primary">Ajouter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Modifier -->
    <div class="modal fade" id="modifierModal" tabindex="-1" aria-labelledby="modifierModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modifierModalLabel">Modifier une Unité d'Enseignement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="" method="POST">
                        <input type="hidden" id="edit_id_ue" name="id_ue">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_id_matiere" class="form-label">Matière</label>
                                <select class="form-select" id="edit_id_matiere" name="id_matiere" required>
                                    <option value="">Sélectionner une matière</option>
                                    <?php foreach ($matieres as $matiere): ?>
                                        <option value="<?= $matiere['id_matiere'] ?>">
                                            <?= htmlspecialchars($matiere['nom']) ?>
                                            <?php if (!empty($matiere['responsable'])): ?>
                                                (Responsable: <?= htmlspecialchars($matiere['responsable']) ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_filiere" class="form-label">Filière</label>
                                <input type="text" class="form-control" id="edit_filiere" name="filiere" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_niveau" class="form-label">Niveau</label>
                                <input type="text" class="form-control" id="edit_niveau" name="niveau" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_annee_scolaire" class="form-label">Année Scolaire</label>
                                <input type="text" class="form-control" id="edit_annee_scolaire" name="annee_scolaire" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_type_enseignement" class="form-label">Type d'Enseignement</label>
                                <select class="form-select" id="edit_type_enseignement" name="type_enseignement" required>
                                    <option value="">Sélectionner un type</option>
                                    <option value="Cours">Cours</option>
                                    <option value="TD">TD</option>
                                    <option value="TP">TP</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_volume_horaire" class="form-label">Volume Horaire (heures)</label>
                                <input type="number" class="form-control" id="edit_volume_horaire" name="volume_horaire" required>
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" name="modifier" class="btn btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialisation de DataTables
            var table = $('#table-ue').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                },
                responsive: true,
                dom: '<"top"f>rt<"bottom"ip><"clear">',
                pageLength: 10,
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn-excel'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Imprimer',
                        className: 'btn-print'
                    }
                ]
            });
            
            // Bouton d'export Excel
            $('#export-excel').on('click', function() {
                table.button('.buttons-excel').trigger();
            });
            
            // Bouton d'impression
            $('#print-table').on('click', function() {
                table.button('.buttons-print').trigger();
            });
            
            // Remplir le formulaire de modification
            $('#modifierModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');
                var matiere = button.data('matiere');
                var filiere = button.data('filiere');
                var niveau = button.data('niveau');
                var annee = button.data('annee');
                var type = button.data('type');
                var volume = button.data('volume');
                
                var modal = $(this);
                modal.find('#edit_id_ue').val(id);
                modal.find('#edit_id_matiere').val(matiere);
                modal.find('#edit_filiere').val(filiere);
                modal.find('#edit_niveau').val(niveau);
                modal.find('#edit_annee_scolaire').val(annee);
                modal.find('#edit_type_enseignement').val(type);
                modal.find('#edit_volume_horaire').val(volume);
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
        });
    </script>
</body>
</html>