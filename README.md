# Cinema Artemis · Homework di Linguaggi per il Web — XML e DOM

## Autore

Lorenzo Petrucci, matricola 1937262

## Repository di GitHub

LinguaggiWeb-XML_DOM su GitHub: https://github.com/lorenzopetrucci1937262/LinguaggiWeb-XML_DOM

## Credenziali di accesso amministratore

- **Username:** `admin`
- **Password:** `Artemis2026!`

Il collegamento **Area Riservata Admin** si trova in fondo a ogni pagina. Per provare il sito come spettatore si può creare un account dall’area personale o direttamente durante la prenotazione.

## Tecniche del sito

Per questa versione di Cinema Artemis ho organizzato la programmazione in XML. Ogni film contiene le proprie proiezioni, ciascuna con un identificativo, una data, un orario e una sala. In PHP uso `DOMDocument` per leggere questi dati e modificare il cartellone dal pannello di gestione. Prima di salvare controllo che il documento rispetti `programmazione.xsd`. Le informazioni sulle sale, sulle capienze e sui prezzi si trovano in `cinema.xml`, accompagnato dalla DTD.

Per utenti e prenotazioni uso MySQL. Ogni prenotazione contiene l’identificativo dello spettatore e quello della proiezione XML. Alla registrazione PHP genera l’hash della password con `password_hash()`. Al login recupera l’hash con una query preparata e verifica la password con `password_verify()`. Dopo l’accesso, la sessione PHP permette allo spettatore di ritrovare le proprie prenotazioni.

## Funzione del sito

Chi visita il sito può vedere i film della settimana, consultare il catalogo con locandine, trame e trailer, oppure partire da un giorno preciso e scegliere uno spettacolo in base alla sala e all’orario. Dalla scheda di un film o dalla programmazione si possono prenotare uno o più posti. Il codice di conferma compare anche nell’area personale.

L’amministratore può inserire una scheda, aggiungere o spostare uno spettacolo e rimuovere le proiezioni concluse. Quando cambia un orario, sia la scheda del film sia la programmazione giornaliera mostrano il dato aggiornato.

## Organizzazione dei file

| File o cartella | Contenuto |
|---|---|
| `cinema.xml` e `cinema.dtd` | Dati del cinema, sale, capienze, tariffe e abbonamenti, con la relativa DTD. |
| `cartellone/programmazione.xml` | Cartellone utilizzato dal sito. È qui che vengono salvate le modifiche dell’amministratore. |
| `programmazione.xml` | Copia di partenza nella cartella principale, letta se manca il file dentro `cartellone/`. |
| `schede-inverno.xml` e `programmazione.xsd` | Schede importabili dal pannello di gestione e schema usato sia per le schede sia per il cartellone. |
| `cinema.sql` e `database.php` | Creazione delle tabelle, connessione e funzioni per utenti e prenotazioni. |
| `catalogo.php`, `operazioni.php` e `archivi.php` | Funzioni PHP per gestire le schede, modificare le proiezioni e salvare i dati. |
| `artemis.css`, `locandine/` e `trailer.php` | Foglio di stile, locandine e riferimenti ai video presenti nelle schede. Le fonti delle immagini sono raccolte in `locandine/FONTI.txt`. |

## Nuove funzionalità dinamiche

- **Avvio automatico con XAMPP.** Copiare la cartella in `htdocs`, avviare Apache e MySQL e aprire la home su localhost. Alla prima apertura PHP usa `cinema.sql` per creare il database `cinema_artemis` e le tabelle mancanti. Se sono già presenti, il sito utilizza gli account e le prenotazioni esistenti. I parametri in `database.php` sono quelli dell’installazione locale usata per il progetto (127.0.0.1, porta 3306, utente `root`, password vuota). Servono le estensioni PHP DOM e PDO MySQL e, per la prima installazione, un utente MySQL autorizzato alla creazione.
- **Programmazione per giorno e sala.** La scheda di un film raccoglie i suoi spettacoli. Nella pagina Programmazione gli stessi nodi XML vengono raggruppati per data e per sala, con il numero di posti ancora disponibili.
- **Prenotazioni e storico.** Dopo la conferma lo spettatore riceve un codice formato dall’ID della proiezione e dal numero della prenotazione. Se annulla, i posti tornano disponibili. Quando l’amministratore rimuove uno spettacolo concluso, nell’area personale resta il riepilogo con film, sala e orario.
- **Gestione del cartellone.** Prima di confermare una modifica, il sito controlla anche la durata del film per evitare sovrapposizioni nella stessa sala. Il cartellone di esempio copre il periodo dal **24 novembre al 2 dicembre 2026**. Per provare una prenotazione dopo quelle date basta aggiungere dal pannello admin uno spettacolo futuro. Se cade entro la domenica della settimana corrente, il film compare anche nella home.

## Note di sviluppo

### Primo avvio e conservazione dei dati

Nel mio precedente homework usavo uno script di installazione per ripartire da un database vuoto. Qui mi serviva un comportamento diverso: uno spettatore deve poter chiudere il sito e ritrovare la prenotazione quando torna. Per questo verifico la presenza delle tabelle e uso `CREATE DATABASE IF NOT EXISTS` e `CREATE TABLE IF NOT EXISTS` per completare l’installazione.

### Due richieste per gli ultimi posti

Può capitare che due persone vedano contemporaneamente lo stesso posto libero. Prima di registrare la prenotazione, il sito acquisisce un blocco MySQL con `GET_LOCK()` e riconta i posti occupati. La seconda richiesta attende la prima e trova la disponibilità aggiornata.

### Salvataggio del cartellone XML

Il pannello admin salva il documento aggiornato in un file temporaneo dentro `cartellone/` e poi lo rinomina. Per questo Apache deve poter scrivere nella cartella. Su macOS, se XAMPP è installato nel percorso standard e il pannello segnala «Apache non può scrivere nella cartella cartellone», aprire il terminale nella cartella che contiene `index.php` ed eseguire:

```bash
gruppo_apache=$(awk '$1 == "Group" {print $2; exit}' /Applications/XAMPP/xamppfiles/etc/httpd.conf)
test -n "$gruppo_apache" && sudo chgrp "$gruppo_apache" ./cartellone && sudo chmod g+rwx ./cartellone
```
