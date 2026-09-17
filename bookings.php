<?php

require_once "config/database.php";


/* =========================================================
   HELPER
========================================================= */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   VARIABLES
========================================================= */

$message = "";
$message_type = "";

$search = isset($_GET["search"])
    ? trim($_GET["search"])
    : "";

$status_filter = isset($_GET["status"])
    ? trim($_GET["status"])
    : "";


/* =========================================================
   ADD BOOKING
========================================================= */

if (isset($_POST["add_booking"])) {

    $user_id =
        (int)($_POST["user_id"] ?? 0);

    $destination_id =
        (int)($_POST["destination_id"] ?? 0);

    $travel_date =
        trim($_POST["travel_date"] ?? "");

    $persons =
        (int)($_POST["persons"] ?? 0);

    $days =
        (int)($_POST["days"] ?? 0);

    $food =
        trim($_POST["food"] ?? "No");

    $stay =
        trim($_POST["stay"] ?? "No");

    $total_cost =
        trim($_POST["total_cost"] ?? "");


    if (
        $user_id <= 0 ||
        $destination_id <= 0 ||
        $travel_date === "" ||
        $persons <= 0 ||
        $days <= 0
    ) {

        $message =
            "Please fill in all required booking fields.";

        $message_type =
            "error";

    } else {

        if ($total_cost === "") {

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
                    total_cost
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, NULL)
            ");

            $stmt->bind_param(
                "iisiiss",
                $user_id,
                $destination_id,
                $travel_date,
                $persons,
                $days,
                $food,
                $stay
            );

        } else {

            $total_cost_value =
                (float)$total_cost;

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
                    total_cost
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "iisiissd",
                $user_id,
                $destination_id,
                $travel_date,
                $persons,
                $days,
                $food,
                $stay,
                $total_cost_value
            );
        }


        if ($stmt->execute()) {

            $message =
                "Booking added successfully.";

            $message_type =
                "success";

        } else {

            $message =
                "Failed to add booking. Please check the selected traveler and destination.";

            $message_type =
                "error";
        }


        $stmt->close();
    }
}



/* =========================================================
   DELETE BOOKING
========================================================= */

if (isset($_GET["delete"])) {

    $booking_id =
        (int)$_GET["delete"];


    if ($booking_id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM bookings
            WHERE booking_id = ?
        ");

        $stmt->bind_param(
            "i",
            $booking_id
        );


        if ($stmt->execute()) {

            $message =
                "Booking deleted successfully.";

            $message_type = "success";

        } else {

            $message =
                "Unable to delete booking. It may be connected to booking services.";

            $message_type = "error";
        }

        $stmt->close();
    }
}


/* =========================================================
   UPDATE BOOKING
========================================================= */

if (isset($_POST["update_booking"])) {

    $booking_id =
        (int)($_POST["booking_id"] ?? 0);

    $user_id =
        (int)($_POST["user_id"] ?? 0);

    $destination_id =
        (int)($_POST["destination_id"] ?? 0);

    $travel_date =
        trim($_POST["travel_date"] ?? "");

    $persons =
        (int)($_POST["persons"] ?? 0);

    $days =
        (int)($_POST["days"] ?? 0);

    $food =
        trim($_POST["food"] ?? "No");

    $stay =
        trim($_POST["stay"] ?? "No");

    $total_cost =
        trim($_POST["total_cost"] ?? "");

    $status =
        trim($_POST["status"] ?? "pending");

    $driver_status =
        trim($_POST["driver_status"] ?? "pending");

    $guide_status =
        trim($_POST["guide_status"] ?? "pending");


    if (
        $booking_id <= 0 ||
        $user_id <= 0 ||
        $destination_id <= 0 ||
        $travel_date === "" ||
        $persons <= 0 ||
        $days <= 0
    ) {

        $message =
            "Please fill in all required booking fields.";

        $message_type = "error";

    } else {

        if ($total_cost === "") {

            $stmt = $conn->prepare("
                UPDATE bookings
                SET
                    user_id = ?,
                    destination_id = ?,
                    travel_date = ?,
                    persons = ?,
                    days = ?,
                    food = ?,
                    stay = ?,
                    total_cost = NULL,
                    status = ?,
                    driver_status = ?,
                    guide_status = ?
                WHERE booking_id = ?
            ");

            $stmt->bind_param(
                "iisiisssssi",
                $user_id,
                $destination_id,
                $travel_date,
                $persons,
                $days,
                $food,
                $stay,
                $status,
                $driver_status,
                $guide_status,
                $booking_id
            );

        } else {

            $total_cost_value =
                (float)$total_cost;

            $stmt = $conn->prepare("
                UPDATE bookings
                SET
                    user_id = ?,
                    destination_id = ?,
                    travel_date = ?,
                    persons = ?,
                    days = ?,
                    food = ?,
                    stay = ?,
                    total_cost = ?,
                    status = ?,
                    driver_status = ?,
                    guide_status = ?
                WHERE booking_id = ?
            ");

            $stmt->bind_param(
                "iisiissdsssi",
                $user_id,
                $destination_id,
                $travel_date,
                $persons,
                $days,
                $food,
                $stay,
                $total_cost_value,
                $status,
                $driver_status,
                $guide_status,
                $booking_id
            );
        }


        if ($stmt->execute()) {

            $message =
                "Booking updated successfully.";

            $message_type = "success";

        } else {

            $message =
                "Failed to update booking.";

            $message_type = "error";
        }

        $stmt->close();
    }
}


/* =========================================================
   EDIT BOOKING
========================================================= */

$edit_booking = null;

if (isset($_GET["edit"])) {

    $edit_id =
        (int)$_GET["edit"];


    if ($edit_id > 0) {

        $stmt = $conn->prepare("
            SELECT
                booking_id,
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
            FROM bookings
            WHERE booking_id = ?
        ");

        $stmt->bind_param(
            "i",
            $edit_id
        );

        $stmt->execute();

        $edit_result =
            $stmt->get_result();


        if ($edit_result->num_rows === 1) {

            $edit_booking =
                $edit_result->fetch_assoc();
        }

        $stmt->close();
    }
}


/* =========================================================
   USERS FOR FORM
========================================================= */

$users_result = $conn->query("
    SELECT
        user_id,
        name,
        email,
        role
    FROM users
    ORDER BY name ASC
");


/* =========================================================
   DESTINATIONS FOR FORM
========================================================= */

$destinations_result = $conn->query("
    SELECT
        destination_id,
        name,
        location
    FROM destinations
    ORDER BY name ASC
");


/* =========================================================
   BOOKING LIST
   JOIN USERS + DESTINATIONS
========================================================= */

$sql = "
    SELECT
        b.booking_id,
        b.travel_date,
        b.persons,
        b.days,
        b.food,
        b.stay,
        b.total_cost,
        b.status,
        b.driver_status,
        b.guide_status,

        u.name AS traveler_name,
        u.email AS traveler_email,

        d.name AS destination_name,
        d.location AS destination_location

    FROM bookings b

    INNER JOIN users u
        ON b.user_id = u.user_id

    INNER JOIN destinations d
        ON b.destination_id = d.destination_id

    WHERE 1 = 1
";


$params = [];
$types = "";


/* SEARCH */

if ($search !== "") {

    $sql .= "
        AND (
            u.name LIKE ?
            OR u.email LIKE ?
            OR d.name LIKE ?
            OR d.location LIKE ?
        )
    ";

    $search_value =
        "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "ssss";
}


/* STATUS FILTER */

$allowed_statuses = [
    "pending",
    "approved",
    "rejected"
];


if (
    $status_filter !== "" &&
    in_array(
        $status_filter,
        $allowed_statuses,
        true
    )
) {

    $sql .= "
        AND b.status = ?
    ";

    $params[] =
        $status_filter;

    $types .= "s";
}


$sql .= "
    ORDER BY b.booking_id DESC
";


$stmt =
    $conn->prepare($sql);


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


$stmt->execute();

$result =
    $stmt->get_result();


/* =========================================================
   STATISTICS
========================================================= */

$total_bookings = 0;
$pending_bookings = 0;
$approved_bookings = 0;
$rejected_bookings = 0;
$total_revenue = 0;


$count_result = $conn->query("
    SELECT
        COUNT(*) AS total_bookings,

        SUM(
            CASE
                WHEN status = 'pending'
                THEN 1
                ELSE 0
            END
        ) AS pending_bookings,

        SUM(
            CASE
                WHEN status = 'approved'
                THEN 1
                ELSE 0
            END
        ) AS approved_bookings,

        SUM(
            CASE
                WHEN status = 'rejected'
                THEN 1
                ELSE 0
            END
        ) AS rejected_bookings,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'approved'
                    THEN total_cost
                    ELSE 0
                END
            ),
            0
        ) AS total_revenue

    FROM bookings
");


if ($count_result) {

    $count_row =
        $count_result->fetch_assoc();

    $total_bookings =
        (int)$count_row["total_bookings"];

    $pending_bookings =
        (int)$count_row["pending_bookings"];

    $approved_bookings =
        (int)$count_row["approved_bookings"];

    $rejected_bookings =
        (int)$count_row["rejected_bookings"];

    $total_revenue =
        (float)$count_row["total_revenue"];
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Pocket Tour | Bookings
    </title>


    <!-- GOOGLE FONT -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- SHARED CSS -->

    <link
        rel="stylesheet"
        href="assets/style.css"
    >


    <style>

        /* =================================================
           BASE / POLISH
        ================================================= */

        * {
            box-sizing: border-box;
        }

        ::selection {
            background: rgba(15,139,141,.22);
        }

        ::-webkit-scrollbar {
            height: 8px;
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--canvas);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 999px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--teal-500);
        }


        /* =================================================
           BOOKING PAGE
        ================================================= */

        .booking-page-header {

            display:flex;

            align-items:flex-end;

            justify-content:space-between;

            margin-bottom:24px;

            gap:20px;
        }

        .booking-page-header h1 {

            color:var(--ink-900);

            font-size:28px;

            font-weight:800;

            line-height:1.2;

            letter-spacing:-.3px;
        }

        .booking-page-header p {

            margin-top:6px;

            color:var(--ink-600);

            font-size:13px;
        }

        .booking-page-tag {

            display:inline-flex;

            align-items:center;

            gap:6px;

            margin-bottom:8px;

            color:var(--teal-500);

            font-size:10.5px;

            font-weight:800;

            letter-spacing:1.2px;

            text-transform:uppercase;
        }

        .booking-page-tag::before {

            content:"";

            width:6px;

            height:6px;

            border-radius:50%;

            background:var(--teal-500);

            box-shadow:0 0 0 4px rgba(15,139,141,.15);
        }


        /* =================================================
           STATS
        ================================================= */

        .booking-stats {

            display:grid;

            grid-template-columns:
                repeat(4,minmax(0,1fr));

            gap:16px;

            margin-bottom:24px;
        }

        .booking-stat {

            position:relative;

            padding:20px;

            overflow:hidden;

            background:var(--surface);

            border:1px solid var(--border);

            border-radius:14px;

            box-shadow:0 1px 2px rgba(0,0,0,.04);

            transition:
                transform .22s ease,
                box-shadow .22s ease,
                border-color .22s ease;
        }

        .booking-stat:hover {

            transform:translateY(-3px);

            box-shadow:0 12px 24px -12px rgba(15,139,141,.28);

            border-color:rgba(15,139,141,.35);
        }

        .booking-stat::before {

            content:"";

            position:absolute;

            left:0;

            top:0;

            bottom:0;

            width:4px;

            background:var(--teal-500);

            border-radius:0 4px 4px 0;
        }

        .booking-stat:nth-child(2)::before {

            background:var(--amber-500);
        }

        .booking-stat:nth-child(3)::before {

            background:var(--green-500);
        }

        .booking-stat:nth-child(4)::before {

            background:var(--blue-500);
        }

        .booking-stat-label {

            display:block;

            color:var(--ink-400);

            font-size:11.5px;

            font-weight:700;

            letter-spacing:.2px;
        }

        .booking-stat-value {

            display:block;

            margin-top:6px;

            color:var(--ink-900);

            font-size:24px;

            font-weight:800;

            letter-spacing:-.3px;
        }

        .booking-stat-description {

            display:block;

            margin-top:5px;

            color:var(--ink-400);

            font-size:10.5px;
        }


        /* =================================================
           FORM
        ================================================= */

        .booking-form-card {

            margin-bottom:24px;

            padding:24px 26px;

            background:var(--surface);

            border:1px solid var(--border);

            border-radius:16px;

            box-shadow:0 1px 2px rgba(0,0,0,.04);
        }

        .booking-form-card h2 {

            margin-bottom:19px;

            color:var(--ink-900);

            font-size:18px;

            font-weight:800;

            display:flex;

            align-items:center;

            gap:8px;
        }

        .booking-form-card h2::before {

            content:"";

            width:4px;

            height:18px;

            border-radius:999px;

            background:var(--teal-500);
        }

        .booking-form-grid {

            display:grid;

            grid-template-columns:
                repeat(2,minmax(0,1fr));

            gap:16px;
        }

        .booking-form-group {

            display:flex;

            flex-direction:column;

            gap:7px;
        }

        .booking-form-group label {

            color:var(--ink-600);

            font-size:11.5px;

            font-weight:700;
        }

        .booking-form-group input,
        .booking-form-group select {

            width:100%;

            padding:11px 13px;

            background:var(--surface);

            border:1.5px solid var(--border);

            border-radius:10px;

            outline:none;

            color:var(--ink-900);

            font-family:inherit;

            font-size:13px;

            transition:
                border-color .2s ease,
                box-shadow .2s ease,
                background .2s ease;
        }

        .booking-form-group select {
            cursor:pointer;
        }

        .booking-form-group input:hover,
        .booking-form-group select:hover {

            border-color:rgba(15,139,141,.45);
        }

        .booking-form-group input:focus,
        .booking-form-group select:focus {

            border-color:var(--teal-500);

            box-shadow:
                0 0 0 4px rgba(15,139,141,.12);
        }

        .booking-form-actions {

            display:flex;

            align-items:center;

            gap:10px;

            margin-top:20px;

            padding-top:18px;

            border-top:1px dashed var(--border);
        }

        .booking-button {

            display:inline-flex;

            align-items:center;

            justify-content:center;

            gap:6px;

            padding:11px 18px;

            border:1px solid transparent;

            border-radius:10px;

            font-family:inherit;

            font-size:12.5px;

            font-weight:800;

            letter-spacing:.2px;

            cursor:pointer;

            text-decoration:none;

            transition:
                transform .18s ease,
                background .18s ease,
                box-shadow .18s ease,
                border-color .18s ease;
        }

        .booking-button.primary {

            background:linear-gradient(135deg, var(--teal-500), var(--teal-600));

            color:#fff;

            box-shadow:0 6px 16px -6px rgba(15,139,141,.55);
        }

        .booking-button.primary:hover {

            transform:translateY(-2px);

            box-shadow:0 10px 22px -6px rgba(15,139,141,.65);
        }

        .booking-button.primary:active {

            transform:translateY(0);
        }

        .booking-button.cancel {

            background:var(--canvas);

            color:var(--ink-600);

            border-color:var(--border);
        }

        .booking-button.cancel:hover {

            transform:translateY(-2px);

            background:var(--surface);

            border-color:var(--ink-400);
        }


        /* =================================================
           ALERT
        ================================================= */

        .booking-alert {

            display:flex;

            align-items:center;

            gap:10px;

            padding:13px 16px;

            margin-bottom:18px;

            border-radius:12px;

            font-size:12.5px;

            font-weight:650;

            animation:slideIn .25s ease;
        }

        @keyframes slideIn {

            from {
                opacity:0;
                transform:translateY(-6px);
            }

            to {
                opacity:1;
                transform:translateY(0);
            }
        }

        .booking-alert strong {

            display:inline-flex;

            align-items:center;

            justify-content:center;

            width:20px;

            height:20px;

            border-radius:50%;

            font-size:11px;

            flex-shrink:0;
        }

        .booking-alert.success {

            background:var(--green-100);

            color:var(--green-500);

            border:1px solid rgba(47,168,79,.22);
        }

        .booking-alert.success strong {

            background:var(--green-500);

            color:#fff;
        }

        .booking-alert.error {

            background:var(--red-100);

            color:var(--red-500);

            border:1px solid rgba(220,53,69,.22);
        }

        .booking-alert.error strong {

            background:var(--red-500);

            color:#fff;
        }


        /* =================================================
           SEARCH
        ================================================= */

        .booking-toolbar {

            display:flex;

            align-items:center;

            gap:12px;

            margin-bottom:16px;
        }

        .booking-search {

            flex:1;

            min-width:0;

            position:relative;
        }

        .booking-search::before {

            content:"⌕";

            position:absolute;

            left:13px;

            top:50%;

            transform:translateY(-50%);

            color:var(--ink-400);

            font-size:15px;

            font-weight:700;

            pointer-events:none;
        }

        .booking-search input {

            padding-left:34px !important;
        }

        .booking-search input,
        .booking-toolbar select {

            width:100%;

            padding:11px 14px;

            background:var(--surface);

            border:1.5px solid var(--border);

            border-radius:10px;

            outline:none;

            color:var(--ink-900);

            font-family:inherit;

            font-size:13px;

            transition:
                border-color .2s ease,
                box-shadow .2s ease;
        }

        .booking-search input:hover,
        .booking-toolbar select:hover {

            border-color:rgba(15,139,141,.45);
        }

        .booking-search input:focus,
        .booking-toolbar select:focus {

            border-color:var(--teal-500);

            box-shadow:
                0 0 0 4px rgba(15,139,141,.12);
        }

        .booking-toolbar select {

            width:170px;

            flex-shrink:0;

            cursor:pointer;
        }


        /* =================================================
           TABLE
        ================================================= */

        .booking-table-card {

            overflow:hidden;

            background:var(--surface);

            border:1px solid var(--border);

            border-radius:16px;

            box-shadow:0 1px 2px rgba(0,0,0,.04);
        }

        .booking-table-header {

            display:flex;

            align-items:center;

            justify-content:space-between;

            gap:15px;

            padding:18px 20px;

            border-bottom:1px solid var(--border);
        }

        .booking-table-tag {

            display:block;

            margin-bottom:5px;

            color:var(--teal-500);

            font-size:10px;

            font-weight:800;

            letter-spacing:1.2px;

            text-transform:uppercase;
        }

        .booking-table-header h2 {

            color:var(--ink-900);

            font-size:16px;

            font-weight:800;
        }

        .booking-count {

            padding:6px 12px;

            background:var(--canvas);

            border:1px solid var(--border);

            border-radius:999px;

            color:var(--ink-600);

            font-size:10.5px;

            font-weight:750;
        }

        .booking-table-wrapper {

            width:100%;

            overflow-x:auto;
        }

        .booking-table {

            width:100%;

            min-width:1300px;

            border-collapse:collapse;
        }

        .booking-table th {

            padding:13px 14px;

            background:rgba(15,139,141,.045);

            color:var(--ink-400);

            border-bottom:1px solid var(--border);

            font-size:10.5px;

            font-weight:800;

            text-align:left;

            text-transform:uppercase;

            letter-spacing:.5px;

            white-space:nowrap;

            position:sticky;

            top:0;
        }

        .booking-table td {

            padding:14px;

            color:var(--ink-600);

            border-bottom:1px solid var(--border);

            font-size:12px;

            vertical-align:middle;
        }

        .booking-table tbody tr:last-child td {

            border-bottom:none;
        }

        .booking-table tbody tr {

            transition:background .18s ease;
        }

        .booking-table tbody tr:hover {

            background:rgba(15,139,141,.05);
        }

        .booking-id {

            color:var(--ink-400);

            font-size:10.5px;

            font-weight:700;
        }

        .booking-traveler {

            color:var(--ink-900);

            font-size:12.5px;

            font-weight:800;
        }

        .booking-email {

            margin-top:3px;

            color:var(--ink-400);

            font-size:10px;
        }

        .booking-destination {

            color:var(--teal-500);

            font-size:12.5px;

            font-weight:750;
        }

        .booking-location {

            margin-top:3px;

            color:var(--ink-400);

            font-size:10px;
        }

        .booking-date {

            color:var(--ink-600);

            font-size:11px;

            font-weight:650;

            white-space:nowrap;
        }

        .booking-number {

            color:var(--ink-900);

            font-weight:750;

            font-size:12px;
        }

        .booking-cost {

            color:var(--ink-900);

            font-weight:800;

            font-size:12.5px;

            white-space:nowrap;
        }


        /* =================================================
           BADGES
        ================================================= */

        .booking-badge {

            display:inline-flex;

            align-items:center;

            gap:5px;

            padding:6px 10px;

            border-radius:999px;

            font-size:9.5px;

            font-weight:800;

            letter-spacing:.2px;

            white-space:nowrap;

            border:1px solid transparent;

            transition:transform .15s ease;
        }

        .booking-badge:hover {

            transform:translateY(-1px);
        }

        .booking-badge::before {

            content:"";

            width:6px;

            height:6px;

            border-radius:50%;

            background:currentColor;

            flex-shrink:0;
        }

        .booking-badge.pending {

            background:var(--amber-100);

            color:var(--amber-500);

            border-color:rgba(245,166,35,.25);
        }

        .booking-badge.approved {

            background:var(--green-100);

            color:var(--green-500);

            border-color:rgba(47,168,79,.25);
        }

        .booking-badge.rejected {

            background:var(--red-100);

            color:var(--red-500);

            border-color:rgba(220,53,69,.25);
        }

        .booking-badge.neutral {

            background:var(--canvas);

            color:var(--ink-600);

            border-color:var(--border);
        }

        .booking-badge.neutral::before {

            display:none;
        }


        /* =================================================
           ACTIONS
        ================================================= */

        .booking-actions {

            display:flex;

            align-items:center;

            gap:7px;
        }

        .booking-action {

            display:inline-flex;

            align-items:center;

            justify-content:center;

            padding:7px 11px;

            border-radius:8px;

            font-size:9.5px;

            font-weight:800;

            text-decoration:none;

            border:1px solid transparent;

            transition:
                transform .18s ease,
                background .18s ease,
                box-shadow .18s ease;
        }

        .booking-action:hover {

            transform:translateY(-2px);
        }

        .booking-action.edit {

            background:var(--blue-100);

            color:var(--blue-500);
        }

        .booking-action.edit:hover {

            background:var(--blue-500);

            color:#fff;

            box-shadow:0 6px 14px -6px rgba(59,130,246,.55);
        }

        .booking-action.delete {

            background:var(--red-100);

            color:var(--red-500);
        }

        .booking-action.delete:hover {

            background:var(--red-500);

            color:#fff;

            box-shadow:0 6px 14px -6px rgba(220,53,69,.55);
        }

        .booking-empty {

            padding:50px 20px !important;

            color:var(--ink-400) !important;

            text-align:center;

            font-size:12.5px !important;
        }


        /* =================================================
           TOGGLE SWITCH (DARK MODE)
        ================================================= */

        .switch {

            position:relative;

            display:inline-block;

            width:38px;

            height:21px;
        }

        .switch input {

            opacity:0;

            width:0;

            height:0;
        }

        .switch-track {

            position:absolute;

            cursor:pointer;

            inset:0;

            background:var(--border);

            border-radius:999px;

            transition:background .25s ease;
        }

        .switch-track::before {

            content:"";

            position:absolute;

            height:15px;

            width:15px;

            left:3px;

            bottom:3px;

            background:#fff;

            border-radius:50%;

            box-shadow:0 1px 3px rgba(0,0,0,.25);

            transition:transform .25s ease;
        }

        .switch input:checked + .switch-track {

            background:var(--teal-500);
        }

        .switch input:checked + .switch-track::before {

            transform:translateX(17px);
        }

        .switch input:focus-visible + .switch-track {

            box-shadow:0 0 0 3px rgba(15,139,141,.3);
        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width:1000px) {

            .booking-stats {

                grid-template-columns:
                    repeat(2,minmax(0,1fr));
            }
        }

        @media (max-width:760px) {

            .booking-page-header {

                align-items:flex-start;

                flex-direction:column;
            }

            .booking-form-grid {

                grid-template-columns:1fr;
            }

            .booking-toolbar {

                align-items:stretch;

                flex-direction:column;
            }

            .booking-toolbar select {

                width:100%;
            }
        }

        @media (max-width:520px) {

            .booking-stats {

                grid-template-columns:1fr;
            }

            .booking-form-card {

                padding:18px;
            }

            .booking-table-header {

                align-items:flex-start;

                flex-direction:column;
            }
        }

    </style>

</head>


<body>

<div class="app">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside class="sidebar">


        <div class="brand">

            <div class="brand-logo">
                P
            </div>

            <div>

                <h2>
                    Pocket Tour
                </h2>

                <span>
                    Travel Booking Management
                </span>

            </div>

        </div>


        <nav class="navigation">

            <div class="nav-label">
                Management
            </div>


            <a
                href="index.php"
                class="nav-item"
            >

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >

                    <path d="M3 11l9-7 9 7"/>

                    <path
                        d="M5 10v9a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-4a1 1 0 0 1 1-1h0a1 1 0 0 1 1 1v4a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-9"
                    />

                </svg>

                Dashboard

            </a>


            <a
                href="users.php"
                class="nav-item"
            >

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >

                    <path
                        d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"
                    />

                    <circle
                        cx="10"
                        cy="7"
                        r="4"
                    />

                    <path
                        d="M23 21v-2a4 4 0 0 0-3-3.87"
                    />

                    <path
                        d="M16 3.13a4 4 0 0 1 0 7.75"
                    />

                </svg>

                Users

            </a>


            <a
                href="destinations.php"
                class="nav-item"
            >

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >

                    <path
                        d="M21 10c0 6-9 12-9 12S3 16 3 10a9 9 0 0 1 18 0z"
                    />

                    <circle
                        cx="12"
                        cy="10"
                        r="3"
                    />

                </svg>

                Destinations

            </a>


            <a
                href="bookings.php"
                class="nav-item active"
            >

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >

                    <rect
                        x="3"
                        y="4"
                        width="18"
                        height="18"
                        rx="2"
                    />

                    <path
                        d="M16 2v4M8 2v4M3 10h18"
                    />

                </svg>

                Bookings

            </a>


            <a
                href="service_providers.php"
                class="nav-item"
            >

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >

                    <path
                        d="M3 16l1.5-5h11L17 16"
                    />

                    <path
                        d="M5.5 11L7 5h6l3 6"
                    />

                    <circle
                        cx="7.5"
                        cy="16.5"
                        r="1.5"
                    />

                    <circle
                        cx="16.5"
                        cy="16.5"
                        r="1.5"
                    />

                </svg>

                Service Providers

            </a>


            <a
                href="booking_services.php"
                class="nav-item"
            >

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >

                    <path
                        d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1.5 1.5"
                    />

                    <path
                        d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1.5-1.5"
                    />

                </svg>

                Booking Services

            </a>

        </nav>


        <!-- SIDEBAR FOOTER -->

        <div class="sidebar-footer">


            <div class="theme-row">

                <span>
                    Dark mode
                </span>

                <label class="switch">

                    <input
                        type="checkbox"
                        id="themeToggle"
                    >

                    <span class="switch-track"></span>

                </label>

            </div>


            <div class="database-status">

                <span class="status-dot"></span>

                <div>

                    <strong>
                        Database Online
                    </strong>

                    <small>
                        MySQL / MariaDB
                    </small>

                </div>

            </div>

        </div>

    </aside>


    <!-- =====================================================
         MAIN
    ====================================================== -->

    <main class="main-content">


        <!-- TOPBAR -->

        <header class="topbar">


            <div class="topbar-heading">

                <strong>
                    Booking Management
                </strong>

                <span>
                    Manage Pocket Tour travel bookings
                </span>

            </div>


            <div class="topbar-right">


                <div class="live-datetime">

                    <div class="live-time">

                        <span class="live-dot"></span>

                        <span id="liveTime">
                            00:00:00
                        </span>

                    </div>

                    <div
                        class="live-date"
                        id="liveDate"
                    >
                        Sep 13, 2026
                    </div>

                </div>


                <div class="admin-profile">

                    <div class="admin-avatar">
                        A
                    </div>

                    <div>

                        <strong>
                            Administrator
                        </strong>

                        <span>
                            Admin
                        </span>

                    </div>

                </div>

            </div>

        </header>


        <!-- PAGE HEADER -->

        <section class="booking-page-header">

            <div>

                <span class="booking-page-tag">
                    Booking Management
                </span>

                <h1>
                    Bookings
                </h1>

                <p>
                    Manage traveler bookings, destinations, trip details and booking status.
                </p>

            </div>

        </section>


        <!-- ALERT -->

        <?php if ($message !== ""): ?>

            <div
                class="booking-alert
                <?= $message_type === "success"
                    ? "success"
                    : "error" ?>"
            >

                <strong>

                    <?= $message_type === "success"
                        ? "✓"
                        : "!" ?>

                </strong>

                <span>
                    <?= e($message) ?>
                </span>

            </div>

        <?php endif; ?>


        <!-- STATISTICS -->

        <section class="booking-stats">


            <div class="booking-stat">

                <span class="booking-stat-label">
                    Total Bookings
                </span>

                <strong class="booking-stat-value">
                    <?= $total_bookings ?>
                </strong>

                <span class="booking-stat-description">
                    All travel requests
                </span>

            </div>


            <div class="booking-stat">

                <span class="booking-stat-label">
                    Pending
                </span>

                <strong class="booking-stat-value">
                    <?= $pending_bookings ?>
                </strong>

                <span class="booking-stat-description">
                    Awaiting approval
                </span>

            </div>


            <div class="booking-stat">

                <span class="booking-stat-label">
                    Approved
                </span>

                <strong class="booking-stat-value">
                    <?= $approved_bookings ?>
                </strong>

                <span class="booking-stat-description">
                    Confirmed bookings
                </span>

            </div>


            <div class="booking-stat">

                <span class="booking-stat-label">
                    Approved Revenue
                </span>

                <strong class="booking-stat-value">

                    ৳<?= number_format(
                        $total_revenue,
                        0
                    ) ?>

                </strong>

                <span class="booking-stat-description">
                    From approved bookings
                </span>

            </div>


        </section>


        <!-- FORM -->

        <section class="booking-form-card" id="booking-form">

            <?php if ($edit_booking): ?>

                <h2>
                    Edit Booking
                </h2>

            <?php else: ?>

                <h2>
                    Add New Booking
                </h2>

            <?php endif; ?>


            <form method="POST">


                <?php if ($edit_booking): ?>

                    <input
                        type="hidden"
                        name="booking_id"
                        value="<?= e(
                            $edit_booking["booking_id"]
                        ) ?>"
                    >

                <?php endif; ?>


                <div class="booking-form-grid">


                    <!-- TRAVELER -->

                    <div class="booking-form-group">

                        <label>
                            Traveler *
                        </label>

                        <select
                            name="user_id"
                            required
                        >

                            <option value="">
                                Select Traveler
                            </option>


                            <?php

                            if ($users_result):

                                while (
                                    $user =
                                    $users_result->fetch_assoc()
                                ):

                            ?>

                                <option
                                    value="<?= e(
                                        $user["user_id"]
                                    ) ?>"
                                    <?= (
                                        $edit_booking &&
                                        $edit_booking["user_id"]
                                        == $user["user_id"]
                                    )
                                        ? "selected"
                                        : "" ?>
                                >

                                    <?= e(
                                        $user["name"]
                                    ) ?>

                                    —
                                    <?= e(
                                        $user["role"]
                                    ) ?>

                                </option>

                            <?php

                                endwhile;

                            endif;

                            ?>

                        </select>

                    </div>


                    <!-- DESTINATION -->

                    <div class="booking-form-group">

                        <label>
                            Destination *
                        </label>

                        <select
                            name="destination_id"
                            required
                        >

                            <option value="">
                                Select Destination
                            </option>


                            <?php

                            if ($destinations_result):

                                while (
                                    $destination =
                                    $destinations_result->fetch_assoc()
                                ):

                            ?>

                                <option
                                    value="<?= e(
                                        $destination["destination_id"]
                                    ) ?>"
                                    <?= (
                                        $edit_booking &&
                                        $edit_booking["destination_id"]
                                        == $destination["destination_id"]
                                    )
                                        ? "selected"
                                        : "" ?>
                                >

                                    <?= e(
                                        $destination["name"]
                                    ) ?>

                                    —
                                    <?= e(
                                        $destination["location"]
                                    ) ?>

                                </option>

                            <?php

                                endwhile;

                            endif;

                            ?>

                        </select>

                    </div>


                    <!-- TRAVEL DATE -->

                    <div class="booking-form-group">

                        <label>
                            Travel Date *
                        </label>

                        <input
                            type="date"
                            name="travel_date"
                            value="<?= $edit_booking
                                ? e(
                                    $edit_booking["travel_date"]
                                )
                                : "" ?>"
                            required
                        >

                    </div>


                    <!-- PERSONS -->

                    <div class="booking-form-group">

                        <label>
                            Number of Travelers *
                        </label>

                        <input
                            type="number"
                            name="persons"
                            min="1"
                            value="<?= $edit_booking
                                ? e(
                                    $edit_booking["persons"]
                                )
                                : "" ?>"
                            placeholder="Example: 3"
                            required
                        >

                    </div>


                    <!-- DAYS -->

                    <div class="booking-form-group">

                        <label>
                            Number of Days *
                        </label>

                        <input
                            type="number"
                            name="days"
                            min="1"
                            value="<?= $edit_booking
                                ? e(
                                    $edit_booking["days"]
                                )
                                : "" ?>"
                            placeholder="Example: 4"
                            required
                        >

                    </div>


                    <!-- FOOD -->

                    <div class="booking-form-group">

                        <label>
                            Food Included
                        </label>

                        <select
                            name="food"
                        >

                            <option
                                value="No"
                                <?= (
                                    !$edit_booking ||
                                    $edit_booking["food"] === "No"
                                )
                                    ? "selected"
                                    : "" ?>
                            >
                                No
                            </option>

                            <option
                                value="Yes"
                                <?= (
                                    $edit_booking &&
                                    $edit_booking["food"] === "Yes"
                                )
                                    ? "selected"
                                    : "" ?>
                            >
                                Yes
                            </option>

                        </select>

                    </div>


                    <!-- STAY -->

                    <div class="booking-form-group">

                        <label>
                            Hotel / Stay
                        </label>

                        <select
                            name="stay"
                        >

                            <option
                                value="No"
                                <?= (
                                    !$edit_booking ||
                                    $edit_booking["stay"] === "No"
                                )
                                    ? "selected"
                                    : "" ?>
                            >
                                No
                            </option>

                            <option
                                value="Yes"
                                <?= (
                                    $edit_booking &&
                                    $edit_booking["stay"] === "Yes"
                                )
                                    ? "selected"
                                    : "" ?>
                            >
                                Yes
                            </option>

                        </select>

                    </div>


                    <!-- COST -->

                    <div class="booking-form-group">

                        <label>
                            Total Cost (৳)
                        </label>

                        <input
                            type="number"
                            name="total_cost"
                            min="0"
                            step="0.01"
                            value="<?= $edit_booking
                                ? e(
                                    $edit_booking["total_cost"]
                                )
                                : "" ?>"
                            placeholder="Example: 25000"
                        >

                    </div>


                    <?php if ($edit_booking): ?>


                        <!-- STATUS -->

                        <div class="booking-form-group">

                            <label>
                                Booking Status
                            </label>

                            <select
                                name="status"
                            >

                                <option
                                    value="pending"
                                    <?= $edit_booking["status"]
                                        === "pending"
                                        ? "selected"
                                        : "" ?>
                                >
                                    Pending
                                </option>

                                <option
                                    value="approved"
                                    <?= $edit_booking["status"]
                                        === "approved"
                                        ? "selected"
                                        : "" ?>
                                >
                                    Approved
                                </option>

                                <option
                                    value="rejected"
                                    <?= $edit_booking["status"]
                                        === "rejected"
                                        ? "selected"
                                        : "" ?>
                                >
                                    Rejected
                                </option>

                            </select>

                        </div>


                        <!-- DRIVER STATUS -->

                        <div class="booking-form-group">

                            <label>
                                Driver Status
                            </label>

                            <select
                                name="driver_status"
                            >

                                <option
                                    value="pending"
                                    <?= $edit_booking["driver_status"]
                                        === "pending"
                                        ? "selected"
                                        : "" ?>
                                >
                                    Pending
                                </option>

                                <option
                                    value="approved"
                                    <?= $edit_booking["driver_status"]
                                        === "approved"
                                        ? "selected"
                                        : "" ?>
                                >
                                    Approved
                                </option>

                                <option
                                    value="rejected"
                                    <?= $edit_booking["driver_status"]
                                        === "rejected"
                                        ? "selected"
                                        : "" ?>
                                >
                                    Rejected
                                </option>

                            </select>

                        </div>


                        <!-- GUIDE STATUS -->

                        <div class="booking-form-group">

                            <label>
                                Guide Status
                            </label>

                            <select
                                name="guide_status"
                            >

                                <option
                                    value="pending"
                                    <?= $edit_booking["guide_status"]
                                        === "pending"
                                        ? "selected"
                                        : "" ?>
                                >
                                    Pending
                                </option>

                                <option
                                    value="approved"
                                    <?= $edit_booking["guide_status"]
                                        === "approved"
                                        ? "selected"
                                        : "" ?>
                                >
                                    Approved
                                </option>

                                <option
                                    value="rejected"
                                    <?= $edit_booking["guide_status"]
                                        === "rejected"
                                        ? "selected"
                                        : "" ?>
                                >
                                    Rejected
                                </option>

                            </select>

                        </div>


                    <?php endif; ?>


                </div>


                <div class="booking-form-actions">


                    <?php if ($edit_booking): ?>

                        <button
                            type="submit"
                            name="update_booking"
                            class="booking-button primary"
                        >
                            Update Booking
                        </button>

                        <a
                            href="bookings.php"
                            class="booking-button cancel"
                        >
                            Cancel
                        </a>

                    <?php else: ?>

                        <button
                            type="submit"
                            name="add_booking"
                            class="booking-button primary"
                        >
                            + Add Booking
                        </button>

                    <?php endif; ?>


                </div>


            </form>

        </section>


        <!-- SEARCH -->

        <form
            method="GET"
            class="booking-toolbar"
        >


            <div class="booking-search">

                <input
                    type="text"
                    name="search"
                    value="<?= e($search) ?>"
                    placeholder="Search by traveler, email, destination or location..."
                >

            </div>


            <select name="status">

                <option
                    value=""
                    <?= $status_filter === ""
                        ? "selected"
                        : "" ?>
                >
                    All Status
                </option>

                <option
                    value="pending"
                    <?= $status_filter === "pending"
                        ? "selected"
                        : "" ?>
                >
                    Pending
                </option>

                <option
                    value="approved"
                    <?= $status_filter === "approved"
                        ? "selected"
                        : "" ?>
                >
                    Approved
                </option>

                <option
                    value="rejected"
                    <?= $status_filter === "rejected"
                        ? "selected"
                        : "" ?>
                >
                    Rejected
                </option>

            </select>


            <button
                type="submit"
                class="booking-button primary"
            >
                Search
            </button>


            <a
                href="bookings.php"
                class="booking-button cancel"
            >
                Reset
            </a>


        </form>


        <!-- TABLE -->

        <section class="booking-table-card">


            <div class="booking-table-header">

                <div>

                    <span class="booking-table-tag">
                        Database Records
                    </span>

                    <h2>
                        Registered Bookings
                    </h2>

                </div>


                <span class="booking-count">

                    <?= $result->num_rows ?>

                    result(s)

                </span>

            </div>


            <div class="booking-table-wrapper">


                <table class="booking-table">


                    <thead>

                        <tr>

                            <th>
                                ID
                            </th>

                            <th>
                                Traveler
                            </th>

                            <th>
                                Destination
                            </th>

                            <th>
                                Travel Date
                            </th>

                            <th>
                                Persons
                            </th>

                            <th>
                                Days
                            </th>

                            <th>
                                Food
                            </th>

                            <th>
                                Stay
                            </th>

                            <th>
                                Total Cost
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Driver
                            </th>

                            <th>
                                Guide
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if ($result->num_rows > 0): ?>


                        <?php while (
                            $row =
                            $result->fetch_assoc()
                        ): ?>


                            <tr>


                                <!-- ID -->

                                <td>

                                    <span class="booking-id">

                                        #<?= e(
                                            $row["booking_id"]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- TRAVELER -->

                                <td>

                                    <div class="booking-traveler">

                                        <?= e(
                                            $row["traveler_name"]
                                        ) ?>

                                    </div>

                                    <div class="booking-email">

                                        <?= e(
                                            $row["traveler_email"]
                                        ) ?>

                                    </div>

                                </td>


                                <!-- DESTINATION -->

                                <td>

                                    <div class="booking-destination">

                                        <?= e(
                                            $row["destination_name"]
                                        ) ?>

                                    </div>

                                    <div class="booking-location">

                                        <?= e(
                                            $row["destination_location"]
                                        ) ?>

                                    </div>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <span class="booking-date">

                                        <?= date(
                                            "M d, Y",
                                            strtotime(
                                                $row["travel_date"]
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <!-- PERSONS -->

                                <td>

                                    <span class="booking-number">

                                        <?= e(
                                            $row["persons"]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- DAYS -->

                                <td>

                                    <span class="booking-number">

                                        <?= e(
                                            $row["days"]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- FOOD -->

                                <td>

                                    <span class="booking-badge neutral">

                                        <?= e(
                                            $row["food"]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- STAY -->

                                <td>

                                    <span class="booking-badge neutral">

                                        <?= e(
                                            $row["stay"]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- COST -->

                                <td>

                                    <?php if (
                                        $row["total_cost"] !== null
                                    ): ?>

                                        <span class="booking-cost">

                                            ৳<?= number_format(
                                                (float)$row["total_cost"],
                                                2
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        <span
                                            style="
                                                color:var(--ink-400);
                                                font-size:10.5px;
                                            "
                                        >
                                            Not set
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="booking-badge
                                        <?= e(
                                            strtolower(
                                                $row["status"]
                                            )
                                        ) ?>"
                                    >

                                        <?= e(
                                            ucfirst(
                                                $row["status"]
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <!-- DRIVER -->

                                <td>

                                    <span
                                        class="booking-badge
                                        <?= e(
                                            strtolower(
                                                $row["driver_status"]
                                            )
                                        ) ?>"
                                    >

                                        <?= e(
                                            ucfirst(
                                                $row["driver_status"]
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <!-- GUIDE -->

                                <td>

                                    <span
                                        class="booking-badge
                                        <?= e(
                                            strtolower(
                                                $row["guide_status"]
                                            )
                                        ) ?>"
                                    >

                                        <?= e(
                                            ucfirst(
                                                $row["guide_status"]
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div class="booking-actions">


                                        <a
                                            href="bookings.php?edit=<?= e(
                                                $row["booking_id"]
                                            ) ?>#booking-form"
                                            class="booking-action edit"
                                        >
                                            Edit
                                        </a>


                                        <a
                                            href="bookings.php?delete=<?= e(
                                                $row["booking_id"]
                                            ) ?>"
                                            class="booking-action delete"
                                            onclick="
                                                return confirm(
                                                    'Are you sure you want to delete this booking?'
                                                );
                                            "
                                        >
                                            Delete
                                        </a>


                                    </div>

                                </td>


                            </tr>


                        <?php endwhile; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="13"
                                class="booking-empty"
                            >

                                No bookings found.

                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </section>


        <!-- FOOTER -->

        <footer>

            <span>
                © <?= date("Y") ?> Pocket Tour
            </span>

            <span>
                PHP + MySQL/MariaDB · XAMPP
            </span>

        </footer>


    </main>

</div>


<!-- =====================================================
     THEME
===================================================== -->

<script>

const root =
    document.documentElement;

const toggle =
    document.getElementById(
        "themeToggle"
    );

const saved =
    localStorage.getItem(
        "pocketTourTheme"
    );


if (saved === "dark") {

    root.setAttribute(
        "data-theme",
        "dark"
    );

    if (toggle) {
        toggle.checked = true;
    }

}


if (!saved) {

    const prefersDark =
        window.matchMedia &&
        window.matchMedia(
            "(prefers-color-scheme: dark)"
        ).matches;


    if (prefersDark) {

        root.setAttribute(
            "data-theme",
            "dark"
        );

        if (toggle) {
            toggle.checked = true;
        }

    }

}


if (toggle) {

    toggle.addEventListener(
        "change",
        function () {

            if (this.checked) {

                root.setAttribute(
                    "data-theme",
                    "dark"
                );

                localStorage.setItem(
                    "pocketTourTheme",
                    "dark"
                );

            } else {

                root.removeAttribute(
                    "data-theme"
                );

                localStorage.setItem(
                    "pocketTourTheme",
                    "light"
                );

            }

        }
    );

}

</script>


<!-- =====================================================
     LIVE DATE & TIME
===================================================== -->

<script>

function updateDateTime() {

    const now =
        new Date();


    const timeOptions = {

        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",

        hour12: true

    };


    const dateOptions = {

        year: "numeric",
        month: "short",
        day: "numeric"

    };


    const time =
        now.toLocaleTimeString(
            "en-US",
            timeOptions
        );


    const date =
        now.toLocaleDateString(
            "en-US",
            dateOptions
        );


    const timeElement =
        document.getElementById(
            "liveTime"
        );


    const dateElement =
        document.getElementById(
            "liveDate"
        );


    if (timeElement) {

        timeElement.textContent =
            time;

    }


    if (dateElement) {

        dateElement.textContent =
            date;

    }

}


updateDateTime();


setInterval(
    updateDateTime,
    1000
);

</script>


</body>

</html>