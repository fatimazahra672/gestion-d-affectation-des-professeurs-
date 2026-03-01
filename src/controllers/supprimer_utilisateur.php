<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Accès non autorisé";
    header("Location: login.php");
    exit();
}

// Vérification CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Token de sécurité invalide";
    header("Location: utilisateurs.php");
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

    // Validation ID
    if (!isset($_POST['id']) || !$user_id = filter_var($_POST['id'], FILTER_VALIDATE_INT)) {
        throw new Exception("ID utilisateur invalide");
    }

    // Étape 1 : Supprimer les références dans departements
    $stmt = $pdo->prepare("UPDATE departements SET chef_departement_id = NULL WHERE chef_departement_id = ?");
    $stmt->execute([$user_id]);

    // Étape 2 : Supprimer l'utilisateur
    $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?");
    $stmt->execute([$user_id]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "Utilisateur #$user_id supprimé avec succès";
    } else {
        $_SESSION['error'] = "Aucun utilisateur trouvé";
    }

} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur SQL : " . $e->getMessage();
    error_log("[".date('Y-m-d H:i:s')."] Erreur suppression : ".$e->getMessage());
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

header("Location: utilisateurs.php");
exit();