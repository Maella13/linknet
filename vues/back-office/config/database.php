<?php
// config/db.php

/* -----------------------------------------------------------------
   Ancienne connexion (production InfinityFree précédente)
------------------------------------------------------------------*/
/*
$host     = 'sql203.infinityfree.com';
$dbname   = 'if0_39310327_linknet';
$username = 'if0_39310327';
$password = 'z3fMhkcseZyc';
*/

/* -----------------------------------------------------------------
   Ancienne connexion (en local)
------------------------------------------------------------------*/
/*
$host     = 'localhost';
$dbname   = 'linknet';
$username = 'root';
$password = '';
/*
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // @var PDO $conn
    $conn = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die('Connexion BD impossible : ' . $e->getMessage());
}
*/

/* -----------------------------------------------------------------
   Nouvelle connexion (Active) – InfinityFree sql309
------------------------------------------------------------------*/
$host     = 'sql309.infinityfree.com';
$dbname   = 'if0_39453622_linknet'; // ← Ton vrai nom de base ici
$username = 'if0_39453622';
$password = 'bellox123';

try {
    // DSN : on précise le port pour être explicite, mais il est facultatif
    $dsn  = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8";
    $conn = new PDO($dsn, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Vous pouvez maintenant utiliser $conn
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}
?>
