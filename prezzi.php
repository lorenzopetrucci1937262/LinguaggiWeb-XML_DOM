<?php
$doc = new DOMDocument();

if (!$doc->load(__DIR__ . '/cinema.xml')) exit('Listino non disponibile.');

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function campo($nodo, $nome) { return $nodo->getElementsByTagName($nome)->item(0)->textContent; }

function euro($valore) { return number_format((float)$valore, 2, ',', '.') . ' €'; }
?>

<!doctype html><html lang="it">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Prezzi · Cinema Artemis</title><link rel="stylesheet" href="artemis.css">
</head>
<body>
    <?php require __DIR__.'/menu-artemis.php'; ?>
    <main class="info-cinema">
    <h1>Il nostro cinema</h1>
    <p><?=h(campo($doc,'indirizzo'))?></p>

    <h2>Sale</h2>
    <table>
        <thead><tr><th>Sala</th><th>File</th><th>Posti</th></tr></thead>
        <tbody>
            <?php foreach ($doc->getElementsByTagName('sala') as $s): ?><tr>
                <td class="sala-<?=h($s->getAttribute('id'))?>"><?=h(campo($s,'nome'))?></td>
                <td><?=h(campo($s,'file'))?></td>
                <td><?=h(campo($s,'posti'))?></td>
            </tr><?php endforeach; ?>
        </tbody>
    </table>
                
    <h2>Prezzi e abbonamenti</h2>
    <table>
        <thead><tr><th>Tariffa</th><th>Quando</th><th>Intero</th><th>Ridotto</th></tr></thead>
        <tbody>
            <?php foreach ($doc->getElementsByTagName('tariffa') as $t): ?>
            <tr><td><?=h($t->getAttribute('nome'))?></td>
            <td><?=h(campo($t,'condizione'))?></td>
            <td><?=euro(campo($t,'intero'))?></td>
            <td><?=euro(campo($t,'ridotto'))?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p>Ridotto per <?=h(campo($doc,'riduzione'))?></p>
    <p>Supplemento per proiezioni 3D: <?=euro(campo($doc,'supplemento3D'))?>.</p>

    <h2>Abbonamenti</h2><ul class="abbonamenti">
    <?php foreach ($doc->getElementsByTagName('abbonamento') as $a): ?>
    <li><h3><?=h($a->getAttribute('nome'))?></h3><p class="quota"><?=euro(campo($a,'prezzo'))?></p>
    <p><strong><?=h(campo($a,'ingressi'))?> ingressi</strong></p><p><?=h(campo($a,'condizione'))?></p></li>
    <?php endforeach; ?></ul></main><?php require __DIR__.'/fondo-artemis.php'; ?>
</body>
</html>
