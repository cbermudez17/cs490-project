<?php

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://web.njit.edu/~jmw34/middle.php');
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($_POST));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $r = curl_exec($ch);
    curl_close($ch);

    header('Content-Type: application/json');
    echo $r;
} else {
    header("HTTP/1.1 405 Method Not Allowed");
}
exit();
?>
