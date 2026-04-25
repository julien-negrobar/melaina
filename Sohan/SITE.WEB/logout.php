<?php
session_start();
session_destroy(); // On détruit la session
header("Location: login.php"); // On renvoie vers le login
exit();
?>