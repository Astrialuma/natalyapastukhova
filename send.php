<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = htmlspecialchars(trim($_POST["name"]));
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $message = htmlspecialchars(trim($_POST["message"]));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $status = "error";
        $msg = "Ungültige E-Mail-Adresse.";
    } elseif (empty($name) || empty($message)) {
        $status = "error";
        $msg = "Bitte alle Felder ausfüllen.";
    } else {

        $mail = new PHPMailer(true);

        try {
            // SMTP Einstellungen (Hostinger)
            $mail->isSMTP();
            $mail->Host = 'smtp.hostinger.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'kontakt@natalyapastukhova.de'; // DEINE DOMAIN MAIL
            $mail->Password = 'DEIN_PASSWORT'; // HIER PASSWORT EINTRAGEN
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            // Absender & Empfänger
            $mail->setFrom('kontakt@natalyapastukhova.de', 'Website Kontakt');
            $mail->addAddress('maximilianfl@outlook.de');

            // Wichtig!
            $mail->addReplyTo($email, $name);

            // Inhalt
            $mail->Subject = "Neue Kontaktanfrage von $name";
            $mail->Body = "Name: $name\nE-Mail: $email\n\nNachricht:\n$message";

            $mail->send();

            $status = "success";
            $msg = "Vielen Dank! Ihre Anfrage wurde erfolgreich gesendet.";

        } catch (Exception $e) {
            $status = "error";
            $msg = "Fehler beim Senden: {$mail->ErrorInfo}";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Kontakt</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100vh;
}

.box {
    background: white;
    padding: 40px;
    border-radius: 12px;
    text-align: center;
    max-width: 400px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
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
    <h2 class="<?php echo $status; ?>">
        <?php echo $msg; ?>
    </h2>

    <a href="/" class="button">Zurück zur Startseite</a>
</div>

</body>
</html>