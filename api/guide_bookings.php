<?php

header("Content-Type: application/json");

require_once "../config/database.php";

$email = trim($_GET["email"] ?? "");

if ($email === "") {

    echo json_encode([
        "success" => false,
        "msg" => "Guide email is required."
    ]);

    exit;
}


/* Find Guide */

$stmt = $conn->prepare("
    SELECT
        u.user_id,
        u.name,
        u.email,
        u.role,
        sp.provider_id
    FROM users u
    INNER JOIN service_providers sp
        ON sp.user_id = u.user_id
    WHERE u.email = ?
      AND u.role = 'Guide'
    LIMIT 1
");

$stmt->bind_param("s", $email);

$stmt->execute();

$result = $stmt->get_result();

$guide = $result->fetch_assoc();

$stmt->close();


if (!$guide) {

    echo json_encode([
        "success" => false,
        "msg" => "Guide account not found."
    ]);

    exit;
}


$provider_id = (int)$guide["provider_id"];


/* Get assigned bookings */

$stmt = $conn->prepare("
    SELECT
        b.booking_id,
        u.name AS traveler_name,
        d.name AS destination_name,
        b.travel_date,
        b.persons,
        b.days,
        b.food,
        b.stay,
        b.total_cost,
        b.status AS booking_status,
        b.guide_status

    FROM booking_services bs

    INNER JOIN bookings b
        ON b.booking_id = bs.booking_id

    INNER JOIN users u
        ON u.user_id = b.user_id

    INNER JOIN destinations d
        ON d.destination_id = b.destination_id

    WHERE bs.provider_id = ?

    ORDER BY b.booking_id DESC
");

$stmt->bind_param("i", $provider_id);

$stmt->execute();

$result = $stmt->get_result();

$bookings = [];


while ($row = $result->fetch_assoc()) {

    $bookings[] = [

        "booking_id" =>
            (int)$row["booking_id"],

        "name" =>
            $row["traveler_name"],

        "place" =>
            $row["destination_name"],

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

        "bookingStatus" =>
            $row["booking_status"],

        "guideStatus" =>
            $row["guide_status"]
    ];
}


$stmt->close();

$conn->close();


echo json_encode([

    "success" => true,

    "provider_id" =>
        $provider_id,

    "bookings" =>
        $bookings

]);
?>