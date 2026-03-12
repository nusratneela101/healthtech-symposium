<?php
$email = $_GET['email'] ?? '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit('Invalid email address.');
}
header("HTTP/1.1 301 Moved Permanently");
header("Location: https://fintech.softandpix.com/unsubscribe.php?email=" . urlencode($email));
exit();
