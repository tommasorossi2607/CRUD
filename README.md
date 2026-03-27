# Web Service REST – Tabella `persona`
## XAMPP + PHP + MariaDB

---

## Struttura cartelle

```
persona_api/
├── config/
│   └── database.php     ← configurazione DB
├── api/
│   └── persona.php      ← endpoint REST (unico file, gestisce tutti i metodi)
├── test_client.html     ← client di test grafico nel browser
└── setup.sql            ← crea DB + tabella + dati di esempio
```

---

## Installazione

1. **Copia** la cartella `persona_api/` dentro `C:\xampp\htdocs\`
2. **Avvia** Apache e MySQL dal pannello XAMPP
3. **Importa** `setup.sql` in phpMyAdmin  
   (oppure: MySQL CLI → `source C:/xampp/htdocs/persona_api/setup.sql`)
4. Apri il browser su `http://localhost/persona_api/test_client.html`

---

## Endpoint

| Metodo   | URL                               | Azione                        |
|----------|-----------------------------------|-------------------------------|
| `GET`    | `/api/persona.php`                | Leggi tutte le persone        |
| `GET`    | `/api/persona.php?id=1`           | Leggi persona per ID          |
| `GET`    | `/api/persona.php?nome=mario`     | Filtra per nome (LIKE)        |
| `POST`   | `/api/persona.php`                | Crea nuova persona            |
| `PUT`    | `/api/persona.php?id=1`           | Aggiorna persona (parziale)   |
| `DELETE` | `/api/persona.php?id=1`           | Elimina persona               |

---

## Esempi con curl

```bash
# READ – tutte
curl http://localhost/persona_api/api/persona.php

# READ – per ID
curl http://localhost/persona_api/api/persona.php?id=1

# CREATE
curl -X POST http://localhost/persona_api/api/persona.php \
     -H "Content-Type: application/json" \
     -d '{"nome":"Luca","cognome":"Verdi","email":"luca.verdi@example.com","eta":22}'

# UPDATE (parziale)
curl -X PUT http://localhost/persona_api/api/persona.php?id=1 \
     -H "Content-Type: application/json" \
     -d '{"eta":30}'

# DELETE
curl -X DELETE http://localhost/persona_api/api/persona.php?id=2
```

---

## Codici di stato HTTP restituiti

| Codice | Situazione                                  |
|--------|---------------------------------------------|
| 200    | OK – operazione riuscita                    |
| 201    | Created – persona inserita                  |
| 400    | Bad Request – campo mancante / dati invalidi|
| 404    | Not Found – ID inesistente                  |
| 405    | Method Not Allowed – metodo non supportato  |
| 409    | Conflict – email duplicata                  |
| 500    | Internal Server Error – errore DB           |

---

## Schema tabella `persona`

| Colonna    | Tipo           | Note                    |
|------------|----------------|-------------------------|
| id         | INT AUTO_INC   | PK                      |
| nome       | VARCHAR(50)    | obbligatorio            |
| cognome    | VARCHAR(50)    | obbligatorio            |
| email      | VARCHAR(100)   | obbligatorio + UNIQUE   |
| eta        | INT(3)         | opzionale               |
| creato_il  | DATETIME       | default CURRENT_TIMESTAMP|
