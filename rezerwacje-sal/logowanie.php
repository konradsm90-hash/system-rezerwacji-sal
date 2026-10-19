<?php
require_once 'konfiguracja.php';

$blad = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']);
    $haslo = $_POST['haslo'];

    $stmt = $pdo->prepare("SELECT * FROM uzytkownicy WHERE login = ?");
    $stmt->execute([$login]);
    $uzytkownik = $stmt->fetch();

    if ($uzytkownik && password_verify($haslo, $uzytkownik['haslo'])) {
        $_SESSION['id_uzytkownika'] = $uzytkownik['id'];
        $_SESSION['login'] = $uzytkownik['login'];
        $_SESSION['rola'] = $uzytkownik['rola'];
        header('Location: panel.php');
        exit;
    } else {
        $blad = 'Nieprawidłowy login lub hasło.';
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Logowanie</title>
    <link rel="stylesheet" href="zasoby/styl.css">
</head>
<body>
<div class="form-container">
    <h2>Logowanie</h2>
    <?php if ($blad): ?><p class="blad"><?= $blad ?></p><?php endif; ?>
    <form method="POST">
        <label>Login: <input type="text" name="login" required></label>
        <label>Hasło: <input type="password" name="haslo" required></label>
        <button type="submit">Zaloguj się</button>
    </form>
    <p><a href="rejestracja.php">Nie masz konta? Zarejestruj się</a> | <a href="index.php">Strona główna</a></p>
</div>
</body>
</html>