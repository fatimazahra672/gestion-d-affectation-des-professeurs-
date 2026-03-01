<?php
session_start();

// Vérifier si l'utilisateur est connecté et est un coordinateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'coordinateur') {
    header("Location: login_coordinateur.php");
    exit;
}

// Données fictives pour le tableau de bord
$vacataires = 2;
$unites_enseignement = 7;
$affectations = 5;
$creneaux = 1;

// Informations personnelles
$email = $_SESSION['email'] ?? 'coordinateur1@example.com';
$filiere = $_SESSION['filiere'] ?? 'Informatique';
$annee_scolaire = $_SESSION['annee_scolaire'] ?? '2024-2025';

// Protection CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Variables initiales
$error = null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['success']);
$affectation = null;
$professeurs = [];
$unites_enseignement = [];
$ues_vacantes = [];

// Connexion à la base de données (exemple)
try {
    // Données fictives pour les UE vacantes
    $ues_vacantes = [
        [
            'id' => 1,
            'code_ue' => 'INF101',
            'intitule' => 'Algorithmique et programmation',
            'credit' => 6,
            'semestre' => 1
        ],
        [
            'id' => 2,
            'code_ue' => 'MAT202',
            'intitule' => 'Mathématiques discrètes',
            'credit' => 4,
            'semestre' => 2
        ],
        [
            'id' => 3,
            'code_ue' => 'RES304',
            'intitule' => 'Réseaux informatiques',
            'credit' => 5,
            'semestre' => 1
        ],
        [
            'id' => 4,
            'code_ue' => 'BDD401',
            'intitule' => 'Bases de données avancées',
            'credit' => 6,
            'semestre' => 2
        ]
    ];

    // Validation des UE vacantes
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valider_vacantes'])) {
        // Enregistrer la validation dans l'historique
        $success = "Liste des UE vacantes validée avec succès";
    }

} catch (Exception $e) {
    $error = "Erreur: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UE Vacantes - Coordinateur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        .vacataires-icon {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }

        .ue-icon {
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
        }

        .affectations-icon {
            background: linear-gradient(135deg, var(--accent-color) 0%, #0077b6 100%);
        }

        .creneaux-icon {
            background: linear-gradient(135deg, #20c997 0%, #0d6efd 100%);
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
        
        /* Styles spécifiques pour la page UE Vacantes */
        .page-title {
            color: var(--dark-purple);
            font-weight: 700;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--light-purple);
        }
        
        .card-ue {
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 25px;
            transition: transform 0.3s ease;
        }
        
        .card-ue:hover {
            transform: translateY(-5px);
        }
        
        .card-header-ue {
            background: linear-gradient(135deg, var(--primary-color), #1abc9c);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .ue-vacante-row {
            border-left: 4px solid var(--vacante-color);
            transition: all 0.3s;
        }
        
        .ue-vacante-row:hover {
            background-color: rgba(255, 107, 107, 0.05);
        }
        
        .action-buttons {
            min-width: 200px;
        }
        
        .btn-affecter {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border: none;
            color: white;
        }
        
        .btn-affecter:hover {
            background: linear-gradient(to right, var(--secondary-color), var(--primary-color));
            color: white;
        }
        
        .btn-valider {
            background: linear-gradient(to right, #f39c12, #e67e22);
            border: none;
            color: white;
            font-weight: 500;
        }
        
        .btn-valider:hover {
            background: linear-gradient(to right, #e67e22, #f39c12);
        }
        
        .semestre-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .s1-badge {
            background-color: #d4edda;
            color: #155724;
        }
        
        .s2-badge {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .table-custom th {
            background: linear-gradient(to right, #e3f2fd, #bbdefb);
            color: var(--dark-purple);
            font-weight: 600;
        }
        
        .no-ue-message {
            background-color: #d4edda;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            font-size: 1.1rem;
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
            <h1>Unités d'Enseignement Vacantes</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-calendar-alt"></i>
                <span class="user-info-label">Année :</span>
                <span class="user-info-value"><?= htmlspecialchars($annee_scolaire) ?></span>
            </div>
            <div class="user-info">
                <i class="fas fa-user-tie"></i>
                <span class="user-info-label">Filière :</span>
                <span class="user-info-value"><?= htmlspecialchars($filiere) ?></span>
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
                    <a href="dashborde_coordinateur.php" class="nav-link">
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
                        <a href="gestion_unites_enseignements.php" class="nav-link">
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
                            <span>créer compet vacataire</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="ue_vacantes.php" class="nav-link active">
                            <i class="fas fa-box-open"></i>
                            <span>UE Vacantes</span>
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
                            <i class="fas fa-user-plus"></i>
                            <span>Créer vacataire</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="Export_Exel.php" class="nav-link">
                            <i class="fas fa-file-excel"></i>
                            <span>Extraire en Excel</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="historique.php" class="nav-link">
                            <i class="fas fa-history"></i>
                            <span>Historique</span>
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
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="Consulter_modules.php" class="nav-link">
                            <i class="fas fa-tasks"></i>
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

        <!-- Contenu principal - UE Vacantes -->
        <div class="main-content">
            <h2 class="page-title">
                <i class="fas fa-box-open me-2"></i>Unités d'Enseignement Vacantes
            </h2>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
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
                                <table class="table table-hover table-custom">
                                    <thead>
                                        <tr>
                                            <th>Code UE</th>
                                            <th>Intitulé</th>
                                            <th>Crédits</th>
                                            <th>Semestre</th>
                                            <th class="action-buttons">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ues_vacantes as $ue): ?>
                                        <tr class="ue-vacante-row">
                                            <td class="fw-bold"><?= htmlspecialchars($ue['code_ue']) ?></td>
                                            <td><?= htmlspecialchars($ue['intitule']) ?></td>
                                            <td class="fw-medium"><?= htmlspecialchars($ue['credit']) ?></td>
                                            <td>
                                                <span class="semestre-badge s<?= $ue['semestre'] ?>-badge">
                                                    S<?= htmlspecialchars($ue['semestre']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="affectation_manuelle.php?ue_id=<?= $ue['id'] ?>" 
                                                       class="btn btn-sm btn-affecter">
                                                       <i class="fas fa-user-plus me-1"></i> Affecter
                                                    </a>
                                                    <a href="details_ue.php?id=<?= $ue['id'] ?>" 
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
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card card-ue">
                        <div class="card-header card-header-ue">
                            <h4 class="mb-0">
                                <i class="fas fa-chart-pie me-2"></i>Répartition par semestre
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-success rounded-circle me-2" style="width: 12px; height: 12px;"></div>
                                <span class="me-3">Semestre 1</span>
                                <div class="bg-primary rounded-circle me-2" style="width: 12px; height: 12px;"></div>
                                <span>Semestre 2</span>
                            </div>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: 60%">
                                    3 UE
                                </div>
                                <div class="progress-bar bg-primary" role="progressbar" style="width: 40%">
                                    2 UE
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card card-ue">
                        <div class="card-header card-header-ue">
                            <h4 class="mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>Statut des UE
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-around text-center">
                                <div>
                                    <div class="fs-2 fw-bold text-danger">4</div>
                                    <div>UE vacantes</div>
                                </div>
                                <div>
                                    <div class="fs-2 fw-bold text-success">12</div>
                                    <div>UE attribuées</div>
                                </div>
                                <div>
                                    <div class="fs-2 fw-bold text-warning">2</div>
                                    <div>En attente</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
        
        // Confirmation avant validation
        document.querySelector('[name="valider_vacantes"]').addEventListener('click', function(e) {
            if (!confirm('Êtes-vous sûr de vouloir valider cette liste d\'UE vacantes ?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>