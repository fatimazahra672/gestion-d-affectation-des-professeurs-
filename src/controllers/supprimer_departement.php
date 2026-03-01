<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Vérification CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Erreur de sécurité");
        }

        $departement_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        
        if (!$departement_id) {
            throw new Exception("ID invalide");
        }

        // Vérification si réaffectation nécessaire
        if (isset($_POST['nouveau_departement'])) {
            $nouveau_dep_id = filter_input(INPUT_POST, 'nouveau_departement', FILTER_VALIDATE_INT);
            
            // Réaffectation des membres
            $stmt = $pdo->prepare("UPDATE utilisateurs SET id_departement = ? WHERE id_departement = ?");
            $stmt->execute([$nouveau_dep_id, $departement_id]);
        }

        // Suppression du département
        $stmt = $pdo->prepare("DELETE FROM departements WHERE departement_id = ?");
        $stmt->execute([$departement_id]);

        $_SESSION['success'] = "Département supprimé" . (isset($nouveau_dep_id) ? " avec réaffectation des membres" : "");
        
    }
} catch(PDOException $e) {
    if ($e->getCode() === '23000') {
        $_SESSION['error'] = "Action requise : Réaffectez les membres avant suppression";
        $_SESSION['departement_a_supprimer'] = $departement_id;
        header("Location: reassigner_departement.php");
        exit();
    } else {
        $_SESSION['error'] = "Erreur base de données : " . $e->getMessage();
    }
} catch(Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

header("Location: gestion_departements.php");
exit();