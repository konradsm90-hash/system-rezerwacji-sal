<?php
// Włączenie pokazywania błędów na czas testów
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'konfiguracja.php';

// Pobranie wybranego tygodnia
$tydzien_offset = isset($_GET['tydzien']) ? (int)$_GET['tydzien'] : 0;
$aktualna_data = new DateTime();
if ($tydzien_offset !== 0) {
    $aktualna_data->modify("{$tydzien_offset} weeks");
}
$aktualna_data->modify('monday this week');

$dni_tygodnia = [];
for ($i = 0; $i < 7; $i++) {
    $dni_tygodnia[] = $aktualna_data->format('Y-m-d');
    $aktualna_data->modify('+1 day');
}

// Pobranie sal z bazy
$sale = $pdo->query("SELECT * FROM sale")->fetchAll();

// Pobranie rezerwacji na ten tydzień
$stmt = $pdo->prepare("
    SELECT r.*, u.login, s.nazwa as nazwa_sali 
    FROM rezerwacje r
    JOIN uzytkownicy u ON r.id_uzytkownika = u.id
    JOIN sale s ON r.id_sali = s.id
    WHERE r.data_rezerwacji BETWEEN ? AND ?
");
$stmt->execute([$dni_tygodnia[0], $dni_tygodnia[6]]);
$rezerwacje = $stmt->fetchAll();

// Organizacja rezerwacji do tablicy wielowymiarowej
$macierz = [];
foreach ($rezerwacje as $rez) {
    $macierz[$rez['id_sali']][$rez['data_rezerwacji']][] = $rez;
}

// Słownik do tłumaczenia dni tygodnia na polski
$dni_pl = [
    'Mon' => 'Pon',
    'Tue' => 'Wt',
    'Wed' => 'Śr',
    'Thu' => 'Czw',
    'Fri' => 'Pt',
    'Sat' => 'Sob',
    'Sun' => 'Nd'
];
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>System Rezerwacji Sal - Harmonogram</title>
    <link rel="stylesheet" href="zasoby/styl.css">
</head>
<body>
    <div class="container">
        <h1>Tygodniowy harmonogram sal</h1>
        
        <div class="nav-links">
            <?php if (isset($_SESSION['id_uzytkownika'])): ?>
                <a href="panel.php">Panel użytkownika (<?= htmlspecialchars($_SESSION['login']) ?>)</a> |
                <?php if ($_SESSION['rola'] === 'admin'): ?>
                    <a href="administrator.php">Panel Administratora</a> |
                <?php endif; ?>
                <a href="wylogowanie.php">Wyloguj się</a>
            <?php else: ?>
                <a href="logowanie.php">Zaloguj się</a> | 
                <a href="rejestracja.php">Zarejestruj się</a>
            <?php endif; ?>
        </div>

        <div class="week-nav">
            <a href="?tydzien=<?= $tydzien_offset - 1 ?>">&laquo; Poprzedni tydzień</a>
            <span style="margin: 0 15px; font-weight: bold;">Tydzień od <?= $dni_tygodnia[0] ?> do <?= $dni_tygodnia[6] ?></span>
            <a href="?tydzien=<?= $tydzien_offset + 1 ?>">Następny tydzień &raquo;</a>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Sala</th>
                    <?php foreach ($dni_tygodnia as $dzien): ?>
                        <?php 
                            $ang_skrot = date('D', strtotime($dzien));
                            $pl_skrot = $dni_pl[$ang_skrot] ?? $ang_skrot;
                        ?>
                        <th><?= $dzien ?><br><small><?= $pl_skrot ?></small></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sale)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: #777;">Brak sal w bazie danych. Zaloguj się jako administrator, aby je dodać!</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sale as $sala): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($sala['nazwa']) ?></strong><br><small>Miejsc: <?= $sala['pojemnosc'] ?></small></td>
                            <?php foreach ($dni_tygodnia as $dzien): ?>
                                <td>
                                    <?php if (!empty($macierz[$sala['id']][$dzien])): ?>
                                        <?php foreach ($macierz[$sala['id']][$dzien] as $r): ?>
                                            <div class="rez-box">
                                                <strong><?= substr($r['godzina_od'], 0, 5) ?> - <?= substr($r['godzina_do'], 0, 5) ?></strong><br>
                                                <small>Przez: <?= htmlspecialchars($r['login']) ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="wolne">Wolne</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>