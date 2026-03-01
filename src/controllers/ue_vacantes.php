<?php
require_once 'config.php';

// Gestion de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification de l'authentification
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'chef_departement') {
    header("Location: login_chef.php");
    exit();
}

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

    // Récupérer les informations du département
    $stmt = $pdo->prepare("
        SELECT d.id_departement, d.nom_departement
        FROM departement d
        JOIN utilisateurs u ON d.id_departement = u.id_departement
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $departement = $stmt->fetch();

    if (!$departement) {
        // Valeurs par défaut si département non trouvé
        $departement = [
            'id_departement' => 1,
            'nom_departement' => 'Département par défaut'
        ];
    }

    // Mettre à jour les variables de session
    $_SESSION['id_departement'] = $departement['id_departement'];
    $_SESSION['departement_nom'] = $departement['nom_departement'];

    // Récupérer les UE vacantes (où id_utilisateur n'est pas 19 ou 20)
    $ues_vacantes = $pdo->query("
        SELECT m.id_matiere, m.nom, m.credit, ue.filiere, ue.niveau, ue.annee_scolaire
        FROM matieres m
        LEFT JOIN unites_enseignements ue ON m.id_matiere = ue.id_matiere
        WHERE (m.id_utilisateur IS NULL OR m.id_utilisateur NOT IN (19, 20))
        AND ue.id_matiere IS NOT NULL
        ORDER BY m.nom
    ")->fetchAll();

    // Statistiques pour les cartes
    $statsQuery = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM utilisateurs WHERE id_departement = ? AND type_utilisateur = 'enseignant') AS total_professeurs,
            (SELECT COUNT(*) FROM utilisateurs WHERE id_departement = ? AND type_utilisateur = 'vacataire') AS total_vacataires,
            (SELECT COUNT(*) FROM specialite WHERE id_departement = ?) AS total_specialites,
            (SELECT COUNT(*) FROM affectations_vacataires WHERE id_vacataire IN
                (SELECT id FROM utilisateurs WHERE id_departement = ? AND type_utilisateur = 'vacataire')) AS total_affectations,
            (SELECT COUNT(*) FROM matieres WHERE id_utilisateur IN (19, 20)) AS total_ue_attribuees,
            (SELECT COUNT(*) FROM matieres WHERE id_utilisateur IS NULL OR id_utilisateur NOT IN (19, 20)) AS total_ue_vacantes
    ");
    $statsQuery->execute([
        $departement['id_departement'],
        $departement['id_departement'],
        $departement['id_departement'],
        $departement['id_departement']
    ]);
    $stats = $statsQuery->fetch();

    // Protection CSRF
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrf_token = $_SESSION['csrf_token'];

    // Traitement du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valider_vacantes'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = "Erreur de sécurité. Veuillez réessayer.";
        } else {
            // Ici vous pourriez ajouter une logique pour marquer les UE comme validées
            $_SESSION['success'] = "La liste des UE vacantes a été validée avec succès.";
            header("Location: ue_vacantes.php");
            exit();
        }
    }

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
    <title>UE Vacantes - Chef Département</title>
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
            --vacante-color: #ff6b6b;
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

        /* Styles spécifiques pour la page UE Vacantes */
        .page-title {
            color: var(--dark-purple);
            border-bottom: 3px solid var(--light-purple);
            padding-bottom: 8px;
            margin-bottom: 20px;
        }

        .card-ue {
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            background: white;
            margin-bottom: 25px;
        }

        .card-ue:hover {
            transform: translateY(-5px);
        }

        .card-header-ue {
            background: linear-gradient(135deg, var(--primary-color), #1abc9c);
            border-radius: 12px 12px 0 0 !important;
            color: white;
            padding: 15px 20px;
        }

        .ue-vacante-row {
            border-left: 4px solid var(--vacante-color);
        }

        .ue-vacante-row:hover {
            background-color: rgba(255, 107, 107, 0.05);
        }

        .btn-affecter {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
        }

        .btn-affecter:hover {
            background: linear-gradient(to right, var(--secondary-color), var(--primary-color));
            color: white;
        }

        .btn-valider {
            background: linear-gradient(to right, #f39c12, #e67e22);
            color: white;
            border: none;
        }

        .btn-valider:hover {
            background: linear-gradient(to right, #e67e22, #f39c12);
        }

        .no-ue-message {
            background-color: #d4edda;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
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
                <img src="images/logo.png" alt="Logo">
            </div>
            <h1>Unités d'Enseignement Vacantes</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-envelope"></i>
                <span><?= sanitize($_SESSION['email'] ?? 'email@exemple.com') ?></span>
            </div>
            <div class="user-info">
                <i class="fas fa-building"></i>
                <span><?= sanitize($_SESSION['departement_nom']) ?></span>
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
                    
                    <div class="nav-item">
                        <a href="ue_vacantes.php" class="nav-link active">
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
                        <a href="dashboard_enseignant.php" class="nav-link">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Tableau de bord</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="Affichage_liste_UE.php" class="nav-link">
                            <i class="fas fa-list-ul"></i>
                            <span>Liste des UE</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="souhaits_enseignants.php" class="nav-link">
                            <i class="fas fa-hand-paper"></i>
                            <span>Souhaits enseignants</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="Calcul_automatique_charge_horaire.php" class="nav-link">
                            <i class="fas fa-calculator"></i>
                            <span>Charge horaire</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="Notification.php" class="nav-link">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="Consulter_modules.php" class="nav-link">
                            <i class="fas fa-book-open"></i>
                            <span>Modules assurés</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="Uploader_notes.php" class="nav-link">
                            <i class="fas fa-upload"></i>
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
            <h2 class="page-title">
                <i class="fas fa-exclamation-triangle me-2"></i>Unités d'Enseignement Vacantes
            </h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <div class="card card-ue">
                <div class="card-header card-header-ue">
                    <h4 class="mb-0">
                        <i class="fas fa-list me-2"></i>Liste des UE non attribuées
                    </h4>
                </div>
                
                <div class="card-body">
                    <?php if (empty($ues_vacantes)): ?>
                        <div class="no-ue-message">
                            <i class="fas fa-check-circle fa-2x mb-3 text-success"></i>
                            <h4>Aucune UE vacante</h4>
                            <p class="mb-0">Toutes les unités d'enseignement sont attribuées !</p>
                        </div>
                    <?php else: ?>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Code UE</th>
                                            <th>Intitulé</th>
                                            <th>Crédits</th>
                                            <th>Filière</th>
                                            <th>Niveau</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ues_vacantes as $ue): ?>
                                        <tr class="ue-vacante-row">
                                            <td class="fw-bold"><?= sanitize($ue['id_matiere']) ?></td>
                                            <td><?= sanitize($ue['nom']) ?></td>
                                            <td><?= sanitize($ue['credit']) ?></td>
                                            <td><?= sanitize($ue['filiere']) ?></td>
                                            <td><?= sanitize($ue['niveau']) ?></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="affectation_manuelle.php?ue_id=<?= $ue['id_matiere'] ?>" 
                                                       class="btn btn-sm btn-affecter">
                                                       <i class="fas fa-user-plus me-1"></i> Affecter
                                                    </a>
                                                    <a href="details_ue.php?id=<?= $ue['id_matiere'] ?>" 
                                                       class="btn btn-sm btn-outline-secondary">
                                                       <i class="fas fa-info-circle me-1"></i> Détails
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" name="valider_vacantes" 
                                        class="btn btn-valider"
                                        onclick="return confirm('Confirmer la validation de ces UE comme vacantes ?')">
                                    <i class="fas fa-check-circle me-2"></i> Valider la liste des UE vacantes
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Statistiques -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card card-ue">
                        <div class="card-header card-header-ue">
                            <h4 class="mb-0">
                                <i class="fas fa-chart-pie me-2"></i>Statistiques des UE
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-around text-center">
                                <div>
                                    <div class="fs-2 fw-bold text-danger"><?= $stats['total_ue_vacantes'] ?? 0 ?></div>
                                    <div>UE vacantes</div>
                                </div>
                                <div>
                                    <div class="fs-2 fw-bold text-success"><?= $stats['total_ue_attribuees'] ?? 0 ?></div>
                                    <div>UE attribuées</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card card-ue">
                        <div class="card-header card-header-ue">
                            <h4 class="mb-0">
                                <i class="fas fa-calendar-alt me-2"></i>Répartition par année
                            </h4>
                        </div>
                        <div class="card-body">
                            <canvas id="ueChart" height="150"></canvas>
                        </div>
                    </div>
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
        // Graphique des UE par année
        const ctx = document.getElementById('ueChart').getContext('2d');
        const ueChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['1ère année', '2ème année', '3ème année'],
                datasets: [{
                    data: [12, 8, 5],
                    backgroundColor: [
                        'rgba(106, 13, 173, 0.8)',
                        'rgba(138, 43, 226, 0.8)',
                        'rgba(75, 0, 130, 0.8)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        // Initialisation de DataTable
        $('table').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
            },
            pageLength: 10
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
    </script>
</body>
</html>