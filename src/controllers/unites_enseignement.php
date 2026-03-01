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

// Protection CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Variables
$error = null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['success']);

$unites_enseignement = [];

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
    
    // Récupération des unités d'enseignement
    $stmt = $pdo->query("
        SELECT ue.*, d.nom as nom_departement
        FROM unites_enseignements ue
        LEFT JOIN departements d ON ue.departement_id = d.departement_id
        ORDER BY ue.code_ue
    ");
    $unites_enseignement = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Erreur de base de données: " . $e->getMessage();
    error_log("DB Error: " . $e->getMessage());
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
    <title>Unités d'Enseignement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1E90FF;
            --primary-magenta: #FF00FF;
            --blue-transparent: rgba(30, 144, 255, 0.3);
            --dark-bg: #0a192f;
        }

        body {
            background: linear-gradient(rgba(10, 25, 47, 0.85), rgba(108, 27, 145, 0.85));
            color: white;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            width: 250px;
            position: fixed;
            height: 100vh;
            background: rgba(44, 62, 80, 0.95);
            color: white;
            padding-top: 20px;
            backdrop-filter: blur(5px);
            border-right: 2px solid var(--primary-blue);
            box-shadow: 4px 0 15px var(--blue-transparent);
            animation: borderGlow 8s infinite alternate;
            z-index: 1000;
        }

        @keyframes borderGlow {
            0% { border-color: var(--primary-blue); }
            50% { border-color: var(--primary-magenta); }
            100% { border-color: var(--primary-blue); }
        }

        .sidebar .nav-link {
            color: white;
            padding: 12px 15px;
            margin: 8px 0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link:hover {
            background: rgba(30, 144, 255, 0.1);
            transform: translateX(10px);
        }

        .sidebar .nav-link.active {
            background: rgba(30, 144, 255, 0.2);
            border-left: 4px solid var(--primary-blue);
        }

        .content-area {
            margin-left: 270px;
            padding: 20px;
        }

        .card {
            background: rgba(10, 25, 47, 0.9);
            border: 2px solid var(--primary-blue);
            border-radius: 10px;
            box-shadow: 0 5px 15px var(--blue-transparent);
            transition: all 0.3s ease;
        }

        .card-header {
            background: linear-gradient(90deg, var(--primary-blue) 0%, var(--dark-bg) 100%);
            color: white;
            padding: 15px;
            border-bottom: 2px solid var(--primary-magenta);
        }

        #ueTable {
            background: rgba(10, 25, 47, 0.9);
            border: 1px solid var(--primary-blue);
            color: white;
        }

        #ueTable thead {
            background: linear-gradient(90deg, var(--primary-blue) 0%, var(--dark-bg) 100%);
            border-bottom: 2px solid var(--primary-magenta);
        }

        .export-buttons {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .export-btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .export-btn i {
            font-size: 1.1rem;
        }

        .export-btn-excel {
            background-color: #1D6F42;
            color: white;
            border: none;
        }

        .export-btn-excel:hover {
            background-color: #155a35;
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0,0,0,0.15);
        }

        .export-btn-print {
            background-color: var(--primary-blue);
            color: white;
            border: none;
        }

        .export-btn-print:hover {
            background-color: var(--dark-blue);
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="text-center mb-4">
            <img src="images/logo.png" alt="Logo" class="img-fluid mb-3" style="filter: drop-shadow(0 0 5px var(--primary-blue));">
            <h5 class="text-white" style="text-shadow: 0 0 10px var(--primary-blue);">
                <?= sanitize($_SESSION['departement_nom'] ?? 'Gestion Pédagogique') ?>
            </h5>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="chef_dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="unites_enseignement.php">
                    <i class="fas fa-book-open me-2"></i> Unités d'Enseignement
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="gestion_professeurs.php">
                    <i class="fas fa-users-cog me-2"></i> Professeurs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="affectation_ue.php">
                    <i class="fas fa-tasks me-2"></i> Affectations
                </a>
            </li>
            <li class="nav-item mt-4">
                <a class="nav-link text-danger" href="logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i> Déconnexion
                </a>
            </li>
        </ul>
    </div>

    <div class="content-area">
        <h1 class="mb-4 text-white"><i class="fas fa-book-open me-2"></i> Unités d'Enseignement</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= sanitize($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= sanitize($success) ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="m-0"><i class="fas fa-list me-2"></i> Liste des Unités d'Enseignement</h5>
            </div>
            <div class="card-body">
                <div class="export-buttons">
                    <button id="export-excel" class="btn export-btn export-btn-excel">
                        <i class="fas fa-file-excel"></i> Exporter vers Excel
                    </button>
                    <button id="export-print" class="btn export-btn export-btn-print">
                        <i class="fas fa-print"></i> Imprimer
                    </button>
                </div>
                
                <table class="table table-hover" id="ueTable">
                    <thead>
                        <tr>
                            <th>Code UE</th>
                            <th>Intitulé</th>
                            <th>Crédits</th>
                            <th>Volume Horaire</th>
                            <th>Niveau</th>
                            <th>Filière</th>
                            <th>Département</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unites_enseignement as $ue): ?>
                        <tr>
                            <td><?= sanitize($ue['code_ue'] ?? $ue['id_ue']) ?></td>
                            <td><?= sanitize($ue['intitule'] ?? $ue['filière']) ?></td>
                            <td><?= sanitize($ue['credit'] ?? $ue['credits'] ?? '3') ?></td>
                            <td><?= sanitize($ue['volume_horaire'] ?? '30') ?> h</td>
                            <td><?= sanitize($ue['niveau'] ?? '') ?></td>
                            <td><?= sanitize($ue['filiere'] ?? $ue['filière'] ?? '') ?></td>
                            <td><?= sanitize($ue['nom_departement'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
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
            var table = $('#ueTable').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                },
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        title: 'Liste des Unités d\'Enseignement',
                        exportOptions: {
                            columns: ':visible'
                        },
                        className: 'btn-excel'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Imprimer',
                        title: 'Liste des Unités d\'Enseignement',
                        exportOptions: {
                            columns: ':visible'
                        },
                        className: 'btn-print'
                    }
                ]
            });

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
