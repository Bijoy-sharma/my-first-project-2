<?php

header("Content-Type: application/json");

require_once "../config/database.php";


/* =========================================================
   ONLY POST REQUEST ALLOWED
========================================================= */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "msg" => "Invalid request method."
    ]);

    exit;
}


/* =========================================================
   GET JSON DATA
========================================================= */

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {

    echo json_encode([
        "success" => false,
        "msg" => "Invalid JSON data."
    ]);

    exit;
}


/* =========================================================
   RECEIVE DATA
========================================================= */

$name = trim($data["name"] ?? "");
$email = trim($data["email"] ?? "");
$phone = trim($data["phone"] ?? "");

$place = trim($data["place"] ?? "");

$persons = (int)($data["persons"] ?? 0);
$date = trim($data["date"] ?? "");
$days = (int)($data["days"] ?? 0);

$food = trim($data["food"] ?? "No");
$stay = trim($data["stay"] ?? "No");


/* =========================================================
   VALIDATION
========================================================= */

if (
    $name === "" ||
    $email === "" ||
    $place === "" ||
    $persons <= 0 ||
    $days <= 0 ||
    $date === ""
) {

    echo json_encode([
        "success" => false,
        "msg" => "Please provide all required booking information."
    ]);

    exit;
}


/* =========================================================
   FIND TRAVELER
========================================================= */

$stmt = $conn->prepare("
    SELECT user_id
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
        "msg" => "Traveler account not found. Please login first."
    ]);

    exit;
}

$user_id = (int)$user["user_id"];


/* =========================================================
   FIND DESTINATION
========================================================= */

$stmt = $conn->prepare("
    SELECT
        destination_id,
        budget,
        hotel_cost,
        resort_cost
    FROM destinations
    WHERE name = ?
    LIMIT 1
");

$stmt->bind_param("s", $place);

$stmt->execute();

$result = $stmt->get_result();

$destination = $result->fetch_assoc();

$stmt->close();


if (!$destination) {

    echo json_encode([
        "success" => false,
        "msg" => "Selected destination was not found in the database."
    ]);

    exit;
}

$destination_id = (int)$destination["destination_id"];


/* =========================================================
   FOOD COST
========================================================= */

$food_cost = 0;

if ($food === "Yes") {
    $food_cost = 800;
}


/* =========================================================
   ACCOMMODATION COST
========================================================= */

$stay_cost = 0;

if ($stay === "Hotel") {

    $stay_cost = (float)$destination["hotel_cost"];

} elseif ($stay === "Resort") {

    $stay_cost = (float)$destination["resort_cost"];

} else {

    // No accommodation
    $stay_cost = 0;
}


/* =========================================================
   BASE TRAVEL COST
========================================================= */

$total_cost =
    $persons *
    $days *
    ($food_cost + $stay_cost);


/* =========================================================
   INSERT BOOKING
========================================================= */

$stmt = $conn->prepare("
    INSERT INTO bookings
    (
        user_id,
        destination_id,
        travel_date,
        persons,
        days,
        food,
        stay,
        total_cost,
        status,
        driver_status,
        guide_status
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        'pending',
        'pending',
        'pending'
    )
");

$stmt->bind_param(
    "iisiissd",
    $user_id,
    $destination_id,
    $date,
    $persons,
    $days,
    $food,
    $stay,
    $total_cost
);


if ($stmt->execute()) {

    $booking_id = $stmt->insert_id;

    echo json_encode([
        "success" => true,
        "msg" => "Travel plan requested successfully.",
        "booking_id" => $booking_id,
        "total_cost" => $total_cost
    ]);

} else {

    echo json_encode([
        "success" => false,
        "msg" => "Failed to create booking."
    ]);
}


$stmt->close();
$conn->close();