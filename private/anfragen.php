<?php
declare(strict_types=1);

require_once '../config.php';

$error = '';

// Abmelden
if (isset($_GET['logout'])) {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    header('Location: anfragen.php');
    exit;
}

// Anmeldung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $password = (string) $_POST['password'];

    if (hash_equals(ADMIN_PASSWORD, $password)) {
        session_regenerate_id(true);
        $_SESSION['contact_admin_logged_in'] = true;

        header('Location: anfragen.php');
        exit;
    }

    $error = 'Das Passwort ist nicht korrekt.';
}

$loggedIn = !empty($_SESSION['contact_admin_logged_in']);
$contacts = [];
$loadError = false;

if ($loggedIn) {
    try {
        $contacts = getContacts();

        // Neueste Anfrage zuerst
        usort($contacts, function ($a, $b) {
            return strcmp(
                (string) ($b['created_at'] ?? ''),
                (string) ($a['created_at'] ?? '')
            );
        });
    } catch (Throwable $exception) {
        error_log('Anfrage-Dashboard: ' . $exception->getMessage());
        $loadError = true;
    }
}

function formatDate(string $date): string
{
    try {
        $dt = new DateTimeImmutable($date);
        return $dt->format('d.m.Y · H:i');
    } catch (Throwable $exception) {
        return 'Unbekanntes Datum';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kontaktanfragen | Dashboard</title>
<style>
* { box-sizing: border-box; }

:root {
    --bg: #f5f6f8;
    --text: #20252d;
    --muted: #7c8492;
    --accent: #b48a78;
    --border: #e8eaf0;
}

body {
    margin: 0;
    background: var(--bg);
    color: var(--text);
    font-family: Inter, -apple-system, BlinkMacSystemFont,
                 "Segoe UI", Arial, sans-serif;
}

button, input { font: inherit; }

.login-wrap {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 20px;
}

.login-card {
    width: 100%;
    max-width: 400px;
    padding: 34px;
    border-radius: 18px;
    background: white;
    box-shadow: 0 15px 50px rgba(20,30,50,.08);
}

.login-card input {
    width: 100%;
    padding: 13px;
    margin: 12px 0;
    border: 1px solid var(--border);
    border-radius: 9px;
}

.primary {
    display: inline-block;
    border: 0;
    border-radius: 9px;
    padding: 12px 18px;
    color: white;
    background: var(--accent);
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
}

.login-card .primary { width: 100%; }

.error {
    color: #a32121;
    background: #fff0f0;
    border-radius: 8px;
    padding: 12px;
}

header {
    background: white;
    border-bottom: 1px solid var(--border);
}

.header-inner, .container {
    width: min(1100px, calc(100% - 40px));
    margin: 0 auto;
}

.header-inner {
    min-height: 82px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.brand { font-size: 20px; font-weight: 750; }
.brand span { color: var(--accent); }

.logout {
    color: #555e6b;
    text-decoration: none;
    font-size: 14px;
}

.container { padding: 38px 0 60px; }

.eyebrow {
    color: var(--accent);
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 1.5px;
    font-weight: 750;
}

h1 { margin: 8px 0; font-size: clamp(27px, 4vw, 36px); }
.subtitle { color: var(--muted); margin-top: 0; }

.stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin: 28px 0;
}

.stat, .toolbar, .contact-card, .empty {
    background: white;
    border: 1px solid var(--border);
    border-radius: 14px;
}

.stat { padding: 22px; }
.stat-label { color: var(--muted); font-size: 13px; }
.stat-value { font-size: 30px; font-weight: 750; margin-top: 8px; }

.toolbar { padding: 14px; margin-bottom: 18px; }

.toolbar input {
    width: 100%;
    padding: 13px 14px;
    border: 1px solid var(--border);
    border-radius: 9px;
    outline-color: var(--accent);
}

.contact-list {
    display: grid;
    gap: 14px;
}

.contact-card {
    padding: 22px;
    transition: box-shadow .2s, transform .2s;
}

.contact-card:hover {
    box-shadow: 0 8px 25px rgba(20,30,50,.05);
    transform: translateY(-1px);
}

.card-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.contact-name { font-size: 18px; font-weight: 700; }

.contact-date {
    font-size: 12px;
    color: var(--muted);
    margin-top: 6px;
}

.email {
    display: inline-block;
    margin-top: 12px;
    color: #8a6252;
    overflow-wrap: anywhere;
    text-decoration: none;
}

.email:hover { text-decoration: underline; }

.message {
    margin-top: 18px;
    padding: 16px;
    background: #f8f8fa;
    border-radius: 10px;
    line-height: 1.7;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.id {
    margin-top: 14px;
    font-size: 11px;
    color: #9aa0aa;
}

.empty {
    padding: 45px 20px;
    text-align: center;
    color: var(--muted);
}

@media (max-width: 600px) {
    .header-inner, .container {
        width: calc(100% - 28px);
    }

    .container { padding-top: 28px; }
    .stats { gap: 10px; }
    .stat { padding: 16px; }
    .stat-value { font-size: 25px; }
    .contact-card { padding: 17px; }
}
</style>
</head>
<body>

<?php if (!$loggedIn): ?>
    <main class="login-wrap">
        <section class="login-card">
            <div class="eyebrow">Administration</div>
            <h1>Kontaktanfragen</h1>
            <p class="subtitle">Bitte melden Sie sich an, um die Anfragen einzusehen.</p>

            <?php if ($error !== ''): ?>
                <p class="error"><?= e($error) ?></p>
            <?php endif; ?>

            <form method="post" action="anfragen.php">
                <label for="password">Admin-Passwort</label>
                <input type="password" id="password" name="password"
                       required autocomplete="current-password">
                <button class="primary" type="submit">Anmelden</button>
            </form>
        </section>
    </main>
<?php else: ?>

<header>
    <div class="header-inner">
        <div class="brand">Kontakt<span>.</span> Dashboard</div>
        <a class="logout" href="anfragen.php?logout=1">Abmelden ↗</a>
    </div>
</header>

<main class="container">
    <div class="eyebrow">Übersicht</div>
    <h1>Kontaktanfragen</h1>
    <p class="subtitle">Alle Nachrichten an einem Ort.</p>

    <?php if ($loadError): ?>
        <div class="error">
            Die Anfragen konnten nicht gelesen werden.
            Prüfe die JSON-Datei und die Dateiberechtigungen.
        </div>
    <?php else: ?>

        <section class="stats">
            <div class="stat">
                <div class="stat-label">Gespeicherte Anfragen</div>
                <div class="stat-value"><?= count($contacts) ?></div>
            </div>
            <div class="stat">
                <div class="stat-label">Neueste Anfrage</div>
                <div class="stat-value" style="font-size:18px">
                    <?php
                    echo !empty($contacts)
                        ? e(formatDate((string) ($contacts[0]['created_at'] ?? '')))
                        : 'Noch keine';
                    ?>
                </div>
            </div>
        </section>

        <div class="toolbar">
            <input type="search" id="search"
                   placeholder="Nach Name, E-Mail oder Nachricht suchen..."
                   aria-label="Anfragen durchsuchen">
        </div>

        <section class="contact-list" id="contactList">
            <?php foreach ($contacts as $contact): ?>
                <?php
                $contactName = (string) ($contact['name'] ?? 'Unbekannt');
                $contactEmail = (string) ($contact['email'] ?? '');
                $contactMessage = (string) ($contact['message'] ?? '');
                $contactDate = (string) ($contact['created_at'] ?? '');
                $contactId = (string) ($contact['id'] ?? '');
                $searchText = strtolower(
                    $contactName . ' ' . $contactEmail . ' ' . $contactMessage
                );
                ?>
                <article class="contact-card"
                    data-search="<?= e($searchText) ?>">

                    <div class="card-top">
                        <div>
                            <div class="contact-name"><?= e($contactName) ?></div>
                            <div class="contact-date">
                                <?= e(formatDate($contactDate)) ?>
                            </div>
                        </div>
                    </div>

                    <a class="email"
                       href="mailto:<?= e($contactEmail) ?>">
                        <?= e($contactEmail) ?>
                    </a>

                    <div class="message"><?= e($contactMessage) ?></div>

                    <?php if ($contactId !== ''): ?>
                        <div class="id">Anfrage-ID: <?= e($contactId) ?></div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>

            <div class="empty" id="noResults"
                 style="<?= count($contacts) === 0 ? '' : 'display:none' ?>">
                <?= count($contacts) === 0
                    ? 'Noch keine Kontaktanfragen vorhanden.'
                    : 'Keine passenden Anfragen gefunden.' ?>
            </div>
        </section>

        <script>
        const search = document.getElementById('search');
        const cards = [...document.querySelectorAll('.contact-card')];
        const noResults = document.getElementById('noResults');

        search.addEventListener('input', () => {
            const query = search.value.trim().toLocaleLowerCase();
            let visible = 0;

            cards.forEach(card => {
                const matches = card.dataset.search.includes(query);
                card.style.display = matches ? '' : 'none';
                if (matches) visible++;
            });

            noResults.style.display = visible === 0 ? '' : 'none';
        });
        </script>

    <?php endif; ?>
</main>

<?php endif; ?>
</body>
</html>
