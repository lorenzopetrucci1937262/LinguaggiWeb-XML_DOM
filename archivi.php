<?php
require_once __DIR__ . '/database.php';

function salvaXml($doc, $file, $schema, $permessi) {
    if (!$doc->schemaValidate(__DIR__ . '/' . $schema)) throw new RuntimeException('Documento incompatibile con lo schema.');
    $cartella = dirname($file);
    $tmp = tempnam($cartella, 'salva-');
    if (!$tmp) throw new RuntimeException('Impossibile creare il file temporaneo.');
    try {
        $xml = $doc->saveXML();
        if (dirname($tmp) !== realpath($cartella) || file_put_contents($tmp,$xml) !== strlen($xml)
            || !chmod($tmp,$permessi) || !rename($tmp,$file)) throw new RuntimeException('Salvataggio non riuscito.');
    } finally { if (file_exists($tmp)) unlink($tmp); }
}

function perAttributo($doc,$tag,$nome,$valore) {
    foreach ($doc->getElementsByTagName($tag) as $nodo) {
        if ($nodo->getAttribute($nome) === $valore) return $nodo;
    }
    return null;
}
function capienze() {
    $capienze = [];
    foreach (apriXml('cinema.xml')->getElementsByTagName('sala') as $s) {
        $capienze[$s->getAttribute('id')] = (int)$s->getElementsByTagName('posti')->item(0)->textContent;
    }
    return $capienze;
}
function nuovoCodiceProiezione() { return 'p-' . bin2hex(random_bytes(6)); }
// La vetrina comprende gli spettacoli da oggi alla domenica di questa settimana.
function nellaSettimanaPubblica($p, $oggi = null) {
    $oggi = $oggi ?? new DateTimeImmutable('today', new DateTimeZone('Europe/Rome'));
    $data = $p->getAttribute('data');
    return $data >= $oggi->format('Y-m-d') && $data <= $oggi->modify('sunday this week')->format('Y-m-d');
}

function inizioSpettacolo($p) { return strtotime($p->getAttribute('data').' '.$p->getAttribute('ora')); }

function codicePrenotazione($proiezione, $numero) {
    return $proiezione . '-' . str_pad((string)$numero, 4, '0', STR_PAD_LEFT);
}

function prenotaPosti($utente,$id,$quantita,$orarioVisto) {
    return conBlocco(function() use ($utente,$id,$quantita,$orarioVisto) {
        $p = perAttributo(apriXml('programmazione.xml'),'proiezione','id',$id);
        if (!$p || inizioSpettacolo($p) <= time()) throw new DomainException('Prenotazioni chiuse per questo spettacolo.');
        if (chiave($p) !== $orarioVisto) throw new DomainException('Lo spettacolo è cambiato. Controlla sala e orario.');
        $posti = filter_var($quantita,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$posti || $posti > capienze()[$p->getAttribute('sala')] - postiPrenotati($id)) throw new DomainException('Quantità superiore ai posti disponibili o non valida.');
        $db = database();
        $query = $db->prepare('INSERT INTO prenotazioni (utente, proiezione, posti) VALUES (?, ?, ?)');
        $query->execute([$utente, $id, $posti]);
        return codicePrenotazione($id, $db->lastInsertId());
    });
}
function proiezioneTerminata($proiezione, $adesso) {
    $durata = $proiezione->parentNode->getElementsByTagName('durata')->item(0);
    $inizio = inizioSpettacolo($proiezione);
    return $durata && (int)$durata->textContent > 0 && $inizio !== false
        && $inizio + 60 * (int)$durata->textContent <= $adesso;
}

function annullaPrenotazione($utente,$id) {
    conBlocco(function() use ($utente,$id) {
        $query = database()->prepare('SELECT proiezione FROM prenotazioni WHERE id = ? AND utente = ?');
        $query->execute([$id, $utente]);
        $idProiezione = $query->fetchColumn();
        if ($idProiezione === false) throw new DomainException('Prenotazione personale non trovata.');
        $p = perAttributo(apriXml('programmazione.xml'), 'proiezione', 'id', $idProiezione);
        if (!$p || proiezioneTerminata($p, time())) {
            throw new DomainException('Questa prenotazione riguarda una proiezione conclusa.');
        }
        $query = database()->prepare('DELETE FROM prenotazioni WHERE id = ? AND utente = ?');
        $query->execute([$id, $utente]);
    });
}
