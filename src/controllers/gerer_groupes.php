<?php
require_once 'config.php';
session_start();

// Vérifier si l'utilisateur est connecté et est un coordinateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'coordinateur') {
    header("Location: login_coordinateur.php");
    exit;
}

// Connexion à la base de données
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Vérifier si la table groupes existe, sinon la créer
try {
    $pdo->query("SELECT 1 FROM groupes LIMIT 1");
} catch (PDOException $e) {
    // La table n'existe pas, on la crée
    $pdo->exec("
        CREATE TABLE groupes (
            id_groupe INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            type ENUM('TP', 'TD') NOT NULL,
            filiere VARCHAR(100) NOT NULL,
            niveau VARCHAR(50) NOT NULL,
            capacite INT NOT NULL DEFAULT 30,
            annee_scolaire VARCHAR(20) NOT NULL,
            departement_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (departement_id) REFERENCES departements(departement_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// Récupérer les informations du coordinateur
$departement_id = $_SESSION['id_departement'] ?? null;
$filiere_coordinateur = $_SESSION['filiere'] ?? 'Informatique'; // Filière par défaut si non définie

// Traitement des actions
$error = null;
$success = null;

// Ajout d'un groupe
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajouter'])) {
    try {
        $nom = $_POST['nom'];
        $type = $_POST['type'];
        $niveau = $_POST['niveau'];
        $capacite = (int)$_POST['capacite'];
        $annee = $_POST['annee_scolaire'];

        // Utiliser la filière du coordinateur
        $filiere = $filiere_coordinateur;

        $sql = "INSERT INTO groupes (nom, type, filiere, niveau, capacite, annee_scolaire, departement_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nom, $type, $filiere, $niveau, $capacite, $annee, $departement_id]);

        $success = "Groupe ajouté avec succès";
        header("Location: gerer_groupes.php?page=liste");
        exit();
    } catch (PDOException $e) {
        $error = "Erreur lors de l'ajout du groupe: " . $e->getMessage();
    }
}

// Suppression d'un groupe
if (isset($_GET['delete'])) {
    try {
        $id = (int)$_GET['delete'];
        $stmt = $pdo->prepare("DELETE FROM groupes WHERE id_groupe = ?");
        $stmt->execute([$id]);

        $success = "Groupe supprimé avec succès";
        header("Location: gerer_groupes.php?page=liste");
        exit();
    } catch (PDOException $e) {
        $error = "Erreur lors de la suppression du groupe: " . $e->getMessage();
    }
}

// Modification d'un groupe
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['modifier'])) {
    try {
        $id = (int)$_POST['id'];
        $nom = $_POST['nom'];
        $type = $_POST['type'];
        $niveau = $_POST['niveau'];
        $capacite = (int)$_POST['capacite'];
        $annee = $_POST['annee_scolaire'];

        // Conserver la filière existante ou utiliser celle du coordinateur
        $stmt = $pdo->prepare("SELECT filiere FROM groupes WHERE id_groupe = ?");
        $stmt->execute([$id]);
        $filiere = $stmt->fetchColumn() ?: $filiere_coordinateur;

        $sql = "UPDATE groupes
                SET nom = ?, type = ?, niveau = ?, capacite = ?, annee_scolaire = ?, departement_id = ?
                WHERE id_groupe = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nom, $type, $niveau, $capacite, $annee, $departement_id, $id]);

        $success = "Groupe modifié avec succès";
        header("Location: gerer_groupes.php?page=liste");
        exit();
    } catch (PDOException $e) {
        $error = "Erreur lors de la modification du groupe: " . $e->getMessage();
    }
}

// Vérifier si la colonne departement_id existe dans la table groupes
try {
    $pdo->query("SELECT departement_id FROM groupes LIMIT 1");
} catch (PDOException $e) {
    // La colonne n'existe pas, on l'ajoute
    $pdo->exec("ALTER TABLE groupes ADD COLUMN departement_id INT");
    $pdo->exec("ALTER TABLE groupes ADD CONSTRAINT fk_departement FOREIGN KEY (departement_id) REFERENCES departements(departement_id) ON DELETE SET NULL");
}

// Récupération des statistiques
$total_groupes = $pdo->query("SELECT COUNT(*) FROM groupes")->fetchColumn() ?: 0;
$total_tp = $pdo->query("SELECT COUNT(*) FROM groupes WHERE type = 'TP'")->fetchColumn() ?: 0;
$total_td = $pdo->query("SELECT COUNT(*) FROM groupes WHERE type = 'TD'")->fetchColumn() ?: 0;
$capacite_totale = $pdo->query("SELECT SUM(capacite) FROM groupes")->fetchColumn() ?: 0;

// Récupération des filières pour le formulaire
$filieres = $pdo->query("SELECT DISTINCT filiere FROM groupes ORDER BY filiere")->fetchAll() ?: [];

// Récupération des niveaux pour le formulaire
$niveaux = $pdo->query("SELECT DISTINCT niveau FROM groupes ORDER BY niveau")->fetchAll() ?: [];

// Récupération des années scolaires pour le formulaire
$annees = $pdo->query("SELECT DISTINCT annee_scolaire FROM groupes ORDER BY annee_scolaire DESC")->fetchAll() ?: [];

// Inclure le script d'enregistrement des visites
require_once 'record_page_visit.php';
recordPageVisit('gerer_groupes.php', 'coordinateur');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Groupes TP/TD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <style>
        :root {
            --primary-color: #6a11cb;
            --secondary-color: #5a0cb2;
            --light-bg: #f5f7ff;
            --card-bg: rgba(255, 255, 255, 0.95);
            --text-color: #333333;
            --white: #ffffff;
            --error-color: #ff4757;
            --success-color: #28a745;
            --border-radius: 10px;
            --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --accent-color: #00bfff;
            --teacher-color: #3498db;
            --teacher-dark: #2980b9;
        }

        body {
            background: linear-gradient(135deg, var(--light-bg) 0%, #c3c7f7 100%);
            color: var(--text-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            margin: 0;
        }

        .header-container {
            background: linear-gradient(90deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 1.5rem;
            box-shadow: 0 2px 15px rgba(106, 17, 203, 0.1);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .header-title {
            font-weight: 600;
            font-size: 2rem;
            margin-left: 20px;
            color: white;
        }

        .header-logo {
            height: 60px;
            width: auto;
        }

        .main-container {
            display: flex;
            max-width: 1400px;
            margin: 2rem auto;
            gap: 1.5rem;
            padding: 0 1rem;
        }

        /* ================ SIDEBAR MODERNE ================ */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--secondary-color) 0%, var(--primary-color) 100%);
            height: 100%;
            padding: 20px 0;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            border-radius: var(--border-radius);
            position: sticky;
            top: 0;
            align-self: stretch;
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

        body {
    margin: 0;
    padding: 0;
}

.header-container {
    margin-bottom: 0; /* Supprime la marge basse */
}

.main-container {
    margin-top: 0; /* Supprime la marge haute */
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
            background: transparent;
            border: none;
            width: 100%;
            text-align: left;
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
            background: rgba(255, 71, 87, 0.2) !important;
            margin-top: 10px;
        }

        .logout-btn:hover {
            background: rgba(255, 71, 87, 0.3) !important;
        }
        /* ================ FIN SIDEBAR MODERNE ================ */

        .content-area {
            flex: 1;
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 2rem;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(90deg, var(--primary-color) 0%, var(--secondary-color) 100%) !important;
            color: white;
            padding: 1rem 1.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
        }

        .table-responsive {
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        table th {
            background-color: var(--primary-color);
            color: white;
            padding: 0.75rem 1rem;
        }

        table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #eee;
        }

        .alert {
            border-radius: var(--border-radius);
        }

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border-color: var(--error-color);
            color: var(--error-color);
        }

        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border-color: var(--success-color);
            color: var(--success-color);
        }

        .stat-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
            transition: transform 0.3s ease;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: white;
            font-size: 1.5rem;
        }

        .search-box {
            position: relative;
            width: 100%;
        }

        .search-box input {
            padding-right: 2.5rem;
            border-radius: var(--border-radius);
        }

        .search-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
        }

        .action-buttons .btn {
            margin-left: 0.5rem;
        }

        .progress {
            height: 20px;
            background-color: #e9ecef;
            border-radius: var(--border-radius);
        }

        .progress-bar {
            border-radius: var(--border-radius);
        }

        .back-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1000;
        }

        .back-to-top.visible {
            opacity: 1;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .main-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                margin-bottom: 1.5rem;
            }

            .header-title {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 768px) {
            .header-title {
                font-size: 1.5rem;
            }

            .header-logo {
                height: 50px;
            }

            .content-area {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>

<!-- En-tête -->
<div class="header-container">
    <div class="header-content">
        <img src="image copy.png" alt="Logo" class="header-logo">
        <h1 class="header-title">Gestion des Groupes TP/TD</h1>
    </div>
</div>

<!-- Contenu principal -->
<div class="main-container">
    <!-- Menu latéral - Style du dashboard coordinateur -->
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
                <span>Coordinateur </span>
                <i class="fas fa-chevron-down arrow"></i>
            </div>
            

            <div class="nav-item">
                        <a href="gestion_unites_enseignements.php" class="nav-link">
                            <i class="fas fa-book"></i>
                            <span>Unités d'enseignement</span>
                        </a>
                    </div>

            <div class="submenu" id="coord-menu">
                <div class="nav-item">
                    <a href="gerer_groupes.php" class="nav-link active">
                        <i class="fas fa-users"></i>
                        <span>Gérer les groupes</span>
                    </a>
                </div>

                 
                <div class="nav-item">
                    <a href="affectation_vactaire.php" class="nav-link">
                        <i class="fas fa-user-tie"></i>
                        <span>Affectation Vacataires</span>
                    </a>
                </div>

                  <div class="nav-item">
                        <a href="creer_vacataire.php" class="nav-link">
                            <i class="fas fa-book"></i>
                            <span>créer compet vacataire</span>
                        </a>
                    </div>

                    
                    <div class="nav-item">
                        <a href="historique.php" class="nav-link">
                            <i class="fas fa-file-excel"></i>
                            <span>historique des années passées </span>
                        </a>
                    </div>
                
                    <div class="nav-item">
                    <a href="Export_Exel.php" class="nav-link">
                        <i class="fas fa-file-excel"></i>
                        <span>Extraire en Excel</span>
                    </a>
                </div>

                <div class="nav-item">
                    <a href="gerer_emplois_temps.php" class="nav-link">
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

                    
                      <div class="nav-item">
                        <a href="historique.php" class="nav-link">
                            <i class="fas fa-comments"></i>
                            <span>Historique</span>
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

    <!-- Zone de contenu principale -->
    <div class="content-area">
        <?php if ($error): ?>
            <div class="alert alert-danger mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success mb-4"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Barre d'outils -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">
                <i class="fas fa-users me-2"></i>
                <?php
                if (!isset($_GET['page']) || $_GET['page'] == 'ajouter') {
                    echo isset($_GET['edit']) ? 'Modifier un Groupe' : 'Ajouter un Groupe';
                } else {
                    echo 'Liste des Groupes';
                }
                ?>
            </h2>

            <div class="d-flex">
                <?php if (!isset($_GET['page']) || $_GET['page'] == 'ajouter'): ?>
                    <a href="?page=liste" class="btn btn-secondary me-2">
                        <i class="fas fa-list me-1"></i> Voir la liste
                    </a>
                <?php else: ?>
                    <a href="?page=ajouter" class="btn btn-success me-2">
                        <i class="fas fa-plus me-1"></i> Nouveau groupe
                    </a>
                <?php endif; ?>
                <button class="btn btn-primary me-2" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Imprimer
                </button>
                <button class="btn btn-success me-2" id="exportExcel">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
                <button class="btn btn-danger" id="exportPDF">
                    <i class="fas fa-file-pdf me-1"></i> PDF
                </button>
            </div>
        </div>

        <!-- Tableau de bord statistique -->
        <?php if (!isset($_GET['page']) || $_GET['page'] == 'ajouter' || $_GET['page'] == 'liste'): ?>
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #6a11cb 0%, #5a0cb2 100%);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?= $total_groupes ?></h3>
                            <p class="mb-0 text-muted">Groupes au total</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                            <i class="fas fa-flask"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?= $total_tp ?></h3>
                            <p class="mb-0 text-muted">Groupes TP</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?= $total_td ?></h3>
                            <p class="mb-0 text-muted">Groupes TD</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #28a745 0%, #218838 100%);">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?= $capacite_totale ?: 0 ?></h3>
                            <p class="mb-0 text-muted">Capacité totale</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Contenu dynamique -->
        <?php
        // Récupérer les données du groupe si on est en mode édition
        $groupe = [];
        if (isset($_GET['edit'])) {
            $id = (int)$_GET['edit'];
            $stmt = $pdo->prepare("SELECT * FROM groupes WHERE id_groupe = ?");
            $stmt->execute([$id]);
            $groupe = $stmt->fetch();
            if (!$groupe) {
                header("Location: gerer_groupes.php?page=liste");
                exit();
            }
        } else {
            // Valeurs par défaut pour un nouveau groupe
            $groupe = [
                'id_groupe' => '',
                'nom' => '',
                'type' => 'TP',
                'filiere' => $filiere_coordinateur,
                'niveau' => '',
                'capacite' => 30,
                'annee_scolaire' => date('Y') . '-' . (date('Y') + 1)
            ];
        }

        // Déterminer les niveaux disponibles en fonction de la filière
        $niveaux_disponibles = [];
        if ($filiere_coordinateur == 'Informatique') {
            $niveaux_disponibles = [
                'gi1' => 'GI1',
                'gi2' => 'GI2',
                'gi3' => 'GI3'
            ];
        } elseif ($filiere_coordinateur == 'Ingénierie de données') {
            $niveaux_disponibles = [
                'id1' => 'ID1',
                'id2' => 'ID2',
                'id3' => 'ID3'
            ];
        } elseif ($filiere_coordinateur == 'Réseaux et Systèmes') {
            $niveaux_disponibles = [
                'rs1' => 'RS1',
                'rs2' => 'RS2',
                'rs3' => 'RS3'
            ];
        } else {
            // Filière par défaut ou autre
            $niveaux_disponibles = [
                'n1' => 'Niveau 1',
                'n2' => 'Niveau 2',
                'n3' => 'Niveau 3'
            ];
        }

        // Si le niveau n'est pas défini, prendre le premier niveau disponible
        if (empty($groupe['niveau'])) {
            $groupe['niveau'] = array_key_first($niveaux_disponibles);
        }
        ?>

        <?php if (!isset($_GET['page']) || $_GET['page'] == 'ajouter'): ?>
            <!-- Formulaire d'ajout/modification -->
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><?= isset($_GET['edit']) ? 'Modifier un Groupe' : 'Ajouter un Groupe' ?></h4>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= $groupe['id_groupe'] ?>">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Nom du groupe</label>
                                <input type="text" class="form-control" name="nom" value="<?= $groupe['nom'] ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Type de groupe</label>
                                <select class="form-select" name="type">
                                    <option value="TP" <?= $groupe['type'] == 'TP' ? 'selected' : '' ?>>TP</option>
                                    <option value="TD" <?= $groupe['type'] == 'TD' ? 'selected' : '' ?>>TD</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Filière</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($filiere_coordinateur) ?>" readonly>
                                <input type="hidden" name="filiere" value="<?= htmlspecialchars($filiere_coordinateur) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Niveau</label>
                                <select class="form-select" name="niveau">
                                    <?php foreach ($niveaux_disponibles as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= $groupe['niveau'] == $value ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Capacité</label>
                                <input type="number" class="form-control" name="capacite" value="<?= $groupe['capacite'] ?>" min="1" max="100" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Année scolaire</label>
                                <select class="form-select" name="annee_scolaire">
                                    <?php
                                    $current_year = date('Y');
                                    for ($i = $current_year - 1; $i <= $current_year + 3; $i++) {
                                        $academic_year = $i . '-' . ($i + 1);
                                        echo '<option value="' . $academic_year . '"';
                                        if ($groupe['annee_scolaire'] == $academic_year) echo ' selected';
                                        echo '>' . $academic_year . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="?page=liste" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Retour
                            </a>
                            <button type="submit" name="<?= isset($_GET['edit']) ? 'modifier' : 'ajouter' ?>" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> <?= isset($_GET['edit']) ? 'Enregistrer' : 'Ajouter' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($_GET['page'] == 'liste'): ?>
            <!-- Liste des groupes -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Liste des Groupes</h4>
                        <div class="search-box">
                            <input type="text" id="searchInput" class="form-control" placeholder="Rechercher...">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <select id="filterType" class="form-select">
                                <option value="">Tous les types</option>
                                <option value="TP">TP</option>
                                <option value="TD">TD</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="filterFiliere" class="form-select">
                                <option value="">Toutes les filières</option>
                                <?php foreach ($filieres as $filiere): ?>
                                    <option value="<?= $filiere['filiere'] ?>"><?= $filiere['filiere'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="filterNiveau" class="form-select">
                                <option value="">Tous les niveaux</option>
                                <?php foreach ($niveaux as $niveau): ?>
                                    <option value="<?= $niveau['niveau'] ?>"><?= $niveau['niveau'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="filterAnnee" class="form-select">
                                <option value="">Toutes les années</option>
                                <?php foreach ($annees as $annee): ?>
                                    <option value="<?= $annee['annee_scolaire'] ?>"><?= $annee['annee_scolaire'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Type</th>
                                    <th>Filière</th>
                                    <th>Niveau</th>
                                    <th>Capacité</th>
                                    <th>Année</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="groupesTableBody">
                                <?php
                                $stmt = $pdo->prepare("SELECT * FROM groupes ORDER BY id_groupe DESC");
                                $stmt->execute();
                                while ($row = $stmt->fetch()):
                                ?>
                                <tr data-type="<?= $row['type'] ?>" 
                                    data-filiere="<?= $row['filiere'] ?>" 
                                    data-niveau="<?= $row['niveau'] ?>" 
                                    data-annee="<?= $row['annee_scolaire'] ?>">
                                    <td><?= $row['id_groupe'] ?></td>
                                    <td><?= htmlspecialchars($row['nom']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $row['type'] == 'TP' ? 'info' : 'warning' ?>">
                                            <?= $row['type'] ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($row['filiere']) ?></td>
                                    <td><?= htmlspecialchars($row['niveau']) ?></td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" 
                                                 role="progressbar" 
                                                 style="width: <?= min(100, ($row['capacite'] / 30) * 100) ?>%" 
                                                 aria-valuenow="<?= $row['capacite'] ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="30">
                                                <?= $row['capacite'] ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= $row['annee_scolaire'] ?></td>
                                    <td class="action-buttons">
                                        <a href="?page=ajouter&edit=<?= $row['id_groupe'] ?>" 
                                           class="btn btn-sm btn-primary" 
                                           title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?page=liste&delete=<?= $row['id_groupe'] ?>" 
                                           class="btn btn-sm btn-danger" 
                                           title="Supprimer"
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce groupe ?')">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Bouton retour en haut -->
<button class="back-to-top" id="backToTop">
    <i class="fas fa-arrow-up"></i>
</button>

<!-- Scripts JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
    // Menu déroulant pour la sidebar
    document.addEventListener('DOMContentLoaded', function() {
        const coordSection = document.getElementById('coord-section');
        const coordMenu = document.getElementById('coord-menu');
        const teacherSection = document.getElementById('teacher-section');
        const teacherMenu = document.getElementById('teacher-menu');
        
        // Gestion des sections dépliables
        function toggleMenu(section, menu) {
            section.addEventListener('click', function() {
                menu.classList.toggle('open');
                const arrow = this.querySelector('.arrow');
                arrow.classList.toggle('rotated');
            });
        }
        
        toggleMenu(coordSection, coordMenu);
        toggleMenu(teacherSection, teacherMenu);
        
        // Ouvrir la section Coordinateur par défaut
        coordMenu.classList.add('open');
        coordSection.querySelector('.arrow').classList.add('rotated');
        
        // Filtrer les groupes
        function filterGroups() {
            const type = document.getElementById('filterType').value.toLowerCase();
            const filiere = document.getElementById('filterFiliere').value.toLowerCase();
            const niveau = document.getElementById('filterNiveau').value.toLowerCase();
            const annee = document.getElementById('filterAnnee').value.toLowerCase();
            const search = document.getElementById('searchInput').value.toLowerCase();
            
            const rows = document.querySelectorAll('#groupesTableBody tr');
            
            rows.forEach(row => {
                const rowType = row.getAttribute('data-type').toLowerCase();
                const rowFiliere = row.getAttribute('data-filiere').toLowerCase();
                const rowNiveau = row.getAttribute('data-niveau').toLowerCase();
                const rowAnnee = row.getAttribute('data-annee').toLowerCase();
                const rowText = row.textContent.toLowerCase();
                
                const typeMatch = !type || rowType.includes(type);
                const filiereMatch = !filiere || rowFiliere.includes(filiere);
                const niveauMatch = !niveau || rowNiveau.includes(niveau);
                const anneeMatch = !annee || rowAnnee.includes(annee);
                const searchMatch = !search || rowText.includes(search);
                
                if (typeMatch && filiereMatch && niveauMatch && anneeMatch && searchMatch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Écouteurs d'événements pour les filtres
        document.getElementById('filterType').addEventListener('change', filterGroups);
        document.getElementById('filterFiliere').addEventListener('change', filterGroups);
        document.getElementById('filterNiveau').addEventListener('change', filterGroups);
        document.getElementById('filterAnnee').addEventListener('change', filterGroups);
        document.getElementById('searchInput').addEventListener('input', filterGroups);
        
        // Bouton retour en haut
        const backToTopButton = document.getElementById('backToTop');
        
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopButton.classList.add('visible');
            } else {
                backToTopButton.classList.remove('visible');
            }
        });
        
        backToTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // Export Excel
        document.getElementById('exportExcel').addEventListener('click', function() {
            const table = document.querySelector('table');
            const wb = XLSX.utils.table_to_book(table);
            XLSX.writeFile(wb, 'groupes.xlsx');
        });
        
        // Export PDF
        document.getElementById('exportPDF').addEventListener('click', function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            doc.autoTable({
                html: 'table',
                theme: 'grid',
                headStyles: {
                    fillColor: [106, 17, 203],
                    textColor: 255
                },
                styles: {
                    cellPadding: 5,
                    fontSize: 10,
                    valign: 'middle'
                },
                margin: { top: 20 },
                didDrawPage: function(data) {
                    doc.text('Liste des Groupes', 14, 10);
                }
            });
            
            doc.save('groupes.pdf');
        });
    });
</script>
</body>
</html>