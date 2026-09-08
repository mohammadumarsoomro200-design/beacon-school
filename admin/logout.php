<?php require __DIR__.'/../config/config.php'; if(user()) audit('logout','auth','User logged out'); session_destroy(); redirect('login.php');
