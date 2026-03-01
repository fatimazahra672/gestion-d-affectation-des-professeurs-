<?php
// Ce fichier sert uniquement à rediriger vers la version correctement orthographiée
// Récupérer les paramètres de l'URL
$query_string = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';

// Rediriger vers le fichier correctement orthographié
header("Location: affectation_vacataire.php" . $query_string);
exit();
?>
