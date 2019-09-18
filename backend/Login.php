<?php

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["username"]) && !empty($_POST["username"]) && isset($_POST["password"]) && !empty($_POST["password"])) {
        $conn = new mysqli("sql2.njit.edu", "cb283", "tJ8YOsDYk", "cb283");
        
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        
        $stmt = $conn->prepare("SELECT username FROM users WHERE username=? AND pass=?");
        $stmt->bind_param("ss", $_POST["username"], hash("sha512", $_POST["password"]));
        $stmt->execute();
        $stmt->bind_result($username);
        
        if ($stmt->fetch()) {
            $response = "Welcome back, " . $username;
        } else {
            $response = "Get out of here! We don't know you";
        }
        
        header('Content-Type: application/json');
        echo json_encode([ "message" => $response ]);
        
        $stmt->close();
        $conn->close();
    } else {
        header("HTTP/1.1 400 Bad Request");
    }
} else {
    header("HTTP/1.1 405 Method Not Allowed");
}

exit();

?>