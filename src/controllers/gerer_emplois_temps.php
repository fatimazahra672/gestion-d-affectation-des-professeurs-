<?php
$conn = new mysqli("localhost", "root", "", "gestion_coordinteur");

if ($conn->connect_error) {
    die("Erreur de connexion: " . $conn->connect_error);
}

$groupes = $conn->query("SELECT * FROM groupes WHERE actif = 1");
$salles = $conn->query("SELECT * FROM salles");
$creneaux = $conn->query("SELECT * FROM creneaux");

// Fonction pour valider et nettoyer les entrées
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Fonction pour vérifier les conflits de créneaux
function check_creneau_conflict($conn, $id_salle, $jour, $heure_debut, $heure_fin, $id_creneau = null) {
    $condition = $id_creneau ? "AND id_creneau != $id_creneau" : "";

    $query = "SELECT * FROM creneaux
              WHERE id_salle = ?
              AND jour = ?
              AND ((heure_debut <= ? AND heure_fin > ?)
                  OR (heure_debut < ? AND heure_fin >= ?)
                  OR (heure_debut >= ? AND heure_fin <= ?))
              $condition";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("isssssss", $id_salle, $jour, $heure_fin, $heure_debut, $heure_fin, $heure_debut, $heure_debut, $heure_fin);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->num_rows > 0;
}

// Initialisation des variables pour les messages
$success_message = "";
$error_message = "";

// Traitement de l'ajout d'un créneau
if (isset($_POST['ajouter'])) {
    // Récupération et nettoyage des données
    $id_groupe = sanitize_input($_POST['id_groupe']);
    $id_salle = sanitize_input($_POST['id_salle']);
    $jour = sanitize_input($_POST['jour']);
    $heure_debut = sanitize_input($_POST['heure_debut']);
    $heure_fin = sanitize_input($_POST['heure_fin']);
    $frequence = sanitize_input($_POST['frequence']);

    // Validation des données
    if (empty($id_groupe) || empty($id_salle) || empty($jour) || empty($heure_debut) || empty($heure_fin) || empty($frequence)) {
        $error_message = "Tous les champs sont obligatoires.";
    } elseif ($heure_debut >= $heure_fin) {
        $error_message = "L'heure de début doit être antérieure à l'heure de fin.";
    } elseif (check_creneau_conflict($conn, $id_salle, $jour, $heure_debut, $heure_fin)) {
        $error_message = "Ce créneau est en conflit avec un autre créneau existant pour cette salle.";
    } else {
        // Préparation et exécution de la requête
        $stmt = $conn->prepare("INSERT INTO creneaux (id_groupe, id_salle, jour, heure_debut, heure_fin, frequence)
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $id_groupe, $id_salle, $jour, $heure_debut, $heure_fin, $frequence);

        if ($stmt->execute()) {
            $success_message = "Créneau ajouté avec succès.";
            header("Location: gerer_emplois_temps.php?page=liste&success=1");
            exit();
        } else {
            $error_message = "Erreur lors de l'ajout du créneau: " . $stmt->error;
        }
    }
}

// Traitement de la modification d'un créneau
if (isset($_POST['modifier'])) {
    // Récupération et nettoyage des données
    $id = sanitize_input($_POST['id_creneau']);
    $id_groupe = sanitize_input($_POST['id_groupe']);
    $id_salle = sanitize_input($_POST['id_salle']);
    $jour = sanitize_input($_POST['jour']);
    $heure_debut = sanitize_input($_POST['heure_debut']);
    $heure_fin = sanitize_input($_POST['heure_fin']);
    $frequence = sanitize_input($_POST['frequence']);

    // Validation des données
    if (empty($id) || empty($id_groupe) || empty($id_salle) || empty($jour) || empty($heure_debut) || empty($heure_fin) || empty($frequence)) {
        $error_message = "Tous les champs sont obligatoires.";
    } elseif ($heure_debut >= $heure_fin) {
        $error_message = "L'heure de début doit être antérieure à l'heure de fin.";
    } elseif (check_creneau_conflict($conn, $id_salle, $jour, $heure_debut, $heure_fin, $id)) {
        $error_message = "Ce créneau est en conflit avec un autre créneau existant pour cette salle.";
    } else {
        // Préparation et exécution de la requête
        $stmt = $conn->prepare("UPDATE creneaux SET id_groupe=?, id_salle=?, jour=?,
                              heure_debut=?, heure_fin=?, frequence=?
                              WHERE id_creneau=?");
        $stmt->bind_param("iissssi", $id_groupe, $id_salle, $jour, $heure_debut, $heure_fin, $frequence, $id);

        if ($stmt->execute()) {
            $success_message = "Créneau modifié avec succès.";
            header("Location: gerer_emplois_temps.php?page=liste&success=2");
            exit();
        } else {
            $error_message = "Erreur lors de la modification du créneau: " . $stmt->error;
        }
    }
}

// Traitement de la suppression d'un créneau
if (isset($_POST['supprimer'])) {
    $id = sanitize_input($_POST['id_creneau']);

    // Préparation et exécution de la requête
    $stmt = $conn->prepare("DELETE FROM creneaux WHERE id_creneau=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $success_message = "Créneau supprimé avec succès.";
            header("Location: gerer_emplois_temps.php?page=liste&success=3");
            exit();
    } else {
        $error_message = "Erreur lors de la suppression du créneau: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des emplois du temps</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #6a0dad;
            --secondary-color: #8a2be2;
            --light-purple: #e6e6fa;
            --dark-purple: #4b0082;
            --accent-color: #00bfff;
            --teacher-color: #3498db;
            --teacher-dark: #2980b9;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fc;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Header */
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

        .header-logo {
            height: 50px;
            display: flex;
            align-items: center;
        }

        .header-logo img {
            height: 100%;
            filter: drop-shadow(0 0 5px rgba(255, 255, 255, 0.5));
        }

        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            padding-left: 15px;
            border-left: 1px solid rgba(255, 255, 255, 0.3);
        }

        .header-right {
            display: flex;
            gap: 20px;
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

        .user-info i {
            color: rgba(255, 255, 255, 0.9);
        }

        .user-info-label {
            font-weight: 600;
            color: rgba(255, 255, 255, 0.8);
        }

        .user-info-value {
            color: white;
            font-weight: 500;
        }

        /* Conteneur principal */
        .main-container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        /* ================ SIDEBAR MODERNE ================ */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--dark-purple) 0%, var(--primary-color) 100%);
            height: 100%;
            padding: 20px 0;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
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
        }

        .sidebar-header h3 {
            color: white;
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }

        .sidebar-menu {
            flex: 1;
            overflow-y: auto;
            padding: 0 15px;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            color: white;
            padding: 12px 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            text-decoration: none;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .nav-link:hover::before {
            left: 100%;
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            border-left: 4px solid var(--accent-color);
        }

        .nav-link i {
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
        }

        .nav-link span {
            flex: 1;
        }

        .logout-btn {
            background: rgba(255, 71, 87, 0.2);
            margin-top: 10px;
        }

        .logout-btn:hover {
            background: rgba(255, 71, 87, 0.3);
        }

        /* Contenu principal */
        .main-content {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            background-color: #f8f9fc;
        }

        /* Cartes de statistiques */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 24px;
        }

        .creneaux-icon {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }

        .salles-icon {
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
        }

        .groupes-icon {
            background: linear-gradient(135deg, var(--accent-color) 0%, #0077b6 100%);
        }

        .jour-icon {
            background: linear-gradient(135deg, #20c997 0%, #0d6efd 100%);
        }

        .stat-info {
            display: flex;
            flex-direction: column;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            line-height: 1.2;
        }

        .stat-label {
            font-size: 14px;
            color: #777;
            font-weight: 500;
        }

        /* Formulaires et tableaux */
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            color: white;
            padding: 15px;
            border-radius: 12px 12px 0 0;
            margin: -25px -25px 20px -25px;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark-purple);
        }

        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(106, 13, 173, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #a71d2a 100%);
            border: none;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            border: none;
        }

        .table {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .table thead {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-purple) 100%);
            color: white;
        }

        .table th {
            border-bottom: none;
        }

        .table td, .table th {
            padding: 12px 15px;
            vertical-align: middle;
        }

        .table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .table tr:hover {
            background-color: #f0f0f5;
        }

        /* Alertes */
        .alert {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border: none;
        }

        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border-left: 4px solid #dc3545;
        }

        .alert-info {
            background-color: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
            border-left: 4px solid #17a2b8;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 220px;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header-left {
                flex-direction: column;
                width: 100%;
                justify-content: center;
            }
            
            .header h1 {
                border-left: none;
                border-top: 1px solid rgba(255, 255, 255, 0.3);
                padding-left: 0;
                padding-top: 10px;
            }
            
            .header-right {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .main-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                padding: 15px 0;
            }
            
            .sidebar-menu {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
            }
            
            .nav-item {
                margin-bottom: 0;
            }
            
            .nav-link {
                padding: 8px 12px;
            }
            
            .stats-cards {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="header-left">
        <div class="header-logo">
            <img src="image.jpg" alt="Logo">
        </div>
        <h1>Gestion des Emplois du Temps</h1>
    </div>
    <div class="header-right">
        <div class="user-info">
            <i class="fas fa-user"></i>
            <div>
                <div class="user-info-label">Coordinateur</div>
                <div class="user-info-value">Admin</div>
            </div>
        </div>
    </div>
</div>

<div class="main-container">
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <img src="image.jpg" alt="Profile">
            <h3>Menu Principal</h3>
        </div>
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link text-white <?= (!isset($_GET['page']) || $_GET['page'] == 'ajouter') ? 'active' : '' ?>"
                       href="gerer_emplois_temps.php?page=ajouter">
                       <i class="fas fa-plus-circle"></i>
                       <span>Ajouter un créneau</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white <?= (isset($_GET['page']) && $_GET['page'] == 'liste') ? 'active' : '' ?>"
                       href="gerer_emplois_temps.php?page=liste">
                       <i class="fas fa-list-alt"></i>
                       <span>Liste des créneaux</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white logout-btn" href="logout.php">
                       <i class="fas fa-sign-out-alt"></i>
                       <span>Déconnexion</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Statistiques -->
        <div class="stats-cards">
            <?php
            // Compter le nombre total de créneaux
            $total_creneaux = $conn->query("SELECT COUNT(*) as total FROM creneaux")->fetch_assoc()['total'];

            // Compter le nombre de salles
            $total_salles = $conn->query("SELECT COUNT(*) as total FROM salles")->fetch_assoc()['total'];

            // Compter le nombre de groupes actifs
            $total_groupes = $conn->query("SELECT COUNT(*) as total FROM groupes WHERE actif = 1")->fetch_assoc()['total'];

            // Compter le nombre de créneaux par jour (pour le jour le plus chargé)
            $jour_plus_charge = $conn->query("SELECT jour, COUNT(*) as total FROM creneaux GROUP BY jour ORDER BY total DESC LIMIT 1")->fetch_assoc();
            ?>

            <div class="stat-card">
                <div class="stat-icon creneaux-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $total_creneaux; ?></div>
                    <div class="stat-label">Créneaux programmés</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon salles-icon">
                    <i class="fas fa-door-open"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $total_salles; ?></div>
                    <div class="stat-label">Salles disponibles</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon groupes-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $total_groupes; ?></div>
                    <div class="stat-label">Groupes actifs</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon jour-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo isset($jour_plus_charge['total']) ? $jour_plus_charge['total'] : '0'; ?></div>
                    <div class="stat-label">Créneaux le <?php echo isset($jour_plus_charge['jour']) ? $jour_plus_charge['jour'] : 'N/A'; ?></div>
                </div>
            </div>
        </div>

        <!-- Affichage des messages de succès -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php
                    switch($_GET['success']) {
                        case 1:
                            echo "Le créneau a été ajouté avec succès.";
                            break;
                        case 2:
                            echo "Le créneau a été modifié avec succès.";
                            break;
                        case 3:
                            echo "Le créneau a été supprimé avec succès.";
                            break;
                        default:
                            echo "Opération réussie.";
                    }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Affichage des messages d'erreur -->
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!isset($_GET['page']) || $_GET['page'] == 'ajouter'): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Ajouter un créneau</h3>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3" id="addCreneauForm">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-users me-1"></i> Groupe
                                </label>
                                <select name="id_groupe" class="form-select" required>
                                    <option value="" disabled selected>Choisir un groupe...</option>
                                    <?php
                                    // Réinitialiser le pointeur de résultat
                                    $groupes->data_seek(0);
                                    while($g = $groupes->fetch_assoc()):
                                    ?>
                                        <option value="<?= $g['id_groupe'] ?>"><?= $g['nom'] ?> - <?= $g['filiere'] ?> (<?= $g['niveau'] ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-door-open me-1"></i> Salle
                                </label>
                                <select name="id_salle" class="form-select" required id="salleSelect">
                                    <option value="" disabled selected>Choisir une salle...</option>
                                    <?php
                                    // Réinitialiser le pointeur de résultat
                                    $salles->data_seek(0);
                                    while($s = $salles->fetch_assoc()):
                                    ?>
                                        <option value="<?= $s['id_salle'] ?>"><?= $s['nom'] ?> (<?= $s['type'] ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-calendar-day me-1"></i> Jour
                                </label>
                                <select name="jour" class="form-select" required>
                                    <option value="" disabled selected>Choisir un jour...</option>
                                    <option value="Lundi">Lundi</option>
                                    <option value="Mardi">Mardi</option>
                                    <option value="Mercredi">Mercredi</option>
                                    <option value="Jeudi">Jeudi</option>
                                    <option value="Vendredi">Vendredi</option>
                                    <option value="Samedi">Samedi</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-clock me-1"></i> Heure de début
                                </label>
                                <input type="time" name="heure_debut" class="form-control" required id="heureDebut">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-hourglass-end me-1"></i> Heure de fin
                                </label>
                                <input type="time" name="heure_fin" class="form-control" required id="heureFin">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-sync-alt me-1"></i> Fréquence
                                </label>
                                <select name="frequence" class="form-select" required>
                                    <option value="" disabled selected>Choisir une fréquence...</option>
                                    <option value="Hebdo">Hebdomadaire</option>
                                    <option value="Bimensuel">Bimensuel</option>
                                    <option value="Ponctuel">Ponctuel</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" name="ajouter" class="btn btn-primary w-100" id="btnAjouter">
                                <i class="fas fa-plus-circle me-2"></i>Ajouter
                            </button>
                        </div>

                        <div class="col-12 mt-4">
                            <div class="alert alert-info" role="alert">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Vérification automatique :</strong> Le système vérifiera automatiquement les conflits de salles avant d'ajouter le créneau.
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Aperçu des disponibilités -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Aperçu des disponibilités</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Horaire</th>
                                    <th>Lundi</th>
                                    <th>Mardi</th>
                                    <th>Mercredi</th>
                                    <th>Jeudi</th>
                                    <th>Vendredi</th>
                                    <th>Samedi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $heures = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
                                foreach ($heures as $heure) {
                                    echo "<tr>";
                                    echo "<td><strong>$heure</strong></td>";

                                    $jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
                                    foreach ($jours as $jour) {
                                        // Vérifier si des créneaux existent à cette heure et ce jour
                                        $heure_fin = date('H:i', strtotime($heure . ' +1 hour'));
                                        $query = "SELECT COUNT(*) as count FROM creneaux
                                                WHERE jour = '$jour'
                                                AND ((heure_debut <= '$heure' AND heure_fin > '$heure')
                                                OR (heure_debut < '$heure_fin' AND heure_fin >= '$heure_fin')
                                                OR (heure_debut >= '$heure' AND heure_fin <= '$heure_fin'))";
                                        $result = $conn->query($query);
                                        $row = $result->fetch_assoc();

                                        if ($row['count'] > 0) {
                                            echo "<td class='bg-danger text-white text-center'><i class='fas fa-times'></i> Occupé</td>";
                                        } else {
                                            echo "<td class='bg-success text-white text-center'><i class='fas fa-check'></i> Libre</td>";
                                        }
                                    }

                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php elseif ($_GET['page'] == 'liste'): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0"><i class="fas fa-list-alt me-2"></i>Liste des créneaux</h3>
                </div>
                <div class="card-body">
                    <!-- Barre de recherche et filtres -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <input type="text" id="searchInput" class="form-control" placeholder="Rechercher...">
                                <button class="btn btn-primary" type="button">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="d-flex justify-content-end gap-2">
                                <select id="filterJour" class="form-select form-select-sm">
                                    <option value="">Tous les jours</option>
                                    <option>Lundi</option>
                                    <option>Mardi</option>
                                    <option>Mercredi</option>
                                    <option>Jeudi</option>
                                    <option>Vendredi</option>
                                    <option>Samedi</option>
                                </select>
                                <select id="filterFrequence" class="form-select form-select-sm">
                                    <option value="">Toutes les fréquences</option>
                                    <option>Hebdo</option>
                                    <option>Bimensuel</option>
                                    <option>Ponctuel</option>
                                </select>
                                <button id="resetFilters" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-undo"></i> Réinitialiser
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="creneauxTable" class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Groupe</th>
                                    <th>Salle</th>
                                    <th>Jour</th>
                                    <th>Début</th>
                                    <th>Fin</th>
                                    <th>Fréquence</th>
                                    <th>Modifier</th>
                                    <th>Supprimer</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php while($c = $creneaux->fetch_assoc()): ?>
                                <tr>
                                    <form method="POST" class="align-middle">
                                        <td><input type="text" name="id_creneau" value="<?= $c['id_creneau'] ?>" readonly class="form-control form-control-sm"></td>

                                        <td>
                                            <select name="id_groupe" class="form-select form-select-sm">
                                                <?php
                                                $groupes_reload = $conn->query("SELECT * FROM groupes WHERE actif = 1");
                                                while($g = $groupes_reload->fetch_assoc()):
                                                    $selected = ($g['id_groupe'] == $c['id_groupe']) ? "selected" : "";
                                                ?>
                                                    <option value="<?= $g['id_groupe'] ?>" <?= $selected ?>><?= $g['nom'] ?> - <?= $g['filiere'] ?> (<?= $g['niveau'] ?>)</option>
                                                <?php endwhile; ?>
                                            </select>
                                        </td>

                                        <td>
                                            <select name="id_salle" class="form-select form-select-sm">
                                                <?php
                                                $salles_reload = $conn->query("SELECT * FROM salles");
                                                while($s = $salles_reload->fetch_assoc()):
                                                    $selected = ($s['id_salle'] == $c['id_salle']) ? "selected" : "";
                                                ?>
                                                    <option value="<?= $s['id_salle'] ?>" <?= $selected ?>><?= $s['nom'] ?> (<?= $s['type'] ?>)</option>
                                                <?php endwhile; ?>
                                            </select>
                                        </td>

                                        <td>
                                            <select name="jour" class="form-select form-select-sm">
                                                <option <?= $c['jour'] == 'Lundi' ? 'selected' : '' ?>>Lundi</option>
                                                <option <?= $c['jour'] == 'Mardi' ? 'selected' : '' ?>>Mardi</option>
                                                <option <?= $c['jour'] == 'Mercredi' ? 'selected' : '' ?>>Mercredi</option>
                                                <option <?= $c['jour'] == 'Jeudi' ? 'selected' : '' ?>>Jeudi</option>
                                                <option <?= $c['jour'] == 'Vendredi' ? 'selected' : '' ?>>Vendredi</option>
                                                <option <?= $c['jour'] == 'Samedi' ? 'selected' : '' ?>>Samedi</option>
                                            </select>
                                        </td>
                                        <td><input type="time" name="heure_debut" value="<?= $c['heure_debut'] ?>" class="form-control form-control-sm"></td>
                                        <td><input type="time" name="heure_fin" value="<?= $c['heure_fin'] ?>" class="form-control form-control-sm"></td>
                                        <td>
                                            <select name="frequence" class="form-select form-select-sm">
                                                <option <?= $c['frequence'] == 'Hebdo' ? 'selected' : '' ?>>Hebdo</option>
                                                <option <?= $c['frequence'] == 'Bimensuel' ? 'selected' : '' ?>>Bimensuel</option>
                                                <option <?= $c['frequence'] == 'Ponctuel' ? 'selected' : '' ?>>Ponctuel</option>
                                            </select>
                                        </td>
                                        <td><button name="modifier" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i> Modifier</button></td>
                                        <td><button name="supprimer" class="btn btn-danger btn-sm" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce créneau ?');"><i class="fas fa-trash-alt"></i> Supprimer</button></td>
                                    </form>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Message si aucun résultat -->
                    <div id="noResults" class="alert alert-info text-center mt-3" style="display: none;">
                        <i class="fas fa-info-circle"></i> Aucun créneau ne correspond à votre recherche.
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Scripts Bootstrap et FontAwesome -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

<!-- Scripts améliorés pour l'interface utilisateur -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Éléments du DOM
    const searchInput = document.getElementById('searchInput');
    const filterJour = document.getElementById('filterJour');
    const filterFrequence = document.getElementById('filterFrequence');
    const resetFilters = document.getElementById('resetFilters');
    const table = document.getElementById('creneauxTable');
    const noResults = document.getElementById('noResults');
    const heureDebut = document.getElementById('heureDebut');
    const heureFin = document.getElementById('heureFin');
    const salleSelect = document.getElementById('salleSelect');
    const addCreneauForm = document.getElementById('addCreneauForm');

    // Fonction pour filtrer le tableau
    function filterTable() {
        const searchValue = searchInput.value.toLowerCase();
        const jourValue = filterJour.value;
        const frequenceValue = filterFrequence.value;

        let visibleCount = 0;
        const rows = table.querySelectorAll('tbody tr');

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const jour = row.querySelector('td:nth-child(4)').textContent.trim();
            const frequence = row.querySelector('td:nth-child(7)').textContent.trim();

            const matchesSearch = text.includes(searchValue);
            const matchesJour = jourValue === '' || jour === jourValue;
            const matchesFrequence = frequenceValue === '' || frequence === frequenceValue;

            const isVisible = matchesSearch && matchesJour && matchesFrequence;

            if (isVisible) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Afficher ou masquer le message "Aucun résultat"
        if (noResults) {
            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }

    // Événements pour les filtres
    if (searchInput) searchInput.addEventListener('input', filterTable);
    if (filterJour) filterJour.addEventListener('change', filterTable);
    if (filterFrequence) filterFrequence.addEventListener('change', filterTable);

    // Réinitialiser les filtres
    if (resetFilters) {
        resetFilters.addEventListener('click', function() {
            if (searchInput) searchInput.value = '';
            if (filterJour) filterJour.value = '';
            if (filterFrequence) filterFrequence.value = '';
            filterTable();
        });
    }

    // Validation du formulaire d'ajout de créneau
    if (addCreneauForm) {
        addCreneauForm.addEventListener('submit', function(e) {
            if (heureDebut && heureFin) {
                const debut = heureDebut.value;
                const fin = heureFin.value;

                if (debut >= fin) {
                    e.preventDefault();
                    alert("L'heure de début doit être antérieure à l'heure de fin.");
                    return false;
                }
            }
            return true;
        });
    }

    // Fermer automatiquement les alertes après 5 secondes
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>
</body>
</html>