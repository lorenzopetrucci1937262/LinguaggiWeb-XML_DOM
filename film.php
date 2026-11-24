<?php
require __DIR__ . '/operazioni.php';
function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
$doc = apriXml('programmazione.xml');
$id = $_GET['id'] ?? '';
$film = cercaFilm($doc, $id);
if ($id !== '' && !$film) http_response_code(404);
?>
<!doctype html><html lang="it">
    <head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?=h($film ? titolo($film) : 'Catalogo film')?> · Cinema Artemis</title>
        <link rel="stylesheet" href="artemis.css">
    </head>
    <body>
        <?php require __DIR__.'/menu-artemis.php'; ?>

        <main>
            <?php if ($id === ''): ?><h1>Catalogo film</h1>
            <?php $oggi = date('Y-m-d'); $gruppi = ['Prossime uscite','Già usciti','Data da confermare'];
            $precedente = null; foreach (filmPerUscita($doc,$oggi) as $f):
            $gruppo = gruppoUscita($f,$oggi);
            if ($precedente !== $gruppo): if ($precedente !== null) echo '</ul>'; ?>
            
            <h2><?=h($gruppi[$gruppo])?></h2>
            <ul class="catalogo">
            <?php $precedente = $gruppo; endif; ?>
            <li>
                <a class="voce-film" href="?id=<?=h(rawurlencode($f->getAttribute('id')))?>">
                    <?php if (locandinaFilm($f)): ?><img loading="lazy" src="<?=h(locandinaFilm($f))?>" alt=""><?php endif; ?>
                    <div class="dati-catalogo"><h3><?=h(titolo($f))?></h3>
                        <p><?=h(implode(' · ',valoriFilm($f,'genere')))?></p>
                        <?php if ($gruppo !== 2): ?><time datetime="<?=h($f->getAttribute('uscita'))?>"><?=h(dataItaliana($f))?></time><?php endif; ?>
                        <?php if (array_filter(iterator_to_array($f->getElementsByTagName('proiezione')), 'nellaSettimanaPubblica')): ?><small>In sala questa settimana</small><?php endif; ?>
                    </div>
                </a>
            </li>
            <?php endforeach; if ($precedente !== null) echo '</ul>'; ?>
            <?php elseif (!$film): ?><h1>Film sconosciuto</h1>
            <?php else: ?><div class="colonne dettaglio-film"><div>
            <article class="pannello-film"><h1><?=h(titolo($film))?></h1>
            <div class="corpo-scheda">
                <div class="testo-scheda">
                    <dl><dt>Uscita italiana</dt><dd><?=h(dataItaliana($film))?></dd>
                    <?php foreach (['regia'=>'Regia','genere'=>'Generi','attore'=>'Cast','durata'=>'Durata'] as $campo=>$nome):
                    $valori = valoriFilm($film, $campo); ?>
                    <dt><?=h($nome)?></dt><dd><?=h($valori ? implode(', ', $valori) . ($campo === 'durata' ? ' min' : '') : 'Da definire')?></dd>
                    <?php endforeach; ?></dl>
                    <?php $video = require __DIR__ . '/trailer.php';
                    if (isset($video[$id])):
                        $tipoVideo = $id === 'scherzetto' ? 'Clip ufficiale' : 'Trailer'; ?>
                    <h2><?=h($tipoVideo)?></h2>
                    <iframe class="trailer-film" src="https://www.youtube-nocookie.com/embed/<?=h($video[$id])?>"
                        title="<?=h($tipoVideo.' di '.titolo($film))?>" loading="lazy"
                        referrerpolicy="strict-origin-when-cross-origin" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
                    <?php endif; ?>
                    <h2>Trama</h2><p><?=h(implode('', valoriFilm($film,'trama')))?></p>
                </div>
            <?php if (locandinaFilm($film)): ?><img class="locandina" src="<?=h(locandinaFilm($film))?>" alt="Locandina di <?=h(titolo($film))?>"><?php endif; ?>
            </div>
            </article>
            
            <section class="orari-film"><h2>Proiezioni in programma</h2>

            <?php
            $sale = [];
            foreach (apriXml('cinema.xml')->getElementsByTagName('sala') as $s) $sale[$s->getAttribute('id')] = valoriFilm($s,'nome')[0];
            $spettacoli = iterator_to_array($film->getElementsByTagName('proiezione'));
            usort($spettacoli, function($a,$b) {
                foreach (['data','ora','sala'] as $campo) {
                    $ordine = strcmp($a->getAttribute($campo), $b->getAttribute($campo));
                    if ($ordine) return $ordine;
                }
                return 0;
            });
            if (!$spettacoli): ?><p>Nessuno spettacolo in programma.</p>
            <?php else:
            $perGiorno = [];
            foreach ($spettacoli as $p) $perGiorno[$p->getAttribute('data')][] = $p;
            $giorni = ['lun','mar','mer','gio','ven','sab','dom'];
            $occupati = postiPerProiezione();
            $capienze = capienze();
            foreach ($perGiorno as $data => $lista):
                $giorno = new DateTimeImmutable($data);
            ?>
            <details class="giorno-film" open>
            <summary><?=h($giorni[(int)$giorno->format('N')-1].' '.$giorno->format('d/m'))?></summary>
            <ul class="turni">
                <?php foreach ($lista as $p):
                    $idSala = $p->getAttribute('sala');
                    $codice = $p->getAttribute('id');
                    $stato = '';
                    if (inizioSpettacolo($p) <= time()) $stato = 'Chiuso';
                    elseif (!$codice) $stato = 'In preparazione';
                    elseif (($occupati[$codice] ?? 0) >= $capienze[$idSala]) $stato = 'Esaurito';
                    $etichetta = substr($p->getAttribute('ora'),0,5);
                    if (in_array($p->getAttribute('in3D'),['1','true'],true)) $etichetta .= ' · 3D';
                ?>
                <li><?php if ($stato === ''): ?>
                    <a class="turno" href="area.php?p=<?=h(rawurlencode($codice))?>">
                        <span class="sala-<?=h($idSala)?>"><?=h($sale[$idSala] ?? $idSala)?></span> · <?=h($etichetta)?>
                    </a>
                    <?php else: ?><span class="turno chiuso"><span class="sala-<?=h($idSala)?>"><?=h($sale[$idSala] ?? $idSala)?></span> · <?=h($etichetta.' · '.$stato)?>
                    </span><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul></details>

            <?php endforeach; endif; ?></section></div>

                <aside class="indice-film"><nav aria-labelledby="titolo-indice">
                <h2 id="titolo-indice">Film in catalogo</h2>
                <ul><?php foreach (filmPerUscita($doc, date('Y-m-d')) as $voce):
                    $idVoce = $voce->getAttribute('id'); ?>
                <li><a href="?id=<?=h(rawurlencode($idVoce))?>"<?=$idVoce === $id ? ' aria-current="page"' : ''?>><?=h(titolo($voce))?></a></li>
                <?php endforeach; ?></ul>
                <a href="film.php">Catalogo completo</a>
                </nav></aside>
            </div><?php endif; ?>
        </main>
        <?php require __DIR__.'/fondo-artemis.php'; ?>
    </body>
</html>
