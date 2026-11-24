<?php
function conBlocco($operazione) {
    $lock = @fopen(cartellaPrivata() . '/programmazione.lock','c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Apache non riesce a scrivere in archivi-privati. Controlla i permessi indicati nella guida.');
    try { return $operazione(); }
    finally { fclose($lock); }
}

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

function cartellaPrivata() {
    $dir = __DIR__ . '/archivi-privati';
    if (!is_dir($dir) && !@mkdir($dir, 0700) && !is_dir($dir)) throw new RuntimeException('Apache non riesce a creare archivi-privati. Segui la configurazione XAMPP nella guida.');
    $regola = "Require all denied\n";
    $file = $dir . '/.htaccess';
    if (!is_file($file) && file_put_contents($file,$regola) !== strlen($regola)) throw new RuntimeException('Protezione archivi non disponibile.');
    if (file_get_contents($file) !== $regola) throw new RuntimeException('Controlla la protezione degli archivi.');
    return $dir;
}
function archivio($nome) {
    $file = __DIR__ . '/archivi-privati/' . $nome . '.xml';
    if (is_file($file)) return apriXml('archivi-privati/' . $nome . '.xml');
    $doc = new DOMDocument('1.0','UTF-8');
    $doc->formatOutput = true;
    $doc->appendChild($doc->createElement($nome));
    return $doc;
}
function registraArchivio($doc) {
    $nome = $doc->documentElement->tagName;
    salvaXml($doc,cartellaPrivata() . '/' . $nome . '.xml',$nome . '.xsd',0600);
}
function perAttributo($doc,$tag,$nome,$valore) {
    foreach ($doc->getElementsByTagName($tag) as $nodo) {
        if ($nodo->getAttribute($nome) === $valore) return $nodo;
    }
    return null;
}
function gruppoPrenotazioni($doc,$id) {
    return perAttributo($doc,'spettacolo','proiezione',$id);
}
function occupati($gruppo) {
    $totale = 0;
    if ($gruppo) foreach ($gruppo->getElementsByTagName('prenotazione') as $r) $totale += (int)$r->getAttribute('posti');
    return $totale;
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

function prenotaPosti($utente,$id,$quantita,$orarioVisto) {
    return conBlocco(function() use ($utente,$id,$quantita,$orarioVisto) {
        $doc = archivio('prenotazioni');
        $gruppo = gruppoPrenotazioni($doc,$id);
        $p = perAttributo(apriXml('programmazione.xml'),'proiezione','id',$id);
        if (!$p || inizioSpettacolo($p) <= time()) throw new DomainException('Prenotazioni chiuse per questo spettacolo.');
        if (chiave($p) !== $orarioVisto) throw new DomainException('Lo spettacolo è cambiato. Controlla sala e orario.');
        $posti = filter_var($quantita,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$posti || $posti > capienze()[$p->getAttribute('sala')] - occupati($gruppo)) throw new DomainException('Quantità superiore ai posti disponibili o non valida.');
        if (!$gruppo) {
            $gruppo = $doc->createElement('spettacolo');
            $doc->documentElement->appendChild($gruppo);
            $gruppo->setAttribute('proiezione',$id);
        }
        $numero = (int)$gruppo->getAttribute('ultimoNumero') + 1;
        $codice = $id . '-' . str_pad((string)$numero,4,'0',STR_PAD_LEFT);
        $gruppo->setAttribute('ultimoNumero',(string)$numero);
        $r = $gruppo->appendChild($doc->createElement('prenotazione'));
        $r->setAttribute('id',$codice);
        $r->setAttribute('utente',$utente);
        $r->setAttribute('posti',(string)$posti);
        registraArchivio($doc);
        return $codice;
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
        $doc = archivio('prenotazioni');
        $r = perAttributo($doc,'prenotazione','id',$id);
        if (!$r || $r->getAttribute('utente') !== $utente) throw new DomainException('Prenotazione personale non trovata.');
        $idProiezione = $r->parentNode->getAttribute('proiezione');
        $p = perAttributo(apriXml('programmazione.xml'), 'proiezione', 'id', $idProiezione);
        if (!$p || proiezioneTerminata($p, time())) {
            throw new DomainException('Questa prenotazione riguarda una proiezione conclusa.');
        }
        $r->parentNode->removeChild($r);
        registraArchivio($doc);
    });
}
