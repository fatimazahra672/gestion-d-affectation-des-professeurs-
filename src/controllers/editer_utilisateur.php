<?php
require_once 'config.php';

// Vérification session et rôle admin
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
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

    // Récupération des départements
    $departements = $pdo->query("SELECT departement_id, nom FROM departements")->fetchAll();

    // Récupération utilisateur
    $user = null;
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $_SESSION['error'] = "Utilisateur introuvable";
            header("Location: utilisateurs.php");
            exit();
        }
    }

} catch (PDOException $e) {
    die("Erreur de base de données : " . htmlspecialchars($e->getMessage()));
}

// Traitement formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $allowed_roles = ['professeur', 'chef_departement', 'coordinateur', 'vacataire', 'admin'];
        $role = $_POST['role'];
        
        if (!in_array($role, $allowed_roles)) {
            throw new Exception("Rôle non autorisé");
        }

        // Construction requête dynamique
        $update_data = [
            'email' => $_POST['email'],
            'role' => $role,
            'id_departement' => !empty($_POST['id_departement']) ? $_POST['id_departement'] : null
        ];

        if (!empty($_POST['password'])) {
            $update_data['mot_de_passe'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $sql = "UPDATE utilisateurs SET ";
        $set = [];
        foreach ($update_data as $key => $value) {
            $set[] = "$key = ?";
        }
        $sql .= implode(', ', $set) . " WHERE id = ?";
        
        $params = array_values($update_data);
        $params[] = $_POST['id'];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $_SESSION['success'] = "Utilisateur mis à jour !";
        header("Location: utilisateurs.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur : " . htmlspecialchars($e->getMessage());
        header("Location: editer_utilisateur.php?id=" . $_POST['id']);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Utilisateur - ENSAH</title>
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
        <!-- Sidebar mis à jour -->
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
                <li class="nav-item">
                    <a class="nav-link" href="affectation_roles.php">
                        <i class="fas fa-user-tie me-2"></i>Affectation Rôles
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="gestion_specialites.php">
                        <i class="fas fa-book me-2"></i>Spécialités
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="import_export.php">
                        <i class="fas fa-file-excel me-2"></i>Import/Export Excel
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
            <h2 class="mb-4"><i class="fas fa-user-edit me-2"></i>Modifier Utilisateur</h2>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger mb-4">
                    <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if ($user): ?>
            <form method="POST" class="border p-4 rounded" style="background: rgba(10, 25, 47, 0.7);">
                <input type="hidden" name="id" value="<?= htmlspecialchars($user['id']) ?>">

                <div class="mb-3">
                    <label for="email" class="form-label">Email :</label>
                    <input type="email" name="email" id="email" required 
                           class="form-control form-control-lg"
                           value="<?= htmlspecialchars($user['email']) ?>">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Nouveau mot de passe :</label>
                    <input type="password" name="password" id="password"
                           class="form-control form-control-lg"
                           placeholder="Laisser vide pour ne pas changer"
                           pattern=".{8,}" title="8 caractères minimum">
                </div>

                <div class="mb-3">
                    <label for="role" class="form-label">Rôle :</label>
                    <select name="role" id="role" required class="form-select form-select-lg">
                        <?php foreach (['admin', 'professeur', 'chef_departement', 'coordinateur', 'vacataire'] as $role): ?>
                            <option value="<?= $role ?>" <?= $user['role'] === $role ? 'selected' : '' ?>>
                                <?= ucfirst($role) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="id_departement" class="form-label">Département :</label>
                    <select name="id_departement" id="id_departement" class="form-select form-select-lg">
                        <option value="">-- Aucun département --</option>
                        <?php foreach ($departements as $dep): ?>
                            <option value="<?= htmlspecialchars($dep['departement_id']) ?>" 
                                <?= $dep['departement_id'] == $user['id_departement'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dep['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-warning btn-lg px-5">
                        <i class="fas fa-save me-2"></i>Mettre à jour
                    </button>
                    <a href="utilisateurs.php" class="btn btn-outline-light btn-lg px-5">
                        <i class="fas fa-times me-2"></i>Annuler
                    </a>
                </div>
            </form>
            <?php endif; ?>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>