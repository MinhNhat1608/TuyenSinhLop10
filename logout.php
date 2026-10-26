<?php require_once __DIR__.'/includes/bootstrap.php';verify_post();$_SESSION=[];session_destroy();go('login.php');
