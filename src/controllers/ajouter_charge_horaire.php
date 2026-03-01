<?php
require_once 'config.php';
session_start();

// Vérification authentification
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Vérifier si l'utilisateur a les permissions nécessaires (chef_departement)
$isChefDepartement = false;

if (isset($_SESSION['type_utilisateur']) && $_SESSION['type_utilisateur'] === 'chef_departement') {
    $isChefDepartement = true;
} else if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'chef_departement') {
    $isChefDepartement = true;
} else if (isset($_SESSION['role']) && $_SESSION['role'] === 'chef_departement') {
    $isChefDepartement = true;
}

if (!$isChefDepartement) {
    header("Location: login.php?error=acces_refuse");
    exit();
}

$message = '';
$error = '';

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
    
    // Récupération du département de l'utilisateur
    $stmt = $pdo->prepare("SELECT id_departement FROM utilisateurs WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $departement_id = $user['id_departement'] ?? 1;
    
    // Récupération des professeurs
    $stmt = $pdo->query("SHOW TABLES LIKE 'professeurs'");
    $profsTableExists = $stmt->rowCount() > 0;
    
    if ($profsTableExists) {
        $stmt = $pdo->prepare("SELECT id, nom, prenom FROM professeurs ORDER BY nom, prenom");
        $stmt->execute();
        $professeurs = $stmt->fetchAll();
    } else {
        $professeurs = [];
    }
    
    // Récupération des unités d'enseignement
    $stmt = $pdo->query("SHOW TABLES LIKE 'unites_enseignements'");
    $ueTableExists = $stmt->rowCount() > 0;
    
    if ($ueTableExists) {
        $stmt = $pdo->prepare("SELECT id_ue, filiere, niveau, type_enseignement, volume_horaire FROM unites_enseignements ORDER BY filiere, niveau");
        $stmt->execute();
        $unites = $stmt->fetchAll();
    } else {
        $unites = [];
    }
    
    // Traitement du formulaire d'ajout
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $professeur_id = $_POST['professeur_id'] ?? '';
        $ue_id = $_POST['ue_id'] ?? '';
        $heures = $_POST['heures'] ?? '';
        
        if (empty($professeur_id) || empty($ue_id) || empty($heures)) {
            $error = "Tous les champs sont obligatoires.";
        } else {
            // Vérifier si la table affectations existe et sa structure
            $stmt = $pdo->query("SHOW TABLES LIKE 'affectations'");
            $affectationsExists = $stmt->rowCount() > 0;
            
            if (!$affectationsExists) {
                // Créer la table affectations avec la structure correcte
                $pdo->exec("CREATE TABLE affectations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    professeur_id INT NOT NULL,
                    ue_id INT NOT NULL,
                    heures INT NOT NULL,
                    specialite_id INT NOT NULL DEFAULT 1,
                    departement_id INT NOT NULL DEFAULT 1,
                    date_affectation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_affectation (professeur_id, ue_id),
                    INDEX idx_professeur (professeur_id),
                    INDEX idx_ue (ue_id),
                    INDEX idx_departement (departement_id)
                )");
                $message = "Table affectations créée avec succès. ";
            } else {
                // Vérifier si la colonne 'heures' existe
                $stmt = $pdo->query("SHOW COLUMNS FROM affectations LIKE 'heures'");
                $heuresColumnExists = $stmt->rowCount() > 0;
                
                if (!$heuresColumnExists) {
                    // Ajouter la colonne heures si elle n'existe pas
                    $pdo->exec("ALTER TABLE affectations ADD COLUMN heures INT NOT NULL DEFAULT 0 AFTER ue_id");
                    $message = "Structure de la table affectations mise à jour. ";
                }
            }
            
            // Vérifier si l'affectation existe déjà
            $stmt = $pdo->prepare("SELECT id FROM affectations WHERE professeur_id = ? AND ue_id = ?");
            $stmt->execute([$professeur_id, $ue_id]);
            $existingAffectation = $stmt->fetch();
            
            if ($existingAffectation) {
                // Mettre à jour l'affectation existante
                $stmt = $pdo->prepare("UPDATE affectations SET heures = ?, specialite_id = ?, departement_id = ? WHERE id = ?");
                $stmt->execute([$heures, 1, $departement_id, $existingAffectation['id']]);
                $message .= "La charge horaire a été mise à jour avec succès.";
            } else {
                // Ajouter une nouvelle affectation
                $stmt = $pdo->prepare("INSERT INTO affectations (professeur_id, ue_id, heures, specialite_id, departement_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$professeur_id, $ue_id, $heures, 1, $departement_id]);
                $message .= "La charge horaire a été ajoutée avec succès.";
            }
        }
    }
    
    // Récupération des affectations existantes pour affichage
    $affectations = [];
    $stmt = $pdo->query("SHOW TABLES LIKE 'affectations'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("
            SELECT a.*, p.nom, p.prenom, u.filiere, u.niveau, u.type_enseignement 
            FROM affectations a 
            LEFT JOIN professeurs p ON a.professeur_id = p.id 
            LEFT JOIN unites_enseignements u ON a.ue_id = u.id_ue 
            WHERE a.departement_id = ? 
            ORDER BY p.nom, p.prenom, u.filiere, u.niveau
        ");
        $stmt->execute([$departement_id]);
        $affectations = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    $error = "Erreur base de données : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Charge Horaire des Enseignants</title>
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

        /* Card styles */
        .card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-purple));
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 25px;
            border-bottom: none;
        }

        .card-body {
            padding: 25px;
        }

        /* Form styles */
        .form-label {
            font-weight: 500;
            color: var(--dark-purple);
        }

        .form-control, .form-select {
            border: 1px solid rgba(0,0,0,0.1);
            padding: 12px 15px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(106, 13, 173, 0.25);
        }

        /* Button styles */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-purple));
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106, 13, 173, 0.4);
        }

        /* Table styles */
        .table-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .table-container:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }

        .table {
            margin-bottom: 0;
            width: 100%;
        }

        .table thead {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-purple));
            color: white;
        }

        .table th {
            font-weight: 500;
            padding: 16px 25px;
            border: none;
            font-size: 0.95rem;
        }

        .table td {
            padding: 14px 25px;
            border-top: 1px solid rgba(0,0,0,0.05);
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: rgba(106, 13, 173, 0.08);
        }

        /* Badges */
        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            font-size: 0.85rem;
        }

        .badge-primary {
            background-color: var(--primary-color);
        }

        .badge-success {
            background-color: #2e8b57;
        }

        .badge-warning {
            background-color: #daa520;
        }

        /* Alert styles */
        .alert {
            border-radius: 8px;
            padding: 15px 20px;
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

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .main-container {
                flex-direction: column;
            }
            
            .main-content {
                padding: 15px;
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
            <h1>Gestion des Charges Horaires</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-envelope"></i>
                <span class="user-info-value">haddadi.mohammed@ensah.ma</span>
            </div>
            <div class="user-info">
                <i class="fas fa-building"></i>
                <span class="user-info-value">Département Informatique</span>
            </div>
        </div>
    </div>

    <!-- Conteneur principal -->
    <div class="main-container">
        <!-- Sidebar Moderne avec sections dépliables -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <img src="https://ui-avatars.com/api/?name=Département+Info&background=8a2be2&color=fff" alt="Chef Département">
                <h3>Département Informatique</h3>
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
                        <a href="ajouter_charge_horaire.php" class="nav-link active">
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
                    
                </div>
                
                <!-- Section Enseignant -->
                <div class="section-title enseignant" id="enseignant-section">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Enseignant</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </div>
                
                <div class="submenu" id="enseignant-menu">
                   
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
                <i class="fas fa-chart-pie"></i>
                Gestion des Charges Horaires
            </h1>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Formulaire d'ajout -->
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h5 class="m-0">
                        <i class="fas fa-edit me-2"></i> Ajouter une charge horaire
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($professeurs)): ?>
                        <div class="alert alert-warning" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Aucun professeur trouvé. Veuillez d'abord ajouter des professeurs dans le système.
                        </div>
                    <?php elseif (empty($unites)): ?>
                        <div class="alert alert-warning" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Aucune unité d'enseignement trouvée. Veuillez d'abord ajouter des unités d'enseignement.
                        </div>
                    <?php else: ?>
                        <form method="post" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="professeur_id" class="form-label">Professeur</label>
                                    <select class="form-select" id="professeur_id" name="professeur_id" required>
                                        <option value="">Sélectionnez un professeur</option>
                                        <?php foreach ($professeurs as $prof): ?>
                                            <option value="<?= $prof['id'] ?>">
                                                <?= htmlspecialchars($prof['nom'] . ' ' . $prof['prenom']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Veuillez sélectionner un professeur.
                                    </div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="ue_id" class="form-label">Unité d'Enseignement</label>
                                    <select class="form-select" id="ue_id" name="ue_id" required>
                                        <option value="">Sélectionnez une UE</option>
                                        <?php foreach ($unites as $ue): ?>
                                            <option value="<?= $ue['id_ue'] ?>" data-volume="<?= $ue['volume_horaire'] ?>">
                                                <?= htmlspecialchars($ue['filiere'] . ' - ' . $ue['niveau'] . ' - ' . $ue['type_enseignement'] . ' (' . $ue['volume_horaire'] . 'h)') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Veuillez sélectionner une unité d'enseignement.
                                    </div>
                                </div>

                                <div class="col-md-2 mb-3">
                                    <label for="heures" class="form-label">Heures</label>
                                    <input type="number" class="form-control" id="heures" name="heures" min="1" max="200" required>
                                    <div class="invalid-feedback">
                                        Nombre d'heures requis.
                                    </div>
                                </div>

                                <div class="col-md-2 mb-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-save me-1"></i> Ajouter
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Liste des affectations existantes -->
            <?php if (!empty($affectations)): ?>
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="m-0">
                        <i class="fas fa-list me-2"></i> Affectations existantes
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Professeur</th>
                                    <th>Filière</th>
                                    <th>Niveau</th>
                                    <th>Type</th>
                                    <th>Heures</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($affectations as $affectation): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($affectation['nom'] . ' ' . $affectation['prenom']) ?></td>
                                        <td><?= htmlspecialchars($affectation['filiere']) ?></td>
                                        <td><?= htmlspecialchars($affectation['niveau']) ?></td>
                                        <td><?= htmlspecialchars($affectation['type_enseignement']) ?></td>
                                        <td><span class="badge badge-primary"><?= $affectation['heures'] ?>h</span></td>
                                        <td><?= date('d/m/Y', strtotime($affectation['date_affectation'])) ?></td>
                                        <td>
                                            <a href="modifier_affectation.php?id=<?= $affectation['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="supprimer_affectation.php?id=<?= $affectation['id'] ?>" class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette affectation ?')">
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
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Validation du formulaire
        (function() {
            'use strict';
            
            const forms = document.querySelectorAll('.needs-validation');
            
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
        })();
        
        // Remplir automatiquement le champ heures avec le volume horaire de l'UE
        document.getElementById('ue_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const volumeHoraire = selectedOption.getAttribute('data-volume');
                document.getElementById('heures').value = volumeHoraire;
            } else {
                document.getElementById('heures').value = '';
            }
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

        // Initialisation de DataTables
        $(document).ready(function() {
            $('table').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                },
                responsive: true,
                dom: '<"top"f>rt<"bottom"ip><"clear">',
                pageLength: 10
            });
        });
    </script>
</body>
</html>