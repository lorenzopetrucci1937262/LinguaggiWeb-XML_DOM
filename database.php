<?php
function database() {
    static $db = null;
    if ($db === null) {
        $host = '127.0.0.1';
        $porta = 3306;
        $utente = 'root';
        $password = '';
        try {
            $connessione = new PDO("mysql:host=$host;port=$porta;charset=utf8mb4", $utente, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Connessione a MySQL non riuscita. Verifica il servizio XAMPP e le credenziali in database.php.');
        }

        try {
            $connessione->exec('USE cinema_artemis');
            $connessione->query('SELECT id FROM utenti LIMIT 0');
            $connessione->query('SELECT id FROM prenotazioni LIMIT 0');
        } catch (PDOException $e) {
            // MySQL segnala 1049 per un database assente e 1146 per una tabella assente.
            if (!in_array((int)($e->errorInfo[1] ?? 0), [1049, 1146], true)) {
                throw new RuntimeException('Impossibile accedere al database cinema_artemis. Verifica i permessi MySQL.');
            }
            $schema = file_get_contents(__DIR__ . '/cinema.sql');
            if ($schema === false) throw new RuntimeException('File cinema.sql illeggibile.');
            try {
                foreach (explode(';', $schema) as $istruzione) {
                    if (trim($istruzione) !== '') $connessione->exec($istruzione);
                }
            } catch (PDOException $e) {
                throw new RuntimeException('Installazione incompleta. Verifica che MySQL consenta di creare il database e le tabelle.');
            }
        }
        $db = $connessione;
    }
    return $db;
}

function registraUtente($nome, $password) {
    $db = database();
    $query = $db->prepare('INSERT INTO utenti (username, password_hash) VALUES (?, ?)');
    try {
        $query->execute([$nome, password_hash($password, PASSWORD_DEFAULT)]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) == 1062) throw new DomainException('Username già utilizzato.');
        throw new RuntimeException('Registrazione non riuscita.');
    }
    return $db->lastInsertId();
}

function accediUtente($nome, $password) {
    $query = database()->prepare('SELECT id, password_hash FROM utenti WHERE username = ?');
    $query->execute([$nome]);
    $utente = $query->fetch();
    if (!$utente || !password_verify($password, $utente['password_hash'])) {
        throw new DomainException('Credenziali errate.');
    }
    return $utente['id'];
}

// Anche le modifiche del cartellone usano questo blocco: sala e posti devono restare coerenti.
function conBlocco($operazione) {
    $db = database();
    if ((int)$db->query("SELECT GET_LOCK('cinema_artemis.cartellone', 10)")->fetchColumn() !== 1) {
        throw new RuntimeException('Cartellone occupato. Riprova tra poco.');
    }
    try {
        return $operazione();
    } finally {
        $db->query("SELECT RELEASE_LOCK('cinema_artemis.cartellone')");
    }
}

function postiPerProiezione() {
    return database()->query('SELECT proiezione, SUM(posti) FROM prenotazioni GROUP BY proiezione')
                     ->fetchAll(PDO::FETCH_KEY_PAIR);
}

function postiPrenotati($id) {
    $query = database()->prepare('SELECT COALESCE(SUM(posti), 0) FROM prenotazioni WHERE proiezione = ?');
    $query->execute([$id]);
    return (int)$query->fetchColumn();
}

function prenotazioniUtente($utente) {
    $query = database()->prepare('SELECT id, proiezione, posti, riepilogo FROM prenotazioni WHERE utente = ? ORDER BY id');
    $query->execute([$utente]);
    return $query->fetchAll();
}
