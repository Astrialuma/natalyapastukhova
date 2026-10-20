<?php
declare(strict_types=1);
error_log('Kontaktformular-Speicherpfad: ' . __DIR__ . '/private/anfragen.json');
error_log('Private-Verzeichnis existiert: ' . (is_dir(__DIR__ . '/private') ? 'JA' : 'NEIN'));
error_log('Private-Verzeichnis beschreibbar: ' . (is_writable(__DIR__ . '/private') ? 'JA' : 'NEIN'));
require_once __DIR__ . '/config.php';



$status = '';
$msg = '';
$name = '';
$email = '';
$message = '';
$redirectAfterSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    // Spam-Falle
    $honeypot = trim((string) ($_POST['website'] ?? ''));

    if ($honeypot !== '') {
        $status = 'success';
        $msg = 'Vielen Dank! Ihre Anfrage wurde abgeschickt.';
        $redirectAfterSuccess = true;

    } elseif ($name === '' || $email === '' || $message === '') {
        $status = 'error';
        $msg = 'Bitte füllen Sie alle Felder aus.';

    } elseif (
        strlen($name) > 120 ||
        strlen($email) > 254 ||
        strlen($message) > 10000
    ) {
        $status = 'error';
        $msg = 'Ihre Eingabe ist zu lang.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $status = 'error';
        $msg = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';

    } else {
        $contact = [
            'id' => bin2hex(random_bytes(8)),
            'name' => $name,
            'email' => $email,
            'message' => $message,
            'created_at' => date(DATE_ATOM),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ];

        try {
            if (saveContact($contact)) {
                $status = 'success';
                $msg = 'Vielen Dank! Ihre Anfrage wurde erfolgreich abgeschickt.';
                $redirectAfterSuccess = true;

                $name = '';
                $email = '';
                $message = '';
            } else {
                $status = 'error';
                $msg = 'Speichern fehlgeschlagen. Bitte versuchen Sie es später erneut.';
            }
        } catch (Throwable $exception) {
            error_log('Kontaktformular: ' . $exception->getMessage());

            $status = 'error';
            $msg = 'Ein interner Fehler ist aufgetreten.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kontakt</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    padding: 30px 16px;
    display: grid;
    place-items: center;
    background: #f5f5f5;
    color: #252525;
    font-family: Arial, sans-serif;
}

.card {
    width: 100%;
    max-width: 480px;
    padding: 36px;
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, .07);
}

h1 {
    margin-top: 0;
    font-size: 28px;
}

.subtitle {
    color: #777;
    line-height: 1.6;
}

label {
    display: block;
    margin: 18px 0 7px;
    font-weight: 600;
    font-size: 14px;
}

input,
textarea {
    width: 100%;
    padding: 13px 14px;
    border: 1px solid #ddd;
    border-radius: 9px;
    font: inherit;
}

input:focus,
textarea:focus {
    outline: 2px solid #b48a78;
    border-color: transparent;
}

textarea {
    min-height: 150px;
    resize: vertical;
}

button,
.redirect-button {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    margin-top: 22px;
    padding: 14px;
    border: 0;
    border-radius: 9px;
    background: #b48a78;
    color: white;
    font-size: 16px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: background .2s ease;
}

button:hover,
.redirect-button:hover {
    background: #986e5d;
}

.notice {
    margin-bottom: 20px;
    padding: 13px;
    border-radius: 8px;
    line-height: 1.5;
}

.error {
    color: #a32121;
    background: #fff0f0;
}

.honeypot {
    position: absolute;
    left: -10000px;
    width: 1px;
    height: 1px;
    overflow: hidden;
}

/* Erfolgs-Popup */
.popup-overlay {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
    background: rgba(0, 0, 0, .35);
    animation: overlayIn .25s ease;
}

.success-popup {
    width: 100%;
    max-width: 390px;
    padding: 32px 26px;
    text-align: center;
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 15px 50px rgba(0, 0, 0, .18);
    animation: popupIn .3s ease;
}

.success-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 56px;
    height: 56px;
    margin: 0 auto 18px;
    border-radius: 50%;
    background: #eaf7ee;
    color: #25834b;
    font-size: 30px;
    font-weight: bold;
}

.success-popup h2 {
    margin: 0 0 12px;
    font-size: 23px;
}

.success-popup p {
    margin: 0;
    color: #666;
    line-height: 1.6;
}

.redirect-countdown {
    margin-top: 14px !important;
    font-size: 13px;
    color: #888 !important;
}

@keyframes overlayIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes popupIn {
    from {
        opacity: 0;
        transform: translateY(12px) scale(.97);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@media (max-width: 480px) {
    .card {
        padding: 24px;
    }
}
</style>
</head>

<body>

<main class="card " style="display: none">
    <h1>Kontakt</h1>

    <p class="subtitle">
        Sie haben eine Frage? Schreiben Sie uns.
        Wir freuen uns auf Ihre Nachricht.
    </p>

    <?php if ($status === 'error'): ?>
        <div class="notice error" role="alert">
            <?= e($msg) ?>
        </div>
    <?php endif; ?>

    <?php if (!$redirectAfterSuccess): ?>
    <form method="post" action="">
        <div class="honeypot" aria-hidden="true">
            <label for="website">Website</label>
            <input
                type="text"
                id="website"
                name="website"
                tabindex="-1"
                autocomplete="off"
            >
        </div>

        <label for="name">Ihr Name</label>
        <input
            id="name"
            name="name"
            type="text"
            maxlength="120"
            required
            autocomplete="name"
            value="<?= e($name) ?>"
        >

        <label for="email">E-Mail-Adresse</label>
        <input
            id="email"
            name="email"
            type="email"
            maxlength="254"
            required
            autocomplete="email"
            value="<?= e($email) ?>"
        >

        <label for="message">Ihre Nachricht</label>
        <textarea
            id="message"
            name="message"
            maxlength="10000"
            required
        ><?= e($message) ?></textarea>

        <button type="submit">Nachricht senden</button>
    </form>
    <?php endif; ?>
</main>

<?php if ($redirectAfterSuccess): ?>
<div class="popup-overlay">
    <div class="success-popup" role="status">
        <div class="success-icon">✓</div>

        <h2>Nachricht gesendet!</h2>

        <p><?= e($msg) ?></p>

        <a class="redirect-button" href="/">
            Jetzt zur Startseite
        </a>

        <p class="redirect-countdown">
            Automatische Weiterleitung in
            <span id="countdown">5</span> Sekunden ...
        </p>
    </div>
</div>

<script>
(function () {
    let seconds = 5;
    const countdown = document.getElementById('countdown');

    const timer = setInterval(function () {
        seconds--;
        countdown.textContent = seconds;

        if (seconds <= 0) {
            clearInterval(timer);
            window.location.href = '/';
        }
    }, 1000);
})();
</script>
<?php endif; ?>

</body>
</html>
