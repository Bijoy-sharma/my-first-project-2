<?php

header("Content-Type: application/json");

require_once "../config/database.php";


/* =========================================================
   CHECK REQUEST METHOD
========================================================= */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "msg" => "Invalid request method."
    ]);

    exit;
}


/* =========================================================
   READ JSON DATA
========================================================= */

$data = json_decode(
    file_get_contents("php://input"),
    true
);

if (!$data) {

    echo json_encode([
        "success" => false,
        "msg" => "Invalid JSON data."
    ]);

    exit;
}


/* =========================================================
   GET LOGIN DATA
========================================================= */

$email = trim($data["email"] ?? "");

$password = $data["password"] ?? "";


if ($email === "" || $password === "") {

    echo json_encode([
        "success" => false,
        "msg" => "Email and password are required."
    ]);

    exit;
}


/* =========================================================
   FIND USER
========================================================= */

$stmt = $conn->prepare("
    SELECT
        user_id,
        name,
        email,
        password,
        role,
        phone
    FROM users
    WHERE email = ?
    LIMIT 1
");

$stmt->bind_param("s", $email);

$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   USER NOT FOUND
========================================================= */

if (!$user) {

    echo json_encode([
        "success" => false,
        "msg" => "Invalid email or password."
    ]);

    exit;
}


/* =========================================================
   CHECK PASSWORD
========================================================= */

$storedPassword = $user["password"] ?? "";

$passwordCorrect = false;


/*
   Your current database stores plaintext passwords.
   Therefore compare directly for now.
*/

if (
    $storedPassword !== "" &&
    hash_equals(
        (string)$storedPassword,
        (string)$password
    )
) {

    $passwordCorrect = true;
}


if (!$passwordCorrect) {

    echo json_encode([
        "success" => false,
        "msg" => "Invalid email or password."
    ]);

    exit;
}


/* =========================================================
   LOGIN SUCCESS
========================================================= */

echo json_encode([

    "success" => true,

    "msg" => "Login successful.",

    "user" => [

        "user_id" => (int)$user["user_id"],

        "name" => $user["name"],

        "email" => $user["email"],

        "role" => $user["role"],

        "phone" => $user["phone"]

    ]

]);


$conn->close();

?>