<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===================== CONFIGURATION BASE DE DONNÉES =====================
$host = 'localhost';
$dbname = 'gestion_coordinteur';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer les informations de l'enseignant
$id_utilisateur = $_SESSION['user_id'] ?? $_SESSION['id_utilisateur'] ?? $_SESSION['id'] ?? 19;

if (!is_numeric($id_utilisateur) || $id_utilisateur <= 0) {
    $id_utilisateur = 19; // Valeur par défaut
}

try {
    $stmt = $pdo->prepare("
        SELECT u.*, s.nom_specialite
        FROM utilisateurs u
        LEFT JOIN specialite s ON u.id_specialite = s.id_specialite
        WHERE u.id = ? AND u.type_utilisateur = 'enseignant'
    ");
    $stmt->execute([$id_utilisateur]);
    $info_enseignant = $stmt->fetch();

    if (!$info_enseignant) {
        die("Profil enseignant introuvable");
    }

} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}

// ===================== REQUÊTE POUR RÉCUPÉRER LES MODULES =====================
$sql = "
SELECT
    ue.id_ue,
    m.nom AS nom_matiere,
    ue.filiere,
    ue.niveau,
    ue.type_enseignement,
    ue.annee_scolaire,
    ue.volume_horaire,
    a.date_affectation
FROM affectations a
JOIN unites_enseignements ue ON a.ue_id = ue.id_ue
JOIN matieres m ON ue.id_matiere = m.id_matiere
WHERE a.professeur_id = :id_enseignant
ORDER BY ue.annee_scolaire DESC, ue.filiere ASC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_enseignant', $id_utilisateur, PDO::PARAM_INT);
    $stmt->execute();
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur de requête : " . $e->getMessage());
}

// Déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login_coordinateur.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modules Affectés</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 10px;
            border: 3px solid white;
            padding: 0;
            object-fit: cover;
            display: block;
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

        /* Contenu principal */
        .main-content {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            background-color: #f8f9fc;
        }

        /* Section de gestion */
        .dashboard-section {
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

        .no-data {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
            font-style: italic;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            margin: 2rem 0;
        }

        .badge {
            font-size: 0.85em;
            padding: 0.5em 0.75em;
        }
        
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: none;
            margin-bottom: 25px;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 15px 25px;
            font-weight: 600;
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
    <header class="header">
        <div class="header-left">
            <img src="images/logo" alt="Logo">
            <h1>Modules Affectés - Enseignant</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <div>
                    <div class="user-info-label">Enseignant</div>
                    <div class="user-info-value"><?= htmlspecialchars($info_enseignant['prenom'] . ' ' . $info_enseignant['nom']) ?></div>
                </div>
            </div>
        </div>
    </header>

    <div class="main-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="https://via.placeholder.com/100" alt="Photo de profil">
                <h3><?= htmlspecialchars($info_enseignant['prenom'] . ' ' . $info_enseignant['nom']) ?></h3>
                <small class="text-white-50"><?= htmlspecialchars($info_enseignant['nom_specialite'] ?? 'Non spécifiée') ?></small>
            </div>
            <nav class="sidebar-menu">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="dashboard_enseignant.php" class="nav-link">
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
                        <a href="Consulter_modules.php" class="nav-link active">
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
                    <li class="nav-item">
                        <a href="?logout=true" class="nav-link logout-btn">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Déconnexion</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="mb-0 text-white">Mes Modules Affectés</h2>
                </div>
                <div class="card-body">
                    <p class="text-muted">Enseignant ID : <?= htmlspecialchars($id_utilisateur) ?></p>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if (count($modules) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-book"></i> Matière</th>
                                        <th><i class="fas fa-graduation-cap"></i> Filière</th>
                                        <th><i class="fas fa-layer-group"></i> Niveau</th>
                                        <th><i class="fas fa-chalkboard"></i> Type</th>
                                        <th><i class="fas fa-clock"></i> Volume Horaire</th>
                                        <th><i class="fas fa-calendar"></i> Année Scolaire</th>
                                        <th><i class="fas fa-calendar-plus"></i> Date Affectation</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($modules as $module): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($module['nom_matiere']) ?></strong></td>
                                        <td><?= htmlspecialchars($module['filiere']) ?></td>
                                        <td><?= htmlspecialchars($module['niveau']) ?></td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?= htmlspecialchars($module['type_enseignement']) ?>
                                            </span>
                                        </td>
                                        <td><strong><?= htmlspecialchars($module['volume_horaire']) ?>h</strong></td>
                                        <td><?= htmlspecialchars($module['annee_scolaire']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($module['date_affectation'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <h4>Aucun module affecté</h4>
                            <p>Aucun module n'a été affecté à votre compte pour le moment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>