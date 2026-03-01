<?php
require_once 'config.php';

// Sécurité et vérification des droits
session_start();
if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.1 401 Unauthorized");
    die("Accès non autorisé");
}

if ($_SESSION['role'] !== 'chef_departement') {
    header("HTTP/1.1 403 Forbidden");
    die("Permission refusée");
}

// Vérification des données POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['prof_id'], $_POST['prof_type'], $_POST['heures_max'])) {
    header("HTTP/1.1 400 Bad Request");
    die("Requête invalide");
}

$profId = (int)$_POST['prof_id'];
$profType = $_POST['prof_type'];
$heuresMax = (int)$_POST['heures_max'];

// Validation des données
if ($profId <= 0 || $heuresMax <= 0 || $heuresMax > 500) {
    header("HTTP/1.1 400 Bad Request");
    die("Données invalides");
}

if (!in_array($profType, ['permanent', 'vacataire'])) {
    header("HTTP/1.1 400 Bad Request");
    die("Type de professeur invalide");
}

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Vérifier que le professeur appartient bien au département de l'utilisateur
    $checkQuery = "SELECT id FROM professeurs WHERE id = :id AND id_departement = :departement_id";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([
        ':id' => $profId,
        ':departement_id' => $_SESSION['id_departement']
    ]);

    if ($checkStmt->rowCount() === 0) {
        header("HTTP/1.1 403 Forbidden");
        die("Professeur non trouvé dans votre département");
    }

    // Mise à jour en fonction du type de professeur
    if ($profType === 'permanent') {
        $updateQuery = "UPDATE professeurs SET heures_max = :heures_max WHERE id = :id";
    } else {
        $updateQuery = "UPDATE professeurs SET heures_vacataire = :heures_max WHERE id = :id";
    }

    $updateStmt = $pdo->prepare($updateQuery);
    $updateStmt->execute([
        ':heures_max' => $heuresMax,
        ':id' => $profId
    ]);

    // Retourner une réponse JSON pour le JavaScript
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Quota horaire mis à jour avec succès',
        'prof_id' => $profId,
        'heures_max' => $heuresMax
    ]);

} catch (PDOException $e) {
    header("HTTP/1.1 500 Internal Server Error");
    die(json_encode([
        'success' => false,
        'message' => 'Erreur de base de données : ' . $e->getMessage()
    ]));
}
?>