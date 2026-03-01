<?php
// Démarrer la session
session_start();

// Afficher les erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure le fichier de configuration
require_once 'config.php';

// Traiter le formulaire de connexion
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
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

            // Vérifier si l'utilisateur existe
            $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Vérifier le mot de passe (en supposant qu'il est stocké en texte brut pour simplifier)
                if ($password === $user['mot_de_passe']) {
                    // Créer la session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['user_type'] = $user['type_utilisateur'];
                    $_SESSION['type_utilisateur'] = $user['type_utilisateur']; // Pour la compatibilité avec charge_horaire.php
                    $_SESSION['role'] = $user['type_utilisateur']; // Pour la compatibilité
                    $_SESSION['id_departement'] = $user['id_departement'];
                    $_SESSION['departement_id'] = $user['id_departement']; // Pour la compatibilité

                    // Rediriger vers le tableau de bord approprié
                    if ($user['type_utilisateur'] === 'chef_departement') {
                        header("Location: chef_dashboard.php");
                    } else {
                        header("Location: dashboard.php");
                    }
                    exit;
                } else {
                    $error = "Mot de passe incorrect.";
                }
            } else {
                $error = "Cet utilisateur n'existe pas.";
            }
        } catch (PDOException $e) {
            $error = "Erreur de connexion à la base de données: " . $e->getMessage();
        }
    }
}

// Récupérer le message d'erreur de l'URL
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'session_invalide':
            $error = "Votre session a expiré. Veuillez vous reconnecter.";
            break;
        case 'acces_refuse':
            $error = "Vous n'avez pas les droits nécessaires pour accéder à cette page.";
            break;
        case 'no_department':
            $error = "Aucun département n'est associé à votre compte.";
            break;
    }
}

// Message de déconnexion
$logout_message = '';
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    $logout_message = "Vous avez été déconnecté avec succès.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(rgba(10, 25, 47, 0.85), rgba(108, 27, 145, 0.85)),
                       url('images/background.jpg') center/cover fixed;
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-container {
            background: rgba(10, 25, 47, 0.9);
            border: 2px solid #1E90FF;
            border-radius: 10px;
            padding: 30px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 5px 15px rgba(30, 144, 255, 0.3);
        }

        .form-control {
            background-color: rgba(10, 25, 47, 0.7);
            color: white;
            border: 1px solid #1E90FF;
            margin-bottom: 15px;
        }

        .form-control:focus {
            background-color: rgba(10, 25, 47, 0.9);
            color: white;
            border-color: #FF00FF;
            box-shadow: 0 0 0 0.25rem rgba(255, 0, 255, 0.25);
        }

        .btn-primary {
            background: linear-gradient(90deg, #1E90FF, #FF00FF);
            border: none;
            width: 100%;
            padding: 10px;
            margin-top: 10px;
        }

        .btn-primary:hover {
            background: linear-gradient(90deg, #FF00FF, #1E90FF);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 0, 255, 0.4);
        }

        .links {
            margin-top: 20px;
            text-align: center;
        }

        .links a {
            color: #1E90FF;
            text-decoration: none;
        }

        .links a:hover {
            color: #FF00FF;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="text-center mb-4">Connexion</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($logout_message)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($logout_message) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Mot de passe</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Se connecter</button>
        </form>

        <div class="links">
            <a href="debug_session.php">Déboguer la session</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
