<?php
require_once 'config.php';

$email = 'newadmin@ensah.ma';
$new_password = 'admin123';

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME,
        DB_USER,
        DB_PASS
    );
    
    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        UPDATE utilisateurs 
        SET mot_de_passe = :hash 
        WHERE email = :email
    ");
    
    $stmt->execute([
        ':hash' => $hash,
        ':email' => $email
    ]);
    
    echo "Mot de passe réinitialisé avec succès!";

} catch(PDOException $e) {
    die("Erreur: " . $e->getMessage());
}