<?php
// Inclure le fichier de configuration
require_once 'config.php';

// Gestion de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=session_invalide");
    exit();
}

// Pour le débogage
error_log("Session user_id: " . $_SESSION['user_id']);
error_log("Session role: " . ($_SESSION['role'] ?? 'non défini'));
error_log("Session user_type: " . ($_SESSION['user_type'] ?? 'non défini'));

// Forcer le type d'utilisateur à chef_departement pour cette page
$_SESSION['user_type'] = 'chef_departement';
$_SESSION['role'] = 'chef_departement';

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
    
    // Récupérer l'email de l'utilisateur
    $emailQuery = $pdo->prepare("SELECT email FROM utilisateurs WHERE id = ?");
    $emailQuery->execute([$_SESSION['user_id']]);
    $user_email = $emailQuery->fetchColumn();
    
    // Récupérer les infos du département
    $departement_nom = $_SESSION['departement_nom'] ?? 'Département';
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer toutes les unités d'enseignement avec noms de matières
function getUnitesEnseignement() {
    global $pdo;

    try {
        // Requête avec jointure pour récupérer les noms de matières
        $query = "SELECT ue.*, m.nom AS nom_matiere 
                  FROM unites_enseignements ue
                  LEFT JOIN matieres m ON ue.id_matiere = m.id_matiere
                  ORDER BY ue.id_ue";
        
        $stmt = $pdo->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur dans getUnitesEnseignement: " . $e->getMessage());
        return [];
    }
}

$unites_enseignement = getUnitesEnseignement();

// Log pour le débogage
error_log("Nombre d'UE récupérées: " . count($unites_enseignement));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Unités d'Enseignement - Chef Département</title>
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
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8f9fc 0%, #e6e6fa 100%);
            min-height: 100vh;
            overflow-x: hidden;
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

        .header-logo img {
            height: 50px;
            filter: drop-shadow(0 0 5px rgba(255, 255, 255, 0.5));
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
            background: white;
        }

        .nav-link {
            color: white;
            padding: 12px 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            border-left: 4px solid var(--accent-color);
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
        }

        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .submenu.open {
            max-height: 500px;
        }

        .main-content {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            background-color: rgba(248, 249, 252, 0.9);
        }
        
        .page-title {
            color: var(--dark-purple);
            padding-bottom: 15px;
            margin-bottom: 25px;
            border-bottom: 2px solid rgba(106, 13, 173, 0.3);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .table-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 8px 20px rgba(106, 13, 173, 0.1);
            margin-bottom: 30px;
            border: 1px solid rgba(106, 13, 173, 0.1);
        }

        .export-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .export-btn {
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: none;
        }
        
        .export-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
        }
        
        .export-excel {
            background: #1D6F42;
            color: white;
        }
        
        .export-print {
            background: var(--primary-color);
            color: white;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(138, 43, 226, 0.05);
        }
        
        th {
            background-color: var(--light-purple);
            color: var(--dark-purple);
        }
        
        .footer {
            text-align: center;
            padding: 15px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            color: white;
            font-size: 0.9rem;
        }
        
        .type-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .type-cours {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .type-td {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .type-tp {
            background-color: #fff3e0;
            color: #ef6c00;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header-left {
                flex-direction: column;
                justify-content: center;
            }
            
            .header h1 {
                border-left: none;
                border-top: 1px solid rgba(255, 255, 255, 0.3);
                padding-top: 10px;
            }
            
            .header-right {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .main-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
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
            <h1>Gestion des Unités d'Enseignement</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-envelope"></i>
                <span class="user-info-value"><?= htmlspecialchars($user_email ?? 'email@exemple.com') ?></span>
            </div>
            <div class="user-info">
                <i class="fas fa-building"></i>
                <span class="user-info-value"><?= htmlspecialchars($departement_nom) ?></span>
            </div>
        </div>
    </div>

    <!-- Conteneur principal -->
    <div class="main-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($departement_nom) ?>&background=8a2be2&color=fff" alt="Chef Département">
                <h3><?= htmlspecialchars($departement_nom) ?></h3>
            </div>
            
            <div class="sidebar-menu">
                <div class="nav-item">
                    <a href="dashboard_enseignant.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Tableau de Bord</span>
                    </a>
                </div>
                <div class="submenu open" id="chef-menu">
                    <div class="nav-item">
                        <a href="Affichage_liste_UE.php" class="nav-link active">
                            <i class="fas fa-book"></i>
                            <span>Listes des UEs</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="souhaits_enseignants.php" class="nav-link">
                            <i class="fas fa-users-cog"></i>
                            <span>Souhaits enseignants</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="Calcul_automatique_charge_horaire.php" class="nav-link">
                            <i class="fas fa-tasks"></i>
                            <span>Charge Horaire</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="Notification.php" class="nav-link">
                            <i class="fas fa-check-double"></i>
                            <span>Notification</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a href="consulter_m.php" class="nav-link">
                            <i class="fas fa-chart-pie"></i>
                            <span>Modules Assurés</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="Uploader_notes.php" class="nav-link">
                            <i class="fas fa-upload"></i>
                            <span>Uploader notes</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="historique.php" class="nav-link">
                            <i class="fas fa-history"></i>
                            <span>Historique</span>
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

        <!-- Contenu principal -->
        <div class="main-content">
            <h1 class="page-title">
                <i class="fas fa-book-open"></i>
                Liste des Unités d'Enseignement
            </h1>
            
            <div class="export-buttons">
                <button id="export-excel" class="btn export-btn export-excel">
                    <i class="fas fa-file-excel"></i> Exporter vers Excel
                </button>
                <button id="export-print" class="btn export-btn export-print">
                    <i class="fas fa-print"></i> Imprimer
                </button>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-list"></i> Modules disponibles
                    </div>
                </div>
                
                <?php if (count($unites_enseignement) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="modulesTable">
                            <thead>
                                <tr>
                                    <th>ID UE</th>
                                    <th>Matière</th>
                                    <th>Filière</th>
                                    <th>Niveau</th>
                                    <th>Année Scolaire</th>
                                    <th>Type</th>
                                    <th>Volume Horaire</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($unites_enseignement as $ue): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($ue['id_ue'] ?? 'N/A') ?></td>
                                        <td>
                                            <?php if (!empty($ue['nom_matiere'])): ?>
                                                <?= htmlspecialchars($ue['nom_matiere']) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Matière non spécifiée</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($ue['filiere'] ?? 'Non spécifié') ?></td>
                                        <td><?= htmlspecialchars($ue['niveau'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($ue['annee_scolaire'] ?? 'N/A') ?></td>
                                        <td>
                                            <span class="type-badge type-<?= strtolower(substr($ue['type_enseignement'] ?? '', 0, 2)) ?>">
                                                <?= htmlspecialchars($ue['type_enseignement'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                        <td><strong><?= htmlspecialchars($ue['volume_horaire'] ?? '0') ?> h</strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-book-open fa-3x mb-3"></i>
                        <h3>Aucune unité d'enseignement trouvée</h3>
                        <p class="mb-0">La liste des unités d'enseignement est vide</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="footer">
        Système de Gestion des Unités d'Enseignement - ENSAH © <?= date('Y') ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialisation de DataTables
            var table = $('#modulesTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                },
                responsive: true,
                pageLength: 10,
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        title: 'Liste des Unités d\'Enseignement',
                        className: 'btn-excel'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Imprimer',
                        title: 'Liste des Unités d\'Enseignement',
                        className: 'btn-print'
                    }
                ],
                initComplete: function() {
                    $('.dataTables_filter input').addClass('form-control');
                    $('.dataTables_length select').addClass('form-select');
                }
            });

            // Lier les boutons d'export
            $('#export-excel').on('click', function() {
                table.button('.buttons-excel').trigger();
            });

            $('#export-print').on('click', function() {
                table.button('.buttons-print').trigger();
            });
        });
    </script>
</body>
</html>