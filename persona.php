<?php
// api/persona.php
// Web Service REST – CRUD completo sulla tabella `persona`
// Base URL: http://localhost/persona_api/api/persona.php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Risponde alle richieste OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

// ID opzionale passato come query string: ?id=3
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

switch ($method) {

    // ──────────────────────────────────────────
    // READ – GET /api/persona.php
    //        GET /api/persona.php?id=1
    // ──────────────────────────────────────────
    case 'GET':
        getPersone($id);
        break;

    // ──────────────────────────────────────────
    // CREATE – POST /api/persona.php
    // Body JSON: { "nome":"...", "cognome":"...", "email":"...", "eta":... }
    // ──────────────────────────────────────────
    case 'POST':
        createPersona();
        break;

    // ──────────────────────────────────────────
    // UPDATE – PUT /api/persona.php?id=1
    // Body JSON: uno o più campi da aggiornare
    // ──────────────────────────────────────────
    case 'PUT':
        if (!$id) { badRequest('ID mancante per la modifica'); }
        updatePersona($id);
        break;

    // ──────────────────────────────────────────
    // DELETE – DELETE /api/persona.php?id=1
    // ──────────────────────────────────────────
    case 'DELETE':
        if (!$id) { badRequest('ID mancante per la cancellazione'); }
        deletePersona($id);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Metodo non supportato: ' . $method]);
}

// ════════════════════════════════════════════════════════════
//  FUNZIONI CRUD
// ════════════════════════════════════════════════════════════

/**
 * READ – restituisce tutte le persone oppure una sola se viene passato $id
 */
function getPersone(?int $id): void {
    $conn = getConnection();

    if ($id) {
        $stmt = $conn->prepare('SELECT * FROM persona WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            notFound("Persona con ID $id non trovata");
        }

        $persona = $result->fetch_assoc();
        echo json_encode(['data' => $persona]);
    } else {
        // Filtri opzionali via query string: ?nome=mario&cognome=rossi
        $where = [];
        $types = '';
        $params = [];

        if (!empty($_GET['nome'])) {
            $where[] = 'nome LIKE ?';
            $types  .= 's';
            $params[] = '%' . $_GET['nome'] . '%';
        }
        if (!empty($_GET['cognome'])) {
            $where[] = 'cognome LIKE ?';
            $types  .= 's';
            $params[] = '%' . $_GET['cognome'] . '%';
        }

        $sql = 'SELECT * FROM persona';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY id ASC';

        $stmt = $conn->prepare($sql);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $persone = [];
        while ($row = $result->fetch_assoc()) {
            $persone[] = $row;
        }

        echo json_encode([
            'totale' => count($persone),
            'data'   => $persone,
        ]);
    }

    $conn->close();
}

/**
 * CREATE – inserisce una nuova persona
 */
function createPersona(): void {
    $body = getJsonBody();

    // Validazione campi obbligatori
    foreach (['nome', 'cognome', 'email'] as $campo) {
        if (empty($body[$campo])) {
            badRequest("Campo obbligatorio mancante: '$campo'");
        }
    }

    $nome    = trim($body['nome']);
    $cognome = trim($body['cognome']);
    $email   = trim($body['email']);
    $eta     = isset($body['eta']) ? (int) $body['eta'] : null;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        badRequest('Indirizzo email non valido');
    }

    $conn = getConnection();

    // Controlla email duplicata
    $check = $conn->prepare('SELECT id FROM persona WHERE email = ?');
    $check->bind_param('s', $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        conflict("Email '$email' già presente nel database");
    }

    $stmt = $conn->prepare(
        'INSERT INTO persona (nome, cognome, email, eta) VALUES (?, ?, ?, ?)'
    );
    $stmt->bind_param('sssi', $nome, $cognome, $email, $eta);

    if (!$stmt->execute()) {
        serverError('Inserimento fallito: ' . $stmt->error);
    }

    $newId = $conn->insert_id;
    $conn->close();

    http_response_code(201);
    echo json_encode([
        'messaggio' => 'Persona creata con successo',
        'id'        => $newId,
    ]);
}

/**
 * UPDATE – aggiorna i dati di una persona esistente
 */
function updatePersona(int $id): void {
    $body = getJsonBody();

    if (empty($body)) {
        badRequest('Nessun dato ricevuto per la modifica');
    }

    $conn = getConnection();

    // Verifica che la persona esista
    $check = $conn->prepare('SELECT id FROM persona WHERE id = ?');
    $check->bind_param('i', $id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        notFound("Persona con ID $id non trovata");
    }

    // Costruisce dinamicamente SET con solo i campi inviati
    $campiPermessi = ['nome', 'cognome', 'email', 'eta'];
    $setClauses    = [];
    $types         = '';
    $params        = [];

    foreach ($campiPermessi as $campo) {
        if (array_key_exists($campo, $body)) {
            $setClauses[] = "$campo = ?";
            $types       .= ($campo === 'eta') ? 'i' : 's';
            $params[]     = $body[$campo];
        }
    }

    if (empty($setClauses)) {
        badRequest('Nessun campo valido da aggiornare');
    }

    // Valida email se presente
    if (isset($body['email'])) {
        if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            badRequest('Indirizzo email non valido');
        }
        // Controlla unicità email (escludendo sé stesso)
        $emailCheck = $conn->prepare('SELECT id FROM persona WHERE email = ? AND id != ?');
        $emailCheck->bind_param('si', $body['email'], $id);
        $emailCheck->execute();
        if ($emailCheck->get_result()->num_rows > 0) {
            conflict("Email '{$body['email']}' già usata da un'altra persona");
        }
    }

    $sql      = 'UPDATE persona SET ' . implode(', ', $setClauses) . ' WHERE id = ?';
    $types   .= 'i';
    $params[] = $id;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        serverError('Aggiornamento fallito: ' . $stmt->error);
    }

    $conn->close();

    echo json_encode([
        'messaggio'    => 'Persona aggiornata con successo',
        'righe_modif.' => $stmt->affected_rows,
    ]);
}

/**
 * DELETE – elimina una persona per ID
 */
function deletePersona(int $id): void {
    $conn = getConnection();

    // Verifica esistenza
    $check = $conn->prepare('SELECT id, nome, cognome FROM persona WHERE id = ?');
    $check->bind_param('i', $id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows === 0) {
        notFound("Persona con ID $id non trovata");
    }

    $persona = $result->fetch_assoc();

    $stmt = $conn->prepare('DELETE FROM persona WHERE id = ?');
    $stmt->bind_param('i', $id);

    if (!$stmt->execute()) {
        serverError('Cancellazione fallita: ' . $stmt->error);
    }

    $conn->close();

    echo json_encode([
        'messaggio' => "Persona eliminata con successo",
        'eliminata' => $persona,
    ]);
}

// ════════════════════════════════════════════════════════════
//  HELPER
// ════════════════════════════════════════════════════════════

function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function badRequest(string $msg): void {
    http_response_code(400);
    echo json_encode(['error' => $msg]);
    exit;
}

function notFound(string $msg): void {
    http_response_code(404);
    echo json_encode(['error' => $msg]);
    exit;
}

function conflict(string $msg): void {
    http_response_code(409);
    echo json_encode(['error' => $msg]);
    exit;
}

function serverError(string $msg): void {
    http_response_code(500);
    echo json_encode(['error' => $msg]);
    exit;
}
