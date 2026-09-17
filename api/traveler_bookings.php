<?php

header("Content-Type: application/json");

require_once "../config/database.php";


/* =========================================================
   GET TRAVELER EMAIL
========================================================= */

$email = trim($_GET["email"] ?? "");


if ($email === "") {

    echo json_encode([
        "success" => false,
        "msg" => "Traveler email is required."
    ]);

    exit;
}


/* =========================================================
   FIND TRAVELER
========================================================= */

$stmt = $conn->prepare("
    SELECT
        user_id,
        name,
        email,
        phone,
        role
    FROM users
    WHERE email = ?
      AND role = 'Traveler'
    LIMIT 1
");

$stmt->bind_param("s", $email);

$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();

$stmt->close();


if (!$user) {

    echo json_encode([
        "success" => false,
        "msg" => "Traveler account not found."
    ]);

    exit;
}


$user_id = (int)$user["user_id"];


/* =========================================================
   GET TRAVELER BOOKINGS
========================================================= */

$stmt = $conn->prepare("
    SELECT
        b.booking_id,
        d.name AS destination_name,
        d.location,
        b.travel_date,
        b.persons,
        b.days,
        b.food,
        b.stay,
        b.total_cost,
        b.status,
        b.driver_status,
        b.guide_status

    FROM bookings b

    INNER JOIN destinations d
        ON d.destination_id = b.destination_id

    WHERE b.user_id = ?

    ORDER BY b.booking_id DESC
");

$stmt->bind_param("i", $user_id);

$stmt->execute();

$result = $stmt->get_result();

$bookings = [];


while ($row = $result->fetch_assoc()) {

    $bookings[] = [

        "booking_id" =>
            (int)$row["booking_id"],

        "place" =>
            $row["destination_name"],

        "location" =>
            $row["location"],

        "date" =>
            $row["travel_date"],

        "persons" =>
            (int)$row["persons"],

        "days" =>
            (int)$row["days"],

        "food" =>
            $row["food"],

        "stay" =>
            $row["stay"],

        "totalCost" =>
            (float)$row["total_cost"],

        "status" =>
            $row["status"],

        "driverStatus" =>
            $row["driver_status"],

        "guideStatus" =>
            $row["guide_status"]
    ];
}


$stmt->close();

$conn->close();


/* =========================================================
   RESPONSE
========================================================= */

echo json_encode([

    "success" => true,

    "user" => [

        "user_id" =>
            (int)$user["user_id"],

        "name" =>
            $user["name"],

        "email" =>
            $user["email"],

        "phone" =>
            $user["phone"],

        "role" =>
            $user["role"]
    ],

    "bookings" =>
        $bookings
]);

?>