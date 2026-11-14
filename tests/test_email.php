<?php

require_once __DIR__ . '/../includes/mailer.php';

$recipient = 'brian.ligtas@csucc.edu.ph';
$recipientName = 'BAUG Test Recipient';

$subject = 'BAUG Email Test';

$message = '
<h2>BAUG Email Test</h2>
<p>Your Gmail SMTP configuration is working!</p>
<p>You can now proceed with email verification.</p>
';

if (send_email($recipient, $recipientName, $subject, $message)) {
    echo 'SUCCESS: Email sent successfully!';
} else {
    echo 'ERROR: Email could not be sent. Check your mail configuration.';
}