<?php
session_start();

if (!isset($_SESSION['user_type'])) {
    header('Location: ../login_coordinateur.php');
    exit();
}

if ($_SESSION['user_type'] !== 'enseignant') {
    header('Location: ../login_coordinateur.php');
    exit();
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ../login_coordinateur.php');
    exit();
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=gestion-coordinteur;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$info = null;

if (isset($_SESSION['id_enseignant'])) {
    $id_enseignant = $_SESSION['id_enseignant'];
    $enseignant = $pdo->prepare("SELECT * FROM enseignants WHERE id_enseignant = ?");
    $enseignant->execute([$id_enseignant]);
    $info = $enseignant->fetch();
} elseif ($user_type === 'enseignant' && $user_id) {
    $enseignant = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ? AND type_utilisateur = 'enseignant'");
    $enseignant->execute([$user_id]);
    $user_info = $enseignant->fetch();

    if ($user_info) {
        $info = array(
            'id_enseignant' => $user_id,
            'nom' => isset($user_info['nom']) ? $user_info['nom'] : 'Non défini',
            'prenom' => isset($user_info['prenom']) ? $user_info['prenom'] : 'Non défini',
            'email' => isset($user_info['email']) ? $user_info['email'] : 'Non défini',
            'specialite' => 'Non définie'
        );

        if (!empty($user_info['id_specialite'])) {
            $stmt = $pdo->prepare("SELECT nom_specialite FROM specialite WHERE id_specialite = ?");
            $stmt->execute([$user_info['id_specialite']]);
            $specialite = $stmt->fetch();

            if ($specialite) {
                $info['specialite'] = $specialite['nom_specialite'];
            }
        }

        $_SESSION['id_enseignant'] = $user_id;
    }
}

if (!$info) {
    $_SESSION['error'] = "Aucune information d'enseignant trouvée.";
    header('Location: ../login_coordinateur.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Enseignant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: url('../image copy 4.png') no-repeat center center fixed;
            background-size: cover;
            color: white;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            display: flex;
        }
        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(10, 25, 47, 0.85);
            z-index: -1;
        }
        .sidebar {
            width: 250px;
            background-color: rgba(10, 25, 47, 0.95);
            padding: 2rem 1rem;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            border-right: 2px solid magenta;
            box-shadow: 2px 0 10px rgba(0,0,0,0.2);
        }
        .sidebar a {
            display: block;
            padding: 10px 15px;
            color: white;
            text-decoration: none;
            margin-bottom: 10px;
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 6px;
            transition: all 0.3s;
        }
        .sidebar a:hover {
            background-color: #1E90FF;
            color: white;
        }
        .main-content {
            margin-left: 250px;
            width: calc(100% - 250px);
        }
        .header-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            background: linear-gradient(90deg, #1E90FF 0%, #0a192f 100%);
            border-bottom: 2px solid magenta;
        }
        .header-title {
            font-size: 2.5rem;
            font-weight: bold;
            text-shadow: 0 0 10px #1E90FF;
        }
        .container {
            padding: 2rem;
        }
        .card {
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid #1E90FF;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 0 10px rgba(30, 144, 255, 0.2);
        }
        .sidebar img {
            max-width: 150px;
            width: 100%;
            height: auto;
            display: block;
            margin: 0 auto 20px;
        }
        .container > .card h3, .container > .card p {
            color: white !important;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <img src="../image copy 5.png" alt="Logo">
        <a href="../Affichage_liste_UE.php">Affichage la liste de UE</a>
        <a href="../souhaits_enseignants.php">Souhaits Enseignants</a>
        <a href="../Calcul_automatique_charge_horaire.php">Calcul automatique de la charge horaire</a>
        <a href="../Notification_non-respect_charge_minimale.php">Notification en cas de non-respect de la charge minimale</a>
        <a href="../Consulter_modules_assurés_assure.php">Consulter la liste des modules assurés et qu'il assure</a>
        <a href="../Uploader_notes_session_normale_rattrapage.php">Uploader les notes de la session normale et rattrapage</a>
        <a href="../Consulter_historique_années_passées.php">Consulter l'historique des années passées</a>
        <a href="?logout=true" class="btn btn-danger w-100 mt-3">Déconnexion</a>
    </div>
    <div class="main-content">
        <div class="header-container">
            <div class="header-title">Tableau de Bord - Enseignant</div>
        </div>
        <div class="container" style="float: right; width: 400px; color: white !important;">
            <div class="card p-3">
                <h3>Bienvenue <?php echo htmlspecialchars($info['prenom'] . ' ' . $info['nom']); ?></h3>
                <p>Email : <?php echo htmlspecialchars($info['email']); ?> | Spécialité : <?php echo htmlspecialchars($info['specialite']); ?></p>
            </div>
        </div>
    </div>
</body>
</html>
