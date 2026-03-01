
<?php
require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=session_invalide");
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$error = null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['success']);

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

    // Traitement des actions (Valider/Décliner)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            echo json_encode(['success' => false, 'message' => "Token CSRF invalide"]);
            exit();
        }

        $souhait_id = (int)($_POST['souhait_id'] ?? 0);
        if ($souhait_id <= 0) {
            echo json_encode(['success' => false, 'message' => "ID de souhait invalide"]);
            exit();
        }

        // Vérifier que le souhait existe et est en attente
        $stmt = $pdo->prepare("SELECT id_souhait FROM souhaits_enseignants WHERE id_souhait = ? AND statut = 'en_attente'");
        $stmt->execute([$souhait_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => "Souhait introuvable ou déjà traité"]);
            exit();
        }

        // Déterminer l'action (valider ou decliner)
        $action = $_POST['action'] ?? '';
        
        if ($action === 'valider') {
            // Validation du souhait
            $stmt = $pdo->prepare("UPDATE souhaits_enseignants SET statut = 'valide', date_validation = NOW() WHERE id_souhait = ?");
            $stmt->execute([$souhait_id]);
            
            if ($stmt->rowCount() > 0) {
                $message = 'Souhait validé avec succès';
                $action = 'valide';
            }
        } 
        elseif ($action === 'decliner') {
            $commentaire = trim($_POST['commentaire'] ?? '');
            if (empty($commentaire)) {
                echo json_encode(['success' => false, 'message' => "Le commentaire est obligatoire"]);
                exit();
            }
            
            // Rejet du souhait
            $stmt = $pdo->prepare("UPDATE souhaits_enseignants SET statut = 'rejete', date_validation = NOW(), commentaire = ? WHERE id_souhait = ?");
            $stmt->execute([$commentaire, $souhait_id]);
            
            if ($stmt->rowCount() > 0) {
                $message = 'Souhait décliné avec succès';
                $action = 'rejete';
            }
        }
        else {
            echo json_encode(['success' => false, 'message' => "Aucune action valide spécifiée"]);
            exit();
        }

        // Récupérer les données mises à jour pour la réponse
        $stmt = $pdo->prepare("
            SELECT 
                s.*,
                p.nom as prof_nom,
                p.prenom as prof_prenom,
                DATE_FORMAT(s.date_souhait, '%d/%m/%Y %H:%i') as date_souhait_format,
                DATE_FORMAT(s.date_validation, '%d/%m/%Y %H:%i') as date_validation_format
            FROM souhaits_enseignants s
            INNER JOIN professeurs p ON s.id_enseignant = p.id
            WHERE s.id_souhait = ?
        ");
        $stmt->execute([$souhait_id]);
        $souhait_mis_a_jour = $stmt->fetch();

        echo json_encode([
            'success' => true,
            'message' => $message,
            'action' => $action,
            'souhait' => $souhait_mis_a_jour
        ]);
        exit();
    }

    // Récupération des souhaits pour l'affichage initial
    function getSouhaits($pdo, $statut) {
        $query = "
            SELECT 
                s.id_souhait,
                s.id_enseignant,
                s.id_ue,
                s.annee_scolaire,
                s.date_souhait,
                s.date_validation,
                s.statut,
                s.commentaire,
                p.nom as prof_nom,
                p.prenom as prof_prenom,
                DATE_FORMAT(s.date_souhait, '%d/%m/%Y %H:%i') as date_souhait_format,
                DATE_FORMAT(s.date_validation, '%d/%m/%Y %H:%i') as date_validation_format
            FROM souhaits_enseignants s
            INNER JOIN professeurs p ON s.id_enseignant = p.id
            WHERE s.statut = ?
            ORDER BY " . ($statut === 'en_attente' ? "s.date_souhait ASC" : "s.date_validation DESC");
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$statut]);
        return $stmt->fetchAll();
    }

    $souhaitsEnAttente = getSouhaits($pdo, 'en_attente');
    $souhaitsValides = getSouhaits($pdo, 'valide');
    $souhaitsDeclines = getSouhaits($pdo, 'rejete');

} catch(PDOException $e) {
    error_log("Erreur PDO: " . $e->getMessage());
    $error = "Erreur de base de données. Veuillez consulter les logs.";
} catch(Exception $e) {
    error_log("Erreur: " . $e->getMessage());
    $error = "Une erreur est survenue: " . $e->getMessage();
}

function sanitize($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

function formatUeInfo($souhait) {
    return !empty($souhait['id_ue']) 
        ? 'UE-'.$souhait['id_ue'] 
        : 'N/A';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Souhaits - Chef de Département</title>
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

        /* Cartes */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 15px 20px;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 600;
        }

        .card-header h5 i {
            margin-right: 10px;
        }

        .card-body {
            padding: 20px;
        }

        /* Badges */
        .badge-en-attente { 
            background-color: #ffc107; 
            color: #212529; 
        }
        .badge-valide { 
            background-color: #28a745; 
            color: white; 
        }
        .badge-rejete { 
            background-color: #dc3545; 
            color: white; 
        }

        /* Tableaux */
        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            margin-bottom: 1rem;
            color: #212529;
        }

        .table th {
            background-color: var(--light-purple);
            border-bottom: 2px solid var(--dark-purple);
        }

        .table-hover tbody tr:hover {
            background-color: rgba(106, 13, 173, 0.05);
        }

        /* Commentaires */
        .comment-text {
            max-width: 200px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        /* Toasts */
        #toastContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
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
                <img src="images/logo.png" alt="Logo ENSAH">
            </div>
            <h1>Validation des Choix - Chef Département</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-envelope"></i>
                <span class="user-info-value"><?= sanitize($_SESSION['email'] ?? 'email@exemple.com') ?></span>
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
                
                <div class="submenu open" id="chef-menu">
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
                        <a href="gestion_choix.php" class="nav-link active">
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
                    <div class="nav-item">
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
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= sanitize($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= sanitize($success) ?></div>
            <?php endif; ?>

            <!-- Souhaits en attente -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="m-0"><i class="fas fa-hourglass-half me-2"></i> Demandes en Attente</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($souhaitsEnAttente)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Aucune demande en attente de validation
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="souhaitsTable">
                                <thead>
                                    <tr>
                                        <th>Professeur</th>
                                        <th>UE</th>
                                        <th>Année Scolaire</th>
                                        <th>Date de demande</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($souhaitsEnAttente as $souhait): 
                                        $ue_info = formatUeInfo($souhait);
                                        $professeur = sanitize($souhait['prof_nom'].' '.$souhait['prof_prenom']);
                                    ?>
                                    <tr id="row-<?= $souhait['id_souhait'] ?>">
                                        <td><?= $professeur ?></td>
                                        <td><?= sanitize($ue_info) ?></td>
                                        <td><?= sanitize($souhait['annee_scolaire'] ?? 'N/A') ?></td>
                                        <td><?= sanitize($souhait['date_souhait_format']) ?></td>
                                        <td>
                                            <span class="badge bg-warning text-dark">
                                                <i class="fas fa-clock me-1"></i> En attente
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline form-valider" data-id="<?= $souhait['id_souhait'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf_token) ?>">
                                                <input type="hidden" name="souhait_id" value="<?= $souhait['id_souhait'] ?>">
                                                <input type="hidden" name="action" value="valider">
                                                <button type="submit" class="btn btn-sm btn-success me-1">
                                                    <i class="fas fa-check me-1"></i> Valider
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                                    data-bs-target="#declinerModal<?= $souhait['id_souhait'] ?>">
                                                <i class="fas fa-times me-1"></i> Décliner
                                            </button>
                                            
                                            <!-- Modal pour décliner -->
                                            <div class="modal fade" id="declinerModal<?= $souhait['id_souhait'] ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Décliner le souhait</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <form method="POST" class="form-decliner" data-id="<?= $souhait['id_souhait'] ?>">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf_token) ?>">
                                                                <input type="hidden" name="souhait_id" value="<?= $souhait['id_souhait'] ?>">
                                                                <input type="hidden" name="action" value="decliner">
                                                                <p>Vous êtes sur le point de décliner le souhait de :</p>
                                                                <p><strong><?= $professeur ?></strong> pour <strong><?= sanitize($ue_info) ?></strong></p>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Commentaire (obligatoire)</label>
                                                                    <textarea class="form-control" name="commentaire" rows="3" required></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                                    <i class="fas fa-arrow-left me-1"></i> Annuler
                                                                </button>
                                                                <button type="submit" class="btn btn-danger">
                                                                    <i class="fas fa-times me-1"></i> Confirmer
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Souhaits validés -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="m-0"><i class="fas fa-check-circle me-2"></i> Souhaits Validés</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($souhaitsValides)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Aucun souhait validé pour le moment
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="souhaitsValidesTable">
                                <thead>
                                    <tr>
                                        <th>Professeur</th>
                                        <th>UE</th>
                                        <th>Année Scolaire</th>
                                        <th>Date de demande</th>
                                        <th>Date de validation</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody id="validedWishesBody">
                                    <?php foreach ($souhaitsValides as $souhait): 
                                        $ue_info = formatUeInfo($souhait);
                                    ?>
                                    <tr id="valide-<?= $souhait['id_souhait'] ?>">
                                        <td><?= sanitize($souhait['prof_nom'].' '.$souhait['prof_prenom']) ?></td>
                                        <td><?= sanitize($ue_info) ?></td>
                                        <td><?= sanitize($souhait['annee_scolaire'] ?? 'N/A') ?></td>
                                        <td><?= sanitize($souhait['date_souhait_format']) ?></td>
                                        <td><?= sanitize($souhait['date_validation_format']) ?></td>
                                        <td>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check me-1"></i> Validé
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Souhaits déclinés -->
            <div class="card">
                <div class="card-header">
                    <h5 class="m-0"><i class="fas fa-times-circle me-2"></i> Souhaits Déclinés</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($souhaitsDeclines)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Aucun souhait décliné pour le moment
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="souhaitsDeclinesTable">
                                <thead>
                                    <tr>
                                        <th>Professeur</th>
                                        <th>UE</th>
                                        <th>Année Scolaire</th>
                                        <th>Date de demande</th>
                                        <th>Date de décision</th>
                                        <th>Statut</th>
                                        <th>Commentaire</th>
                                    </tr>
                                </thead>
                                <tbody id="declinedWishesBody">
                                    <?php foreach ($souhaitsDeclines as $souhait): 
                                        $ue_info = formatUeInfo($souhait);
                                    ?>
                                    <tr id="rejete-<?= $souhait['id_souhait'] ?>">
                                        <td><?= sanitize($souhait['prof_nom'].' '.$souhait['prof_prenom']) ?></td>
                                        <td><?= sanitize($ue_info) ?></td>
                                        <td><?= sanitize($souhait['annee_scolaire'] ?? 'N/A') ?></td>
                                        <td><?= sanitize($souhait['date_souhait_format']) ?></td>
                                        <td><?= sanitize($souhait['date_validation_format']) ?></td>
                                        <td>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times me-1"></i> Décliné
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($souhait['commentaire'])): ?>
                                                <div class="comment-text">
                                                    <?= nl2br(sanitize($souhait['commentaire'])) ?>
                                                </div>
                                            <?php else: ?>
                                                <em class="text-muted">Aucun commentaire</em>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="toastContainer"></div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    // Initialisation des DataTables
    const initDataTables = () => {
        $('#souhaitsTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json' },
            responsive: true,
            order: [[3, 'asc']],
            columnDefs: [
                { targets: [1, 5], orderable: false }
            ]
        });

        $('#souhaitsValidesTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json' },
            responsive: true,
            order: [[4, 'desc']],
            columnDefs: [
                { targets: [1], orderable: false }
            ]
        });

        $('#souhaitsDeclinesTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json' },
            responsive: true,
            order: [[4, 'desc']],
            columnDefs: [
                { targets: [1, 6], orderable: false }
            ]
        });
    };
    
    initDataTables();

    // Fonction pour afficher les toasts
    function showToast(message, type = 'success') {
        const toast = $(`
            <div class="toast align-items-center text-white bg-${type} border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `);
        $('#toastContainer').append(toast);
        setTimeout(() => toast.remove(), 5000);
    }

    // Fonction pour créer une nouvelle ligne dans le tableau
    function createRowForStatus(souhait, status) {
        const profName = souhait.prof_nom + ' ' + souhait.prof_prenom;
        const ueInfo = 'UE-' + (souhait.id_ue || 'N/A');
        
        if (status === 'valide') {
            return `
                <tr id="valide-${souhait.id_souhait}">
                    <td>${escapeHtml(profName)}</td>
                    <td class="ue-info">${escapeHtml(ueInfo)}</td>
                    <td>${escapeHtml(souhait.annee_scolaire || 'N/A')}</td>
                    <td>${escapeHtml(souhait.date_souhait_format)}</td>
                    <td>${escapeHtml(souhait.date_validation_format)}</td>
                    <td>
                        <span class="badge bg-success">
                            <i class="fas fa-check me-1"></i> Validé
                        </span>
                    </td>
                </tr>
            `;
        } else {
            const commentaire = souhait.commentaire
                ? `<div class="comment-text">${escapeHtml(souhait.commentaire)}</div>`
                : '<em class="text-muted">Aucun commentaire</em>';
            
            return `
                <tr id="rejete-${souhait.id_souhait}">
                    <td>${escapeHtml(profName)}</td>
                    <td class="ue-info">${escapeHtml(ueInfo)}</td>
                    <td>${escapeHtml(souhait.annee_scolaire || 'N/A')}</td>
                    <td>${escapeHtml(souhait.date_souhait_format)}</td>
                    <td>${escapeHtml(souhait.date_validation_format)}</td>
                    <td>
                        <span class="badge bg-danger">
                            <i class="fas fa-times me-1"></i> Décliné
                        </span>
                    </td>
                    <td>${commentaire}</td>
                </tr>
            `;
        }
    }
     // Fonction pour échapper le HTML
    function escapeHtml(unsafe) {
        return unsafe 
            ? unsafe.toString()
                 .replace(/&/g, "&amp;")
                 .replace(/</g, "&lt;")
                 .replace(/>/g, "&gt;")
                 .replace(/"/g, "&quot;")
                 .replace(/'/g, "&#039;")
            : '';
    }

    // Gestion de la soumission des formulaires
    $(document).on('submit', '.form-valider, .form-decliner', function(e) {
        e.preventDefault();
        const form = $(this);
        const isDecliner = form.hasClass('form-decliner');
        const souhaitId = form.data('id');

        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Fermer le modal si c'est un déclin
                    if (isDecliner) {
                        form.closest('.modal').modal('hide');
                        form.find('textarea[name="commentaire"]').val('');
                    }
                    
                    // Supprimer la ligne du tableau des souhaits en attente
                    $(`#row-${souhaitId}`).remove();
                    
                    // Détruire les DataTables existants
                    $('#souhaitsTable, #souhaitsValidesTable, #souhaitsDeclinesTable').DataTable().destroy();
                    
                    // Ajouter la nouvelle ligne dans le tableau approprié
                    const newRow = createRowForStatus(response.souhait, response.action);
                    
                    if (response.action === 'valide') {
                        $('#validedWishesBody').prepend(newRow);
                    } else {
                        $('#declinedWishesBody').prepend(newRow);
                    }
                    
                    // Réinitialiser les DataTables
                    initDataTables();
                    
                    showToast(response.message);
                } else {
                    showToast(response.message, 'danger');
                    if (isDecliner && response.message.includes('commentaire')) {
                        form.find('textarea[name="commentaire"]').focus();
                    }
                }
            },
            error: function(xhr) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    showToast('Erreur: ' + (response.message || xhr.statusText), 'danger');
                } catch (e) {
                    showToast('Erreur: ' + xhr.statusText, 'danger');
                }
            }
        });
    });

    // Validation du formulaire de déclin
    $(document).on('submit', '.form-decliner', function(e) {
        const textarea = $(this).find('textarea[name="commentaire"]');
        if (!textarea.val().trim()) {
            e.preventDefault();
            showToast('Le commentaire est obligatoire', 'danger');
            textarea.focus();
        }
    });
});
</script>
</body>
</html>