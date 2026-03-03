<?php
session_start();

$redirect_url = "login.php";
if (isset($_GET['reason']) && $_GET['reason'] === 'suspended') {
    $redirect_url .= "?reason=suspended";
}

session_unset();
session_destroy();

header("Location: ../login.php");
exit();