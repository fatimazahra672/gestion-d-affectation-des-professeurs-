<?php
require_once 'config.php';
session_start();

// Vérification des droits
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chef_departement') {
    header("Location: login.php");
    exit;
}

$departement_id = $_SESSION['departement_id'];
$departement_nom = '';
$stats = [];

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

    // Récupérer le nom du département
    $stmt = $pdo->prepare("SELECT nom FROM departements WHERE departement_id = ?");
    $stmt->execute([$departement_id]);
    $departement = $stmt->fetch();
    $departement_nom = $departement ? $departement['nom'] : 'Département inconnu';

    // Statistiques
    $stats = [
        'total_ue' => $pdo->prepare("SELECT COUNT(*) FROM unites_enseignement WHERE departement_id = ?")->execute([$departement_id]) ? $stmt->fetchColumn() : 0,
        'ue_vacantes' => $pdo->prepare("SELECT COUNT(*) FROM unites_enseignement WHERE departement_id = ? AND statut = 'actif'")->execute([$departement_id]) ? $stmt->fetchColumn() : 0,
        'total_enseignants' => $pdo->prepare("SELECT COUNT(*) FROM enseignants e JOIN users u ON e.user_id = u.id WHERE e.departement_id = ?")->execute([$departement_id]) ? $stmt->fetchColumn() : 0,
        'total_affectations' => $pdo->prepare("SELECT COUNT(*) FROM affectations WHERE departement_id = ?")->execute([$departement_id]) ? $stmt->fetchColumn() : 0
    ];

} catch(PDOException $e) {
    $error = "Erreur de base de données : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Chef de département</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1E90FF;
            --primary-green: #2ecc71;
            --primary-orange: #e67e22;
            --primary-red: #e74c3c;
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-blue {
            border-left: 5px solid var(--primary-blue);
        }
        
        .card-green {
            border-left: 5px solid var(--primary-green);
        }
        
        .card-orange {
            border-left: 5px solid var(--primary-orange);
        }
        
        .card-red {
            border-left: 5px solid var(--primary-red);
        }
        
        .icon-blue {
            color: var(--primary-blue);
        }
        
        .icon-green {
            color: var(--primary-green);
        }
        
        .icon-orange {
            color: var(--primary-orange);
        }
        
        .icon-red {
            color: var(--primary-red);
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb2d);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <?php include 'navbar_chef.php'; ?>
    
    <div class="container mt-4">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="welcome-banner">
            <h2><i class="fas fa-university me-2"></i>Bienvenue dans le département <?= htmlspecialchars($departement_nom) ?></h2>
            <p class="mb-0">Gérez les unités d'enseignement, les enseignants et les affectations de votre département.</p>
        </div>
        
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card card-blue h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted">Total UE</h6>
                                <h3><?= $stats['total_ue'] ?? 0 ?></h3>
                            </div>
                            <div class="icon-blue">
                                <i class="fas fa-book-open fa-3x"></i>
                            </div>
                        </div>
                        <a href="ue_vacantes.php" class="btn btn-sm btn-outline-primary mt-3">Voir les UE</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-4">
                <div class="card card-green h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted">UE Vacantes</h6>
                                <h3><?= $stats['ue_vacantes'] ?? 0 ?></h3>
                            </div>
                            <div class="icon-green">
                                <i class="fas fa-clipboard-check fa-3x"></i>
                            </div>
                        </div>
                        <a href="ue_vacantes.php" class="btn btn-sm btn-outline-success mt-3">Gérer les UE vacantes</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-4">
                <div class="card card-orange h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted">Enseignants</h6>
                                <h3><?= $stats['total_enseignants'] ?? 0 ?></h3>
                            </div>
                            <div class="icon-orange">
                                <i class="fas fa-chalkboard-teacher fa-3x"></i>
                            </div>
                        </div>
                        <a href="gestion_enseignants.php" class="btn btn-sm btn-outline-warning mt-3">Gérer les enseignants</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-4">
                <div class="card card-red h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted">Affectations</h6>
                                <h3><?= $stats['total_affectations'] ?? 0 ?></h3>
                            </div>
                            <div class="icon-red">
                                <i class="fas fa-tasks fa-3x"></i>
                            </div>
                        </div>
                        <a href="affectations.php" class="btn btn-sm btn-outline-danger mt-3">Gérer les affectations</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notifications récentes</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted text-center">Aucune notification récente</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Calendrier</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted text-center">Aucun événement à venir</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
