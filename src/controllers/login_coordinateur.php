<?php
session_start();

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'gestion_coordinteur';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Échec de la connexion : " . $conn->connect_error);
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$log_file = 'login_debug.log';
file_put_contents($log_file, "=== Nouvelle tentative de connexion ===\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $mot_de_passe = $_POST['mot_de_passe'];
    $user_found = false;

    file_put_contents($log_file, "Email: $email\n", FILE_APPEND);

    $stmt = $conn->prepare("SELECT * FROM coordinateurs WHERE email = ?");
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        file_put_contents($log_file, "Erreur préparation requête coordinateurs: " . $conn->error . "\n", FILE_APPEND);
    }

    if ($stmt && isset($result)) {
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($mot_de_passe, $user['mot_de_passe']) || $mot_de_passe === $user['mot_de_passe']) {
                $stmt_enseignant = $conn->prepare("SELECT * FROM enseignants WHERE id_enseignant = ?");
                if ($stmt_enseignant) {
                    $stmt_enseignant->bind_param("i", $user['id_enseignant']);
                    $stmt_enseignant->execute();
                    $enseignant = $stmt_enseignant->get_result()->fetch_assoc();
                } else {
                    $enseignant = array();
                }

                $_SESSION['user_id'] = $user['id_coordinateur'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['user_type'] = 'coordinateur';
                $_SESSION['id_enseignant'] = $user['id_enseignant'];
                $_SESSION['filiere'] = $user['filiere'];
                $_SESSION['annee_scolaire'] = $user['annee_scolaire'];
                $_SESSION['nom'] = isset($enseignant['nom']) ? $enseignant['nom'] : '';
                $_SESSION['prenom'] = isset($enseignant['prenom']) ? $enseignant['prenom'] : '';
                $_SESSION['specialite'] = isset($enseignant['specialite']) ? $enseignant['specialite'] : '';

                header("Location: dashborde_coordinateur.php");
                exit;
            } else {
                $erreur = "Mot de passe incorrect pour coordinateur.";
                $user_found = true;
            }
        }
    }

    if (!$user_found) {
        $stmt = $conn->prepare("SELECT * FROM enseignants WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if (password_verify($mot_de_passe, $user['mot_de_passe']) || $mot_de_passe === $user['mot_de_passe']) {
                    $_SESSION['user_id'] = $user['id_enseignant'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['user_type'] = 'enseignant';
                    $_SESSION['nom'] = isset($user['nom']) ? $user['nom'] : '';
                    $_SESSION['prenom'] = isset($user['prenom']) ? $user['prenom'] : '';
                    $_SESSION['specialite'] = isset($user['specialite']) ? $user['specialite'] : '';
                    $_SESSION['id_enseignant'] = $user['id_enseignant'];

                    header("Location:dashboard_enseignant.php");
                    exit;
                } else {
                    $erreur = "Mot de passe incorrect pour enseignant.";
                    $user_found = true;
                }
            }
        } else {
            file_put_contents($log_file, "Erreur préparation requête enseignants: " . $conn->error . "\n", FILE_APPEND);
        }
    }

    if (!$user_found) {
        $stmt = $conn->prepare("SELECT * FROM utilisateurs WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if (password_verify($mot_de_passe, $user['mot_de_passe']) || $mot_de_passe === $user['mot_de_passe']) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['user_type'] = $user['type_utilisateur'];
                    $_SESSION['nom'] = isset($user['nom']) ? $user['nom'] : '';
                    $_SESSION['prenom'] = isset($user['prenom']) ? $user['prenom'] : '';
                    $_SESSION['id_departement'] = isset($user['id_departement']) ? $user['id_departement'] : null;
                    $_SESSION['id_specialite'] = isset($user['id_specialite']) ? $user['id_specialite'] : null;

                    // CORRECTION ICI : Ajout de la condition pour vacataire
                    if ($user['type_utilisateur'] === 'coordinateur') {
                        header("Location: dashborde_coordinateur.php");
                        exit;
                    } elseif ($user['type_utilisateur'] === 'enseignant') {
                        header("Location: dashboard_enseignant.php");
                        exit;
                    } elseif ($user['type_utilisateur'] === 'admin') {
                        header("Location: admin_dashboard.php");
                        exit;
                    } elseif ($user['type_utilisateur'] === 'vacataire') {
                        header("Location: dashboard_vacataire.php");
                        exit;
                    } else {
                        header("Location: chef_dashboard.php");
                        exit;
                    }
                }
            }
        } else {
            file_put_contents($log_file, "Erreur préparation requête utilisateurs: " . $conn->error . "\n", FILE_APPEND);
        }
    }

    if (!$user_found) {
        $erreur = "Aucun utilisateur trouvé avec cet email.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7ff 0%, #c3c7f7 100%);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #333333;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: -10%;
            right: -10%;
            width: 40%;
            height: 40%;
            background-color: #d9d2ff;
            border-radius: 50%;
            z-index: -1;
        }

        body::after {
            content: '';
            position: absolute;
            bottom: -5%;
            left: 0;
            width: 100%;
            height: 30%;
            background: linear-gradient(180deg, transparent 0%, #6a11cb 100%);
            border-radius: 50% 50% 0 0 / 100% 100% 0 0;
            z-index: -1;
        }

        .login-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(106, 17, 203, 0.1);
            width: 90%;
            max-width: 450px;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .login-container h1 {
            font-size: 2.5rem;
            margin-bottom: 25px;
            font-weight: 700;
            color: #6a11cb;
            letter-spacing: 1px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0ff;
            border-radius: 30px;
            background-color: #f5f7ff;
            color: #333333;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            border-color: #6a11cb;
            box-shadow: 0 0 0 3px rgba(106, 17, 203, 0.1);
            background-color: #ffffff;
        }

        .forgot-password {
            display: block;
            text-align: right;
            color: #6a11cb;
            font-size: 0.85rem;
            margin-bottom: 15px;
            text-decoration: none;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        .login-button {
            width: 100%;
            padding: 12px 15px;
            background-color: #6a11cb;
            color: #ffffff;
            border: none;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(106, 17, 203, 0.3);
        }

        .login-button:hover {
            background-color: #5a0cb2;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(106, 17, 203, 0.4);
        }

        .signup-link {
            margin-top: 20px;
            display: block;
            color: #6a11cb;
            opacity: 0.9;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.9rem;
            text-align: center;
            text-decoration: none;
        }

        .signup-link:hover {
            color: #5a0cb2;
            opacity: 1;
            text-decoration: underline;
        }

        .error-message {
            color: #ff4757;
            margin-top: 15px;
            font-weight: 500;
            font-size: 0.9rem;
            text-align: center;
        }

        @media (max-width: 768px) {
            .login-container {
                padding: 30px 20px;
            }

            body::before, body::after {
                opacity: 0.5;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>WELCOME!</h1>
        <form method="post">
            <div class="form-group">
                <input type="email" name="email" id="email" placeholder="USERNAME" required>
            </div>
            <div class="form-group">
                <input type="password" name="mot_de_passe" id="mot_de_passe" placeholder="PASSWORD" required>
            </div>
            <a href="reset_password.php" class="forgot-password">Forgot Password?</a>
            <button type="submit" class="login-button">LOGIN</button>
        </form>

        <?php if (isset($erreur)) { ?>
            <div class="error-message"><?php echo $erreur; ?></div>
        <?php } ?>

        <a href="inscription.php" class="signup-link">CREATE ACCOUNT</a>
        <a href="login_enseignant.php" class="signup-link" style="margin-top: 10px;">CONNEXION ENSEIGNANT</a>
    </div>
</body>
</html>