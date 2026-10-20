
<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ==================================================
// DEBUGGING: HIER EIN- UND AUSSCHALTEN
// ==================================================

$DEBUG = true; // true = Debugging an, false = Debugging aus

// ==================================================
// KONFIGURATION
// ==================================================

error_reporting(E_ALL);
ini_set('display_errors', $DEBUG ? '1' : '0');
ini_set('log_errors', '1');

// Debug-Ausgaben in eine Datei schreiben
$debugLog = __DIR__ . '/contact-debug.log';

function debugLog($text)
{
    global $DEBUG, $debugLog;

    if (!$DEBUG) {
        return;
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $text . PHP_EOL;

    // In PHP-Fehlerlog schreiben
    error_log(trim($line));

    // Zusätzlich in eigene Datei schreiben
    $result = @file_put_contents(
        $debugLog,
        $line,
        FILE_APPEND | LOCK_EX
    );

    if ($result === false) {
        error_log('DEBUG: Konnte Debug-Datei nicht beschreiben.');
    }
}

// PHP-Warnungen und Fehler protokollieren
set_error_handler(function ($severity, $message, $file, $line) {
    debugLog("PHP-Fehler [$severity]: $message in $file:$line");

    // Normale PHP-Behandlung beibehalten
    return false;
});

// Unbehandelte Ausnahmen protokollieren
set_exception_handler(function ($exception) {
    debugLog(
        'UNBEHANDELTE AUSNAHME: ' .
        get_class($exception) . ': ' .
        $exception->getMessage() .
        ' in ' . $exception->getFile() .
        ':' . $exception->getLine()
    );

    http_response_code(500);

    echo 'Ein unerwarteter Fehler ist aufgetreten.';

    if ($GLOBALS['DEBUG']) {
        echo '<pre>' .
            htmlspecialchars(
                $exception->getMessage(),
                ENT_QUOTES,
                'UTF-8'
            ) .
            '</pre>';
    }
});

// ==================================================
// PHPMailer LADEN
// ==================================================

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

debugLog('========================================');
debugLog('Kontaktformular aufgerufen');
debugLog('Request-Methode: ' . ($_SERVER['REQUEST_METHOD'] ?? 'unbekannt'));
debugLog('PHP-Version: ' . PHP_VERSION);

// ==================================================
// STATUS-VARIABLEN
// ==================================================

$status = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    debugLog('POST-Anfrage empfangen');

    // Eingaben sicher einlesen
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    debugLog('Name vorhanden: ' . ($name !== '' ? 'Ja' : 'Nein'));
    debugLog('E-Mail vorhanden: ' . ($email !== '' ? 'Ja' : 'Nein'));
    debugLog('Nachricht vorhanden: ' . ($message !== '' ? 'Ja' : 'Nein'));

    // ==================================================
    // EINGABEN VALIDIEREN
    // ==================================================

    if ($name === '' || $email === '' || $message === '') {

        debugLog('VALIDIERUNG FEHLGESCHLAGEN: Feld leer');

        $status = 'error';
        $msg = 'Bitte alle Felder ausfüllen.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        debugLog('VALIDIERUNG FEHLGESCHLAGEN: Ungültige E-Mail-Adresse');

        $status = 'error';
        $msg = 'Ungültige E-Mail-Adresse.';

    } else {

        debugLog('Validierung erfolgreich');

        // ==================================================
        // PHPMailer INITIALISIEREN
        // ==================================================

        $mail = new PHPMailer(true);

        try {

            debugLog('PHPMailer wird konfiguriert');

            // SMTP-Debug-Ausgaben ebenfalls protokollieren
            if ($DEBUG) {
                $mail->SMTPDebug = 2;

                $mail->Debugoutput = function ($str, $level) {
                    debugLog("SMTP DEBUG [$level]: " . trim($str));
                };
            } else {
                $mail->SMTPDebug = 0;
            }

            // SMTP-Einstellungen
            $mail->isSMTP();

            $mail->Host = 'smtp.titan.email';
            $mail->SMTPAuth = true;

            $mail->Username = 'kontakt@natalyapastukhova.de';

            // Besser über eine Umgebungsvariable bereitstellen
            $mail->Password = getenv('TITAN_SMTP_PASSWORD') ?: '';

            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            $mail->Timeout = 20;
            $mail->CharSet = 'UTF-8';

            // Absender
            $mail->setFrom(
                'kontakt@natalyapastukhova.de',
                'Website Kontakt'
            );

            $mail->addReplyTo($email, $name);

            // Empfänger
            $mail->addAddress('kontakt@natalyapastukhova.de');

            // Inhalt
            $mail->isHTML(false);
            $mail->Subject = "Neue Kontaktanfrage von $name";

            $body  = "Name: $name\n";
            $body .= "E-Mail: $email\n\n";
            $body .= "Nachricht:\n$message";

            $mail->Body = $body;

            debugLog('SMTP-Konfiguration abgeschlossen');

            if ($mail->Password === '') {
                throw new Exception(
                    'SMTP-Passwort fehlt. TITAN_SMTP_PASSWORD ist nicht gesetzt.'
                );
            }

            debugLog('Versuche SMTP-Verbindung und E-Mail-Versand');

            // ==================================================
            // E-MAIL SENDEN
            // ==================================================

            $mail->send();

            debugLog('ERFOLG: PHPMailer send() war erfolgreich');

            $status = 'success';
            $msg = 'Vielen Dank! Ihre Anfrage wurde erfolgreich gesendet.';

        } catch (\Throwable $e) {

            debugLog(
                'VERSANDFEHLER: ' .
                get_class($e) . ': ' .
                $e->getMessage()
            );

            if (isset($mail)) {
                debugLog('PHPMailer ErrorInfo: ' . $mail->ErrorInfo);
            }

            $status = 'error';

            // Im Debug-Modus mehr Informationen anzeigen
            if ($DEBUG) {
                $msg = 'Fehler beim Senden: ' .
                    ($mail->ErrorInfo ?: $e->getMessage());
            } else {
                $msg = 'Die Nachricht konnte nicht gesendet werden. Bitte versuchen Sie es später erneut.';
            }
        }
    }

} else {
    debugLog('Kein POST: Formularseite wurde normal aufgerufen');
}

debugLog('Verarbeitung abgeschlossen');

?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kontakt</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    margin: 0;
}

.box {
    background: white;
    padding: 40px;
    border-radius: 12px;
    text-align: center;
    width: calc(100% - 80px);
    max-width: 400px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    overflow-wrap: anywhere;
}

.success {
    color: green;
}

.error {
    color: red;
}

a.button {
    display: inline-block;
    margin-top: 20px;
    padding: 12px 20px;
    background: #b48a78;
    color: white;
    text-decoration: none;
    border-radius: 6px;
}
</style>
</head>

<body>
<div class="box">

    <?php if ($status !== ''): ?>
        <h2 class="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
        </h2>
    <?php else: ?>
        <h2>Kontakt</h2>
        <p>Bitte füllen Sie das Kontaktformular aus.</p>
    <?php endif; ?>

    <a href="/" class="button">Zurück zur Startseite</a>

</div>
</body>
</html>
