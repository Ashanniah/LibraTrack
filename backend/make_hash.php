<?php
// Visit this file in your browser once to print a bcrypt hash, then delete it.
$plain = 'admin123'; // or any password you want
$hash = password_hash($plain, PASSWORD_BCRYPT); 
echo $hash, PHP_EOL;