<?php
require __DIR__.'/operazioni.php';
try {
    database();
} catch (RuntimeException $e) {
    http_response_code(503);
    exit(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
function h($s) { return htmlspecialchars($s,ENT_QUOTES,'UTF-8'); }
$doc = apriXml('programmazione.xml');
$films = filmPerUscita($doc,date('Y-m-d'));
$inSala = [];
$prossimi = [];

foreach ($films as $film) {
    $orari = array_filter(iterator_to_array($film->getElementsByTagName('proiezione')),'nellaSettimanaPubblica');
    if ($orari) $inSala[] = $film;
    elseif (gruppoUscita($film,date('Y-m-d')) === 0) $prossimi[] = $film;

}
$ultimo = max(0, count($inSala) - 3);
$richiesto = filter_input(INPUT_GET, 'primo', FILTER_VALIDATE_INT);
$primo = max(0, min($ultimo, (int)$richiesto));
$visibili = array_slice($inSala, $primo, 3);
?>
<!doctype html><html lang="it">
    <head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Cinema Artemis · Terracina</title><link rel="stylesheet" href="artemis.css">
    </head>
    <body>
        <?php require __DIR__.'/menu-artemis.php'; ?>

        <main>
            <div class="titolo-vetrina">
            <h1>Al cinema questa settimana</h1>
            <?php if ($ultimo > 0): ?>
            <nav id="comandi" aria-label="Scorri i film in sala">
            <?php if ($primo > 0): ?><a href="?primo=<?=$primo-1?>" aria-label="Film precedente">←</a>
            <?php else: ?><span aria-hidden="true">←</span><?php endif; ?>
            <?php if ($primo < $ultimo): ?><a href="?primo=<?=$primo+1?>" aria-label="Film successivo">→</a>
            <?php else: ?><span aria-hidden="true">→</span><?php endif; ?>
            </nav><?php endif; ?></div>

            <?php if (!$inSala): ?><p>La programmazione della settimana sarà pubblicata qui.</p>
            <?php else: ?><section class="carosello" id="carosello" aria-label="Film in proiezione">
                <?php foreach ($visibili as $film): $link = 'film.php?id='.rawurlencode($film->getAttribute('id')); ?>
                <article class="scheda">
                <a class="invito-film" href="<?=h($link)?>">
                <?php if (locandinaFilm($film)): ?><img class="poster-home" src="<?=h(locandinaFilm($film))?>" alt="<?=h(titolo($film))?>">
                <?php endif; ?><h2><?=h(titolo($film))?></h2></a>
                <p><strong>Generi</strong> · <?=h(implode(' · ',valoriFilm($film,'genere')))?></p>
                <p><?=h(valoriFilm($film,'trama')[0])?></p></article>
                <?php endforeach; ?>
            </section><?php endif; ?>

            <section class="uscite-home">
                <h2>Prossime uscite</h2>
                <?php if ($prossimi): ?><ul>
                <?php foreach (array_slice($prossimi,0,3) as $film): ?>
                <li><time datetime="<?=h($film->getAttribute('uscita'))?>"><?=h(dataItaliana($film))?></time>
                <a href="film.php?id=<?=h(rawurlencode($film->getAttribute('id')))?>"><?=h(titolo($film))?></a></li>
                <?php endforeach; ?></ul>
                <?php else: ?><p>Le prossime date di uscita sono da confermare.</p><?php endif; ?>
                <a href="programmazione.php">Tutti gli orari e i posti disponibili</a>
            </section>
        </main>
        <?php require __DIR__.'/fondo-artemis.php'; ?>
    </body>
</html>
