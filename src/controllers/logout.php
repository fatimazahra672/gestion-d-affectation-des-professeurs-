<?php
// Destruction totale de la session
session_start();
$_SESSION = [];
session_regenerate_id(true);
session_destroy();

// Suppression des cookies
$all_cookies = $_COOKIE;
foreach ($all_cookies as $name => $value) {
    setcookie($name, '', 1);
    setcookie($name, '', 1, '/');
}

// Nettoyage du buffer
while (ob_get_level()) ob_end_clean();

// Redirection avec JavaScript et meta refresh
header("Location: login_coordinateur.php?logout=1");
echo '<meta http-equiv="refresh" content="0;url=login.php?logout=1">';
echo '<script>window.location.replace("login.php?logout=1");</script>';
exit();
?>