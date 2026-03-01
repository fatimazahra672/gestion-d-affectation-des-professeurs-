<?php
require_once 'config.php';

// Correction : Vérifier l'état de la session avant de démarrer
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification des droits admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';
$enseignants = [];
$departements = [];
$specialites = [];

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

    // Récupérer la liste des départements et spécialités
    $departements = $pdo->query("SELECT id_departement, nom_departement FROM departement")->fetchAll();
    $specialites = $pdo->query("SELECT id_specialite, nom_specialite, id_departement FROM specialite")->fetchAll();

    // Récupérer la liste des enseignants
    $enseignants = $pdo->query("
        SELECT u.*, d.nom_departement, s.nom_specialite
        FROM utilisateurs u
        LEFT JOIN departement d ON u.id_departement = d.id_departement
        LEFT JOIN specialite s ON u.id_specialite = s.id_specialite
        WHERE u.type_utilisateur = 'enseignant'
        ORDER BY u.nom, u.prenom
    ")->fetchAll();

    // Traitement du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'ajouter':
                    $nom = trim($_POST['nom']);
                    $prenom = trim($_POST['prenom']);
                    $email = trim($_POST['email']);
                    $departement_id = $_POST['departement_id'];
                    $specialite_id = $_POST['specialite_id'] ?? null;
                    $password = $_POST['password'];
                    
                    // Validation
                    if (empty($nom) || empty($prenom) || empty($email) || empty($departement_id) || empty($password)) {
                        $error = 'Tous les champs sont obligatoires';
                        break;
                    }
                    
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $error = 'Email invalide';
                        break;
                    }
                    
                    if (strlen($password) < 8) {
                        $error = 'Le mot de passe doit contenir au moins 8 caractères';
                        break;
                    }
                    
                    // Vérifier si l'email existe déjà
                    $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
                    $stmt->execute([$email]);
                    
                    if ($stmt->rowCount() > 0) {
                        $error = 'Cet email est déjà utilisé';
                        break;
                    }
                    
                    // Hachage du mot de passe
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insertion
                    $stmt = $pdo->prepare("
                        INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, type_utilisateur, id_departement, id_specialite)
                        VALUES (?, ?, ?, ?, 'enseignant', ?, ?)
                    ");
                    $stmt->execute([$nom, $prenom, $email, $password_hash, $departement_id, $specialite_id]);
                    
                    $success = 'Enseignant ajouté avec succès';
                    break;
                    
                case 'modifier':
                    $id = $_POST['id'];
                    $nom = trim($_POST['nom']);
                    $prenom = trim($_POST['prenom']);
                    $email = trim($_POST['email']);
                    $departement_id = $_POST['departement_id'];
                    $specialite_id = $_POST['specialite_id'] ?? null;
                    $password = $_POST['password'] ?? '';
                    
                    // Validation
                    if (empty($nom) || empty($prenom) || empty($email) || empty($departement_id)) {
                        $error = 'Tous les champs sont obligatoires';
                        break;
                    }
                    
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $error = 'Email invalide';
                        break;
                    }
                    
                    if (!empty($password) && strlen($password) < 8) {
                        $error = 'Le mot de passe doit contenir au moins 8 caractères';
                        break;
                    }
                    
                    // Vérifier si l'email existe déjà pour un autre utilisateur
                    $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $error = 'Cet email est déjà utilisé par un autre utilisateur';
                        break;
                    }
                    
                    // Mise à jour
                    $sql = "UPDATE utilisateurs SET nom = ?, prenom = ?, email = ?, id_departement = ?, id_specialite = ?";
                    $params = [$nom, $prenom, $email, $departement_id, $specialite_id];
                    
                    if (!empty($password)) {
                        $sql .= ", mot_de_passe = ?";
                        $params[] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    
                    $sql .= " WHERE id = ?";
                    $params[] = $id;
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    $success = 'Enseignant modifié avec succès';
                    break;
                    
                case 'supprimer':
                    $id = $_POST['user_id'];
                    
                    $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $success = 'Enseignant supprimé avec succès';
                    break;
            }
        }
    }
} catch(PDOException $e) {
    $error = 'Erreur de base de données : ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Enseignants - ENSAH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css" rel="stylesheet">
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
        
        /* Footer de la sidebar avec bouton de déconnexion */
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

        /* Section de gestion des enseignants */
        .enseignants-section {
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
            display: inline-flex;
            align-items: center;
            gap: 5px;
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
            <img src="images/Logo.png" alt="ENSAH Logo">
            <h1>Gestion des Enseignants</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-user"></i>
                <span class="user-info-value">Administrateur</span>
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
                    <a href="admin_dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Tableau de Bord</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="#" class="nav-link dropdown-toggle" id="userDropdown" data-bs-toggle="collapse" data-bs-target="#userSubmenu" aria-expanded="true">
                        <i class="fas fa-users-cog"></i>
                        <span>Gestion Utilisateurs</span>
                        <i class="fas fa-chevron-down ms-auto"></i>
                    </a>
                    <div class="dropdown-menu collapse show" id="userSubmenu">
                        <a href="gestion_chef_departement.php" class="nav-link">
                            <i class="fas fa-user-tie"></i>
                            <span>Chefs de département</span>
                        </a>
                        <a href="gestion_coordinateur.php" class="nav-link">
                            <i class="fas fa-user-cog"></i>
                            <span>Coordinateurs</span>
                        </a>
                        <a href="gestion_enseignant.php" class="nav-link active">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span>Enseignants</span>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Footer de la sidebar avec bouton de déconnexion -->
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
                <h2 class="mb-0"><i class="fas fa-chalkboard-teacher me-2 text-primary"></i>Liste des Enseignants</h2>
                <button class="add-btn" data-bs-toggle="modal" data-bs-target="#ajouterModal">
                    <i class="fas fa-plus-circle"></i> Ajouter Enseignant
                </button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="enseignants-section">
                <div class="table-responsive">
                    <table class="table table-hover" id="enseignantsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Email</th>
                                <th>Département</th>
                                <th>Spécialité</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enseignants as $ens): ?>
                            <tr>
                                <td><?= htmlspecialchars($ens['id']) ?></td>
                                <td><?= htmlspecialchars($ens['nom']) ?></td>
                                <td><?= htmlspecialchars($ens['prenom']) ?></td>
                                <td><?= htmlspecialchars($ens['email']) ?></td>
                                <td><?= htmlspecialchars($ens['nom_departement'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($ens['nom_specialite'] ?? 'N/A') ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm edit-btn me-1 btn-modifier"
                                            data-id="<?= htmlspecialchars($ens['id']) ?>"
                                            data-nom="<?= htmlspecialchars($ens['nom']) ?>"
                                            data-prenom="<?= htmlspecialchars($ens['prenom']) ?>"
                                            data-email="<?= htmlspecialchars($ens['email']) ?>"
                                            data-type="<?= htmlspecialchars($ens['type_enseignant'] ?? 'permanent') ?>"
                                            data-departement="<?= htmlspecialchars($ens['id_departement'] ?? '') ?>"
                                            data-specialite="<?= htmlspecialchars($ens['id_specialite'] ?? '') ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm delete-btn btn-supprimer"
                                            data-id="<?= htmlspecialchars($ens['id']) ?>"
                                            data-nom="<?= htmlspecialchars($ens['prenom'] . ' ' . $ens['nom']) ?>">
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
                    <h5 class="modal-title">Ajouter un enseignant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" class="needs-validation" novalidate>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="ajouter">

                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                            <div class="invalid-feedback">Veuillez entrer le nom</div>
                        </div>

                        <div class="mb-3">
                            <label for="prenom" class="form-label">Prénom</label>
                            <input type="text" class="form-control" id="prenom" name="prenom" required>
                            <div class="invalid-feedback">Veuillez entrer le prénom</div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            <div class="invalid-feedback">Veuillez entrer un email valide</div>
                        </div>

                        <input type="hidden" name="type" value="permanent">

                        <div class="mb-3">
                            <label for="departement_id" class="form-label">Département</label>
                            <select class="form-select" id="departement_id" name="departement_id" required>
                                <option value="" selected disabled>Sélectionnez un département</option>
                                <?php foreach ($departements as $departement): ?>
                                    <option value="<?= $departement['id_departement'] ?>"><?= htmlspecialchars($departement['nom_departement']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un département</div>
                        </div>

                        <div class="mb-3">
                            <label for="specialite_id" class="form-label">Spécialité</label>
                            <select class="form-select" id="specialite_id" name="specialite_id">
                                <option value="" selected disabled>Sélectionnez une spécialité</option>
                                <?php foreach ($specialites as $specialite): ?>
                                    <option value="<?= $specialite['id_specialite'] ?>" data-department="<?= $specialite['id_departement'] ?>">
                                        <?= htmlspecialchars($specialite['nom_specialite']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Mot de passe</label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="8">
                            <div class="invalid-feedback">Le mot de passe doit contenir au moins 8 caractères</div>
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
                    <h5 class="modal-title">Modifier l'enseignant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" class="needs-validation" novalidate>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="modifier">
                        <input type="hidden" name="id" id="modifier_id">

                        <div class="mb-3">
                            <label for="modifier_nom" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="modifier_nom" name="nom" required>
                            <div class="invalid-feedback">Veuillez entrer le nom</div>
                        </div>

                        <div class="mb-3">
                            <label for="modifier_prenom" class="form-label">Prénom</label>
                            <input type="text" class="form-control" id="modifier_prenom" name="prenom" required>
                            <div class="invalid-feedback">Veuillez entrer le prénom</div>
                        </div>

                        <div class="mb-3">
                            <label for="modifier_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="modifier_email" name="email" required>
                            <div class="invalid-feedback">Veuillez entrer un email valide</div>
                        </div>

                        <input type="hidden" name="type" id="modifier_type" value="permanent">

                        <div class="mb-3">
                            <label for="modifier_departement_id" class="form-label">Département</label>
                            <select class="form-select" id="modifier_departement_id" name="departement_id" required>
                                <?php foreach ($departements as $departement): ?>
                                    <option value="<?= $departement['id_departement'] ?>"><?= htmlspecialchars($departement['nom_departement']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un département</div>
                        </div>

                        <div class="mb-3">
                            <label for="modifier_specialite_id" class="form-label">Spécialité</label>
                            <select class="form-select" id="modifier_specialite_id" name="specialite_id">
                                <option value="" selected disabled>Sélectionnez une spécialité</option>
                                <?php foreach ($specialites as $specialite): ?>
                                    <option value="<?= $specialite['id_specialite'] ?>" data-department="<?= $specialite['id_departement'] ?>">
                                        <?= htmlspecialchars($specialite['nom_specialite']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="modifier_password" class="form-label">Mot de passe (laisser vide pour ne pas changer)</label>
                            <input type="password" class="form-control" id="modifier_password" name="password" minlength="8">
                            <div class="invalid-feedback">Le mot de passe doit contenir au moins 8 caractères</div>
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
                    <p>Êtes-vous sûr de vouloir supprimer l'enseignant <span id="supprimer_nom"></span> ?</p>
                    <p class="text-danger">Cette action est irréversible.</p>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="supprimer">
                    <input type="hidden" name="user_id" id="supprimer_id">
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Filtrer les spécialités par département
            function filterSpecialites(departementId, targetId) {
                if (!departementId) {
                    $(targetId + ' option').show();
                    $(targetId).val('');
                    return;
                }
                
                // Cacher toutes les options
                $(targetId + ' option').hide();
                
                // Afficher l'option par défaut
                $(targetId + ' option[value=""]').show();
                
                // Afficher les spécialités du département sélectionné
                $(targetId + ' option[data-department="' + departementId + '"]').show();
                
                // Sélectionner la première option visible
                $(targetId).val($(targetId + ' option:visible:first').val());
            }
            
            // Filtrer les spécialités quand un département est sélectionné
            $('#departement_id').change(function() {
                const departementId = $(this).val();
                filterSpecialites(departementId, '#specialite_id');
            });
            
            $('#modifier_departement_id').change(function() {
                const departementId = $(this).val();
                filterSpecialites(departementId, '#modifier_specialite_id');
            });
            
            // Initialisation de DataTables avec les boutons d'exportation
            $('#enseignantsTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                },
                responsive: true,
                autoWidth: false,
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Tous"]],
                columnDefs: [
                    { orderable: false, targets: [6] },
                    { className: "text-center", targets: [6] }
                ],
                // Configuration des boutons d'exportation
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excelHtml5',
                        text: '<i class="fas fa-file-excel me-1"></i> Excel',
                        className: 'btn',
                        title: 'Liste des Enseignants - ENSAH',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5] // Colonnes à exporter
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="fas fa-file-pdf me-1"></i> PDF',
                        className: 'btn',
                        title: 'Liste des Enseignants - ENSAH',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5] // Colonnes à exporter
                        },
                        customize: function(doc) {
                            // Personnalisation du PDF
                            doc.content[1].table.widths = 
                                Array(doc.content[1].table.body[0].length + 1).join('*').split('');
                            doc.styles.tableHeader.fillColor = '#6a0dad';
                            doc.styles.title.fontSize = 16;
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print me-1"></i> Imprimer',
                        className: 'btn',
                        title: 'Liste des Enseignants - ENSAH',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5], // Colonnes à exporter
                            stripHtml: false
                        },
                        customize: function(win) {
                            // Personnalisation de l'impression
                            $(win.document.body).find('h1').css({
                                'text-align': 'center',
                                'color': '#6a0dad',
                                'margin-bottom': '20px'
                            });
                            $(win.document.body).find('table').addClass('table-bordered');
                        }
                    }
                ]
            });

            // Réinitialiser le formulaire d'ajout quand le modal est ouvert
            $('#ajouterModal').on('show.bs.modal', function() {
                $('#ajouterModal form')[0].reset();
                $('#specialite_id option').show();
            });

            // Gérer le clic sur le bouton modifier
            $('.btn-modifier').click(function() {
                const id = $(this).data('id');
                const nom = $(this).data('nom');
                const prenom = $(this).data('prenom');
                const email = $(this).data('email');
                const type = $(this).data('type');
                const departement = $(this).data('departement');
                const specialite = $(this).data('specialite');

                $('#modifier_id').val(id);
                $('#modifier_nom').val(nom);
                $('#modifier_prenom').val(prenom);
                $('#modifier_email').val(email);
                $('#modifier_type').val(type);
                $('#modifier_departement_id').val(departement);
                
                // Filtrer les spécialités pour ce département
                filterSpecialites(departement, '#modifier_specialite_id');
                
                // Sélectionner la spécialité
                if (specialite) {
                    $('#modifier_specialite_id').val(specialite);
                }

                $('#modifierModal').modal('show');
            });

            // Gérer le clic sur le bouton supprimer
            $('.btn-supprimer').click(function() {
                const id = $(this).data('id');
                const nom = $(this).data('nom');

                $('#supprimer_id').val(id);
                $('#supprimer_nom').text(nom);

                $('#supprimerModal').modal('show');
            });

            // Validation côté client
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
        });
    </script>
</body>
</html>