<?php
// Vérifier si la session est démarrée, sinon la démarrer
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: login_coordinateur.php");
    exit();
}

// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "gestion_coordinteur");
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

// Vérifier si la colonne frequence existe dans la table emplois_temps
$result = $conn->query("SHOW COLUMNS FROM emplois_temps LIKE 'frequence'");
if ($result->num_rows == 0) {
    // La colonne n'existe pas, on l'ajoute
    $conn->query("ALTER TABLE emplois_temps ADD COLUMN frequence VARCHAR(20) NOT NULL DEFAULT 'Hebdo'");
    echo "<div style='background-color: #d4edda; color: #155724; padding: 10px; margin-bottom: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>
        Colonne 'frequence' ajoutée à la table emplois_temps.
    </div>";
}

// Récupérer les données pour les formulaires
$groupes = $conn->query("SELECT id_groupe, nom FROM groupes ORDER BY nom")->fetch_all(MYSQLI_ASSOC);
$salles = $conn->query("SELECT id_salle, nom FROM salles ORDER BY nom")->fetch_all(MYSQLI_ASSOC);
$ues = $conn->query("SELECT id_ue, code_ue, intitule FROM unites_enseignements ORDER BY intitule")->fetch_all(MYSQLI_ASSOC);
$enseignants = $conn->query("SELECT id, nom, prenom FROM utilisateurs WHERE type_utilisateur = 'enseignant' ORDER BY nom, prenom")->fetch_all(MYSQLI_ASSOC);

// Traitement du formulaire
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajouter'])) {
    // Débogage - Afficher toutes les valeurs POST
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<h3>Valeurs du formulaire :</h3>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    echo "</div>";

    try {
        // Récupération des données du formulaire
        $jour = $_POST['jour'] ?? '';
        $heure_debut = $_POST['heure_debut'] ?? '';
        $heure_fin = $_POST['heure_fin'] ?? '';
        $id_groupe = !empty($_POST['id_groupe']) ? (int)$_POST['id_groupe'] : 0;
        $id_salle = !empty($_POST['id_salle']) ? (int)$_POST['id_salle'] : 0;
        $id_ue = !empty($_POST['id_ue']) ? (int)$_POST['id_ue'] : 0;
        $id_enseignant = !empty($_POST['id_enseignant']) ? (int)$_POST['id_enseignant'] : null;
        $frequence = $_POST['frequence'] ?? '';

        // Vérification des champs obligatoires
        if (empty($jour) || empty($heure_debut) || empty($heure_fin) || empty($id_groupe) || empty($id_salle) || empty($id_ue) || empty($frequence)) {
            throw new Exception("Tous les champs sont obligatoires sauf l'enseignant.");
        }

        // Vérifier que l'heure de fin est après l'heure de début
        if (strtotime($heure_fin) <= strtotime($heure_debut)) {
            throw new Exception("L'heure de fin doit être après l'heure de début");
        }

        // Vérifier les conflits d'emploi du temps pour la salle
        $sql = "SELECT * FROM emplois_temps
                WHERE id_salle = ? AND jour = ?
                AND ((heure_debut <= ? AND heure_fin > ?)
                OR (heure_debut < ? AND heure_fin >= ?)
                OR (heure_debut >= ? AND heure_fin <= ?))";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssss", $id_salle, $jour, $heure_fin, $heure_debut, $heure_fin, $heure_debut, $heure_debut, $heure_fin);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            throw new Exception("Conflit d'horaire : la salle est déjà occupée à ce moment");
        }

        // Vérifier les conflits d'emploi du temps pour le groupe
        $sql = "SELECT * FROM emplois_temps
                WHERE id_groupe = ? AND jour = ?
                AND ((heure_debut <= ? AND heure_fin > ?)
                OR (heure_debut < ? AND heure_fin >= ?)
                OR (heure_debut >= ? AND heure_fin <= ?))";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssss", $id_groupe, $jour, $heure_fin, $heure_debut, $heure_fin, $heure_debut, $heure_debut, $heure_fin);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            throw new Exception("Conflit d'horaire : le groupe a déjà un cours à ce moment");
        }

        // Insérer l'emploi du temps
        $sql = "INSERT INTO emplois_temps (jour, heure_debut, heure_fin, id_groupe, id_salle, id_ue, id_enseignant, frequence)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssiiiss", $jour, $heure_debut, $heure_fin, $id_groupe, $id_salle, $id_ue, $id_enseignant, $frequence);

        if ($stmt->execute()) {
            $message = "Emploi du temps ajouté avec succès";
            $messageType = "success";
            header("Location: emplois_temps_form.php?success=1");
            exit();
        } else {
            throw new Exception("Erreur lors de l'ajout de l'emploi du temps : " . $stmt->error);
        }

    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $messageType = "danger";
    }
}

// Afficher un message de succès si redirection après ajout réussi
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = "Emploi du temps ajouté avec succès";
    $messageType = "success";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un Emploi du Temps</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #6a11cb;
            --secondary-color: #5a0cb2;
            --light-bg: #f5f7ff;
            --card-bg: rgba(255, 255, 255, 0.95);
            --text-color: #333333;
            --white: #ffffff;
            --error-color: #ff4757;
            --success-color: #28a745;
            --border-radius: 10px;
            --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        body {
            background: linear-gradient(135deg, var(--light-bg) 0%, #c3c7f7 100%);
            color: var(--text-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(90deg, var(--primary-color) 0%, var(--secondary-color) 100%) !important;
            color: white;
            padding: 1rem 1.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
        }

        .form-label {
            font-weight: 600;
            color: var(--primary-color);
        }

        .form-control, .form-select {
            border-radius: var(--border-radius);
            border: 1px solid #e0e0ff;
            padding: 0.6rem 1rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(106, 17, 203, 0.25);
        }

        .alert {
            border-radius: var(--border-radius);
        }

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border-color: var(--error-color);
            color: var(--error-color);
        }

        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border-color: var(--success-color);
            color: var(--success-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">
                <i class="fas fa-calendar-alt me-2"></i> Ajouter un Emploi du Temps
            </h1>
            <div>
                <a href="dashboard_coordinateur.php" class="btn btn-secondary me-2">
                    <i class="fas fa-home me-1"></i> Tableau de bord
                </a>
                <a href="emplois_temps_complet.php" class="btn btn-primary">
                    <i class="fas fa-list me-1"></i> Liste des emplois du temps
                </a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Formulaire d'ajout d'un emploi du temps</h5>
            </div>
            <div class="card-body">
                <form method="post" action="" class="row g-3">
                    <div class="col-md-6">
                        <label for="jour" class="form-label">Jour <span class="text-danger">*</span></label>
                        <select class="form-select" id="jour" name="jour" required>
                            <option value="">Sélectionner un jour</option>
                            <option value="Lundi">Lundi</option>
                            <option value="Mardi">Mardi</option>
                            <option value="Mercredi">Mercredi</option>
                            <option value="Jeudi">Jeudi</option>
                            <option value="Vendredi">Vendredi</option>
                            <option value="Samedi">Samedi</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="heure_debut" class="form-label">Heure de début <span class="text-danger">*</span></label>
                        <input type="time" class="form-control" id="heure_debut" name="heure_debut" required>
                    </div>
                    <div class="col-md-3">
                        <label for="heure_fin" class="form-label">Heure de fin <span class="text-danger">*</span></label>
                        <input type="time" class="form-control" id="heure_fin" name="heure_fin" required>
                    </div>
                    <div class="col-md-6">
                        <label for="id_groupe" class="form-label">Groupe <span class="text-danger">*</span></label>
                        <select class="form-select" id="id_groupe" name="id_groupe" required>
                            <option value="">Sélectionner un groupe</option>
                            <?php foreach ($groupes as $groupe): ?>
                                <option value="<?= $groupe['id_groupe'] ?>"><?= htmlspecialchars($groupe['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="id_salle" class="form-label">Salle <span class="text-danger">*</span></label>
                        <select class="form-select" id="id_salle" name="id_salle" required>
                            <option value="">Sélectionner une salle</option>
                            <?php foreach ($salles as $salle): ?>
                                <option value="<?= $salle['id_salle'] ?>"><?= htmlspecialchars($salle['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="id_ue" class="form-label">Unité d'enseignement <span class="text-danger">*</span></label>
                        <select class="form-select" id="id_ue" name="id_ue" required>
                            <option value="">Sélectionner une UE</option>
                            <?php foreach ($ues as $ue): ?>
                                <option value="<?= $ue['id_ue'] ?>"><?= htmlspecialchars($ue['code_ue'] . ' - ' . $ue['intitule']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="id_enseignant" class="form-label">Enseignant (optionnel)</label>
                        <select class="form-select" id="id_enseignant" name="id_enseignant">
                            <option value="">Sélectionner un enseignant</option>
                            <?php foreach ($enseignants as $enseignant): ?>
                                <option value="<?= $enseignant['id'] ?>"><?= htmlspecialchars($enseignant['nom'] . ' ' . $enseignant['prenom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="frequence" class="form-label">Fréquence <span class="text-danger">*</span></label>
                        <select class="form-select" id="frequence" name="frequence" required>
                            <option value="">Sélectionner une fréquence</option>
                            <option value="Hebdo">Hebdomadaire</option>
                            <option value="Bimensuel">Bimensuel</option>
                            <option value="Ponctuel">Ponctuel</option>
                        </select>
                    </div>
                    <div class="col-12 mt-4">
                        <button type="submit" name="ajouter" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Enregistrer
                        </button>
                        <a href="emplois_temps_complet.php" class="btn btn-secondary ms-2">
                            <i class="fas fa-times me-1"></i> Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
