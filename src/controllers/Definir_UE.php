<?php
// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "gestion_coordinteur");

if ($conn->connect_error) {
    die("Connexion échouée : " . $conn->connect_error);
}

// Insertion avec validation des données
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ajouter_ue'])) {
    // Récupération et nettoyage des données
    $id_matiere = sanitize_input($_POST["id_matiere"]);
    $filiere = sanitize_input($_POST["filiere"]);
    $niveau = isset($_POST["niveau"]) ? sanitize_input($_POST["niveau"]) : "GI1"; // Valeur par défaut
    $annee = sanitize_input($_POST["annee"]);
    $type = sanitize_input($_POST["type"]);
    $volume = sanitize_input($_POST["volume"]);

    // Validation des données
    if (empty($id_matiere) || empty($filiere) || empty($annee) || empty($type) || empty($volume)) {
        $error_message = "Tous les champs sont obligatoires.";
    } elseif (!is_numeric($volume) || $volume <= 0) {
        $error_message = "Le volume horaire doit être un nombre positif.";
    } elseif (!preg_match("/^\d{4}-\d{4}$/", $annee)) {
        $error_message = "Le format de l'année scolaire doit être YYYY-YYYY (ex: 2024-2025).";
    } else {
        // Vérifier si cette UE existe déjà
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM unites_enseignements WHERE id_matiere = ? AND filiere = ? AND type_enseignement = ? AND annee_scolaire = ?");
        $check_stmt->bind_param("isss", $id_matiere, $filiere, $type, $annee);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['count'] > 0) {
            $error_message = "Cette unité d'enseignement existe déjà.";
        } else {
            // Insertion des données
            $stmt = $conn->prepare("INSERT INTO unites_enseignements (id_matiere, filiere, niveau, annee_scolaire, type_enseignement, volume_horaire) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssi", $id_matiere, $filiere, $niveau, $annee, $type, $volume);

            if ($stmt->execute()) {
                // Redirection vers chef_dashboard.php
                header("Location: chef_dashboard.php");
                exit();
            } else {
                $error_message = "Erreur lors de l'ajout de l'UE: " . $stmt->error;
            }
        }
    }
}

// Fonction pour valider et nettoyer les entrées
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Initialisation des variables pour les messages
$success_message = "";
$error_message = "";

// Suppression avec requête préparée
if (isset($_GET['delete'])) {
    $id = sanitize_input($_GET['delete']);

    // Vérifier si l'UE est utilisée dans d'autres tables
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM affectations_vacataires WHERE id_ue = ?");
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
        // L'UE est utilisée, ne pas supprimer
        header("Location: chef_dashboard.php");
        exit();
    } else {
        // L'UE n'est pas utilisée, on peut la supprimer
        $stmt = $conn->prepare("DELETE FROM unites_enseignements WHERE id_ue = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        header("Location: chef_dashboard.php");
        exit();
    }
}

// Récupération des matières avec requête préparée
$matieres_stmt = $conn->prepare("SELECT id_matiere, nom FROM matieres ORDER BY nom ASC");
$matieres_stmt->execute();
$matieres = $matieres_stmt->get_result();

// Récupération des UE avec requête préparée et tri
$ues_stmt = $conn->prepare("SELECT ue.*, m.nom AS nom_matiere
                          FROM unites_enseignements ue
                          JOIN matieres m ON ue.id_matiere = m.id_matiere
                          ORDER BY ue.annee_scolaire DESC, m.nom ASC");
$ues_stmt->execute();
$ues = $ues_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Définir les Unités d'Enseignement</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Variables de couleur */
        :root {
            --primary-purple: #6a11cb;
            --secondary-purple: #5a0cb2;
            --light-bg: #f5f7ff;
            --dark-bg: rgba(10, 25, 47, 0.85);
            --card-bg: rgba(10, 25, 47, 0.9);
            --hover-color: rgba(106, 17, 203, 0.2);
            --text-color: #333333;
            --border-radius: 20px;
            --box-shadow: 0 5px 15px rgba(106, 17, 203, 0.3);
        }

        /* Style général avec fond dégradé comme la page de connexion */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7ff 0%, #c3c7f7 100%);
            margin: 0;
            padding: 0;
            color: var(--text-color);
            position: relative;
            min-height: 100vh;
            scroll-behavior: smooth;
            overflow-x: hidden;
        }

        /* Formes décoratives comme sur la page de connexion */
        body::before {
            content: '';
            position: absolute;
            top: -10%;
            right: -10%;
            width: 40%;
            height: 40%;
            background-color: #d9d2ff;
            border-radius: 50%;
            z-index: 0;
        }

        body::after {
            content: '';
            position: absolute;
            bottom: -5%;
            left: 0;
            width: 100%;
            height: 30%;
            background: linear-gradient(180deg, transparent 0%, var(--primary-purple) 100%);
            border-radius: 50% 50% 0 0 / 100% 100% 0 0;
            z-index: 0;
        }

        /* Overlay semi-transparent - retiré car nous utilisons un gradient */

        /* Style du header */
        header {
            width: 250px;
            background-color: #1e2a3a; /* Couleur bleu foncé de la navbar */
            color: #fff;
            position: fixed;
            height: 100%;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 2px 0 8px rgba(0, 0, 0, 0.4);
            z-index: 1;
        }

        /* Style pour la sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        /* Style du titre du header */
        .sidebar h1 {
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 40px;
            letter-spacing: 2px;
            color: #fff;
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }

        /* Style de la navigation */
        nav ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        nav ul li {
            margin: 15px 0;
        }

        nav ul li a {
            color: #ecf0f1;
            text-decoration: none;
            font-size: 18px;
            display: block;
            padding: 12px 20px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        /* Effet au survol des liens */
        nav ul li a:hover {
            background-color: rgba(255, 255, 255, 0.2);
            padding-left: 30px;
            color: #fff;
            text-shadow: 0 0 5px rgba(255, 255, 255, 0.5);
        }

        /* Style pour le lien actif */
        nav ul li a.active {
            background-color: rgba(255, 255, 255, 0.3);
            color: white;
            border-left: 4px solid #fff;
            padding-left: 26px;
            font-weight: 600;
        }

        nav ul li a.active:hover {
            padding-left: 30px;
        }

        /* Style pour les icônes */
        .me-2 {
            margin-right: 8px;
        }

        /* Contenu principal */
        .content {
            margin-left: 250px;
            padding: 30px;
            width: calc(100% - 250px);
            position: relative;
            z-index: 1;
        }

        /* Style des titres */
        h2 {
            color: var(--primary-purple);
            font-size: 2rem;
            margin-bottom: 30px;
            text-shadow: 0 0 10px rgba(106, 17, 203, 0.5);
            text-align: center;
        }

        /* Formulaire */
        form {
            background-color: #ffffff;
            padding: 40px;
            border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(106, 17, 203, 0.1);
            width: 100%;
            max-width: 800px;
            margin: 0 auto 50px;
            position: relative;
            overflow: hidden;
            animation: fadeIn 1s ease;
        }

        form::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(106, 17, 203, 0.05) 0%, transparent 70%);
            animation: formGlow 10s infinite linear;
            z-index: -1;
        }

        @keyframes formGlow {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
            transition: all 0.3s ease;
        }

        label {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--primary-purple);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        label i {
            margin-right: 8px;
            font-size: 1.2rem;
            color: var(--secondary-purple);
        }

        select, input, button {
            width: 100%;
            padding: 12px 15px;
            margin-top: 6px;
            margin-bottom: 25px;
            border: 1px solid #e0e0ff;
            border-radius: 30px;
            background-color: var(--light-bg);
            color: var(--text-color);
            font-size: 1rem;
            transition: all 0.3s ease;
            position: relative;
        }

        select:focus, input:focus {
            outline: none;
            border-color: var(--primary-purple);
            box-shadow: 0 0 0 3px rgba(106, 17, 203, 0.1);
            background-color: #ffffff;
        }

        button[type="submit"] {
            background-color: var(--primary-purple);
            color: #ffffff;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(106, 17, 203, 0.3);
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
            border: none;
        }

        button[type="submit"]::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: all 0.5s ease;
        }

        button[type="submit"]:hover {
            background-color: var(--secondary-purple);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(106, 17, 203, 0.4);
        }

        button[type="submit"]:hover::before {
            left: 100%;
        }

        /* Animations pour le formulaire */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Tableaux */
        .table-container {
            position: relative;
            margin-top: 30px;
            animation: fadeIn 1s ease;
        }

        table {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            border-collapse: separate;
            border-spacing: 0;
            background-color: #ffffff;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(106, 17, 203, 0.1);
            border: 1px solid #e0e0ff;
            animation: fadeIn 1s ease;
            table-layout: fixed; /* Pour des colonnes de largeur fixe */
        }

        th, td {
            text-align: left;
            padding: 15px;
            border-bottom: 1px solid rgba(106, 17, 203, 0.1);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        th {
            background-color: var(--light-bg);
            color: var(--text-color);
            font-size: 1.1rem;
            font-weight: 600;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        th i {
            margin-right: 8px;
            color: var(--primary-purple);
        }

        th:hover {
            background-color: rgba(106, 17, 203, 0.1);
        }

        th::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, var(--primary-purple) 0%, var(--secondary-purple) 100%);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }

        th:hover::after {
            transform: scaleX(1);
        }

        td {
            font-size: 1rem;
            color: var(--text-color);
            transition: all 0.3s ease;
        }

        tr {
            transition: all 0.3s ease;
        }

        tr:hover {
            background-color: rgba(106, 17, 203, 0.05);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.05);
        }

        tr:nth-child(odd) {
            background-color: #ffffff;
        }

        tr:nth-child(even) {
            background-color: var(--light-bg);
        }

        /* Boutons */
        .btn {
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn i {
            margin-right: 5px;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
            border: none;
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.2);
        }

        .btn-danger:hover {
            background-color: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(220, 53, 69, 0.3);
        }

        /* Footer */
        footer {
            margin-left: 250px;
            padding: 20px;
            text-align: center;
            color: #fff;
            background-color: #5a0cb2;
            position: relative;
            z-index: 1;
        }

        /* Alertes */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            width: 100%;
            max-width: 800px;
            animation: fadeIn 0.5s ease;
            box-shadow: 0 10px 30px rgba(106, 17, 203, 0.1);
            background-color: #fff;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 4px;
        }

        .alert-success {
            color: #28a745;
        }

        .alert-success::before {
            background-color: #28a745;
        }

        .alert-danger {
            color: #dc3545;
        }

        .alert-danger::before {
            background-color: #dc3545;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
        }

        .badge-primary {
            background-color: rgba(106, 17, 203, 0.1);
            color: var(--primary-purple);
            border: 1px solid var(--primary-purple);
        }

        .badge-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid #28a745;
        }

        .badge-warning {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border: 1px solid #ffc107;
        }

        /* Liens */
        .liste-link {
            display: inline-block;
            margin-top: 15px;
            color: var(--primary-purple);
            font-weight: 500;
            font-size: 1.1rem;
            text-decoration: none;
            transition: all 0.3s ease;
            padding: 10px 20px;
            background-color: var(--light-bg);
            border-radius: 30px;
            border: 1px solid #e0e0ff;
            display: inline-flex;
            align-items: center;
        }

        .liste-link i {
            margin-right: 8px;
        }

        .liste-link:hover {
            color: var(--secondary-purple);
            transform: translateY(-2px);
            background-color: rgba(106, 17, 203, 0.1);
            border-color: var(--primary-purple);
            box-shadow: var(--box-shadow);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .content {
                padding: 20px;
                margin-left: 250px;
            }

            form {
                max-width: 700px;
            }

            table {
                max-width: 900px;
            }
        }

        @media (max-width: 992px) {
            .content {
                margin-left: 250px;
                width: calc(100% - 250px);
            }

            form {
                padding: 30px;
                max-width: 100%;
            }

            table {
                max-width: 100%;
            }
        }

        @media (max-width: 768px) {
            header {
                width: 100%;
                height: auto;
                position: relative;
                padding: 15px;
            }

            .content, footer {
                margin-left: 0;
                width: 100%;
                padding: 15px;
            }

            form {
                padding: 20px;
                width: 100%;
            }

            select, input, button {
                padding: 12px;
            }

            .alert {
                max-width: 100%;
            }

            table {
                font-size: 0.85rem;
            }

            th, td {
                padding: 8px 10px;
            }
        }

        @media (max-width: 576px) {
            form {
                padding: 15px;
            }

            .liste-link {
                width: 100%;
                justify-content: center;
            }
        }
        .content {
    margin-left: 250px;
    padding: 30px;
    width: calc(100% - 250px);
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center; /* Centre horizontalement */
}

table {
    width: 100%;
    max-width: 1200px; /* Limite la largeur maximale */
    margin: 30px auto; /* Centre la table horizontalement */
    /* ... autres propriétés existantes ... */
}
    </style>
</head>
<body>

<header>
    <div class="sidebar">
        <h1>Gestion des coordinateurs</h1>
        <nav>
            <ul>
                <li><a href="gerer_groupes.php"><i class="fas fa-users me-2"></i> Gérer groupes TP/TD</a></li>
                <li><a href="gerer_emplois_temps.php"><i class="fas fa-calendar-alt me-2"></i> Gérer emplois temps</a></li>
                <li><a href="affectation_vactaire.php"><i class="fas fa-user-tie me-2"></i> Affectation des Vacataires</a></li>
                <li><a href="chef_dashboard.php" class="active"><i class="fas fa-tachometer-alt me-2"></i> Tableau de bord</a></li>
                <li><a href="Extraire_D_Excel.php"><i class="fas fa-file-excel me-2"></i> Extraire en Excel</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Déconnexion</a></li>
            </ul>
        </nav>
    </div>
</header>

<div class="content">
    <!-- Affichage des messages de succès -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php
                switch($_GET['success']) {
                    case 1:
                        echo "L'unité d'enseignement a été supprimée avec succès.";
                        break;
                    case 2:
                        echo "L'unité d'enseignement a été ajoutée avec succès.";
                        break;
                    default:
                        echo "Opération réussie.";
                }
            ?>
        </div>
    <?php endif; ?>

    <!-- Affichage des messages d'erreur -->
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">
            <?php
                switch($_GET['error']) {
                    case 1:
                        echo "Impossible de supprimer cette unité d'enseignement car elle est utilisée dans des affectations.";
                        break;
                    default:
                        echo "Une erreur est survenue.";
                }
            ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <?php if (!isset($_GET['page']) || $_GET['page'] == 'ajouter'): ?>
        <!-- Page formulaire d'ajout -->
        <h2>Définir une Unité d'Enseignement</h2>

        <form method="POST" action="">
            <div class="form-group">
                <label><i class="fas fa-book-open"></i> Matière :</label>
                <select name="id_matiere" required>
                    <option value="">-- Choisir une matière --</option>
                    <?php while($m = $matieres->fetch_assoc()): ?>
                        <option value="<?= $m['id_matiere'] ?>"><?= $m['nom'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label><i class="fas fa-graduation-cap"></i> Filière :</label>
                <input type="text" name="filiere" placeholder="Ex: Informatique, Génie Civil..." required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-layer-group"></i> Niveau :</label>
                <select name="niveau" required>
                    <option value="GI1">Première année cycle d'ingénieur (GI1)</option>
                    <option value="GI2">Deuxième année cycle d'ingénieur (GI2)</option>
                    <option value="GI3">Troisième année cycle d'ingénieur (GI3)</option>
                    <option value="Master">Master</option>
                    <option value="Doctorat">Doctorat</option>
                </select>
            </div>

            <div class="form-group">
                <label><i class="fas fa-calendar-alt"></i> Année Scolaire :</label>
                <input type="text" name="annee" placeholder="2024-2025" required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-chalkboard"></i> Type d'enseignement :</label>
                <select name="type" required>
                    <option value="Cours">Cours</option>
                    <option value="TD">TD</option>
                    <option value="TP">TP</option>
                </select>
            </div>

            <div class="form-group">
                <label><i class="fas fa-clock"></i> Volume horaire :</label>
                <input type="number" name="volume" min="1" required>
            </div>

            <button type="submit" name="ajouter_ue"><i class="fas fa-plus-circle"></i> Ajouter l'unité d'enseignement</button>
        </form>

        <a href="chef_dashboard.php" class="liste-link"><i class="fas fa-list"></i> Retour au tableau de bord</a>

    <?php elseif ($_GET['page'] == 'liste'): ?>
        <!-- Page liste des UE -->
        <h2>Liste des Unités d'Enseignement</h2>
        <a href="chef_dashboard.php" class="liste-link"><i class="fas fa-arrow-left"></i> Retour au tableau de bord</a>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> ID</th>
                        <th><i class="fas fa-book-open"></i> Matière</th>
                        <th><i class="fas fa-graduation-cap"></i> Filière</th>
                        <th><i class="fas fa-layer-group"></i> Niveau</th>
                        <th><i class="fas fa-calendar-alt"></i> Année</th>
                        <th><i class="fas fa-chalkboard"></i> Type</th>
                        <th><i class="fas fa-clock"></i> Volume horaire</th>
                        <th><i class="fas fa-cogs"></i> Actions</th>
                    </tr>
                </thead>
            <tbody>
                <?php while($ue = $ues->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($ue['id_ue']) ?></td>
                    <td><?= htmlspecialchars($ue['nom_matiere']) ?></td>
                    <td><?= htmlspecialchars($ue['filiere']) ?></td>
                    <td><?= htmlspecialchars($ue['niveau']) ?></td>
                    <td><?= htmlspecialchars($ue['annee_scolaire']) ?></td>
                    <td>
                        <span class="badge badge-<?= $ue['type_enseignement'] == 'Cours' ? 'primary' : ($ue['type_enseignement'] == 'TD' ? 'success' : 'warning') ?>">
                            <?= htmlspecialchars($ue['type_enseignement']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($ue['volume_horaire']) ?> h</td>
                    <td>
                        <a class="btn btn-danger" href="?delete=<?= $ue['id_ue'] ?>&page=liste" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette unité d\'enseignement ?')">
                            <i class="fas fa-trash-alt"></i> Supprimer
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>




</body>
</html>