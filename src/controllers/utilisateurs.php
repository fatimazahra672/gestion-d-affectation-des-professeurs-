<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Génération token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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

    $sql = "
        SELECT 
            u.id,
            u.email,
            u.role,
            u.date_creation,
            d.nom AS departement,
            COUNT(a.id) AS total_affectations
        FROM utilisateurs u
        LEFT JOIN departements d ON u.id_departement = d.departement_id
        LEFT JOIN affectations a ON u.id = a.utilisateur_id
        GROUP BY u.id
        ORDER BY u.date_creation DESC
    ";

    $users = $pdo->query($sql)->fetchAll();

} catch(PDOException $e) {
    die("Erreur SQL : " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Utilisateurs - ENSAH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1E90FF;
            --primary-magenta: magenta;
            --blue-transparent: rgba(30, 144, 255, 0.3);
            --dark-bg: #0a192f;
        }

        body {
            background: linear-gradient(rgba(10, 25, 47, 0.85), rgba(108, 27, 145, 0.85)),
                        url('images/background.jpg') center center/cover fixed;
            color: white;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            background: rgba(44, 62, 80, 0.95);
            backdrop-filter: blur(5px);
            border-right: 2px solid var(--primary-blue);
            box-shadow: 4px 0 15px var(--blue-transparent);
            animation: borderGlow 8s infinite alternate;
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
            position: relative;
            overflow: hidden;
        }

        .sidebar .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(18, 84, 151, 0.2), transparent);
            transition: 0.5s;
        }

        .sidebar .nav-link:hover {
            background: rgba(30, 144, 255, 0.1);
            transform: translateX(10px);
        }

        .sidebar .nav-link:hover::before {
            left: 100%;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(30, 144, 255, 0.1);
        }

        .badge-admin { background-color: #dc3545; }
        .badge-chef { background-color: #ffc107; }
        .badge-prof { background-color: #28a745; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar identique au dashboard -->
        <nav class="col-md-3 col-lg-2 sidebar">
            <div class="text-center mb-4">
                <img src="images/logo.jpg" alt="ENSAH" class="img-fluid mb-3" style="filter: drop-shadow(0 0 5px var(--primary-blue));">
                <h5 class="text-white" style="text-shadow: 0 0 10px var(--primary-blue);">Administration ENSAH</h5>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="admin_dashboard.php">
                        <i class="fas fa-chart-line me-2"></i>Tableau de bord
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="utilisateurs.php" style="
                        background: rgba(30, 144, 255, 0.2);
                        border-left: 4px solid var(--primary-blue);
                        transform: translateX(8px);
                    ">
                        <i class="fas fa-users-cog me-2"></i>Gestion Utilisateurs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="gestion_departements.php">
                        <i class="fas fa-building me-2"></i>Gestion Départements
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link text-danger" href="logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Contenu principal -->
        <main class="col-md-9 ms-sm-auto col-lg-10 p-4">
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div class="d-flex justify-content-between mb-4 align-items-center">
                <h2 class="mb-0">
                    <i class="fas fa-users-cog me-2"></i>
                    Liste des Utilisateurs
                </h2>
                <a href="creer_utilisateur.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i>Nouvel Utilisateur
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Département</th>
                            <th>Affectations</th>
                            <th>Création</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['id']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <span class="badge rounded-pill <?= match($user['role']) {
                                    'admin' => 'badge-admin',
                                    'chef_departement' => 'badge-chef',
                                    default => 'badge-prof'
                                } ?>">
                                    <?= htmlspecialchars($user['role']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($user['departement'] ?? 'Non affecté') ?></td>
                            <td>
                                <span class="badge bg-info">
                                    <?= htmlspecialchars($user['total_affectations']) ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($user['date_creation'])) ?></td>
                            <td>
                                <a href="editer_utilisateur.php?id=<?= htmlspecialchars($user['id']) ?>" 
                                   class="btn btn-sm btn-warning me-2">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="supprimer_utilisateur.php" method="POST" class="d-inline">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($user['id']) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <button type="submit" 
                                            class="btn btn-sm btn-danger" 
                                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>