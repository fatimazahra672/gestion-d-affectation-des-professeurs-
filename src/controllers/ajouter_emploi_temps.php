<?php
session_start();

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

// Vérifier et créer les tables nécessaires
$tables = [
    'groupes' => "CREATE TABLE groupes (
        id_groupe INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        id_specialite INT NOT NULL,
        niveau VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'salles' => "CREATE TABLE salles (
        id_salle INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        capacite INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'unites_enseignements' => "CREATE TABLE unites_enseignements (
        id_ue INT AUTO_INCREMENT PRIMARY KEY,
        id_matiere INT NOT NULL,
        filiere VARCHAR(100) NOT NULL,
        niveau VARCHAR(50) NOT NULL,
        annee_scolaire VARCHAR(20) NOT NULL,
        type_enseignement VARCHAR(50) NOT NULL,
        volume_horaire INT NOT NULL
    )",
    'emplois_temps' => "CREATE TABLE emplois_temps (
        id_emploi INT AUTO_INCREMENT PRIMARY KEY,
        jour VARCHAR(20) NOT NULL,
        heure_debut TIME NOT NULL,
        heure_fin TIME NOT NULL,
        id_groupe INT NOT NULL,
        id_salle INT NOT NULL,
        id_ue INT NOT NULL,
        id_enseignant INT NULL,
        frequence VARCHAR(20) NOT NULL DEFAULT 'Hebdo',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

// Vérifier si les tables existent et les créer si nécessaire
foreach ($tables as $table => $sql) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        // La table n'existe pas, on la crée
        if ($conn->query($sql) === TRUE) {
            echo "<div class='alert alert-success'>Table $table créée avec succès</div>";
        } else {
            echo "<div class='alert alert-danger'>Erreur lors de la création de la table $table : " . $conn->error . "</div>";
        }
    }
}

// Vérifier si la table utilisateurs existe
$result = $conn->query("SHOW TABLES LIKE 'utilisateurs'");
if ($result->num_rows == 0) {
    // La table n'existe pas, on la crée
    $sql = "CREATE TABLE utilisateurs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        prenom VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        mot_de_passe VARCHAR(255) NOT NULL,
        type_utilisateur ENUM('admin', 'coordinateur', 'chef_departement', 'enseignant') NOT NULL,
        id_departement INT NULL,
        id_specialite INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    if ($conn->query($sql) === TRUE) {
        echo "<div class='alert alert-success'>Table utilisateurs créée avec succès</div>";

        // Ajouter un utilisateur de test (coordinateur)
        $nom = "Coordinateur";
        $prenom = "Test";
        $email = "coordinateur@test.com";
        $mot_de_passe = password_hash("password", PASSWORD_DEFAULT);
        $type_utilisateur = "coordinateur";

        $sql = "INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, type_utilisateur)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $nom, $prenom, $email, $mot_de_passe, $type_utilisateur);

        if ($stmt->execute()) {
            echo "<div class='alert alert-success'>Utilisateur coordinateur créé avec succès (email: coordinateur@test.com, mot de passe: password)</div>";
        } else {
            echo "<div class='alert alert-danger'>Erreur lors de la création de l'utilisateur : " . $stmt->error . "</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>Erreur lors de la création de la table utilisateurs : " . $conn->error . "</div>";
    }
}

// Récupérer les données pour les formulaires
$groupes = [];
$result = $conn->query("SELECT id_groupe, nom FROM groupes ORDER BY nom");
if ($result) {
    $groupes = $result->fetch_all(MYSQLI_ASSOC);
} else {
    echo "<div class='alert alert-warning'>Erreur lors de la récupération des groupes : " . $conn->error . "</div>";
}

$salles = [];
$result = $conn->query("SELECT id_salle, nom FROM salles ORDER BY nom");
if ($result) {
    $salles = $result->fetch_all(MYSQLI_ASSOC);
} else {
    echo "<div class='alert alert-warning'>Erreur lors de la récupération des salles : " . $conn->error . "</div>";
}

$ues = [];
$result = $conn->query("SELECT id_ue, CONCAT(filiere, ' - ', niveau, ' (', type_enseignement, ', ', volume_horaire, 'h)') AS description FROM unites_enseignements ORDER BY filiere, niveau, type_enseignement");
if ($result) {
    $ues = $result->fetch_all(MYSQLI_ASSOC);
} else {
    echo "<div class='alert alert-warning'>Erreur lors de la récupération des UEs : " . $conn->error . "</div>";
}

$enseignants = [];
$result = $conn->query("SELECT id, nom, prenom FROM utilisateurs WHERE type_utilisateur = 'enseignant' ORDER BY nom, prenom");
if ($result) {
    $enseignants = $result->fetch_all(MYSQLI_ASSOC);
} else {
    echo "<div class='alert alert-warning'>Erreur lors de la récupération des enseignants : " . $conn->error . "</div>";
}

// Ajouter des données de test si les tables sont vides
if (empty($groupes)) {
    echo "<div class='alert alert-info'>Aucun groupe trouvé. Ajout de données de test...</div>";
    $conn->query("INSERT INTO groupes (nom, id_specialite, niveau) VALUES ('Groupe A', 1, 'Licence 3')");
    $conn->query("INSERT INTO groupes (nom, id_specialite, niveau) VALUES ('Groupe B', 1, 'Master 1')");
    $result = $conn->query("SELECT id_groupe, nom FROM groupes ORDER BY nom");
    if ($result) {
        $groupes = $result->fetch_all(MYSQLI_ASSOC);
    }
}

if (empty($salles)) {
    echo "<div class='alert alert-info'>Aucune salle trouvée. Ajout de données de test...</div>";
    $conn->query("INSERT INTO salles (nom, capacite, type) VALUES ('Salle 101', 30, 'Cours')");
    $conn->query("INSERT INTO salles (nom, capacite, type) VALUES ('Salle 102', 20, 'TP')");
    $result = $conn->query("SELECT id_salle, nom FROM salles ORDER BY nom");
    if ($result) {
        $salles = $result->fetch_all(MYSQLI_ASSOC);
    }
}

if (empty($ues)) {
    echo "<div class='alert alert-info'>Aucune UE trouvée. Ajout de données de test...</div>";
    $conn->query("INSERT INTO unites_enseignements (id_matiere, filiere, niveau, annee_scolaire, type_enseignement, volume_horaire) VALUES (1, 'Informatique', 'gi1', '2024/2025', 'Cours', 30)");
    $conn->query("INSERT INTO unites_enseignements (id_matiere, filiere, niveau, annee_scolaire, type_enseignement, volume_horaire) VALUES (2, 'Informatique', 'gi1', '2024/2025', 'TD', 15)");
    $result = $conn->query("SELECT id_ue, CONCAT(filiere, ' - ', niveau, ' (', type_enseignement, ', ', volume_horaire, 'h)') AS description FROM unites_enseignements ORDER BY filiere, niveau, type_enseignement");
    if ($result) {
        $ues = $result->fetch_all(MYSQLI_ASSOC);
    }
}

if (empty($enseignants)) {
    echo "<div class='alert alert-info'>Aucun enseignant trouvé. Ajout de données de test...</div>";
    $mot_de_passe = password_hash("password", PASSWORD_DEFAULT);
    $conn->query("INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, type_utilisateur) VALUES ('Dupont', 'Jean', 'jean.dupont@test.com', '$mot_de_passe', 'enseignant')");
    $conn->query("INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, type_utilisateur) VALUES ('Martin', 'Sophie', 'sophie.martin@test.com', '$mot_de_passe', 'enseignant')");
    $result = $conn->query("SELECT id, nom, prenom FROM utilisateurs WHERE type_utilisateur = 'enseignant' ORDER BY nom, prenom");
    if ($result) {
        $enseignants = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Traitement du formulaire
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajouter'])) {
    // Afficher les données reçues pour le débogage
    echo "<div class='alert alert-info'>";
    echo "<h4>Données reçues :</h4>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    echo "</div>";

    // Récupération des données du formulaire
    $jour = isset($_POST['jour']) ? $_POST['jour'] : '';
    $heure_debut = isset($_POST['heure_debut']) ? $_POST['heure_debut'] : '';
    $heure_fin = isset($_POST['heure_fin']) ? $_POST['heure_fin'] : '';
    $id_groupe = isset($_POST['id_groupe']) ? (int)$_POST['id_groupe'] : 0;
    $id_salle = isset($_POST['id_salle']) ? (int)$_POST['id_salle'] : 0;
    $id_ue = isset($_POST['id_ue']) ? (int)$_POST['id_ue'] : 0;
    $id_enseignant = isset($_POST['id_enseignant']) && !empty($_POST['id_enseignant']) ? (int)$_POST['id_enseignant'] : null;
    $frequence = isset($_POST['frequence']) ? $_POST['frequence'] : '';

    // Vérification des champs obligatoires
    if (empty($jour) || empty($heure_debut) || empty($heure_fin) || empty($id_groupe) || empty($id_salle) || empty($id_ue) || empty($frequence)) {
        $message = "Tous les champs sont obligatoires sauf l'enseignant.";
        $messageType = "danger";

        // Afficher quels champs sont vides
        echo "<div class='alert alert-warning'>";
        echo "<h4>Champs vides :</h4>";
        echo "<ul>";
        if (empty($jour)) echo "<li>Jour</li>";
        if (empty($heure_debut)) echo "<li>Heure de début</li>";
        if (empty($heure_fin)) echo "<li>Heure de fin</li>";
        if (empty($id_groupe)) echo "<li>Groupe</li>";
        if (empty($id_salle)) echo "<li>Salle</li>";
        if (empty($id_ue)) echo "<li>UE</li>";
        if (empty($frequence)) echo "<li>Fréquence</li>";
        echo "</ul>";
        echo "</div>";
    } else {
        // Tous les champs sont remplis, on peut insérer
        $sql = "INSERT INTO emplois_temps (jour, heure_debut, heure_fin, id_groupe, id_salle, id_ue, id_enseignant, frequence)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("sssiiiis", $jour, $heure_debut, $heure_fin, $id_groupe, $id_salle, $id_ue, $id_enseignant, $frequence);

            if ($stmt->execute()) {
                $message = "Emploi du temps ajouté avec succès";
                $messageType = "success";
            } else {
                $message = "Erreur lors de l'ajout de l'emploi du temps : " . $stmt->error;
                $messageType = "danger";
            }

            $stmt->close();
        } else {
            $message = "Erreur de préparation de la requête : " . $conn->error;
            $messageType = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un Emploi du Temps</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12 mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h1>Ajouter un Emploi du Temps</h1>
                    <div>
                        <a href="dashboard_coordinateur.php" class="btn btn-secondary">
                            <i class="fas fa-home"></i> Tableau de bord
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Formulaire d'ajout</h5>
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
                                <option value="<?= $ue['id_ue'] ?>"><?= htmlspecialchars($ue['description']) ?></option>
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
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                        <a href="dashboard_coordinateur.php" class="btn btn-secondary ms-2">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
