<?php
require_once 'config.php';
session_start();

// Vérification de l'authentification et des droits d'administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login_coordinateur.php");
    exit;
}

// Connexion à la base de données
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

    // Récupérer la liste des départements
    $departements = $pdo->query("SELECT id_departement AS id, nom_departement AS nom FROM departement ORDER BY nom_departement")->fetchAll();

    // Récupérer la liste des filières
    $filieres = $pdo->query("SELECT id_filiere AS id, nom_filiere AS nom, id_departement FROM filiere ORDER BY nom_filiere")->fetchAll();

    // Récupérer la liste des spécialités
    $specialites = $pdo->query("SELECT id_specialite AS id, nom_specialite AS nom, id_departement FROM specialite ORDER BY nom_specialite")->fetchAll();

    // Vérifier si la colonne id_specialite existe dans la table utilisateurs
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'utilisateurs' AND COLUMN_NAME = 'id_specialite'
    ");
    $stmt->execute([DB_NAME]);
    $specialite_column_exists = ($stmt->rowCount() > 0);

    // Vérifier si la colonne id_filiere existe dans la table utilisateurs
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'utilisateurs' AND COLUMN_NAME = 'id_filiere'
    ");
    $stmt->execute([DB_NAME]);
    $filiere_column_exists = ($stmt->rowCount() > 0);

    // Récupérer la liste des coordinateurs existants
    if ($specialite_column_exists && $filiere_column_exists) {
        $coordinateurs = $pdo->query("
            SELECT u.id, u.nom, u.prenom, u.email,
                   u.id_filiere AS filiere_id,
                   f.nom_filiere AS filiere,
                   d.id_departement AS departement_id, d.nom_departement AS departement,
                   s.id_specialite AS specialite_id, s.nom_specialite AS specialite
            FROM utilisateurs u
            JOIN departement d ON u.id_departement = d.id_departement
            LEFT JOIN filiere f ON u.id_filiere = f.id_filiere
            LEFT JOIN specialite s ON u.id_specialite = s.id_specialite
            WHERE u.type_utilisateur = 'coordinateur'
            ORDER BY u.nom, u.prenom
        ")->fetchAll();
    } elseif ($specialite_column_exists) {
        $coordinateurs = $pdo->query("
            SELECT u.id, u.nom, u.prenom, u.email,
                   (SELECT f.id_filiere FROM filiere f WHERE f.id_departement = d.id_departement LIMIT 1) AS filiere_id,
                   (SELECT f.nom_filiere FROM filiere f WHERE f.id_departement = d.id_departement LIMIT 1) AS filiere,
                   d.id_departement AS departement_id, d.nom_departement AS departement,
                   s.id_specialite AS specialite_id, s.nom_specialite AS specialite
            FROM utilisateurs u
            JOIN departement d ON u.id_departement = d.id_departement
            LEFT JOIN specialite s ON u.id_specialite = s.id_specialite
            WHERE u.type_utilisateur = 'coordinateur'
            ORDER BY u.nom, u.prenom
        ")->fetchAll();
    } else {
        // Si les colonnes n'existent pas encore, on utilise une requête sans ces colonnes
        $coordinateurs = $pdo->query("
            SELECT u.id, u.nom, u.prenom, u.email,
                   (SELECT f.id_filiere FROM filiere f WHERE f.id_departement = d.id_departement LIMIT 1) AS filiere_id,
                   (SELECT f.nom_filiere FROM filiere f WHERE f.id_departement = d.id_departement LIMIT 1) AS filiere,
                   d.id_departement AS departement_id, d.nom_departement AS departement,
                   NULL AS specialite_id, 'Non définie' AS specialite
            FROM utilisateurs u
            JOIN departement d ON u.id_departement = d.id_departement
            WHERE u.type_utilisateur = 'coordinateur'
            ORDER BY u.nom, u.prenom
        ")->fetchAll();
    }

} catch(PDOException $e) {
    die("Erreur de base de données : " . htmlspecialchars($e->getMessage()));
}

// Traitement des actions (ajout, modification, suppression)
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'ajouter':
                // Code pour ajouter un coordinateur
                try {
                    $nom = trim($_POST['nom']);
                    $prenom = trim($_POST['prenom']);
                    $email = trim($_POST['email']);
                    $filiere_id = $_POST['filiere_id'];
                    $password = $_POST['password'];

                    // Validation
                    if (empty($nom) || empty($prenom) || empty($email) || empty($filiere_id) || empty($password)) {
                        throw new Exception('Tous les champs sont obligatoires');
                    }

                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('Email invalide');
                    }

                    if (strlen($password) < 8) {
                        throw new Exception('Le mot de passe doit contenir au moins 8 caractères');
                    }

                    // Vérifier si l'email existe déjà
                    $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
                    $stmt->execute([$email]);

                    if ($stmt->rowCount() > 0) {
                        throw new Exception('Cet email est déjà utilisé');
                    }

                    // Hachage du mot de passe
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);

                    // Transaction pour garantir l'intégrité des données
                    $pdo->beginTransaction();

                    // Récupérer l'id_departement correspondant à la filière sélectionnée
                    $stmt = $pdo->prepare("SELECT id_departement FROM filiere WHERE id_filiere = ?");
                    $stmt->execute([$filiere_id]);
                    $departement_id = $stmt->fetchColumn();

                    // Récupérer l'id_specialite
                    $specialite_id = isset($_POST['specialite_id']) ? intval($_POST['specialite_id']) : null;

                    // Vérifier que la spécialité appartient bien au département
                    if ($specialite_id) {
                        $stmt = $pdo->prepare("SELECT id_specialite FROM specialite WHERE id_specialite = ? AND id_departement = ?");
                        $stmt->execute([$specialite_id, $departement_id]);
                        if (!$stmt->fetch()) {
                            // Si la spécialité n'appartient pas au département, on la met à null
                            $specialite_id = null;
                        }
                    }

                    // Création de l'utilisateur
                    $stmt = $pdo->prepare("
                        INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, type_utilisateur, id_departement, id_specialite, id_filiere)
                        VALUES (?, ?, ?, ?, 'coordinateur', ?, ?, ?)
                    ");
                    $stmt->execute([$nom, $prenom, $email, $password_hash, $departement_id, $specialite_id, $filiere_id]);

                    $pdo->commit();
                    $message = "Coordinateur ajouté avec succès.";
                    $messageType = "success";
                } catch(Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $message = "Erreur lors de l'ajout : " . $e->getMessage();
                    $messageType = "danger";
                }
                break;

            case 'modifier':
                // Code pour modifier un coordinateur
                try {
                    $user_id = $_POST['id'];
                    $nom = trim($_POST['nom']);
                    $prenom = trim($_POST['prenom']);
                    $email = trim($_POST['email']);
                    $filiere_id = $_POST['filiere_id'];
                    $password = $_POST['password'];

                    // Validation
                    if (empty($nom) || empty($prenom) || empty($email) || empty($filiere_id)) {
                        throw new Exception('Tous les champs sont obligatoires sauf le mot de passe');
                    }

                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('Email invalide');
                    }

                    if (!empty($password) && strlen($password) < 8) {
                        throw new Exception('Le mot de passe doit contenir au moins 8 caractères');
                    }

                    // Vérifier si l'email existe déjà pour un autre utilisateur
                    $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $user_id]);

                    if ($stmt->rowCount() > 0) {
                        throw new Exception('Cet email est déjà utilisé par un autre utilisateur');
                    }

                    // Transaction pour garantir l'intégrité des données
                    $pdo->beginTransaction();

                    // Récupérer l'id_departement correspondant à la filière sélectionnée
                    $stmt = $pdo->prepare("SELECT id_departement FROM filiere WHERE id_filiere = ?");
                    $stmt->execute([$filiere_id]);
                    $departement_id = $stmt->fetchColumn();

                    // Récupérer l'id_specialite
                    $specialite_id = isset($_POST['specialite_id']) ? intval($_POST['specialite_id']) : null;

                    // Vérifier que la spécialité appartient bien au département
                    if ($specialite_id) {
                        $stmt = $pdo->prepare("SELECT id_specialite FROM specialite WHERE id_specialite = ? AND id_departement = ?");
                        $stmt->execute([$specialite_id, $departement_id]);
                        if (!$stmt->fetch()) {
                            // Si la spécialité n'appartient pas au département, on la met à null
                            $specialite_id = null;
                        }
                    }

                    // Mise à jour de l'utilisateur
                    $sql = "UPDATE utilisateurs SET nom = ?, prenom = ?, email = ?, id_departement = ?, id_specialite = ?, id_filiere = ?";
                    $params = [$nom, $prenom, $email, $departement_id, $specialite_id, $filiere_id];

                    if (!empty($password)) {
                        $sql .= ", mot_de_passe = ?";
                        $params[] = password_hash($password, PASSWORD_DEFAULT);
                    }

                    $sql .= " WHERE id = ?";
                    $params[] = $user_id;

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    $pdo->commit();
                    $message = "Coordinateur modifié avec succès.";
                    $messageType = "success";
                } catch(Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $message = "Erreur lors de la modification : " . $e->getMessage();
                    $messageType = "danger";
                }
                break;

            case 'supprimer':
                // Code pour supprimer un coordinateur
                try {
                    $user_id = $_POST['id'];

                    // Transaction pour garantir l'intégrité des données
                    $pdo->beginTransaction();

                    // Suppression de l'utilisateur
                    $stmt = $pdo->prepare("
                        DELETE FROM utilisateurs
                        WHERE id = ? AND type_utilisateur = 'coordinateur'
                    ");
                    $stmt->execute([$user_id]);

                    $pdo->commit();
                    $message = "Coordinateur supprimé avec succès.";
                    $messageType = "success";
                } catch(Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $message = "Erreur lors de la suppression : " . $e->getMessage();
                    $messageType = "danger";
                }
                break;
        }

        // Rediriger pour éviter la resoumission du formulaire
        header("Location: gestion_coordinateur.php?message=" . urlencode($message) . "&messageType=" . urlencode($messageType));
        exit;
    }
}

// Récupérer le message de la redirection
if (isset($_GET['message']) && isset($_GET['messageType'])) {
    $message = $_GET['message'];
    $messageType = $_GET['messageType'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Coordinateurs - ENSAH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
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

        /* Style des sous-menus */
        .dropdown-menu {
            display: none;
            padding: 0;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            margin: 5px 0 10px 0;
            border-left: 2px solid var(--accent-color);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .dropdown-menu .nav-link {
            padding: 10px 15px 10px 45px;
            border-radius: 0;
            border-left: none;
            background: transparent;
        }
        
        .dropdown-menu .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(0);
        }
        
        .dropdown-menu .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            border-left: 3px solid var(--accent-color);
        }

        /* Footer de la sidebar */
        .sidebar-footer {
            padding: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: auto;
            background: rgba(0, 0, 0, 0.1);
        }
        
        .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 10px;
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
        /* ================ FIN DU SIDEBAR MODERNE ================ */

        /* Contenu principal */
        .main-content {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            background-color: #f8f9fc;
        }

        /* Section de gestion des coordinateurs */
        .coordinateurs-section {
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

        .add-btn {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(106, 13, 173, 0.3);
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

        .edit-btn {
            background: rgba(106, 13, 173, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(106, 13, 173, 0.2);
        }

        .edit-btn:hover {
            background: rgba(106, 13, 173, 0.2);
        }

        .delete-btn {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .delete-btn:hover {
            background: rgba(220, 53, 69, 0.2);
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

        /* Modal */
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            color: white;
        }

        .modal-title {
            color: white;
        }

        .form-label {
            font-weight: 500;
            color: var(--dark-purple);
        }
        
        /* MODIFICATION: Styles pour les boutons d'export */
        .dt-buttons .btn {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            margin-right: 5px;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .dt-buttons .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(106, 13, 173, 0.3);
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
    <!-- En-tête -->
    <div class="header">
        <div class="header-left">
            <img src="images/logo.png" alt="ENSAH Logo">
            <h1>Gestion des Coordinateurs</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-envelope"></i>
                <span class="user-info-value">admin@ensah.ma</span>
            </div>
            <div class="user-info">
                <i class="fas fa-calendar-alt"></i>
                <span class="user-info-label">Année :</span>
                <span class="user-info-value">2023-2024</span>
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
                    <a href="admin_dashboard.php" class="nav-link">
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
                        <a href="gestion_coordinateur.php" class="nav-link active">
                            <i class="fas fa-user-cog"></i>
                            <span>Coordinateurs</span>
                        </a>
                        <a href="gestion_enseignant.php" class="nav-link">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span>Enseignants</span>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Footer de la sidebar avec bouton de déconnexion en bas -->
            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </nav>

        <!-- Contenu principal -->
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0"><i class="fas fa-user-cog me-2 text-primary"></i>Liste des Coordinateurs</h2>
                <button class="add-btn" data-bs-toggle="modal" data-bs-target="#ajouterModal">
                    <i class="fas fa-plus-circle"></i> Ajouter Coordinateur
                </button>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="coordinateurs-section">
                <div class="table-responsive">
                    <table class="table table-hover" id="coordinateursTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Email</th>
                                <th>Filière</th>
                                <th>Département</th>
                                <th>Spécialité</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coordinateurs as $coord): ?>
                            <tr>
                                <td><?= htmlspecialchars($coord['id']) ?></td>
                                <td><?= htmlspecialchars($coord['nom']) ?></td>
                                <td><?= htmlspecialchars($coord['prenom']) ?></td>
                                <td><?= htmlspecialchars($coord['email']) ?></td>
                                <td><?= htmlspecialchars($coord['filiere']) ?></td>
                                <td><?= htmlspecialchars($coord['departement']) ?></td>
                                <td><?= htmlspecialchars($coord['specialite']) ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary me-1 btn-modifier"
                                            data-id="<?= $coord['id'] ?>"
                                            data-nom="<?= htmlspecialchars($coord['nom']) ?>"
                                            data-prenom="<?= htmlspecialchars($coord['prenom']) ?>"
                                            data-email="<?= htmlspecialchars($coord['email']) ?>"
                                            data-filiere="<?= $coord['filiere_id'] ?>"
                                            data-departement="<?= $coord['departement_id'] ?>"
                                            data-specialite="<?= $coord['specialite_id'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger btn-supprimer"
                                            data-id="<?= $coord['id'] ?>"
                                            data-nom="<?= htmlspecialchars($coord['prenom'] . ' ' . $coord['nom']) ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ajouter -->
    <div class="modal fade" id="ajouterModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un coordinateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="ajouter">

                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>

                        <div class="mb-3">
                            <label for="prenom" class="form-label">Prénom</label>
                            <input type="text" class="form-control" id="prenom" name="prenom" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="departement_id" class="form-label">Département</label>
                            <select class="form-select" id="departement_id" name="departement_id" required>
                                <option value="">Sélectionner un département</option>
                                <?php foreach ($departements as $dept): ?>
                                <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="filiere_id" class="form-label">Filière</label>
                            <select class="form-select" id="filiere_id" name="filiere_id" required>
                                <option value="">Sélectionner d'abord un département</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="specialite_id" class="form-label">Spécialité</label>
                            <select class="form-select" id="specialite_id" name="specialite_id">
                                <option value="">Sélectionner d'abord une filière</option>
                            </select>
                        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Mot de passe</label>
            <input type="password" class="form-control" id="password" name="password" required minlength="8">
            <div class="form-text text-light">Le mot de passe doit contenir au moins 8 caractères.</div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-primary">Ajouter</button>
    </div>
</form>
            </div>
        </div>
    </div>

    <!-- Modal Modifier -->
    <div class="modal fade" id="modifierModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier le coordinateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="modifier">
                        <input type="hidden" name="id" id="modifier_id">

                        <div class="mb-3">
                            <label for="modifier_nom" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="modifier_nom" name="nom" required>
                        </div>

                        <div class="mb-3">
                            <label for="modifier_prenom" class="form-label">Prénom</label>
                            <input type="text" class="form-control" id="modifier_prenom" name="prenom" required>
                        </div>

                        <div class="mb-3">
                            <label for="modifier_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="modifier_email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="modifier_departement_id" class="form-label">Département</label>
                            <select class="form-select" id="modifier_departement_id" name="departement_id" required>
                                <option value="">Sélectionner un département</option>
                                <?php foreach ($departements as $dept): ?>
                                <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="modifier_filiere_id" class="form-label">Filière</label>
                            <select class="form-select" id="modifier_filiere_id" name="filiere_id" required>
                                <option value="">Sélectionner d'abord un département</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="modifier_specialite_id" class="form-label">Spécialité</label>
                            <select class="form-select" id="modifier_specialite_id" name="specialite_id">
                                <option value="">Sélectionner d'abord une filière</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="modifier_password" class="form-label">Mot de passe (laisser vide pour ne pas changer)</label>
                            <input type="password" class="form-control" id="modifier_password" name="password" minlength="8">
                            <div class="form-text text-light">Le mot de passe doit contenir au moins 8 caractères.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Supprimer -->
    <div class="modal fade" id="supprimerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer le coordinateur <span id="supprimer_nom"></span> ?</p>
                    <p class="text-danger">Cette action est irréversible.</p>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="supprimer">
                    <input type="hidden" name="id" id="supprimer_id">
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">Supprimer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script>
        // Données globales pour les filières et spécialités
        const filieresData = <?= json_encode($filieres) ?>;
        const specialitesData = <?= json_encode($specialites) ?>;

        // Fonction pour charger les filières en fonction du département sélectionné
        function chargerFilieres(departementId, selecteurFiliere, filiereIdSelectionne = null) {
            const $selectFiliere = $(selecteurFiliere);
            $selectFiliere.empty().append('<option value="">Chargement...</option>');

            if (!departementId) {
                $selectFiliere.empty().append('<option value="">Sélectionner d\'abord un département</option>');
                return;
            }

            // Filtrer les filières par département
            const filieres = filieresData.filter(filiere => filiere.id_departement == departementId);

            $selectFiliere.empty().append('<option value="">Sélectionner une filière</option>');

            if (filieres.length > 0) {
                filieres.forEach(filiere => {
                    $selectFiliere.append(new Option(filiere.nom, filiere.id));
                });

                if (filiereIdSelectionne) {
                    $selectFiliere.val(filiereIdSelectionne);
                }
            } else {
                $selectFiliere.append('<option value="">Aucune filière disponible</option>');
            }
        }

        // Fonction pour charger les spécialités en fonction du département sélectionné
        function chargerSpecialites(departementId, selecteurSpecialite, specialiteIdSelectionne = null) {
            const $selectSpecialite = $(selecteurSpecialite);
            $selectSpecialite.empty().append('<option value="">Chargement...</option>');

            if (!departementId) {
                $selectSpecialite.empty().append('<option value="">Sélectionner d\'abord un département</option>');
                return;
            }

            // Filtrer les spécialités par département
            const specialites = specialitesData.filter(specialite => specialite.id_departement == departementId);

            $selectSpecialite.empty().append('<option value="">Sélectionner une spécialité</option>');

            if (specialites.length > 0) {
                specialites.forEach(specialite => {
                    $selectSpecialite.append(new Option(specialite.nom, specialite.id));
                });

                if (specialiteIdSelectionne) {
                    $selectSpecialite.val(specialiteIdSelectionne);
                }
            } else {
                $selectSpecialite.append('<option value="">Aucune spécialité disponible</option>');
            }
        }

        $(document).ready(function() {
            // Initialisation de DataTables avec boutons d'export personnalisés
            $('#coordinateursTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel me-1"></i> Excel',
                        className: 'btn',
                        title: 'Liste des Coordinateurs'
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf me-1"></i> PDF',
                        className: 'btn',
                        title: 'Liste des Coordinateurs'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print me-1"></i> Imprimer',
                        className: 'btn',
                        title: 'Liste des Coordinateurs'
                    }
                ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                },
                responsive: true,
                autoWidth: false,
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Tous"]],
                columnDefs: [
                    { orderable: false, targets: [7] },
                    { className: "text-center", targets: [7] }
                ]
            });

            // Gestion des sélecteurs de département et filière dans le formulaire d'ajout
            $('#departement_id').change(function() {
                const departementId = $(this).val();
                chargerFilieres(departementId, '#filiere_id');
                chargerSpecialites(departementId, '#specialite_id');
            });

            // Gestion des sélecteurs de département et filière dans le formulaire de modification
            $('#modifier_departement_id').change(function() {
                const departementId = $(this).val();
                chargerFilieres(departementId, '#modifier_filiere_id');
                chargerSpecialites(departementId, '#modifier_specialite_id');
            });

            // Gestion du modal de modification
            $('.btn-modifier').click(function() {
                const id = $(this).data('id');
                const nom = $(this).data('nom');
                const prenom = $(this).data('prenom');
                const email = $(this).data('email');
                const filiereId = $(this).data('filiere');
                const departementId = $(this).data('departement');
                const specialiteId = $(this).data('specialite');

                $('#modifier_id').val(id);
                $('#modifier_nom').val(nom);
                $('#modifier_prenom').val(prenom);
                $('#modifier_email').val(email);

                // Sélectionner le département
                $('#modifier_departement_id').val(departementId);

                // Charger les filières et sélectionner celle du coordinateur
                chargerFilieres(departementId, '#modifier_filiere_id', filiereId);

                // Charger les spécialités et sélectionner celle du coordinateur
                chargerSpecialites(departementId, '#modifier_specialite_id', specialiteId);

                $('#modifierModal').modal('show');
            });

            // Gestion du modal de suppression
            $('.btn-supprimer').click(function() {
                const id = $(this).data('id');
                const nom = $(this).data('nom');

                $('#supprimer_id').val(id);
                $('#supprimer_nom').text(nom);

                $('#supprimerModal').modal('show');
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