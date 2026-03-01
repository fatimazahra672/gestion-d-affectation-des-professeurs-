<?php
// Ce fichier sert de redirection vers gestion_choix.php
// pour maintenir la compatibilité avec les liens existants

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Rediriger vers gestion_choix.php
header("Location: gestion_choix.php");
exit;
?>
