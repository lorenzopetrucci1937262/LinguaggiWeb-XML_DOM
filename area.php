<?php
require __DIR__ . '/operazioni.php';
session_start();
$_SESSION['tokenUtente'] ??= bin2hex(random_bytes(24));
function h($s) { return htmlspecialchars($s,ENT_QUOTES,'UTF-8'); }
function tokenUtente() { echo '<input type="hidden" name="token" value="'.h($_SESSION['tokenUtente']).'">'; }
function descrivi($p) {
    $sala = $p->getAttribute('sala');
    return h(titolo($p->parentNode).' · '.date('d/m/Y H:i',inizioSpettacolo($p)))
        .' · <span class="sala-'.h($sala).'">Sala '.h(ucfirst($sala)).'</span>';
}
$id = $_GET['p'] ?? '';
if (!is_string($id)) $id = '';
$errore = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($_POST as $v) if (!is_string($v)) throw new DomainException('Modulo non valido.');
        if (!hash_equals($_SESSION['tokenUtente'],$_POST['token'] ?? '')) throw new DomainException('Ricarica la pagina.');
        $azione = $_POST['azione'] ?? '';
        $utente = $_SESSION['spettatoreSql'] ?? '';
        if ($azione === 'registrati' || $azione === 'accedi') {
            $nome = strtolower(trim($_POST['username'] ?? ''));
            $password = $_POST['password'] ?? '';
            if ($azione === 'registrati') {
                if (!preg_match('/^[a-z0-9_.-]{3,32}$/D',$nome) || strlen($password)<8 || strlen($password)>72 || strpos($password,"\0")!==false) {
                    throw new DomainException('Username di 3–32 caratteri (lettere, numeri, punto, trattino o underscore) e password di 8–72 byte.');
                }
                $utente = registraUtente($nome, $password);
            } else {
                $utente = accediUtente($nome, $password);
            }
            session_regenerate_id(true);
            $_SESSION['spettatoreSql'] = $utente;
            $_SESSION['nomeSpettatore'] = $nome;
            $_SESSION['tokenUtente'] = bin2hex(random_bytes(24));
        } elseif (!$utente) {
            throw new DomainException('Accedi per gestire le tue prenotazioni.');
        } elseif ($azione === 'esci') {
            unset($_SESSION['spettatoreSql'],$_SESSION['nomeSpettatore']);
            $_SESSION['tokenUtente'] = bin2hex(random_bytes(24));
            session_regenerate_id(true);
        } elseif ($azione === 'prenota') {
            $codice = prenotaPosti($utente,$id,$_POST['posti'] ?? '',$_POST['spettacolo'] ?? '');
            $_SESSION['messaggioUtente'] = 'Prenotazione confermata: '.$codice;
            $_SESSION['tokenUtente'] = bin2hex(random_bytes(24));
        } elseif ($azione === 'annulla') {
            annullaPrenotazione($utente,$_POST['codice'] ?? '');
            $_SESSION['messaggioUtente'] = 'Prenotazione annullata.';
        } else throw new DomainException('Operazione sconosciuta.');
        header('Location: area.php'.($id ? '?p='.rawurlencode($id) : ''),true,303);
        exit;
    } catch (DomainException | RuntimeException $e) { $errore = $e->getMessage(); }
}
$programma = apriXml('programmazione.xml');
$utente = $_SESSION['spettatoreSql'] ?? '';
$p = $id === '' ? null : perAttributo($programma,'proiezione','id',$id);
?>
<!doctype html><html lang="it">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Prenotazioni · Artemis</title>
        <link rel="stylesheet" href="artemis.css">
    </head>
    <body>
        <?php require __DIR__.'/menu-artemis.php'; ?>

        <main>
            <h1>Prenotazioni</h1>
            <?php if ($errore): ?><p role="alert"><?=h($errore)?></p><?php endif; ?>
            <?php if (isset($_SESSION['messaggioUtente'])): ?><p role="status"><?=h($_SESSION['messaggioUtente'])?></p><?php unset($_SESSION['messaggioUtente']); endif; ?>
            <?php if ($id && !$p): ?><p>Proiezione non disponibile.</p><?php endif; ?>
            <?php if ($p):
            $liberi = capienze()[$p->getAttribute('sala')] - postiPrenotati($id); ?>

            <h2><?=descrivi($p)?></h2>

            <?php if (inizioSpettacolo($p) <= time()): ?><p>Prenotazioni chiuse.</p>
            <?php elseif ($liberi === 0): ?><p>Spettacolo esaurito.</p>
            <?php else: ?><p>Posti disponibili: <?=h($liberi)?></p>

            <?php if ($utente): ?><form method="post"><?php tokenUtente(); ?>
                <input type="hidden" name="spettacolo" value="<?=h(chiave($p))?>">
                <label>Quanti posti? <input type="number" required name="posti" min="1" max="<?=h($liberi)?>" value="1"></label>
                <button name="azione" value="prenota">Conferma prenotazione</button>
            </form>
            <?php endif; endif; endif; ?>
            <?php if (!$utente): ?><h2>Accedi o crea un account</h2>
            <form method="post"><?php tokenUtente(); ?>
                <label>Username <input name="username" required autocomplete="username"></label>
                <label>Password <input type="password" name="password" required></label>
                <button name="azione" value="accedi">Accedi</button>
                <button name="azione" value="registrati">Registrati</button>
            </form>
            <?php else: ?><p>Utente: <?=h($_SESSION['nomeSpettatore'])?></p>

            <form method="post"><?php tokenUtente(); ?>
                <button name="azione" value="esci">Esci</button>
            </form>
            <h2>Le mie prenotazioni</h2>
            <?php $prenotazioni = prenotazioniUtente($utente);
            foreach ($prenotazioni as $r):
                $spettacolo = perAttributo($programma, 'proiezione', 'id', $r['proiezione']);
                $storico = $r['riepilogo'] ?? ''; ?>
            <article>
                <h3><?=$spettacolo ? descrivi($spettacolo) : ($storico !== '' ? h($storico).' · Conclusa' : 'Prenotazione fuori dal cartellone')?></h3>
                <p>Codice <?=h(codicePrenotazione($r['proiezione'], $r['id']))?> · Posti: <?=h($r['posti'])?></p>
                <?php if ($spettacolo && !proiezioneTerminata($spettacolo, time())): ?>
                    <form method="post"><?php tokenUtente(); ?><input type="hidden" name="codice" value="<?=h($r['id'])?>">
                    <button name="azione" value="annulla">Annulla prenotazione</button>
                </form><?php endif; ?>
            </article>
            <?php endforeach; if (!$prenotazioni): ?><p>Nessuna prenotazione.</p><?php endif; endif; ?>
        </main>
        <?php require __DIR__.'/fondo-artemis.php'; ?>
    </body>
</html>
