<?php
function cercaFilm($doc, $id) {
    foreach ($doc->getElementsByTagName('film') as $film) {
        if ($film->getAttribute('id') === $id) return $film;
    }
    return null;
}

function valoriFilm($film, $nome) {
    $valori = [];
    foreach ($film->getElementsByTagName($nome) as $nodo) $valori[] = $nodo->textContent;
    return $valori;
}

function aggiungiTesto($padre, $nome, $testo) {
    $doc = $padre->ownerDocument;
    $elemento = $doc->createElement($nome);
    $elemento->appendChild($doc->createTextNode($testo));
    $padre->appendChild($elemento);
}

function righeScheda($testo) {
    $righe = [];
    foreach (preg_split('/\R/u', $testo) as $riga) {
        if (trim($riga) !== '') $righe[] = trim($riga);
    }
    return $righe;
}

function scriviScheda($doc, $sale, $dati) {
    foreach (['titolo','trama','regia','genere','attore'] as $campo) {
        if (trim($dati[$campo] ?? '') === '') throw new DomainException('Compila il campo ' . $campo . '.');
    }
    $id = $dati['film'] ?? '';
    $precedente = cercaFilm($doc, $id);
    if ($id !== '' && !$precedente) throw new DomainException('Film sconosciuto.');
    $durata = trim($dati['durata'] ?? '');
    if ($durata !== '' && !filter_var($durata, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]])) {
        throw new DomainException('Indica una durata intera positiva.');
    }
    
    $limite = $dati['limite'] ?? '1';
    if (!in_array($limite, ['1','2'], true)) throw new DomainException('Limite per fascia non valido.');
    $film = $doc->createElement('film');
    $film->setAttribute('id', $precedente ? $id : 'film-' . bin2hex(random_bytes(8)));
    $film->setAttribute('stato', $precedente ? $precedente->getAttribute('stato') : 'in_calendario');
    $film->setAttribute('maxPerFascia', $limite);
    $uscita = trim($dati['uscita'] ?? ($precedente ? $precedente->getAttribute('uscita') : ''));
    if ($uscita !== '') {
        $data = DateTimeImmutable::createFromFormat('!Y-m-d',$uscita);
        if (!$data || $data->format('Y-m-d') !== $uscita) throw new DomainException('Data di uscita non valida.');
        $film->setAttribute('uscita',$uscita);
    }

    aggiungiTesto($film, 'titolo', trim($dati['titolo']));
    if ($durata !== '') aggiungiTesto($film, 'durata', $durata);
    foreach (righeScheda($dati['regia']) as $nome) aggiungiTesto($film, 'regia', $nome);
    foreach (['generi'=>'genere', 'cast'=>'attore'] as $contenitore=>$campo) {
        $gruppo = $film->appendChild($doc->createElement($contenitore));
        foreach (righeScheda($dati[$campo]) as $voce) aggiungiTesto($gruppo, $campo, $voce);
    }
    aggiungiTesto($film, 'trama', trim($dati['trama']));
    if (!$precedente) {
        $doc->documentElement->appendChild($film);
        return $film->getAttribute('id');
    }
    $spettacoli = iterator_to_array($precedente->getElementsByTagName('proiezione'));
    if ($spettacoli && $durata === '') throw new DomainException('Per un film con spettacoli serve la durata.');
    foreach ($spettacoli as $p) $film->appendChild($p);
    $doc->documentElement->replaceChild($film, $precedente);

    // Durata e limite possono rendere incompatibili gli orari già inseriti.
    if ($durata === implode('', valoriFilm($precedente, 'durata')) &&
        $limite === ($precedente->getAttribute('maxPerFascia') ?: '1')) return $id;
    foreach ($spettacoli as $p) {
        $verifica = ['azione'=>'modifica', 'film'=>$id, 'originale'=>chiave($p),
                     'sala'=>$p->getAttribute('sala'), 'data'=>$p->getAttribute('data'),
                     'ora'=>substr($p->getAttribute('ora'), 0, 5)];
        if (in_array($p->getAttribute('in3D'), ['1','true'], true)) $verifica['in3D'] = '1';
        applicaModifica($doc, $sale, $verifica);
    }
    return $id;
}

function gruppoUscita($film,$oggi) {
    $data = $film->getAttribute('uscita');
    if ($data === '') return 2;
    return $data >= $oggi ? 0 : 1;
}

function filmPerUscita($doc,$oggi) {
    $films = iterator_to_array($doc->getElementsByTagName('film'));
    usort($films,function($a,$b) use ($oggi) {
        $gruppo = gruppoUscita($a,$oggi);
        $ordine = $gruppo <=> gruppoUscita($b,$oggi);
        if ($ordine) return $ordine;
        $ordine = strcmp($a->getAttribute('uscita'),$b->getAttribute('uscita'));
        if ($ordine) return $gruppo === 1 ? -$ordine : $ordine;
        return strcasecmp(titolo($a),titolo($b));
    });
    return $films;
}

function dataItaliana($film) {
    $data = $film->getAttribute('uscita');
    return $data ? implode('/',array_reverse(explode('-',$data))) : 'Da confermare';
}

function locandinaFilm($film) {
    $id = $film->getAttribute('id');
    if (preg_match('/^[a-z0-9-]+$/D',$id) && is_file(__DIR__.'/locandine/'.$id.'.jpg')) {
        return 'locandine/'.$id.'.jpg';
    }
    $immagini = ['dune-3'=>'dune3','avengers-doomsday'=>'doomsday','hunger-games'=>'hungergames'];
    $nome = $immagini[$film->getAttribute('id')] ?? '';
    return $nome ? 'locandine/'.$nome.'.png' : '';
}
