<?php
require_once 'konfiguracja.php';

$blad = '';
$sukces = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']);
    $haslo = $_POST['haslo'];
    $haslo_potwierdz = $_POST['haslo_potwierdz'];

    if (empty($login) || empty($haslo)) {
        $blad = 'Wypełnij wszystkie pola!';
    } elseif ($haslo !== $haslo_potwierdz) {
        $blad = 'Hasła nie są identyczne!';
    } else {
        // Sprawdzenie czy login jest wolny
        $stmt = $pdo->prepare("SELECT id FROM uzytkownicy WHERE login = ?");
        $stmt->execute([$login]);
        
        if ($stmt->fetch()) {
            $blad = 'Ten login jest już zajęty!';
        } else {
            // Hashowanie hasła
            $haslo_hash = password_hash($haslo, PASSWORD_DEFAULT);
            $ins = $pdo->prepare("INSERT INTO uzytkownicy (login, haslo, rola) VALUES (?, ?, 'uzytkownik')");
            $ins->execute([$login, $haslo_hash]);
            $sukces = 'Konto zostało utworzone! Możesz się teraz zalogować.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Rejestracja</title>
    <link rel="stylesheet" href="zasoby/styl.css">
</head>
<body>
<div class="form-container">
    <h2>Rejestracja użytkownika</h2>
    <?php if ($blad): ?><p class="blad"><?= $blad ?></p><?php endif; ?>
    <?php if ($sukces): ?><p class="sukces"><?= $sukces ?></p><?php endif; ?>
    
    <form method="POST">
        <label>Login: <input type="text" name="login" required></label>
        <label>Hasło: <input type="password" name="haslo" required></label>
        <label>Potwierdź hasło: <input type="password" name="haslo_potwierdz" required></label>
        <button type="submit">Zarejestruj się</button>
    </form>
    <p><a href="logowanie.php">Masz już konto? Zaloguj się</a> | <a href="index.php">Strona główna</a></p>
</div>
</body>
</html>