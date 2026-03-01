<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$isChefDepartement = false;

if (isset($_SESSION['type_utilisateur']) && $_SESSION['type_utilisateur'] === 'chef_departement') {
    $isChefDepartement = true;
}
else if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'chef_departement') {
    $isChefDepartement = true;
}
else if (isset($_SESSION['role']) && $_SESSION['role'] === 'chef_departement') {
    $isChefDepartement = true;
}

// Debug - à supprimer en production
$isChefDepartement = true;

if (!$isChefDepartement) {
    header("Location: login.php?error=acces_refuse");
    exit();
}

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

    // Récupération du département
    $stmt = $pdo->prepare("SELECT id_departement FROM utilisateurs WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $departement_id = $user['id_departement'];

    // Vérifier la structure des tables
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'professeurs'");
        $profsTableExists = $stmt->rowCount() > 0;

        if ($profsTableExists) {
            $query = "
                SELECT
                    p.id,
                    p.nom,
                    p.prenom,
                    'enseignant' as type,
                    192 as heures_max,
                    0 as heures_affectees
                FROM
                    professeurs p
                WHERE
                    1=1
                ORDER BY
                    p.nom, p.prenom
            ";
        } else {
            $stmt = $pdo->query("SHOW TABLES LIKE 'enseignants'");
            $enseignantsExists = $stmt->rowCount() > 0;

            if ($enseignantsExists) {
                $stmt = $pdo->query("DESCRIBE enseignants");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                $idDepartementExists = in_array('id_departement', $columns);

                if ($idDepartementExists) {
                    $query = "
                        (SELECT
                            e.id_enseignant as id,
                            e.nom,
                            e.prenom,
                            'permanent' as type,
                            192 as heures_max,
                            COALESCE(SUM(ue.volume_horaire), 0) as heures_affectees
                        FROM enseignants e
                        LEFT JOIN affectations_vacataires av ON av.id_vacataire = e.id_enseignant
                        LEFT JOIN unites_enseignements ue ON av.id_matiere = ue.id_matiere
                        WHERE e.id_departement = :departement_id
                        GROUP BY e.id_enseignant)
                    ";
                } else {
                    $query = "
                        (SELECT
                            e.id_enseignant as id,
                            e.nom,
                            e.prenom,
                            'permanent' as type,
                            192 as heures_max,
                            COALESCE(SUM(ue.volume_horaire), 0) as heures_affectees
                        FROM enseignants e
                        LEFT JOIN affectations_vacataires av ON av.id_vacataire = e.id_enseignant
                        LEFT JOIN unites_enseignements ue ON av.id_matiere = ue.id_matiere
                        GROUP BY e.id_enseignant)
                    ";
                }

                $stmt = $pdo->query("SHOW TABLES LIKE 'vacataires'");
                $vacatairesExists = $stmt->rowCount() > 0;

                if ($vacatairesExists) {
                    $query .= "
                        UNION
                        (SELECT
                            v.id_vacataire as id,
                            v.nom,
                            v.prenom,
                            'vacataire' as type,
                            96 as heures_max,
                            COALESCE(SUM(ue.volume_horaire), 0) as heures_affectees
                        FROM vacataires v
                        LEFT JOIN affectations_vacataires av ON av.id_vacataire = v.id_vacataire
                        LEFT JOIN unites_enseignements ue ON av.id_matiere = ue.id_matiere
                        GROUP BY v.id_vacataire)
                    ";
                }

                $query .= " ORDER BY nom, prenom";
            } else {
                $query = "
                    SELECT
                        0 as id,
                        'Aucune' as nom,
                        'donnée' as prenom,
                        'permanent' as type,
                        192 as heures_max,
                        0 as heures_affectees
                    FROM
                        dual
                ";
            }
        }
    } catch (PDOException $e) {
        $query = "
            SELECT
                0 as id,
                'Erreur' as nom,
                'structure' as prenom,
                'permanent' as type,
                192 as heures_max,
                0 as heures_affectees
            FROM
                dual
        ";
    }

    $hasDepIdParam = strpos($query, ':departement_id') !== false;
    $stmt = $pdo->prepare($query);

    if ($hasDepIdParam) {
        $stmt->execute([':departement_id' => $departement_id]);
    } else {
        $stmt->execute();
    }

    $professeurs = $stmt->fetchAll();

    foreach ($professeurs as &$prof) {
        $prof['pourcentage'] = $prof['heures_max'] > 0
            ? round(($prof['heures_affectees'] / $prof['heures_max']) * 100)
            : 0;
    }
    unset($prof);

} catch (PDOException $e) {
    die("Erreur base de données : " . $e->getMessage());
}

function getAlertClass($pourcentage) {
    if ($pourcentage >= 100) return 'danger';
    if ($pourcentage >= 80) return 'warning';
    return 'success';
}

try {
    $emailQuery = $pdo->prepare("SELECT email FROM utilisateurs WHERE id = ?");
    $emailQuery->execute([$_SESSION['user_id']]);
    $user_email = $emailQuery->fetchColumn();
} catch (PDOException $e) {
    $user_email = 'email@exemple.com';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Charges Horaires</title>
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

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fc;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

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

        .user-info-value {
            color: white;
            font-weight: 500;
        }

        .main-container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

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
            padding-left: 20px;
        }

        .submenu.open {
            max-height: 1000px;
        }

        .submenu .nav-link {
            padding: 10px 15px 10px 35px;
            font-size: 0.9rem;
        }

        .main-content {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            background-color: #f8f9fc;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            border: none;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 15px 20px;
            border-bottom: none;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 600;
        }

        .card-header h5 i {
            margin-right: 10px;
        }

        .progress {
            height: 25px;
            border-radius: 5px;
        }

        #chargeTable {
            background: white;
            border: 1px solid #dee2e6;
        }

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
                <span class="user-info-value"><?= htmlspecialchars($user_email ?? 'email@exemple.com') ?></span>
            </div>
            <div class="user-info">
                <i class="fas fa-building"></i>
                <span class="user-info-value"><?= htmlspecialchars($_SESSION['departement_nom'] ?? 'Département') ?></span>
            </div>
        </div>
    </div>

    <div class="main-container">
        <nav class="sidebar">
            <div class="sidebar-header">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['departement_nom'] ?? 'Chef') ?>&background=8a2be2&color=fff" alt="Chef Département">
                <h3><?= htmlspecialchars($_SESSION['departement_nom'] ?? 'Chef Département') ?></h3>
            </div>
            
            <div class="sidebar-menu">
                <div class="nav-item">
                    <a href="chef_dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Tableau de Bord</span>
                    </a>
                </div>
                
                <div class="section-title coordinateur active" id="chef-section">
                    <i class="fas fa-user-tie"></i>
                    <span>Chef Département</span>
                    <i class="fas fa-chevron-down arrow rotated"></i>
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
                        <a href="charge_horaire.php" class="nav-link active">
                            <i class="fas fa-chart-pie"></i>
                            <span>Charge Horaire</span>
                        </a>
                    </div>
                </div>
                
                <div class="section-title enseignant" id="enseignant-section">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Enseignant</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </div>
                
                <div class="submenu" id="enseignant-menu">
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
                    
                    <div class="nav-item">
                        <a href="reporting.php" class="nav-link">
                            <i class="fas fa-file-alt"></i>
                            <span>Reporting</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="import_export.php" class="nav-link">
                            <i class="fas fa-file-excel"></i>
                            <span>Import/Export</span>
                        </a>
                    </div>
                </div>
                
                <div class="nav-item">
                    <a href="logout.php" class="nav-link logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Déconnexion</span>
                    </a>
                </div>
            </div>
        </nav>

        <main class="main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-chart-pie me-2"></i> Gestion des Charges Horaires
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="ajouter_charge_horaire.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i> Ajouter une charge
                    </a>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h5 class="m-0 font-weight-bold text-white">
                        <i class="fas fa-table me-2"></i> Répartition par enseignant
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="chargeTable">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Nom</th>
                                    <th>Prénom</th>
                                    <th>Type</th>
                                    <th>Heures Affectées</th>
                                    <th>Heures Max</th>
                                    <th>Charge</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($professeurs as $prof): ?>
                                <tr class="table-<?= getAlertClass($prof['pourcentage']) ?>">
                                    <td><?= htmlspecialchars($prof['nom']) ?></td>
                                    <td><?= htmlspecialchars($prof['prenom']) ?></td>
                                    <td>
                                        <span class="badge <?= $prof['type'] === 'permanent' ? 'bg-primary' : 'bg-info' ?>">
                                            <?= ucfirst($prof['type']) ?>
                                        </span>
                                    </td>
                                    <td><?= $prof['heures_affectees'] ?></td>
                                    <td><?= $prof['heures_max'] ?></td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar bg-<?= getAlertClass($prof['pourcentage']) ?>"
                                                 style="width: <?= $prof['pourcentage'] ?>%">
                                                <?= $prof['pourcentage'] ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary details-btn"
                                                data-prof-id="<?= $prof['id'] ?>"
                                                data-prof-type="<?= $prof['type'] ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#detailsModal">
                                            <i class="fas fa-eye"></i> Détails
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h5 class="m-0 font-weight-bold text-white">
                        <i class="fas fa-chart-bar me-2"></i> Statistiques
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="chart-container">
                                <canvas id="typeChart"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-container">
                                <canvas id="occupationChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">Détail des affectations</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="detailsContent">
                            <div class="text-center my-5">
                                <i class="fas fa-spinner fa-spin fa-3x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
    $(document).ready(function() {
        // Initialisation DataTable
        $('#chargeTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
            },
            order: [[5, 'desc']]
        });

        // Gestion des détails
        $('.details-btn').click(function() {
            const profId = $(this).data('prof-id');
            const profType = $(this).data('prof-type');
            $('#detailsContent').load(`ajax_charge_details.php?prof_id=${profId}&type=${profType}`);
        });

        // Gestion des sections dépliables de la sidebar
        $('.section-title').click(function() {
            // Fermer toutes les autres sections
            $('.section-title').not(this).removeClass('active');
            $('.section-title').not(this).find('.arrow').removeClass('rotated');
            $('.submenu').not($('#' + this.id.replace('section', 'menu'))).removeClass('open');
            
            // Basculer l'état de la section cliquée
            $(this).toggleClass('active');
            $(this).find('.arrow').toggleClass('rotated');
            $('#' + this.id.replace('section', 'menu')).toggleClass('open');
        });

        // Préparation des données pour les graphiques
        const stats = {
            permanent: { heures: 0, count: 0, total: 0 },
            vacataire: { heures: 0, count: 0, total: 0 }
        };

        <?php foreach ($professeurs as $prof): ?>
            stats.<?= $prof['type'] ?>.heures += <?= $prof['heures_affectees'] ?>;
            stats.<?= $prof['type'] ?>.count++;
            stats.<?= $prof['type'] ?>.total += <?= $prof['pourcentage'] ?>;
        <?php endforeach; ?>

        // Graphique de répartition
        new Chart(document.getElementById('typeChart'), {
            type: 'doughnut',
            data: {
                labels: ['Permanents', 'Vacataires'],
                datasets: [{
                    data: [stats.permanent.heures, stats.vacataire.heures],
                    backgroundColor: ['#4e73df', '#1cc88a']
                }]
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: 'Répartition des heures'
                    }
                }
            }
        });

        // Graphique de charge moyenne
        new Chart(document.getElementById('occupationChart'), {
            type: 'bar',
            data: {
                labels: ['Permanents', 'Vacataires'],
                datasets: [{
                    label: 'Charge moyenne (%)',
                    data: [
                        stats.permanent.count ? stats.permanent.total / stats.permanent.count : 0,
                        stats.vacataire.count ? stats.vacataire.total / stats.vacataire.count : 0
                    ],
                    backgroundColor: ['#4e73df', '#1cc88a']
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Charge moyenne par type'
                    }
                }
            }
        });
    });
    </script>
</body>
</html>