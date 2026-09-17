<?php

header("Content-Type: application/json");

require_once "../config/database.php";


if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "msg" => "Invalid request method."
    ]);

    exit;
}


$data = json_decode(
    file_get_contents("php://input"),
    true
);


$booking_id =
    (int)($data["booking_id"] ?? 0);

$guide_email =
    trim($data["guide_email"] ?? "");

$status =
    trim($data["status"] ?? "");


$allowed_statuses = [
    "accepted",
    "rejected"
];


if (
    $booking_id <= 0 ||
    $guide_email === "" ||
    !in_array($status, $allowed_statuses, true)
) {

    echo json_encode([
        "success" => false,
        "msg" => "Invalid booking, guide, or status."
    ]);

    exit;
}


/* Find Guide provider */

$stmt = $conn->prepare("
    SELECT sp.provider_id

    FROM users u

    INNER JOIN service_providers sp
        ON sp.user_id = u.user_id

    WHERE u.email = ?
      AND u.role = 'Guide'

    LIMIT 1
");

$stmt->bind_param(
    "s",
    $guide_email
);

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


$provider_id =
    (int)$guide["provider_id"];


/* Verify assignment */

$stmt = $conn->prepare("
    SELECT booking_service_id

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

$result = $stmt->get_result();

$assignment = $result->fetch_assoc();

$stmt->close();


if (!$assignment) {

    echo json_encode([
        "success" => false,
        "msg" => "This booking is not assigned to this guide."
    ]);

    exit;
}


/* Update Guide status */

$stmt = $conn->prepare("
    UPDATE bookings

    SET guide_status = ?

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

        "msg" =>
            "Guide status updated successfully.",

        "booking_id" =>
            $booking_id,

        "status" =>
            $status
    ]);

} else {

    echo json_encode([

        "success" => false,

        "msg" =>
            "Failed to update guide status."
    ]);
}


$stmt->close();

$conn->close();

?>