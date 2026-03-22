<?php
    $user = 'admin';
    $password = '1234';

    $username = $_POST['name'] ?? '';
    $passwordform = $_POST['password'] ?? ''; 

    if(empty($username) || empty($passwordform)){
        echo "Inserisci username e password";
        exit;
    }

    if($username == $user && $passwordform == $password){
        header("Location: index.html");
    }else{
        echo 'Accesso negato';
    }
    
?>