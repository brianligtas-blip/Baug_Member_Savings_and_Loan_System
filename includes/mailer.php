<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

$mailConfig = require __DIR__ . '/../config/mail.php';

function send_email($recipient, $recipientName, $subject, $message)
{
    global $mailConfig;

    $mail = new PHPMailer(true);

    try {
        // Gmail SMTP settings
        $mail->isSMTP();
        $mail->Host       = $mailConfig['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailConfig['username'];
        $mail->Password   = $mailConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $mailConfig['port'];

        // Sender information
        $mail->setFrom(
            $mailConfig['from_email'],
            $mailConfig['from_name']
        );

        // Recipient information
        $mail->addAddress($recipient, $recipientName);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);

        $mail->send();

        return true;

    } catch (Exception $e) {

        error_log("Email sending failed: " . $mail->ErrorInfo);

        return false;
    }
}