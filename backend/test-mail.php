<?php
require_once __DIR__ . '/mailer.php';

$result = send_mail(
  'your.personal.email@gmail.com',         // recipient to test with
  'LibraTrack Test Email',
  '<p>Hello! This is a test email from your LibraTrack system.</p>'
);

var_dump($result);
