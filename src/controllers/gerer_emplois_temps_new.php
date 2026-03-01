<?php
// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "gestion_coordinteur");

if ($conn->connect_error) {
    die("Erreur de connexion: " . $conn->connect_error);
}

// Vérifier si la table creneaux existe
$result = $conn->query("SHOW TABLES LIKE 'creneaux'");
if ($result->num_rows == 0) {
    // La table n'existe pas, on la crée
    $sql = "CREATE TABLE creneaux (
        id_creneau INT AUTO_INCREMENT PRIMARY KEY,
        id_groupe INT NOT NULL,
        id_salle INT NOT NULL,
        jour VARCHAR(20) NOT NULL,
        heure_debut TIME NOT NULL,
        heure_fin TIME NOT NULL,
        frequence VARCHAR(20) NOT NULL DEFAULT 'Hebdo',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    if ($conn->query($sql) === TRUE) {
        echo "<div class='alert alert-success'>Table creneaux créée avec succès</div>";
    } else {
        echo "<div class='alert alert-danger'>Erreur lors de la création de la table creneaux : " . $conn->error . "</div>";
    }
}

// Récupération des données
$groupes = $conn->query("SELECT * FROM groupes");
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

// Messages
$success_message = "";
$error_message = "";

// Traitement de l'ajout d'un créneau
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajouter'])) {
    $id_groupe = isset($_POST['id_groupe']) ? sanitize_input($_POST['id_groupe']) : '';
    $id_salle = isset($_POST['id_salle']) ? sanitize_input($_POST['id_salle']) : '';
    $jour = isset($_POST['jour']) ? sanitize_input($_POST['jour']) : '';
    $heure_debut = isset($_POST['heure_debut']) ? sanitize_input($_POST['heure_debut']) : '';
    $heure_fin = isset($_POST['heure_fin']) ? sanitize_input($_POST['heure_fin']) : '';
    $frequence = isset($_POST['frequence']) ? sanitize_input($_POST['frequence']) : '';

    // Validation
    if (empty($id_groupe) || empty($id_salle) || empty($jour) || empty($heure_debut) || empty($heure_fin) || empty($frequence)) {
        $error_message = "Tous les champs sont obligatoires.";
    } elseif ($heure_debut >= $heure_fin) {
        $error_message = "L'heure de début doit être antérieure à l'heure de fin.";
    } elseif (check_creneau_conflict($conn, $id_salle, $jour, $heure_debut, $heure_fin)) {
        $error_message = "Conflit de créneau pour cette salle.";
    } else {
        $stmt = $conn->prepare("INSERT INTO creneaux (id_groupe, id_salle, jour, heure_debut, heure_fin, frequence) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $id_groupe, $id_salle, $jour, $heure_debut, $heure_fin, $frequence);

        if ($stmt->execute()) {
            header("Location: gerer_emplois_temps_new.php?page=liste&success=1");
            exit();
        } else {
            $error_message = "Erreur: " . $stmt->error;
        }
    }
}

// Traitement de la modification
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['modifier'])) {
    $id = sanitize_input($_POST['id_creneau']);
    $id_groupe = sanitize_input($_POST['id_groupe']);
    $id_salle = sanitize_input($_POST['id_salle']);
    $jour = sanitize_input($_POST['jour']);
    $heure_debut = sanitize_input($_POST['heure_debut']);
    $heure_fin = sanitize_input($_POST['heure_fin']);
    $frequence = sanitize_input($_POST['frequence']);

    if (empty($id_groupe) || empty($id_salle) || empty($jour) || empty($heure_debut) || empty($heure_fin) || empty($frequence)) {
        $error_message = "Tous les champs sont obligatoires.";
    } elseif ($heure_debut >= $heure_fin) {
        $error_message = "L'heure de début doit être antérieure à l'heure de fin.";
    } elseif (check_creneau_conflict($conn, $id_salle, $jour, $heure_debut, $heure_fin, $id)) {
        $error_message = "Conflit de créneau pour cette salle.";
    } else {
        $stmt = $conn->prepare("UPDATE creneaux SET id_groupe=?, id_salle=?, jour=?, heure_debut=?, heure_fin=?, frequence=? WHERE id_creneau=?");
        $stmt->bind_param("iissssi", $id_groupe, $id_salle, $jour, $heure_debut, $heure_fin, $frequence, $id);

        if ($stmt->execute()) {
            header("Location: gerer_emplois_temps_new.php?page=liste&success=2");
            exit();
        } else {
            $error_message = "Erreur: " . $stmt->error;
        }
    }
}

// Traitement de la suppression
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['supprimer'])) {
    $id = sanitize_input($_POST['id_creneau']);

    $stmt = $conn->prepare("DELETE FROM creneaux WHERE id_creneau=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: gerer_emplois_temps_new.php?page=liste&success=3");
        exit();
    } else {
        $error_message = "Erreur: " . $stmt->error;
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
            --primary-color: #6a11cb;
            --secondary-color: #5a0cb2;
            --light-bg: #f5f7ff;
        }

        body {
            background: linear-gradient(135deg, #e8f0fe 0%, #d8d0f9 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container {
            background-color: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(138, 79, 255, 0.3);
        }

        .form-control, .form-select {
            background-color: #f5f7ff;
            border-radius: 8px;
        }

        .btn-primary {
            background: #8a4fff;
            border: none;
        }

        .table thead {
            background: #8a4fff !important;
            color: white !important;
        }
    </style>
</head>
<body>
<div class="d-flex">
    <nav class="text-white p-3" style="width: 250px; min-height: 100vh; background-color: rgba(10, 25, 47, 0.9);">
        <div class="text-center mb-4">
            <img src="image.jpg" class="rounded-circle" style="width: 100px; height: 100px; object-fit: cover;">
            <h4 class="mt-3">Menu</h4>
        </div>

        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link text-white <?= (!isset($_GET['page']) || $_GET['page'] == 'ajouter') ? 'active' : '' ?>"
                   href="gerer_emplois_temps_new.php?page=ajouter">
                   <i class="fas fa-plus-circle me-2"></i> Ajouter
                </a>
            </li>
            <li class="nav-item mt-2">
                <a class="nav-link text-white <?= (isset($_GET['page']) && $_GET['page'] == 'liste') ? 'active' : '' ?>"
                   href="gerer_emplois_temps_new.php?page=liste">
                   <i class="fas fa-list-alt me-2"></i> Liste
                </a>
            </li>
        </ul>
    </nav>

    <div class="container mt-4">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php
                switch($_GET['success']) {
                    case 1: echo "Créneau ajouté avec succès!"; break;
                    case 2: echo "Créneau modifié avec succès!"; break;
                    case 3: echo "Créneau supprimé avec succès!"; break;
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!isset($_GET['page']) || $_GET['page'] == 'ajouter'): ?>
            <div class="card">
                <div class="card-header text-white" style="background: var(--primary-color);">
                    <h3><i class="fas fa-plus-circle me-2"></i>Ajouter un créneau</h3>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Groupe</label>
                            <select name="id_groupe" class="form-select" required>
                                <option value="">Choisir un groupe...</option>
                                <?php while($g = $groupes->fetch_assoc()): ?>
                                    <option value="<?= $g['id_groupe'] ?>"><?= $g['nom'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Salle</label>
                            <select name="id_salle" class="form-select" required>
                                <option value="">Choisir une salle...</option>
                                <?php while($s = $salles->fetch_assoc()): ?>
                                    <option value="<?= $s['id_salle'] ?>"><?= $s['nom'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Jour</label>
                            <select name="jour" class="form-select" required>
                                <option value="">Choisir un jour...</option>
                                <option value="Lundi">Lundi</option>
                                <option value="Mardi">Mardi</option>
                                <option value="Mercredi">Mercredi</option>
                                <option value="Jeudi">Jeudi</option>
                                <option value="Vendredi">Vendredi</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Heure de début</label>
                            <input type="time" name="heure_debut" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Heure de fin</label>
                            <input type="time" name="heure_fin" class="form-control" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Fréquence</label>
                            <select name="frequence" class="form-select" required>
                                <option value="">Choisir une fréquence...</option>
                                <option value="Hebdo">Hebdomadaire</option>
                                <option value="Bimensuel">Bimensuel</option>
                                <option value="Ponctuel">Ponctuel</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="ajouter" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-2"></i>Ajouter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php elseif ($_GET['page'] == 'liste'): ?>
            <div class="card">
                <div class="card-header text-white" style="background: var(--primary-color);">
                    <h3><i class="fas fa-list-alt me-2"></i>Liste des créneaux</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Groupe</th>
                                    <th>Salle</th>
                                    <th>Jour</th>
                                    <th>Début</th>
                                    <th>Fin</th>
                                    <th>Fréquence</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($c = $creneaux->fetch_assoc()): ?>
                                <tr>
                                    <form method="POST">
                                        <input type="hidden" name="id_creneau" value="<?= $c['id_creneau'] ?>">
                                        <td>
                                            <select name="id_groupe" class="form-select form-select-sm">
                                                <?php
                                                $groupes->data_seek(0);
                                                while($g = $groupes->fetch_assoc()):
                                                    $selected = ($g['id_groupe'] == $c['id_groupe']) ? 'selected' : '';
                                                ?>
                                                    <option value="<?= $g['id_groupe'] ?>" <?= $selected ?>><?= $g['nom'] ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="id_salle" class="form-select form-select-sm">
                                                <?php
                                                $salles->data_seek(0);
                                                while($s = $salles->fetch_assoc()):
                                                    $selected = ($s['id_salle'] == $c['id_salle']) ? 'selected' : '';
                                                ?>
                                                    <option value="<?= $s['id_salle'] ?>" <?= $selected ?>><?= $s['nom'] ?></option>
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
                                        <td>
                                            <button name="modifier" class="btn btn-sm btn-warning me-2">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button name="supprimer" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </form>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Script pour améliorer l'interface
document.addEventListener('DOMContentLoaded', function() {
    // Fermer automatiquement les alertes après 5 secondes
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            new bootstrap.Alert(alert).close();
        });
    }, 5000);
});
</script>
</body>
</html>
