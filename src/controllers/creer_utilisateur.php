<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Génération du token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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

    $departements = $pdo->query("SELECT departement_id, nom FROM departements")->fetchAll();

} catch (PDOException $e) {
    die("Erreur de base de données : " . htmlspecialchars($e->getMessage()));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Vérification CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Token de sécurité invalide");
        }

        $allowed_roles = ['professeur', 'chef_departement', 'coordinateur', 'vacataire'];
        $role = $_POST['role'];
        
        if (!in_array($role, $allowed_roles)) {
            throw new Exception("Rôle non autorisé");
        }

        $stmt = $pdo->prepare("INSERT INTO utilisateurs (email, mot_de_passe, role, id_departement) VALUES (?, ?, ?, ?)");
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $id_departement = !empty($_POST['id_departement']) ? $_POST['id_departement'] : null;

        $stmt->execute([
            $_POST['email'],
            $password,
            $role,
            $id_departement
        ]);

        $_SESSION['success'] = "Utilisateur créé avec succès !";
        header("Location: utilisateurs.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur : " . htmlspecialchars($e->getMessage());
        header("Location: creer_utilisateur.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un utilisateur - ENSAH</title>
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

        .sidebar .nav-link:hover::before {
            left: 100%;
        }

        .sidebar .nav-link:hover {
            background: rgba(30, 144, 255, 0.1);
            transform: translateX(10px);
        }

        .form-control {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid var(--primary-blue);
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.2);
            border-color: var(--primary-magenta);
            box-shadow: 0 0 10px var(--blue-transparent);
        }

        .alert {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid var(--primary-blue);
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar complet -->
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
                
               
                <li class="nav-item mt-4">
                    <a class="nav-link text-danger" href="logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                    </a>
                </li>
            </ul>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 p-4">
            <h2 class="mb-4"><i class="fas fa-user-plus me-2"></i>Nouvel Utilisateur</h2>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger mb-4">
                    <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="border p-4 rounded" style="background: rgba(10, 25, 47, 0.7);">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="mb-3">
                    <label for="email" class="form-label">Email :</label>
                    <input type="email" name="email" id="email" required class="form-control form-control-lg">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Mot de passe :</label>
                    <input type="password" name="password" id="password" required 
                           class="form-control form-control-lg"
                           pattern=".{8,}" title="8 caractères minimum">
                </div>

                <div class="mb-3">
                    <label for="role" class="form-label">Rôle :</label>
                    <select name="role" id="role" required class="form-select form-select-lg">
                        <option value="">-- Sélectionnez un rôle --</option>
                        <option value="professeur">Professeur</option>
                        <option value="chef_departement">Chef de Département</option>
                        <option value="coordinateur">Coordinateur</option>
                        <option value="vacataire">Vacataire</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="id_departement" class="form-label">Département :</label>
                    <select name="id_departement" id="id_departement" class="form-select form-select-lg">
                        <option value="">-- Aucun département --</option>
                        <?php foreach ($departements as $dep): ?>
                            <option value="<?= htmlspecialchars($dep['departement_id']) ?>">
                                <?= htmlspecialchars($dep['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        <i class="fas fa-save me-2"></i>Créer
                    </button>
                    <a href="utilisateurs.php" class="btn btn-outline-light btn-lg px-5">
                        <i class="fas fa-times me-2"></i>Annuler
                    </a>
                </div>
            </form>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>