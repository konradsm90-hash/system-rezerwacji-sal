<?php
require_once 'konfiguracja.php';

if (!isset($_SESSION['id_uzytkownika'])) {
    header('Location: logowanie.php');
    exit;
}

$blad = '';
$sukces = '';

// Domyślne wartości czasu (dzisiaj, godz. 10:00 - 12:00)
$podglad_data = isset($_REQUEST['podglad_data']) ? $_REQUEST['podglad_data'] : date('Y-m-d');
$podglad_od = isset($_REQUEST['podglad_od']) ? $_REQUEST['podglad_od'] : '10:00';
$podglad_do = isset($_REQUEST['podglad_do']) ? $_REQUEST['podglad_do'] : '12:00';

// Formatowanie do czytelnego formatu 24h (HH:MM)
$podglad_od_24 = date('H:i', strtotime($podglad_od));
$podglad_do_24 = date('H:i', strtotime($podglad_do));

// 1. Obsługa formularza rezerwacji
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rezerwuj'])) {
    $id_sali = (int)$_POST['id_sali'];
    $data = $_POST['data_rezerwacji'];
    $godzina_od = $_POST['godzina_od'];
    $godzina_do = $_POST['godzina_do'];
    $cykl_tygodni = isset($_POST['cykl_tygodni']) ? (int)$_POST['cykl_tygodni'] : 1;
    $id_uzytkownika = $_SESSION['id_uzytkownika'];
    $dzisiaj = date('Y-m-d');

    if ($data < $dzisiaj) {
        $blad = "Nie można rezerwować sal w przeszłości!";
    } elseif ($godzina_od >= $godzina_do) {
        $blad = "Godzina zakończenia musi być późniejsza niż godzina rozpoczęcia!";
    } else {
        $kolizja_wykryta = false;
        $daty_do_rezerwacji = [];

        for ($i = 0; $i < $cykl_tygodni; $i++) {
            $biezaca_data = date('Y-m-d', strtotime("$data +$i week"));
            
            $sprawdz = $pdo->prepare("
                SELECT COUNT(*) FROM rezerwacje 
                WHERE id_sali = ? 
                  AND data_rezerwacji = ? 
                  AND (godzina_od < ? AND godzina_do > ?)
            ");
            $sprawdz->execute([$id_sali, $biezaca_data, $godzina_do, $godzina_od]);
            
            if ($sprawdz->fetchColumn() > 0) {
                $kolizja_wykryta = true;
                $blad = "Ta sala jest już zarezerwowana w wybranym terminie ($biezaca_data)!";
                break;
            }
            $daty_do_rezerwacji[] = $biezaca_data;
        }

        if (!$kolizja_wykryta) {
            $ins = $pdo->prepare("INSERT INTO rezerwacje (id_sali, id_uzytkownika, data_rezerwacji, godzina_od, godzina_do) VALUES (?, ?, ?, ?, ?)");
            foreach ($daty_do_rezerwacji as $d) {
                $ins->execute([$id_sali, $id_uzytkownika, $d, $godzina_od, $godzina_do]);
            }
            $sukces = $cykl_tygodni > 1 ? "Pomyślnie utworzono rezerwację cykliczną ($cykl_tygodni tyg.)!" : "Sala została pomyślnie zarezerwowana!";
            
            $podglad_data = $data;
            $podglad_od = $godzina_od;
            $podglad_do = $godzina_do;
            $podglad_od_24 = date('H:i', strtotime($podglad_od));
            $podglad_do_24 = date('H:i', strtotime($podglad_do));
        }
    }
}

// 2. Anulowanie rezerwacji
if (isset($_GET['anuluj'])) {
    $id_rez = (int)$_GET['anuluj'];
    $del = $pdo->prepare("DELETE FROM rezerwacje WHERE id = ? AND id_uzytkownika = ?");
    $del->execute([$id_rez, $_SESSION['id_uzytkownika']]);
    header("Location: panel.php?podglad_data=$podglad_data&podglad_od=$podglad_od&podglad_do=$podglad_do");
    exit;
}

// Pobranie sal
$sale = $pdo->query("SELECT * FROM sale ORDER BY id ASC")->fetchAll();

// Sprawdzanie dostępności
$sprawdz_mapa = $pdo->prepare("
    SELECT DISTINCT id_sali FROM rezerwacje 
    WHERE data_rezerwacji = ? 
      AND (godzina_od < ? AND godzina_do > ?)
");
$sprawdz_mapa->execute([$podglad_data, $podglad_do, $podglad_od]);
$zajete_sale_ids = $sprawdz_mapa->fetchAll(PDO::FETCH_COLUMN);

// Pobranie własnych rezerwacji
$moje = $pdo->prepare("
    SELECT r.*, s.nazwa as nazwa_sali 
    FROM rezerwacje r 
    JOIN sale s ON r.id_sali = s.id 
    WHERE r.id_uzytkownika = ? 
    ORDER BY r.data_rezerwacji DESC, r.godzina_od ASC
");
$moje->execute([$_SESSION['id_uzytkownika']]);
$moje_rezerwacje = $moje->fetchAll();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Panel Użytkownika - Rezerwacja Sal</title>
    <link rel="stylesheet" href="zasoby/styl.css">
    <style>
        .filtr-bar {
            background: #eef2ff;
            border: 2px solid #c7d2fe;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        .filtr-bar form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .filtr-bar label {
            margin-bottom: 0;
            flex: 1;
            min-width: 160px;
        }
        .filtr-bar button {
            margin-top: 0;
            width: auto;
            padding: 11px 25px;
            background: #4f46e5;
        }
        .mapa-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .sala-karta {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            background: #ffffff;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: border-color 0.3s, background-color 0.3s;
        }
        .sala-karta.wolna {
            border-color: #10b981;
            background: #f0fdf4;
        }
        .sala-karta.zajeta {
            border-color: #ef4444;
            background: #fef2f2;
        }
        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
            color: white;
        }
        .status-badge.wolna { background-color: #10b981; }
        .status-badge.zajeta { background-color: #ef4444; }

        .opis-wyposazenie {
            font-size: 0.9em;
            color: #4b5563;
            margin: 12px 0;
            line-height: 1.4;
            background: rgba(255,255,255,0.7);
            padding: 10px;
            border-radius: 6px;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .form-karta-pola {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 10px;
        }
        .form-karta-pola label {
            font-size: 0.8em;
            font-weight: 600;
            margin-bottom: 2px;
            color: #374151;
        }
        .form-karta-pola input, .form-karta-pola select {
            padding: 6px 8px;
            font-size: 0.85em;
            margin-top: 2px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Panel Użytkownika</h1>
    
    <div class="nav-links">
        <a href="index.php"> Harmonogram tygodniowy</a> | 
        <?php if ($_SESSION['rola'] === 'admin'): ?>
            <a href="administrator.php">Panel Administratora</a> | 
        <?php endif; ?>
        <a href="wylogowanie.php">Wyloguj się (<?= htmlspecialchars($_SESSION['login']) ?>)</a>
    </div>

    <?php if ($blad): ?><p class="blad"><?= $blad ?></p><?php endif; ?>
    <?php if ($sukces): ?><p class="sukces"><?= $sukces ?></p><?php endif; ?>

    <!-- Wyszukiwarka terminów -->
    <div class="filtr-bar">
        <h3 style="margin-bottom:12px; border:none; padding:0;"> Sprawdź dostępność sal w terminie:</h3>
        <form method="GET" action="panel.php">
            <label>Data: 
                <input type="date" name="podglad_data" value="<?= htmlspecialchars($podglad_data) ?>" min="<?= date('Y-m-d') ?>" required>
            </label>
            <label>Godzina Od: 
                <input type="time" name="podglad_od" value="<?= htmlspecialchars($podglad_od_24) ?>" step="60" required>
            </label>
            <label>Godzina Do: 
                <input type="time" name="podglad_do" value="<?= htmlspecialchars($podglad_do_24) ?>" step="60" required>
            </label>
            <button type="submit">Sprawdź dostępność</button>
        </form>
    </div>

    <h3> Mapa Sal (Termin: <?= htmlspecialchars($podglad_data) ?>, godz. <?= htmlspecialchars($podglad_od_24) ?> – <?= htmlspecialchars($podglad_do_24) ?>):</h3>
    
    <div class="mapa-grid">
        <?php foreach ($sale as $s): ?>
            <?php 
                $jest_zajeta = in_array($s['id'], $zajete_sale_ids);
                $klasa_statusu = $jest_zajeta ? 'zajeta' : 'wolna';
                $etykieta_statusu = $jest_zajeta ? '🔴 ZAREZERWOWANA' : '🟢 WOLNA';
            ?>
            <div class="sala-karta <?= $klasa_statusu ?>">
                <div>
                    <span class="status-badge <?= $klasa_statusu ?>"><?= $etykieta_statusu ?></span>
                    <h3 style="margin-right: 130px; border:none; padding:0;"><?= htmlspecialchars($s['nazwa']) ?></h3>
                    <p style="margin: 5px 0;"><strong>Miejsc:</strong> <?= $s['pojemnosc'] ?> osób</p>
                    
                    <div class="opis-wyposazenie">
                        <strong> Wyposażenie:</strong><br>
                        <?= nl2br(htmlspecialchars($s['opis'])) ?>
                    </div>
                </div>

                <div>
                    <form method="POST" style="margin-top: 10px;">
                        <input type="hidden" name="id_sali" value="<?= $s['id'] ?>">

                        <div class="form-karta-pola">
                            <label style="grid-column: span 2;">Data rezerwacji:
                                <input type="date" name="data_rezerwacji" value="<?= htmlspecialchars($podglad_data) ?>" min="<?= date('Y-m-d') ?>" required>
                            </label>
                            <label>Od godziny:
                                <input type="time" name="godzina_od" value="<?= htmlspecialchars($podglad_od_24) ?>" step="60" required>
                            </label>
                            <label>Do godziny:
                                <input type="time" name="godzina_do" value="<?= htmlspecialchars($podglad_do_24) ?>" step="60" required>
                            </label>
                            <label style="grid-column: span 2;">Cykl powtarzania:
                                <select name="cykl_tygodni" <?= $jest_zajeta ? 'disabled' : '' ?>>
                                    <option value="1">Tylko w tym dniu</option>
                                    <option value="2">Co tydzień (2 tygodnie)</option>
                                    <option value="3">Co tydzień (3 tygodnie)</option>
                                    <option value="4">Co tydzień (4 tygodnie)</option>
                                </select>
                            </label>
                        </div>

                        <input type="hidden" name="podglad_data" value="<?= htmlspecialchars($podglad_data) ?>">
                        <input type="hidden" name="podglad_od" value="<?= htmlspecialchars($podglad_od_24) ?>">
                        <input type="hidden" name="podglad_do" value="<?= htmlspecialchars($podglad_do_24) ?>">

                        <?php if ($jest_zajeta): ?>
                            <button type="button" disabled style="background: #9ca3af; cursor: not-allowed; margin-top: 12px;">
                                Niedostępna w tym czasie
                            </button>
                        <?php else: ?>
                            <button type="submit" name="rezerwuj" style="background: #10b981; margin-top: 12px;">
                                Zarezerwuj tę salę
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <br><hr><br>

    <!-- Lista Moje Rezerwacje -->
    <h3> Moje Rezerwacje</h3>
    <table>
        <thead>
            <tr>
                <th>Sala</th>
                <th>Data</th>
                <th>Godziny</th>
                <th>Akcja</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($moje_rezerwacje)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: #777;">Nie masz jeszcze żadnych zarezerwowanych sal.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($moje_rezerwacje as $r): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['nazwa_sali']) ?></strong></td>
                    <td><?= $r['data_rezerwacji'] ?></td>
                    <td><?= date('H:i', strtotime($r['godzina_od'])) ?> - <?= date('H:i', strtotime($r['godzina_do'])) ?></td>
                    <td>
                        <a href="panel.php?anuluj=<?= $r['id'] ?>&podglad_data=<?= $podglad_data ?>&podglad_od=<?= $podglad_od_24 ?>&podglad_do=<?= $podglad_do_24 ?>" onclick="return confirm('Czy na pewno chcesz anulować tę rezerwację?')" style="color: #dc2626; font-weight: 600; text-decoration: none;">Anuluj</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>