<?php

header("Content-Type: application/json");

require_once "../config/database.php";


/* =========================================================
   GET DESTINATION NAME
========================================================= */

$place = trim($_GET["place"] ?? "");


if ($place === "") {

    echo json_encode([
        "success" => false,
        "msg" => "Destination is required."
    ]);

    exit;
}


/* =========================================================
   GET DESTINATION PRICES
========================================================= */

$stmt = $conn->prepare("
    SELECT
        destination_id,
        name,
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


/* =========================================================
   DESTINATION NOT FOUND
========================================================= */

if (!$destination) {

    echo json_encode([
        "success" => false,
        "msg" => "Destination not found."
    ]);

    exit;
}


/* =========================================================
   RETURN DATA
========================================================= */

echo json_encode([
    "success" => true,
    "destination_id" => (int)$destination["destination_id"],
    "name" => $destination["name"],
    "hotel_cost" => (float)$destination["hotel_cost"],
    "resort_cost" => (float)$destination["resort_cost"]
]);


$conn->close();