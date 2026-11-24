<?php
require __DIR__ . '/operazioni.php';
session_start();
if (empty($_SESSION['admin'])) {
    header('Location: admin.php');
    exit;
}
function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
$doc = apriXml('programmazione.xml');
$id = $_GET['id'] ?? '';
$film = cercaFilm($doc, $id);
if ($id !== '' && !$film) { http_response_code(404); exit('Film sconosciuto.'); }
$campi = ['titolo'=>'Titolo', 'uscita'=>'Uscita italiana', 'durata'=>'Durata in minuti', 'regia'=>'Regia',
          'genere'=>'Generi', 'attore'=>'Cast', 'trama'=>'Trama'];
foreach ($campi as $campo=>$nome) $valori[$campo] = $film ? implode("\n", valoriFilm($film, $campo)) : '';
$valori['uscita'] = $film ? $film->getAttribute('uscita') : '';
$limite = $film ? ($film->getAttribute('maxPerFascia') ?: '1') : '1';
$versione = hash('sha256', $doc->saveXML());
$errore = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($_POST as $v) if (!is_string($v)) throw new DomainException('Modulo non valido.');
        if (!hash_equals($_SESSION['token'], $_POST['token'] ?? '')) throw new DomainException('Ricarica il modulo.');
        if (($_POST['versione'] ?? '') !== $versione) throw new DomainException('Archivio aggiornato da un’altra scheda. Controlla i dati prima di riprovare.');
        $valori = array_intersect_key($_POST, $campi) + $valori;
        $limite = $_POST['limite'] ?? '1';
        $sale = [];
        foreach (apriXml('cinema.xml')->getElementsByTagName('sala') as $s) $sale[$s->getAttribute('id')] = true;
        $idSalvato = salvaModifica($sale, ['azione'=>'scheda','film'=>$id,'limite'=>$limite,'versione'=>$versione] + $valori);
        $_SESSION['esito'] = 'Scheda salvata.';
        header('Location: admin.php?film=' . rawurlencode($idSalvato), true, 303);
        exit;
    } catch (DomainException | RuntimeException $e) { $errore = $e->getMessage(); }
}
?>

<!doctype html><html lang="it">
    <head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Scheda film · Artemis</title><link rel="stylesheet" href="artemis.css">
    </head>
    <body>
        <?php require __DIR__.'/menu-artemis.php'; ?>
        <a href="admin.php">Gestione proiezioni</a>

        <h1><?= $film ? 'Modifica scheda' : 'Nuovo film in calendario' ?></h1>
        
        <?php if ($errore): ?><p role="alert"><?=h($errore)?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="token" value="<?=h($_SESSION['token'])?>">
            <input type="hidden" name="versione" value="<?=h($versione)?>">
            <?php foreach ($campi as $campo=>$nome): ?>
            <p><label><?=h($nome)?>
            <?php if ($campo === 'uscita'): ?><br>
            
            <input name="uscita" type="date" value="<?=h($valori[$campo])?>"><?php elseif ($campo === 'durata'): ?> (facoltativa fino alla prima proiezione)<br>
            <input name="durata" type="number" min="1" value="<?=h($valori[$campo])?>">

            <?php elseif ($campo === 'titolo'): ?><br><input name="titolo" required value="<?=h($valori[$campo])?>">
            <?php else: ?><?= $campo === 'trama' ? '' : ' (una voce per riga)' ?><br>

            <textarea name="<?=h($campo)?>" rows="3" cols="36" required><?=h($valori[$campo])?></textarea>
            <?php endif; ?></label></p>
            <?php endforeach; ?>
            <p><label>Proiezioni massime per fascia <select name="limite"><option value="1">1</option><option value="2" <?= $limite === '2' ? 'selected' : '' ?>>2 (eccezione)</option></select></label></p>
            <button>Salva scheda</button>
        </form>
        <?php require __DIR__.'/fondo-artemis.php'; ?>
    </body>
</html>
