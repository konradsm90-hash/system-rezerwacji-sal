<?php
require_once 'konfiguracja.php';

// Sprawdzenie czy użytkownik jest adminem
if (!isset($_SESSION['id_uzytkownika']) || $_SESSION['rola'] !== 'admin') {
    die("Brak dostępu. Tę stronę może przeglądać tylko administrator.");
}

$blad = '';
$sukces = '';

// Dodawanie nowej sali
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_sale'])) {
    $nazwa = trim($_POST['nazwa']);
    $pojemnosc = (int)$_POST['pojemnosc'];
    $opis = trim($_POST['opis']);

    if (!empty($nazwa) && $pojemnosc > 0) {
        $ins = $pdo->prepare("INSERT INTO sale (nazwa, pojemnosc, opis) VALUES (?, ?, ?)");
        $ins->execute([$nazwa, $pojemnosc, $opis]);
        $sukces = "Sala została pomyślnie dodana!";
    } else {
        $blad = "Wprowadź poprawne dane sali!";
    }
}

// Usuwanie sali
if (isset($_GET['usun'])) {
    $id_sali = (int)$_GET['usun'];
    $del = $pdo->prepare("DELETE FROM sale WHERE id = ?");
    $del->execute([$id_sali]);
    header('Location: administrator.php');
    exit;
}

$sale = $pdo->query("SELECT * FROM sale")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Panel Administratora - Zarządzanie Salami</title>
    <link rel="stylesheet" href="zasoby/styl.css">
    <style>
        /* Styl dedykowany do formularza w panelu admina */
        .form-admin {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .form-admin label {
            display: block;
            margin-bottom: 15px;
            font-weight: 600;
            color: #374151;
        }
        .form-admin input, 
        .form-admin textarea {
            width: 100%;
            padding: 10px 12px;
            margin-top: 6px;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1em;
        }
        .form-admin textarea {
            resize: vertical;
            height: 80px;
        }
        .form-admin button {
            background: #4f46e5;
            color: white;
            border: none;
            padding: 12px 20px;
            cursor: pointer;
            border-radius: 6px;
            font-size: 1em;
            font-weight: 600;
            width: 100%;
            margin-top: 10px;
            transition: background 0.2s;
        }
        .form-admin button:hover {
            background: #4338ca;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Panel Administratora</h1>
    
    <div class="nav-links">
        <a href="panel.php">&laquo; Powrót do panelu użytkownika</a> | 
        <a href="index.php">Strona główna</a> | 
        <a href="wylogowanie.php">Wyloguj się</a>
    </div>

    <?php if ($blad): ?><p class="blad"><?= $blad ?></p><?php endif; ?>
    <?php if ($sukces): ?><p class="sukces"><?= $sukces ?></p><?php endif; ?>

    <div class="form-admin">
        <h3>Dodaj nową salę</h3>
        <form method="POST">
            <label>Nazwa sali: 
                <input type="text" name="nazwa" placeholder="np. Sala Konferencyjna A" required>
            </label>
            <label>Pojemność (ilość osób): 
                <input type="number" name="pojemnosc" min="1" placeholder="np. 20" required>
            </label>
            <label>Opis sali: 
                <textarea name="opis" placeholder="Opcjonalny opis wyposażenia (np. projektor, tablica)..."></textarea>
            </label>
            <button type="submit" name="dodaj_sale">Dodaj salę do systemu</button>
        </form>
    </div>

    <h3>Lista sal w systemie</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nazwa</th>
                <th>Pojemność</th>
                <th>Opis</th>
                <th>Akcja</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($sale)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: #777;">Brak sal w bazie. Dodaj pierwszą powyżej!</td>
                </tr>
            <?php else: ?>
                <?php foreach ($sale as $sala): ?>
                <tr>
                    <td><?= $sala['id'] ?></td>
                    <td><strong><?= htmlspecialchars($sala['nazwa']) ?></strong></td>
                    <td><?= $sala['pojemnosc'] ?> osób</td>
                    <td><?= htmlspecialchars($sala['opis']) ?: '<em>Brak opisu</em>' ?></td>
                    <td>
                        <a href="administrator.php?usun=<?= $sala['id'] ?>" onclick="return confirm('Czy na pewno chcesz usunąć tę salę?')" style="color: #dc2626; text-decoration: none; font-weight: 600;">Usuń</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>