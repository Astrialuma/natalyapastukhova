<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Eingaben säubern
    $name = htmlspecialchars(trim($_POST["name"]));
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $message = htmlspecialchars(trim($_POST["message"]));

    // Validierung
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Ungültige E-Mail-Adresse.");
    }

    if (empty($name) || empty($message)) {
        die("Bitte alle Felder ausfüllen.");
    }

    // Empfänger
    $to = "kontakt@natalyapastukhova.de";
    $subject = "Neue Kontaktanfrage von $name";

    // Nachricht
    $body = "Name: $name\n";
    $body .= "E-Mail: $email\n\n";
    $body .= "Nachricht:\n$message";

    // Header
    $headers = "From: $email\r\n";
    $headers .= "Reply-To: $email\r\n";

    // Senden
    if (mail($to, $subject, $body, $headers)) {
        echo "Vielen Dank! Ihre Nachricht wurde gesendet.";
    } else {
        echo "Fehler beim Senden. Bitte später erneut versuchen.";
    }
}
?>