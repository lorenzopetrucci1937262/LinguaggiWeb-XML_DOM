<?php
require __DIR__ . '/accesso.php';
require __DIR__ . '/operazioni.php';
session_start();
$_SESSION['token'] ??= bin2hex(random_bytes(24));
function h($s) { return htmlspecialchars($s,ENT_QUOTES,'UTF-8'); }
function token() { echo '<input type="hidden" name="token" value="'.h($_SESSION['token']).'">'; }
$errore = '';
$sale = [];
foreach (apriXml('cinema.xml')->getElementsByTagName('sala') as $s) {
    $sale[$s->getAttribute('id')] = $s->getElementsByTagName('nome')->item(0)->textContent;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($_POST as $v) if (!is_string($v)) throw new DomainException('Modulo non valido.');
        if (!hash_equals($_SESSION['token'], $_POST['token'] ?? '')) throw new DomainException('Ricarica il modulo.');
        $azione = $_POST['azione'] ?? '';
        if ($azione === 'login') {
            if (($_POST['utente'] ?? '') !== 'admin' || !password_verify($_POST['password'] ?? '', HASH_ADMIN)) {
                throw new DomainException('Credenziali errate.');
            }
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
        } elseif (empty($_SESSION['admin'])) {
            throw new DomainException('Accedi alla gestione.');
        } elseif ($azione === 'logout') {
            $_SESSION = [];
            session_destroy();
        } else {
            $risultato = salvaModifica($sale,$_POST);
            $_SESSION['esito'] = $azione === 'rientro' ? 'Film riportato in calendario.' : 'Programmazione salvata.';
            if ($azione === 'rimuoviTerminate') $_SESSION['esito'] = "Proiezioni terminate rimosse: $risultato.";
            if ($azione === 'importaSchede') $_SESSION['esito'] = "Schede aggiunte o datate: $risultato.";
        }
        header('Location: admin.php?film=' . rawurlencode($_POST['film'] ?? ''),true,303);
        exit;
    } catch (DomainException | RuntimeException $e) { $errore = $e->getMessage(); }
}
?>
<!doctype html><html lang="it">
    <head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Gestione proiezioni · Artemis</title><link rel="stylesheet" href="artemis.css">
    </head>
    <body><?php require __DIR__.'/menu-artemis.php'; ?>
        <a href="programmazione.php">Torna alla programmazione</a>
        <h1>Gestione proiezioni</h1>

        <?php if ($errore): ?><p role="alert"><?=h($errore)?></p><?php endif; ?>
        <?php if (empty($_SESSION['admin'])): ?>
        <form method="post"><?php token(); ?>
        <label>Utente <input name="utente" required autocomplete="username"></label>
        <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
        <button name="azione" value="login">Accedi</button></form>

        <?php else:
        $doc = apriXml('programmazione.xml');
        $versione = hash('sha256',$doc->saveXML());
        $films = [];
        foreach ($doc->getElementsByTagName('film') as $f) $films[$f->getAttribute('id')] = $f;
        $id = $_POST['film'] ?? $_GET['film'] ?? '';
        if (!is_string($id) || !isset($films[$id])) $id = array_key_first($films);
        $originale = $_POST['originale'] ?? $_GET['originale'] ?? '';
        $film = $films[$id] ?? null;
        $scelta = null;
        if ($film) {
            foreach ($film->getElementsByTagName('proiezione') as $p) {
                if (chiave($p) === $originale) $scelta = $p;
            }
        }
        if (!empty($_SESSION['esito'])) { echo '<p role="status">'.h($_SESSION['esito']).'</p>'; unset($_SESSION['esito']); }
        ?>

        <form method="post"><?php token(); ?><button name="azione" value="logout">Esci</button></form>
        <?php if ((new DOMXPath($doc))->query('//proiezione[not(@id)]')->length): ?>

        <form method="post"><?php token(); ?><input type="hidden" name="versione" value="<?=h($versione)?>">
        <button name="azione" value="assegnaCodici">Assegna i codici alle proiezioni esistenti</button></form>

        <?php endif; ?>
        <form method="post"><?php token(); ?><input type="hidden" name="versione" value="<?=h($versione)?>">
        <button name="azione" value="importaSchede">Aggiorna schede e date di uscita</button></form>

        <form method="post"><?php token(); ?>
        <input type="hidden" name="versione" value="<?=h($versione)?>">
        <p>Rimuovi dal cartellone gli spettacoli già conclusi, calcolati in base alla durata del film. I biglietti restano nello storico personale.</p>
        <button name="azione" value="rimuoviTerminate">Rimuovi proiezioni terminate</button></form>

        <p><a href="gestione-film.php">Nuovo film in calendario</a></p>
        <form method="get"><label>Film <select name="film">
        <?php foreach ($films as $k=>$f): ?><option value="<?=h($k)?>" <?=$k===$id?'selected':''?>><?=h(titolo($f))?></option><?php endforeach; ?>
        </select></label><button>Apri scheda</button></form>

        <?php if ($film): ?>
        <h2><?=h(titolo($film))?></h2>

        <p><a href="gestione-film.php?id=<?=h(rawurlencode($id))?>">Modifica scheda film</a></p>
        <?php $spettacoli = $film->getElementsByTagName('proiezione'); if ($spettacoli->length): ?>
        <details><summary>Riporta in calendario</summary>

        <?php $righe = riepilogoRientro($film); $posti = array_sum(array_column($righe,'posti')); ?>
        <p>Rientro di <strong><?=h(titolo($film))?></strong>. Spettacoli da rimuovere: <?=count($righe)?>.</p>

        <table>
            <thead><tr><th>Data</th><th>Ora</th><th>Sala</th><th>Posti prenotati</th></tr></thead>
            <tbody>
                <?php foreach ($righe as $riga): $p = $riga['proiezione']; ?>
                <tr><td><?=h($p->getAttribute('data'))?></td><td><?=h(substr($p->getAttribute('ora'),0,5))?></td>
                <td><span class="sala-<?=h($p->getAttribute('sala'))?>"><?=h($sale[$p->getAttribute('sala')])?></span></td><td><?=h($riga['posti'])?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($posti): ?><p>Rientro bloccato: ci sono <?=h($posti)?> posti prenotati.</p>
        <?php else: ?><p>Confermando rimuovi tutti gli orari elencati. La scheda del film resta nel catalogo.</p>
        
        <form method="post"><?php token(); ?>
        <input type="hidden" name="versione" value="<?=h($versione)?>">
        <input type="hidden" name="film" value="<?=h($id)?>">
        <button name="azione" value="rientro">Conferma il rientro in calendario</button></form>

        <?php endif; ?></details>
        <ul><?php foreach ($spettacoli as $p): ?><li>
        <a href="?<?=h(http_build_query(['film'=>$id,'originale'=>chiave($p)]))?>"><?=h($p->getAttribute('data'))?> · <?=h(substr($p->getAttribute('ora'),0,5))?> · <span class="sala-<?=h($p->getAttribute('sala'))?>"><?=h($sale[$p->getAttribute('sala')])?></span></a>
        </li><?php endforeach; ?></ul>
        <?php else: ?><p>Film in calendario, in attesa della prima proiezione.</p><?php endif; ?>

        <h3><?=$scelta?'Modifica proiezione':'Nuova proiezione'?></h3>
        <?php if ($scelta): ?><a href="?film=<?=h(rawurlencode($id))?>">Passa a nuova proiezione</a><?php endif; ?>
        
        <form method="post"><?php token(); ?>
            <input type="hidden" name="versione" value="<?=h($versione)?>">
            <input type="hidden" name="film" value="<?=h($id)?>">
            <input type="hidden" name="originale" value="<?=h($scelta ? chiave($scelta) : '')?>">
            <label>Sala <select name="sala"><?php foreach ($sale as $k=>$nome): ?><option class="sala-<?=h($k)?>" value="<?=h($k)?>" <?=$scelta && $scelta->getAttribute('sala')===$k?'selected':''?>><?=h($nome)?></option><?php endforeach; ?></select></label>
            <label>Giorno <input type="date" name="data" value="<?=h($scelta ? $scelta->getAttribute('data') : date('Y-m-d'))?>" required></label>
            <label>Inizio <input type="time" name="ora" min="15:30" max="22:00" value="<?=h($scelta ? substr($scelta->getAttribute('ora'),0,5) : '18:30')?>" required></label>
            <?php if (!$film->getElementsByTagName('durata')->length): ?>
            <label>Durata del film in minuti <input type="number" name="durata" min="1" required></label><?php endif; ?>
            <label><input type="checkbox" name="in3D" <?=$scelta && in_array($scelta->getAttribute('in3D'),['true','1'],true)?'checked':''?>> Proiezione 3D</label>
            <button name="azione" value="<?=$scelta?'modifica':'aggiungi'?>">Salva proiezione</button>
            <?php if ($scelta): ?><button name="azione" value="elimina" formnovalidate onclick="return confirm('Eliminare la proiezione selezionata?')">Elimina proiezione</button><?php endif; ?>
        </form>
        <?php endif; endif; ?>
        <?php require __DIR__.'/fondo-artemis.php'; ?>
        
    </body>
</html>
