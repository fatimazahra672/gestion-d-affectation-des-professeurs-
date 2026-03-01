<?php
// ============================================================
// FICHIER EXEMPLE DE CONFIGURATION
// Copiez ce fichier en "db_config.php" et remplissez vos valeurs
// Ne committez JAMAIS db_config.php sur GitHub !
// ============================================================

// Activation du rapport d'erreurs (désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configuration des sessions
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure'   => false, // Mettre true en production (HTTPS)
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_path'     => '/',
    ]);
}

// ========================
// Constantes de connexion BD — À MODIFIER
// ========================
define('DB_HOST', 'localhost');         // Hôte de la base de données
define('DB_NAME', 'gestion_coordinteur'); // Nom de la base de données
define('DB_USER', 'root');              // Utilisateur MySQL
define('DB_PASS', '');                  // Mot de passe MySQL

// Connexion à la base de données via PDO
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

