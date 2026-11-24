<?php
require_once __DIR__ . '/operazioni.php';
$occupati = postiPerProiezione();
$cinema = apriXml('cinema.xml');
$programmazione = apriXml('programmazione.xml');

function h(string $testo) {
    return htmlspecialchars($testo, ENT_QUOTES, 'UTF-8');
}

$sale = [];
foreach ($cinema->getElementsByTagName('sala') as $sala) {
    $sale[$sala->getAttribute('id')] = [
        'nome' => $sala->getElementsByTagName('nome')->item(0)->textContent,
        'posti' => $sala->getElementsByTagName('posti')->item(0)->textContent
    ];
}

// Nell’XML gli orari sono figli del film. Per il calendario li raggruppiamo per data e sala.
$calendario = [];
$inCalendario = [];
foreach ($programmazione->getElementsByTagName('film') as $film) {
    $titolo = $film->getElementsByTagName('titolo')->item(0)->textContent;
    if ($film->getAttribute('stato') === 'in_calendario') {
        $inCalendario[$film->getAttribute('id')] = $titolo;
        continue;
    }
    foreach ($film->getElementsByTagName('proiezione') as $proiezione) {
        $data = $proiezione->getAttribute('data');
        $idSala = $proiezione->getAttribute('sala');
        $calendario[$data][$idSala][] = [
            'codice' => $proiezione->getAttribute('id'),
            'inizio' => inizioSpettacolo($proiezione),
            'liberi' => $sale[$idSala]['posti'] - ($occupati[$proiezione->getAttribute('id')] ?? 0),
            'ora' => $proiezione->getAttribute('ora'),
            'titolo' => $titolo,
            'id' => $film->getAttribute('id'),
            'in3D' => in_array($proiezione->getAttribute('in3D'), ['true','1'], true)
        ];
    }
}
ksort($calendario);
$nomi = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'];
$oggi = date('Y-m-d');
$giorni = [];
$primaDisponibile = null;
foreach (array_keys($calendario) as $data) {
    $giornoData = new DateTimeImmutable($data);
    $giorni[$data] = $nomi[(int)$giornoData->format('N') - 1] . ' ' . $giornoData->format('d/m/Y');
    if ($primaDisponibile === null && $data >= $oggi) $primaDisponibile = $data;
}
$giorno = $_GET['giorno'] ?? '';
if (!is_string($giorno) || !isset($giorni[$giorno])) $giorno = $primaDisponibile ?? array_key_last($giorni);
?>

<!doctype html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cinema Artemis · Programmazione</title>

<link rel="stylesheet" href="artemis.css"></head>

<body><?php require __DIR__.'/menu-artemis.php'; ?>

<main>

    <h1>Programmazione</h1>

    <?php if (!$giorni): ?><p>Nessuna proiezione programmata.</p><?php else: ?>
    <nav class="giorni-programmazione" aria-label="Giorno di programmazione">
    <?php foreach ($giorni as $data => $etichetta): ?>
    <a class="turno" href="?giorno=<?=h($data)?>"<?=$data === $giorno ? ' aria-current="date"' : ''?>><?=h($etichetta)?></a>
    <?php endforeach; ?>
    </nav>

    <?php foreach ($sale as $idSala => $sala): ?>
        
    <section>
        <h2><span class="sala-<?=h($idSala)?>"><?= h($sala['nome']) ?></span> · <?= h($sala['posti']) ?> posti</h2>
        <?php $spettacoli = $calendario[$giorno][$idSala] ?? [];
        usort($spettacoli, function ($a, $b) { return strcmp($a['ora'], $b['ora']); });
        if ($spettacoli): ?>
        <table>
            <thead><tr><th scope="col">Orario</th><th scope="col">Film</th><th scope="col">Prenotazioni</th></tr></thead>
            <tbody>
                <?php foreach ($spettacoli as $spettacolo): ?>
                <tr><td><?= h(substr($spettacolo['ora'], 0, 5)) ?></td><td><a href="film.php?id=<?= h(rawurlencode($spettacolo['id'])) ?>"><?= h($spettacolo['titolo']) ?></a><?= $spettacolo['in3D'] ? ' · 3D' : '' ?></td><td>
                <?php if (!$spettacolo['codice']): ?><span class="turno chiuso">In preparazione</span>
                <?php elseif ($spettacolo['inizio'] <= time()): ?><span class="turno chiuso">Chiuso</span>
                <?php elseif ($spettacolo['liberi'] <= 0): ?><span class="turno chiuso">Esaurito</span>
                <?php else: ?><a class="turno prenota" href="area.php?p=<?=h(rawurlencode($spettacolo['codice']))?>">Prenota · <?=h($spettacolo['liberi'])?> <?= $spettacolo['liberi'] === 1 ? 'posto libero' : 'posti liberi' ?></a>
                <?php endif; ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><p>Nessuna proiezione per questo giorno.</p><?php endif; ?>
    </section>
    <?php endforeach; ?>

    <?php endif; ?>
    <?php if ($inCalendario): ?>
    <h2>Film in calendario · orari da definire</h2>
    <ul><?php foreach ($inCalendario as $id => $titolo): ?><li><a href="film.php?id=<?= h(rawurlencode($id)) ?>"><?= h($titolo) ?></a></li><?php endforeach; ?></ul>
    <?php endif; ?>
</main>
<?php require __DIR__.'/fondo-artemis.php'; ?></body>
</html>
