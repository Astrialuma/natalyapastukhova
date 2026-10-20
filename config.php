<?php
declare(strict_types=1);

session_start();

// Passwort für die Verwaltungsseite ändern!
const ADMIN_PASSWORD = '8838Weber!';

// Speicherordner
define('PRIVATE_DIR', __DIR__ . '/private');
define('CONTACTS_FILE', PRIVATE_DIR . '/anfragen.json');

// Ordner erstellen, falls er nicht existiert
if (!is_dir(PRIVATE_DIR)) {
    if (!mkdir(PRIVATE_DIR, 0750, true) && !is_dir(PRIVATE_DIR)) {
        throw new RuntimeException('Speicherordner konnte nicht erstellt werden.');
    }
}

// JSON-Datei initialisieren
if (!file_exists(CONTACTS_FILE)) {
    if (file_put_contents(CONTACTS_FILE, "[]", LOCK_EX) === false) {
        throw new RuntimeException('JSON-Datei konnte nicht erstellt werden.');
    }
}

// Alle gespeicherten Anfragen lesen
function getContacts(): array
{
    $json = file_get_contents(CONTACTS_FILE);

    if ($json === false) {
        throw new RuntimeException('Anfragen-Datei konnte nicht gelesen werden.');
    }

    $data = json_decode($json, true);

    if (!is_array($data)) {
        throw new RuntimeException('Die JSON-Datei ist beschädigt oder ungültig.');
    }

    return $data;
}

// Anfragen sicher speichern
function saveContact(array $contact): bool
{
    $file = fopen(CONTACTS_FILE, 'c+');

    if ($file === false) {
        return false;
    }

    try {
        if (!flock($file, LOCK_EX)) {
            return false;
        }

        rewind($file);
        $json = stream_get_contents($file);

        $contacts = json_decode($json ?: '[]', true);

        if (!is_array($contacts)) {
            return false;
        }

        $contacts[] = $contact;

        $encoded = json_encode(
            $contacts,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        if ($encoded === false) {
            return false;
        }

        rewind($file);

        if (!ftruncate($file, 0)) {
            return false;
        }

        if (fwrite($file, $encoded) === false) {
            return false;
        }

        fflush($file);

        return true;

    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
}

// Ausgabe gegen HTML-Injection absichern
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
