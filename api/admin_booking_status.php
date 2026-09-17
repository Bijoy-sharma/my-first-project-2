<?php

header("Content-Type: application/json");

require_once "../config/database.php";


/* =========================================================
   METHOD CHECK
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

$data =
    json_decode(
        file_get_contents("php://input"),
        true
    );


$booking_id =
    (int)($data["booking_id"] ?? 0);

$status =
    strtolower(
        trim(
            $data["status"] ?? ""
        )
    );

$admin_email =
    trim(
        $data["admin_email"] ?? ""
    );


/* =========================================================
   VALIDATION
========================================================= */

$allowed_statuses = [
    "approved",
    "rejected"
];


if (
    $booking_id <= 0 ||
    $admin_email === "" ||
    !in_array(
        $status,
        $allowed_statuses,
        true
    )
) {

    echo json_encode([
        "success" => false,
        "msg" => "Invalid booking, admin or status."
    ]);

    exit;
}


/* =========================================================
   VERIFY ADMIN
========================================================= */

$stmt = $conn->prepare("
    SELECT
        user_id,
        name,
        email,
        role
    FROM users
    WHERE email = ?
      AND role = 'Admin'
    LIMIT 1
");

$stmt->bind_param(
    "s",
    $admin_email
);

$stmt->execute();

$result =
    $stmt->get_result();

$admin =
    $result->fetch_assoc();

$stmt->close();


if (!$admin) {

    echo json_encode([
        "success" => false,
        "msg" => "Admin account not found."
    ]);

    exit;
}


/* =========================================================
   CHECK BOOKING
========================================================= */

$stmt = $conn->prepare("
    SELECT
        booking_id
    FROM bookings
    WHERE booking_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $booking_id
);

$stmt->execute();

$result =
    $stmt->get_result();

$booking =
    $result->fetch_assoc();

$stmt->close();


if (!$booking) {

    echo json_encode([
        "success" => false,
        "msg" => "Booking not found."
    ]);

    exit;
}


/* =========================================================
   UPDATE STATUS
========================================================= */

$stmt = $conn->prepare("
    UPDATE bookings
    SET status = ?
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
            "Booking #{$booking_id} has been {$status}.",

        "booking_id" =>
            $booking_id,

        "status" =>
            $status
    ]);

} else {

    echo json_encode([

        "success" => false,

        "msg" =>
            "Failed to update booking status."
    ]);
}


$stmt->close();

$conn->close();

?>