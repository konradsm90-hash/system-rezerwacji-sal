<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$serwer  = 'localhost';
$baza    = 'rezerwacje_db';
$uzytkownik = 'root';
$haslo   = '';

try {
    $pdo = new PDO("mysql:host=$serwer;dbname=$baza;charset=utf8mb4", $uzytkownik, $haslo, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Błąd połączenia z bazą danych: " . $e->getMessage());
}
?>