<?php
require_once __DIR__ . '/catalogo.php';
require_once __DIR__ . '/archivi.php';
date_default_timezone_set('Europe/Rome');
function apriXml($nome) {
    $doc = new DOMDocument();
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;
    $file = __DIR__ . '/' . $nome;
    if ($nome === 'programmazione.xml' && is_file(__DIR__ . '/cartellone/programmazione.xml')) {
        $file = __DIR__ . '/cartellone/programmazione.xml';
    }
    if (!$doc->load($file, LIBXML_NONET)) throw new RuntimeException('Archivio XML illeggibile.');
    return $doc;
}
function titolo($film) { return $film->getElementsByTagName('titolo')->item(0)->textContent; }
function chiave($p) {
    return implode('|', [$p->parentNode->getAttribute('id'), $p->getAttribute('sala'),
        $p->getAttribute('data'), $p->getAttribute('ora')]);
}
// Le tre fasce raggruppano anche partenze leggermente sfalsate.
function fascia($ora) {
    if ($ora < '18:00') return 1;
    if ($ora < '21:00') return 2;
    return 3;
}
function applicaModifica($doc, $sale, $dati) {
    $azione = $dati['azione'] ?? '';
    $film = null;
    foreach ($doc->getElementsByTagName('film') as $f) {
        if ($f->getAttribute('id') === ($dati['film'] ?? '')) $film = $f;
    }
    if (!$film) throw new DomainException('Film sconosciuto.');
    $nodo = null;
    if ($azione !== 'aggiungi') {
        foreach ($film->getElementsByTagName('proiezione') as $p) {
            if (chiave($p) === ($dati['originale'] ?? '')) $nodo = $p;
        }
        if (!$nodo) throw new DomainException('Seleziona una proiezione esistente.');
    }
    if ($azione === 'elimina') {
        $prenotati = postiPrenotati($nodo->getAttribute('id'));
        if ($prenotati) throw new DomainException("Spettacolo con $prenotati posti prenotati: eliminazione bloccata.");
        $film->removeChild($nodo);
        if (!$film->getElementsByTagName('proiezione')->length) $film->setAttribute('stato','in_calendario');
        return;
    }
    if (!in_array($azione,['aggiungi','modifica'],true)) throw new DomainException('Azione sconosciuta.');
    $sala = $dati['sala'] ?? '';
    $data = $dati['data'] ?? '';
    $ora = $dati['ora'] ?? '';
    $inizio = DateTimeImmutable::createFromFormat('!Y-m-d H:i', "$data $ora");
    if (!isset($sale[$sala]) || !$inizio || $inizio->format('Y-m-d H:i') !== "$data $ora") {
        throw new DomainException('Verifica sala, data e ora.');
    }
    if ($ora < '15:30' || $ora > '22:00') throw new DomainException('Scegli un inizio fra le 15:30 e le 22:00.');
    $durata = $film->getElementsByTagName('durata')->item(0);
    if (!$durata) {
        $minuti = filter_var($dati['durata'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$minuti) throw new DomainException('Per il primo spettacolo indica la durata in minuti.');
        $durata = $doc->createElement('durata',(string)$minuti);
        $film->insertBefore($durata,$film->getElementsByTagName('regia')->item(0));
    }
    $fine = $inizio->getTimestamp() + (int)$durata->textContent * 60;
    $repliche = 0;
    foreach ($doc->getElementsByTagName('proiezione') as $p) {
        if ($nodo && $nodo->isSameNode($p)) continue;
        $altroFilm = $p->parentNode;
        $altraOra = $p->getAttribute('ora');
        if ($p->getAttribute('sala') === $sala) {
            $da = strtotime($p->getAttribute('data') . ' ' . $altraOra);
            $a = $da + (int)$altroFilm->getElementsByTagName('durata')->item(0)->textContent * 60;
            if ($inizio->getTimestamp() < $a && $da < $fine) throw new DomainException('La sala è occupata durante lo spettacolo.');
            if ($p->getAttribute('data') === $data && fascia($altraOra) === fascia($ora)) {
                throw new DomainException('Questa sala ha già uno spettacolo nella fascia scelta.');
            }
        }
        if ($altroFilm->isSameNode($film) && $p->getAttribute('data') === $data && fascia($altraOra) === fascia($ora)) $repliche++;
    }
    $limite = (int)($film->getAttribute('maxPerFascia') ?: 1);
    if ($repliche >= $limite) throw new DomainException('Limite di proiezioni del film nella fascia raggiunto.');
    if (!$nodo) {
        $nodo = $doc->createElement('proiezione');
        $nodo->setAttribute('id', nuovoCodiceProiezione());
        $film->appendChild($nodo);
    }
    if ($nodo->getAttribute('sala') && $nodo->getAttribute('sala') !== $sala) {
        $prenotati = postiPrenotati($nodo->getAttribute('id'));
        if ($prenotati > capienze()[$sala]) throw new DomainException("Sala troppo piccola per i $prenotati posti prenotati.");
    }
    $nodo->setAttribute('sala',$sala);
    $nodo->setAttribute('data',$data);
    $nodo->setAttribute('ora',$ora . ':00');
    if (isset($dati['in3D'])) $nodo->setAttribute('in3D','true');
    else $nodo->removeAttribute('in3D');
    $film->setAttribute('stato','in_proiezione');
}
// La lista è una copia: removeChild modificherà la NodeList del documento.
function riepilogoRientro($film) {
    $occupati = postiPerProiezione();
    $righe = [];
    foreach ($film->getElementsByTagName('proiezione') as $p) {
        $righe[] = ['proiezione'=>$p, 'posti'=>$occupati[$p->getAttribute('id')] ?? 0];
    }
    return $righe;
}

function riportaInCalendario($doc,$id) {
    $film = cercaFilm($doc,$id);
    if (!$film) throw new DomainException('Film sconosciuto.');
    $righe = riepilogoRientro($film);
    if (!$righe) throw new DomainException('Questo film è già in calendario.');
    $posti = array_sum(array_column($righe,'posti'));
    if ($posti > 0) throw new DomainException("Rientro bloccato: ci sono $posti posti prenotati.");
    foreach ($righe as $riga) $film->removeChild($riga['proiezione']);
    $film->setAttribute('stato','in_calendario');
}
function importaSchedeInverno($doc) {
    $schede = apriXml('schede-inverno.xml');
    if (!$schede->schemaValidate(__DIR__ . '/programmazione.xsd')) throw new DomainException('Schede non valide.');
    $titoli = [];
    foreach ($doc->getElementsByTagName('film') as $film) $titoli[trim(titolo($film))] = $film;
    $aggiunte = 0;
    foreach ($schede->getElementsByTagName('film') as $film) {
        if ($film->getAttribute('stato') !== 'in_calendario' || $film->getElementsByTagName('proiezione')->length) {
            throw new DomainException('L’importazione accetta solo schede senza spettacoli.');
        }
        $esistente = cercaFilm($doc,$film->getAttribute('id')) ?? ($titoli[trim(titolo($film))] ?? null);
        if ($esistente) {
            if (!$esistente->hasAttribute('uscita') && $film->hasAttribute('uscita')) {
                $esistente->setAttribute('uscita',$film->getAttribute('uscita'));
                $aggiunte++;
            }
            continue;
        }
        $titoli[trim(titolo($film))] = $doc->documentElement->appendChild($doc->importNode($film,true));
        $aggiunte++;
    }
    return $aggiunte;
}

function rimuoviProiezioniTerminate($doc, $adesso) {
    $storico = database()->prepare('UPDATE prenotazioni SET riepilogo = ? WHERE proiezione = ?');
    $numero = 0;
    foreach ($doc->getElementsByTagName('film') as $film) {
        $durata = (int)(valoriFilm($film, 'durata')[0] ?? 0);
        if ($durata <= 0) continue;
        foreach (iterator_to_array($film->getElementsByTagName('proiezione')) as $p) {
            $inizio = inizioSpettacolo($p);
            if ($inizio === false || $inizio + 60 * $durata > $adesso) continue;
            $descrizione = titolo($film).' · '.date('d/m/Y H:i', $inizio).' · Sala '.ucfirst($p->getAttribute('sala'));
            $storico->execute([$descrizione, $p->getAttribute('id')]);
            $film->removeChild($p);
            $numero++;
            if (!$film->getElementsByTagName('proiezione')->length) $film->setAttribute('stato', 'in_calendario');
        }
    }
    return $numero;
}

function salvaCartellone($doc) {
    $dir = __DIR__ . '/cartellone';
    if (!is_dir($dir) && !@mkdir($dir, 0755) && !is_dir($dir)) {
        throw new RuntimeException('Apache non riesce a creare cartellone. Segui la configurazione XAMPP nella guida.');
    }
    if (!is_writable($dir)) throw new RuntimeException('Apache non può scrivere nella cartella cartellone. Controlla i permessi indicati nella guida.');
    salvaXml($doc, $dir . '/programmazione.xml', 'programmazione.xsd', 0644);
}

function salvaModifica($sale,$dati) {
    return conBlocco(function() use ($sale,$dati) {
        $doc = apriXml('programmazione.xml');
        if (hash('sha256',$doc->saveXML()) !== ($dati['versione'] ?? '')) {
            throw new DomainException('Il cartellone è cambiato. Ricontrolla i dati aggiornati.');
        }
        if (($dati['azione'] ?? '') === 'importaSchede') {
            $numero = importaSchedeInverno($doc);
            if ($numero) salvaCartellone($doc);
            return $numero;
        }
        foreach ($doc->getElementsByTagName('proiezione') as $p) {
            if (!$p->hasAttribute('id')) $p->setAttribute('id', nuovoCodiceProiezione());
        }
        if ($dati['azione'] === 'rimuoviTerminate') {
            $numero = rimuoviProiezioniTerminate($doc, time());
            if ($numero) salvaCartellone($doc);
            return $numero;
        }
        $idSalvato = null;
        if (($dati['azione'] ?? '') === 'scheda') $idSalvato = scriviScheda($doc, $sale, $dati);
        elseif ($dati['azione'] === 'rientro') riportaInCalendario($doc,$dati['film'] ?? '');
        elseif ($dati['azione'] !== 'assegnaCodici') applicaModifica($doc,$sale,$dati);
        salvaCartellone($doc);
        return $idSalvato;
    });
}
