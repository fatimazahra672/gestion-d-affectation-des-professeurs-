<?php
// Vérifier si la session est démarrée, sinon la démarrer
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // Si cette page est incluse dans une autre page, la redirection sera gérée par cette page
    // Sinon, rediriger vers la page de connexion
    if (!defined('INCLUDED_FILE')) {
        header("Location: login_coordinateur.php");
        exit();
    }
}

// Connexion à la base
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'gestion_coordinteur';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

// Vérifier si la table emplois_temps existe, sinon la créer
$result = $conn->query("SHOW TABLES LIKE 'emplois_temps'");
if ($result->num_rows == 0) {
    // La table n'existe pas, on la crée
    $sql = "CREATE TABLE emplois_temps (
        id_emploi INT AUTO_INCREMENT PRIMARY KEY,
        jour ENUM('Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi') NOT NULL,
        heure_debut TIME NOT NULL,
        heure_fin TIME NOT NULL,
        id_groupe INT NOT NULL,
        id_salle INT NOT NULL,
        id_ue INT NOT NULL,
        id_enseignant INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (id_groupe) REFERENCES groupes(id_groupe) ON DELETE CASCADE,
        FOREIGN KEY (id_salle) REFERENCES salles(id_salle) ON DELETE CASCADE,
        FOREIGN KEY (id_ue) REFERENCES unites_enseignements(id_ue) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql);
}

// Traitement des actions
$message = '';
$messageType = '';

// Ajout d'un emploi du temps
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajouter'])) {
    try {
        $jour = $_POST['jour'];
        $heure_debut = $_POST['heure_debut'];
        $heure_fin = $_POST['heure_fin'];
        $id_groupe = (int)$_POST['id_groupe'];
        $id_salle = (int)$_POST['id_salle'];
        $id_ue = (int)$_POST['id_ue'];
        $id_enseignant = !empty($_POST['id_enseignant']) ? (int)$_POST['id_enseignant'] : null;

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
        $sql = "INSERT INTO emplois_temps (jour, heure_debut, heure_fin, id_groupe, id_salle, id_ue, id_enseignant)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssiiis", $jour, $heure_debut, $heure_fin, $id_groupe, $id_salle, $id_ue, $id_enseignant);
        $stmt->execute();

        $message = "Emploi du temps ajouté avec succès";
        $messageType = "success";

    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $messageType = "danger";
    }
}

// Suppression d'un emploi du temps
if (isset($_GET['delete'])) {
    try {
        $id = (int)$_GET['delete'];
        $stmt = $conn->prepare("DELETE FROM emplois_temps WHERE id_emploi = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $message = "Emploi du temps supprimé avec succès";
        $messageType = "success";

    } catch (Exception $e) {
        $message = "Erreur lors de la suppression : " . $e->getMessage();
        $messageType = "danger";
    }
}

// Modification d'un emploi du temps
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['modifier'])) {
    try {
        $id = (int)$_POST['id'];
        $jour = $_POST['jour'];
        $heure_debut = $_POST['heure_debut'];
        $heure_fin = $_POST['heure_fin'];
        $id_groupe = (int)$_POST['id_groupe'];
        $id_salle = (int)$_POST['id_salle'];
        $id_ue = (int)$_POST['id_ue'];
        $id_enseignant = !empty($_POST['id_enseignant']) ? (int)$_POST['id_enseignant'] : null;

        // Vérifier que l'heure de fin est après l'heure de début
        if (strtotime($heure_fin) <= strtotime($heure_debut)) {
            throw new Exception("L'heure de fin doit être après l'heure de début");
        }

        // Vérifier les conflits d'emploi du temps pour la salle (en excluant l'emploi du temps actuel)
        $sql = "SELECT * FROM emplois_temps
                WHERE id_salle = ? AND jour = ? AND id_emploi != ?
                AND ((heure_debut <= ? AND heure_fin > ?)
                OR (heure_debut < ? AND heure_fin >= ?)
                OR (heure_debut >= ? AND heure_fin <= ?))";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isisssss", $id_salle, $jour, $id, $heure_fin, $heure_debut, $heure_fin, $heure_debut, $heure_debut, $heure_fin);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            throw new Exception("Conflit d'horaire : la salle est déjà occupée à ce moment");
        }

        // Vérifier les conflits d'emploi du temps pour le groupe (en excluant l'emploi du temps actuel)
        $sql = "SELECT * FROM emplois_temps
                WHERE id_groupe = ? AND jour = ? AND id_emploi != ?
                AND ((heure_debut <= ? AND heure_fin > ?)
                OR (heure_debut < ? AND heure_fin >= ?)
                OR (heure_debut >= ? AND heure_fin <= ?))";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isisssss", $id_groupe, $jour, $id, $heure_fin, $heure_debut, $heure_fin, $heure_debut, $heure_debut, $heure_fin);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            throw new Exception("Conflit d'horaire : le groupe a déjà un cours à ce moment");
        }

        // Mettre à jour l'emploi du temps
        $sql = "UPDATE emplois_temps
                SET jour = ?, heure_debut = ?, heure_fin = ?, id_groupe = ?, id_salle = ?, id_ue = ?, id_enseignant = ?
                WHERE id_emploi = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssiiisi", $jour, $heure_debut, $heure_fin, $id_groupe, $id_salle, $id_ue, $id_enseignant, $id);
        $stmt->execute();

        $message = "Emploi du temps modifié avec succès";
        $messageType = "success";

    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $messageType = "danger";
    }
}

// Récupérer les données pour les formulaires
$groupes = $conn->query("SELECT id_groupe, nom FROM groupes ORDER BY nom")->fetch_all(MYSQLI_ASSOC);
$salles = $conn->query("SELECT id_salle, nom FROM salles ORDER BY nom")->fetch_all(MYSQLI_ASSOC);
$ues = $conn->query("SELECT id_ue, code_ue, intitule FROM unites_enseignements ORDER BY intitule")->fetch_all(MYSQLI_ASSOC);
$enseignants = $conn->query("SELECT id, nom, prenom FROM utilisateurs WHERE type_utilisateur = 'enseignant' ORDER BY nom, prenom")->fetch_all(MYSQLI_ASSOC);

// Récupérer les emplois du temps
$emplois = $conn->query("
    SELECT e.*,
           g.nom AS nom_groupe,
           s.nom AS nom_salle,
           u.intitule AS nom_ue,
           CONCAT(ens.nom, ' ', ens.prenom) AS nom_enseignant
    FROM emplois_temps e
    JOIN groupes g ON e.id_groupe = g.id_groupe
    JOIN salles s ON e.id_salle = s.id_salle
    JOIN unites_enseignements u ON e.id_ue = u.id_ue
    LEFT JOIN utilisateurs ens ON e.id_enseignant = ens.id
    ORDER BY e.jour, e.heure_debut
")->fetch_all(MYSQLI_ASSOC);

// Définir la page à afficher
$page = isset($_GET['page']) ? $_GET['page'] : 'liste';
?>
