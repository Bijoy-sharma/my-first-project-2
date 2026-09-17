<?php

header("Content-Type: application/json");

require_once "../config/database.php";


/* 
   CHECK REQUEST METHOD
 */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "msg" => "Invalid request method."
    ]);

    exit;
}


/* 
   READ JSON DATA
 */

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


/* 
   GET DATA
 */

$booking_id =
    (int)($data["booking_id"] ?? 0);

$driver_email =
    trim($data["driver_email"] ?? "");

$status =
    trim($data["status"] ?? "");


/* =========================================================
   VALIDATE DATA
========================================================= */

if (
    $booking_id <= 0 ||
    $driver_email === "" ||
    $status === ""
) {

    echo json_encode([
        "success" => false,
        "msg" => "Booking ID, driver email and status are required."
    ]);

    exit;
}


/* =========================================================
   VALIDATE STATUS
========================================================= */

$allowed_statuses = [
    "accepted",
    "rejected"
];


if (!in_array($status, $allowed_statuses, true)) {

    echo json_encode([
        "success" => false,
        "msg" => "Invalid driver status."
    ]);

    exit;
}


/* =========================================================
   FIND DRIVER
========================================================= */

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
      AND u.role = 'Driver'
    LIMIT 1
");


$stmt->bind_param(
    "s",
    $driver_email
);


$stmt->execute();

$result =
    $stmt->get_result();

$driver =
    $result->fetch_assoc();

$stmt->close();


if (!$driver) {

    echo json_encode([
        "success" => false,
        "msg" => "Driver account not found."
    ]);

    exit;
}


$provider_id =
    (int)$driver["provider_id"];


/* =========================================================
   CHECK DRIVER ASSIGNMENT
========================================================= */

$stmt = $conn->prepare("
    SELECT
        booking_service_id
    FROM booking_services
    WHERE booking_id = ?
      AND provider_id = ?
    LIMIT 1
");


$stmt->bind_param(
    "ii",
    $booking_id,
    $provider_id
);


$stmt->execute();

$result =
    $stmt->get_result();

$assignment =
    $result->fetch_assoc();

$stmt->close();


if (!$assignment) {

    echo json_encode([
        "success" => false,
        "msg" => "This booking is not assigned to this driver."
    ]);

    exit;
}


/* =========================================================
   UPDATE DRIVER STATUS
========================================================= */

$stmt = $conn->prepare("
    UPDATE bookings
    SET driver_status = ?
    WHERE booking_id = ?
");


$stmt->bind_param(
    "si",
    $status,
    $booking_id
);


if ($stmt->execute()) {

    echo json_encode([
        "success" => true,
        "msg" => "Driver status updated successfully.",
        "booking_id" => $booking_id,
        "status" => $status
    ]);

} else {

    echo json_encode([
        "success" => false,
        "msg" => "Failed to update driver status."
    ]);

}


$stmt->close();

$conn->close();

?>