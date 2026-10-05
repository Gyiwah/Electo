<?php
require_once __DIR__ . '/../includes/security.php';

if(isset($_SESSION['admin'])){
    safe_redirect('home.php');
}

safe_redirect('../index.php?role=admin');
?>