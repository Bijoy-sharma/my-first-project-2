<?php

require_once "config/database.php";

/* =========================================================
   HELPERS
========================================================= */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function table_exists($conn, $table)
{
    $allowedTables = [
        "users",
        "destinations",
        "bookings",
        "service_providers",
        "booking_services"
    ];

    if (!in_array($table, $allowedTables, true)) {
        return false;
    }

    $database = $conn->real_escape_string(
        $conn->query("SELECT DATABASE()")
            ->fetch_row()[0]
    );

    $table = $conn->real_escape_string($table);

    $result = $conn->query("
        SELECT COUNT(*) AS table_count
        FROM information_schema.tables
        WHERE table_schema = '{$database}'
          AND table_name = '{$table}'
    ");

    if (!$result) {
        return false;
    }

    $row = $result->fetch_assoc();

    return ((int)$row["table_count"] > 0);
}

function pct($part, $whole)
{
    if ((float)$whole <= 0) {
        return 0;
    }

    return round(
        ((float)$part / (float)$whole) * 100,
        1
    );
}


/* =========================================================
   DEFAULT VALUES
   ---------------------------------------------------------
   These prevent undefined-variable errors in the dashboard.
========================================================= */

$totalUsers = 0;

$travelerCount = 0;
$driverCount = 0;
$guideCount = 0;
$adminCount = 0;

$totalDestinations = 0;

$totalProviders = 0;
$providerDrivers = 0;
$providerGuides = 0;

$totalBookings = 0;
$pendingBookings = 0;
$approvedBookings = 0;
$rejectedBookings = 0;

$totalBookingValue = 0;
$avgBookingValue = 0;

$approvalRate = 0;

$upcomingTrips = 0;
$totalTravelersServed = 0;

$driverAccepted = 0;
$driverPending = 0;
$driverRejected = 0;

$guideAccepted = 0;
$guidePending = 0;
$guideRejected = 0;

$totalBookingServices = 0;

$newestUsers = [];
$topDestinations = [];
$recentBookings = [];
$topTravelers = [];

$chartTrendLabels = [];
$chartTrendCounts = [];
$chartTrendRevenue = [];

$chartRoleLabels = [
    "Travelers",
    "Drivers",
    "Guides",
    "Admins"
];

$chartRoleData = [
    0,
    0,
    0,
    0
];

$dbOnline = false;


/* =========================================================
   DATABASE STATUS
========================================================= */

$dbOnline = $conn->ping();


/* =========================================================
   USERS
========================================================= */

if (table_exists($conn, "users")) {

    $roleResult = $conn->query("
        SELECT
            role,
            COUNT(*) AS cnt
        FROM users
        GROUP BY role
    ");

    if ($roleResult) {

        while ($row = $roleResult->fetch_assoc()) {

            $role = strtolower(
                trim(
                    (string)$row["role"]
                )
            );

            $count = (int)$row["cnt"];

            $totalUsers += $count;

            if ($role === "traveler") {

                $travelerCount = $count;

            } elseif ($role === "driver") {

                $driverCount = $count;

            } elseif ($role === "guide") {

                $guideCount = $count;

            } elseif ($role === "admin") {

                $adminCount = $count;
            }
        }

        $roleResult->free();
    }


    /* -----------------------------------------------------
       Newest accounts
    ----------------------------------------------------- */

    $newestResult = $conn->query("
        SELECT
            user_id,
            name,
            email,
            role
        FROM users
        ORDER BY user_id DESC
        LIMIT 5
    ");

    if ($newestResult) {

        while ($row = $newestResult->fetch_assoc()) {
            $newestUsers[] = $row;
        }

        $newestResult->free();
    }
}


/* =========================================================
   ROLE CHART
========================================================= */

$chartRoleData = [
    $travelerCount,
    $driverCount,
    $guideCount,
    $adminCount
];


/* =========================================================
   DESTINATIONS
========================================================= */

if (table_exists($conn, "destinations")) {

    $destinationResult = $conn->query("
        SELECT
            COUNT(*) AS cnt
        FROM destinations
    ");

    if ($destinationResult) {

        $row = $destinationResult->fetch_assoc();

        $totalDestinations =
            (int)$row["cnt"];

        $destinationResult->free();
    }
}


/* =========================================================
   SERVICE PROVIDERS
   ---------------------------------------------------------
   service_providers has:
       provider_id
       user_id

   Driver/Guide role comes from users.role.
========================================================= */

if (
    table_exists($conn, "service_providers") &&
    table_exists($conn, "users")
) {

    $providerResult = $conn->query("
        SELECT

            COUNT(*) AS total_providers,

            SUM(
                CASE
                    WHEN u.role = 'Driver'
                    THEN 1
                    ELSE 0
                END
            ) AS drivers,

            SUM(
                CASE
                    WHEN u.role = 'Guide'
                    THEN 1
                    ELSE 0
                END
            ) AS guides

        FROM service_providers sp

        INNER JOIN users u
            ON sp.user_id = u.user_id

        WHERE u.role IN (
            'Driver',
            'Guide'
        )
    ");

    if ($providerResult) {

        $row =
            $providerResult->fetch_assoc();

        $totalProviders =
            (int)($row["total_providers"] ?? 0);

        $providerDrivers =
            (int)($row["drivers"] ?? 0);

        $providerGuides =
            (int)($row["guides"] ?? 0);

        $providerResult->free();
    }
}


/* =========================================================
   BOOKING OVERVIEW
========================================================= */

if (table_exists($conn, "bookings")) {

    $bookingCountResult = $conn->query("
        SELECT

            COUNT(*) AS total_bookings,

            COALESCE(
                SUM(
                    CASE
                        WHEN status = 'pending'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS pending_bookings,

            COALESCE(
                SUM(
                    CASE
                        WHEN status = 'approved'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS approved_bookings,

            COALESCE(
                SUM(
                    CASE
                        WHEN status = 'rejected'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
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
            ) AS total_booking_value,

            COALESCE(
                AVG(
                    CASE
                        WHEN status = 'approved'
                        THEN total_cost
                        ELSE NULL
                    END
                ),
                0
            ) AS avg_booking_value,

            COALESCE(
                SUM(
                    CASE
                        WHEN travel_date >= CURDATE()
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS upcoming_trips,

            COALESCE(
                SUM(
                    CASE
                        WHEN status = 'approved'
                        THEN persons
                        ELSE 0
                    END
                ),
                0
            ) AS travelers_served

        FROM bookings
    ");

    if ($bookingCountResult) {

        $row =
            $bookingCountResult->fetch_assoc();

        $totalBookings =
            (int)($row["total_bookings"] ?? 0);

        $pendingBookings =
            (int)($row["pending_bookings"] ?? 0);

        $approvedBookings =
            (int)($row["approved_bookings"] ?? 0);

        $rejectedBookings =
            (int)($row["rejected_bookings"] ?? 0);

        $totalBookingValue =
            (float)($row["total_booking_value"] ?? 0);

        $avgBookingValue =
            (float)($row["avg_booking_value"] ?? 0);

        $upcomingTrips =
            (int)($row["upcoming_trips"] ?? 0);

        $totalTravelersServed =
            (int)($row["travelers_served"] ?? 0);

        $bookingCountResult->free();
    }
}


/* =========================================================
   APPROVAL RATE
========================================================= */

$approvalRate = pct(
    $approvedBookings,
    $totalBookings
);


/* =========================================================
   DRIVER / GUIDE STATUS
========================================================= */

if (table_exists($conn, "bookings")) {

    $serviceStatusResult = $conn->query("
        SELECT

            COALESCE(
                SUM(
                    CASE
                        WHEN driver_status = 'accepted'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS driver_accepted,

            COALESCE(
                SUM(
                    CASE
                        WHEN driver_status = 'pending'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS driver_pending,

            COALESCE(
                SUM(
                    CASE
                        WHEN driver_status = 'rejected'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS driver_rejected,

            COALESCE(
                SUM(
                    CASE
                        WHEN guide_status = 'accepted'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS guide_accepted,

            COALESCE(
                SUM(
                    CASE
                        WHEN guide_status = 'pending'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS guide_pending,

            COALESCE(
                SUM(
                    CASE
                        WHEN guide_status = 'rejected'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS guide_rejected

        FROM bookings
    ");

    if ($serviceStatusResult) {

        $row =
            $serviceStatusResult->fetch_assoc();

        $driverAccepted =
            (int)($row["driver_accepted"] ?? 0);

        $driverPending =
            (int)($row["driver_pending"] ?? 0);

        $driverRejected =
            (int)($row["driver_rejected"] ?? 0);

        $guideAccepted =
            (int)($row["guide_accepted"] ?? 0);

        $guidePending =
            (int)($row["guide_pending"] ?? 0);

        $guideRejected =
            (int)($row["guide_rejected"] ?? 0);

        $serviceStatusResult->free();
    }
}


/* =========================================================
   BOOKING SERVICES COUNT
========================================================= */

if (table_exists($conn, "booking_services")) {

    $bookingServicesResult = $conn->query("
        SELECT
            COUNT(*) AS cnt
        FROM booking_services
    ");

    if ($bookingServicesResult) {

        $row =
            $bookingServicesResult->fetch_assoc();

        $totalBookingServices =
            (int)($row["cnt"] ?? 0);

        $bookingServicesResult->free();
    }
}


/* =========================================================
   BOOKING TREND — LAST 6 MONTHS
========================================================= */

$months = [];

for ($i = 5; $i >= 0; $i--) {

    $timestamp =
        strtotime("-{$i} months");

    $ym =
        date("Y-m", $timestamp);

    $label =
        date("M", $timestamp);

    $months[$ym] = [
        "label" => $label,
        "bookings" => 0,
        "revenue" => 0
    ];
}


if (table_exists($conn, "bookings")) {

    $trendResult = $conn->query("
        SELECT

            DATE_FORMAT(
                travel_date,
                '%Y-%m'
            ) AS ym,

            COUNT(*) AS bookings_count,

            COALESCE(
                SUM(
                    CASE
                        WHEN status = 'approved'
                        THEN total_cost
                        ELSE 0
                    END
                ),
                0
            ) AS revenue

        FROM bookings

        WHERE travel_date >=
            DATE_SUB(
                CURDATE(),
                INTERVAL 6 MONTH
            )

        GROUP BY
            DATE_FORMAT(
                travel_date,
                '%Y-%m'
            )

        ORDER BY ym ASC
    ");

    if ($trendResult) {

        while ($row = $trendResult->fetch_assoc()) {

            $ym = $row["ym"];

            if (isset($months[$ym])) {

                $months[$ym]["bookings"] =
                    (int)$row["bookings_count"];

                $months[$ym]["revenue"] =
                    (float)$row["revenue"];
            }
        }

        $trendResult->free();
    }
}


/* ---------------------------------------------------------
   Convert trend data to JSON for Chart.js
--------------------------------------------------------- */

foreach ($months as $month) {

    $chartTrendLabels[] =
        $month["label"];

    $chartTrendCounts[] =
        $month["bookings"];

    $chartTrendRevenue[] =
        $month["revenue"];
}


$chartTrendLabels =
    json_encode(
        $chartTrendLabels,
        JSON_UNESCAPED_UNICODE
    );

$chartTrendCounts =
    json_encode(
        $chartTrendCounts
    );

$chartTrendRevenue =
    json_encode(
        $chartTrendRevenue
    );


/* =========================================================
   ROLE CHART JSON
========================================================= */

$chartRoleLabels =
    json_encode(
        $chartRoleLabels,
        JSON_UNESCAPED_UNICODE
    );

$chartRoleData =
    json_encode(
        $chartRoleData
    );


/* =========================================================
   TOP DESTINATIONS
========================================================= */

if (
    table_exists($conn, "destinations") &&
    table_exists($conn, "bookings")
) {

    $topDestinationResult = $conn->query("
        SELECT

            d.name,

            d.location,

            COUNT(
                b.booking_id
            ) AS booking_count

        FROM destinations d

        INNER JOIN bookings b
            ON b.destination_id =
               d.destination_id

        GROUP BY

            d.destination_id,
            d.name,
            d.location

        ORDER BY
            booking_count DESC

        LIMIT 5
    ");

    if ($topDestinationResult) {

        while (
            $row =
            $topDestinationResult->fetch_assoc()
        ) {

            $topDestinations[] =
                $row;
        }

        $topDestinationResult->free();
    }
}


$maxDestinationBookings = 0;

foreach (
    $topDestinations
    as $destination
) {

    $count =
        (int)(
            $destination["booking_count"]
            ?? 0
        );

    if (
        $count >
        $maxDestinationBookings
    ) {

        $maxDestinationBookings =
            $count;
    }
}


/* =========================================================
   TOP TRAVELERS
========================================================= */

if (
    table_exists($conn, "bookings") &&
    table_exists($conn, "users")
) {

    $topTravelerResult = $conn->query("
        SELECT

            u.name,

            u.email,

            COUNT(
                b.booking_id
            ) AS trips,

            COALESCE(
                SUM(
                    b.total_cost
                ),
                0
            ) AS spent

        FROM bookings b

        INNER JOIN users u
            ON b.user_id =
               u.user_id

        WHERE
            b.status = 'approved'

            AND u.role = 'Traveler'

        GROUP BY

            b.user_id,
            u.name,
            u.email

        ORDER BY
            spent DESC

        LIMIT 5
    ");

    if ($topTravelerResult) {

        while (
            $row =
            $topTravelerResult->fetch_assoc()
        ) {

            $topTravelers[] =
                $row;
        }

        $topTravelerResult->free();
    }
}


/* =========================================================
   RECENT BOOKINGS
========================================================= */

if (
    table_exists($conn, "bookings") &&
    table_exists($conn, "users") &&
    table_exists($conn, "destinations")
) {

    $recentBookingResult = $conn->query("
        SELECT

            b.booking_id,

            b.travel_date,

            b.persons,

            b.status,

            b.driver_status,

            b.guide_status,

            b.total_cost,

            u.name AS traveler_name,

            u.email AS traveler_email,

            d.name AS destination_name,

            d.location AS location

        FROM bookings b

        INNER JOIN users u
            ON b.user_id =
               u.user_id

        INNER JOIN destinations d
            ON b.destination_id =
               d.destination_id

        ORDER BY
            b.booking_id DESC

        LIMIT 8
    ");

    if ($recentBookingResult) {

        while (
            $row =
            $recentBookingResult->fetch_assoc()
        ) {

            $recentBookings[] =
                $row;
        }

        $recentBookingResult->free();
    }
}


/* =========================================================
   CLOSE / FINAL DATABASE VALUES
========================================================= */

$dbOnline = $conn->ping();


/* =========================================================
   NOTE
   ---------------------------------------------------------
   We intentionally keep $conn open because the HTML page
   may still need it during rendering.
========================================================= */

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="Pocket Tour Professional Administration Control Center"
    >

    <title>
        Pocket Tour | Admin Control Center
    </title>


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
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>


<style>

/* =========================================================
   ROOT — premium type scale increased across the board;
   a warm gold accent layered on the navy/teal base marks
   this as the "control center", used only for emphasis.
========================================================= */

:root {

    --navy-950:#071525;
    --navy-900:#0B1B2E;
    --navy-800:#102640;
    --navy-700:#16324F;

    --teal-500:#0F8B8D;
    --teal-600:#0C7274;
    --teal-100:#E3F4F3;

    --gold-500:#D9A441;
    --gold-600:#B9862A;
    --gold-100:#FBF1DD;

    --canvas:#F3F6F9;
    --surface:#FFFFFF;

    --ink-950:#0D1825;
    --ink-900:#142131;
    --ink-700:#344455;
    --ink-600:#4B5A6B;
    --ink-400:#8290A0;

    --border:#E3E8EE;

    --green:#2FA84F;
    --green-light:#EAF7EE;

    --amber:#D99100;
    --amber-light:#FFF6DD;

    --red:#D94A4A;
    --red-light:#FDECEC;

    --blue:#4777D8;
    --blue-light:#ECF2FF;

    --purple:#8067C8;
    --purple-light:#F0ECFA;

    --shadow:
        0 12px 35px rgba(13,32,51,.07);

    --shadow-hover:
        0 18px 45px rgba(13,32,51,.12);

    --radius:16px;

    /* type scale — bumped up a full step from the original
       for a more confident, "premium control room" feel */
    --fs-body:15.5px;
    --fs-sm:12.5px;
    --fs-xs:11px;
    --fs-h1:36px;
    --fs-h2:17px;
    --fs-stat:34px;
}


/* =========================================================
   RESET
========================================================= */

* {
    margin:0;
    padding:0;
    box-sizing:border-box;
}


html {
    scroll-behavior:smooth;
}


body {

    font-family:
        "DM Sans",
        "Plus Jakarta Sans",
        Arial,
        sans-serif;

    background:
        var(--canvas);

    color:
        var(--ink-900);

    min-height:100vh;

    line-height:1.55;

    font-size:
        var(--fs-body);
}


@media (prefers-reduced-motion: reduce) {

    *,
    *::before,
    *::after {
        animation-duration:.001ms !important;
        animation-iteration-count:1 !important;
        transition-duration:.001ms !important;
        scroll-behavior:auto !important;
    }
}


/* =========================================================
   APP
========================================================= */

.app {

    display:flex;

    min-height:100vh;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    position:fixed;

    top:0;
    left:0;
    bottom:0;

    width:268px;

    background:
        linear-gradient(
            180deg,
            #071525 0%,
            #0B1B2E 55%,
            #102640 100%
        );

    color:white;

    display:flex;

    flex-direction:column;

    z-index:100;

    box-shadow:
        8px 0 35px rgba(4,18,31,.08);
}


/* brand */

.brand {

    height:92px;

    padding:0 24px;

    display:flex;

    align-items:center;

    gap:12px;

    border-bottom:
        1px solid
        rgba(255,255,255,.07);
}


.brand-logo {

    width:44px;
    height:44px;

    border-radius:12px;

    background:
        linear-gradient(
            135deg,
            #18A4A5,
            #0F7274
        );

    display:flex;

    align-items:center;
    justify-content:center;

    font-size:20px;

    font-weight:800;

    box-shadow:
        0 8px 20px
        rgba(15,139,141,.25);
}


.brand h2 {

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size:16px;

    font-weight:800;
}


.brand span {

    display:block;

    color:
        rgba(255,255,255,.43);

    font-size:10px;

    margin-top:3px;

    letter-spacing:.5px;
}


/* navigation */

.navigation {

    padding:22px 13px;
}


.nav-label {

    padding:
        0 12px 10px;

    color:
        rgba(255,255,255,.35);

    font-size:10px;

    font-weight:800;

    text-transform:uppercase;

    letter-spacing:1.2px;
}


.nav-item {

    display:flex;

    align-items:center;

    gap:12px;

    padding:12px 13px;

    margin-bottom:4px;

    border-radius:9px;

    color:
        rgba(255,255,255,.62);

    text-decoration:none;

    font-size:13px;

    font-weight:600;

    transition:
        transform .2s ease,
        background .2s ease,
        color .2s ease;
}


.nav-item svg {

    width:18px;
    height:18px;

    flex-shrink:0;

    stroke:currentColor;
}


.nav-item:hover {

    color:white;

    background:
        rgba(255,255,255,.055);

    transform:
        translateX(2px);
}


.nav-item.active {

    color:white;

    background:
        linear-gradient(
            90deg,
            rgba(15,139,141,.28),
            rgba(15,139,141,.10)
        );

    box-shadow:
        inset 3px 0
        var(--teal-500);
}


.nav-badge {

    margin-left:auto;

    background:
        rgba(217,164,65,.18);

    color:
        var(--gold-500);

    font-size:9px;

    font-weight:800;

    padding:2px 7px;

    border-radius:20px;
}


/* sidebar footer */

.sidebar-footer {

    margin-top:auto;

    padding:16px;

    border-top:
        1px solid
        rgba(255,255,255,.07);
}


.database-status {

    display:flex;

    align-items:center;

    gap:10px;

    padding:12px;

    border-radius:11px;

    background:
        rgba(255,255,255,.04);

    border:
        1px solid
        rgba(255,255,255,.06);
}


.status-dot {

    width:8px;
    height:8px;

    border-radius:50%;

    background:
        var(--green);

    box-shadow:
        0 0 0 4px
        rgba(47,168,79,.12);

    animation:
        pulse 2s infinite;
}


.status-dot.offline {

    background:
        var(--red);

    box-shadow:
        0 0 0 4px
        rgba(217,74,74,.12);
}


.database-status strong {

    display:block;

    font-size:11px;
}


.database-status small {

    display:block;

    color:
        rgba(255,255,255,.4);

    font-size:9px;

    margin-top:2px;
}


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left:268px;

    width:
        calc(100% - 268px);

    min-height:100vh;
}


/* =========================================================
   TOPBAR
========================================================= */

.topbar {

    height:88px;

    padding:
        0 32px;

    background:
        rgba(255,255,255,.94);

    backdrop-filter:
        blur(15px);

    border-bottom:
        1px solid
        var(--border);

    display:flex;

    align-items:center;

    justify-content:space-between;

    position:sticky;

    top:0;

    z-index:50;
}


.topbar-heading strong {

    display:block;

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size:21px;

    font-weight:800;

    letter-spacing:-.4px;
}


.topbar-heading span {

    display:block;

    margin-top:3px;

    color:
        var(--ink-400);

    font-size:12px;
}


.topbar-right {

    display:flex;

    align-items:center;

    gap:18px;
}


.live-datetime {

    display:flex;

    align-items:center;

    gap:10px;

    padding-right:18px;

    border-right:
        1px solid
        var(--border);
}


.live-time {

    display:flex;

    align-items:center;

    gap:7px;

    font-size:13px;

    font-weight:800;
}


.live-dot {

    width:7px;
    height:7px;

    border-radius:50%;

    background:
        var(--green);

    box-shadow:
        0 0 0 3px
        rgba(47,168,79,.12);

    animation:
        pulse 2s infinite;
}


.live-date {

    color:
        var(--ink-400);

    font-size:11px;

    font-weight:600;
}


.admin-profile {

    display:flex;

    align-items:center;

    gap:10px;
}


.admin-avatar {

    width:40px;
    height:40px;

    border-radius:11px;

    background:
        linear-gradient(
            135deg,
            var(--teal-500),
            var(--teal-600)
        );

    color:white;

    display:flex;

    align-items:center;
    justify-content:center;

    font-size:14px;

    font-weight:800;

    box-shadow:
        0 7px 18px
        rgba(15,139,141,.18);
}


.admin-profile strong {

    display:block;

    font-size:12.5px;

    font-weight:800;
}


.admin-profile span {

    display:block;

    margin-top:2px;

    color:
        var(--ink-400);

    font-size:10.5px;
}


/* =========================================================
   CONTENT
========================================================= */

.content {

    padding:32px;
}


/* =========================================================
   HERO
========================================================= */

.hero {

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:20px;

    margin-bottom:26px;

    padding:
        30px 32px;

    border-radius:18px;

    color:white;

    background:
        radial-gradient(
            circle at 90% 10%,
            rgba(15,139,141,.38),
            transparent 30%
        ),
        radial-gradient(
            circle at 10% 120%,
            rgba(217,164,65,.16),
            transparent 40%
        ),
        linear-gradient(
            135deg,
            var(--navy-950),
            var(--navy-800)
        );

    position:relative;

    overflow:hidden;

    box-shadow:
        0 18px 45px
        rgba(11,27,46,.13);

    animation:
        slideUp .55s ease both;
}


.hero::before {

    content:"";

    position:absolute;

    width:280px;
    height:280px;

    border-radius:50%;

    border:
        36px solid
        rgba(255,255,255,.035);

    right:-95px;
    top:-115px;
}


.hero-content {

    position:relative;

    z-index:2;
}


.hero-label {

    color:
        #79D4D4;

    font-size:10.5px;

    text-transform:uppercase;

    letter-spacing:1.4px;

    font-weight:800;
}


.hero h1 {

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size:
        var(--fs-h1);

    line-height:1.18;

    margin-top:8px;

    letter-spacing:-1px;
}


.hero p {

    color:
        rgba(255,255,255,.6);

    font-size:13px;

    margin-top:9px;

    max-width:620px;
}


.hero-meta {

    display:flex;

    gap:22px;

    margin-top:18px;

    position:relative;

    z-index:2;
}


.hero-meta div strong {

    display:block;

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size:19px;
}


.hero-meta div span {

    display:block;

    color:
        rgba(255,255,255,.5);

    font-size:10px;

    margin-top:2px;
}


.hero-actions {

    display:flex;

    flex-direction:column;

    gap:9px;

    position:relative;

    z-index:2;
}


.hero-btn {

    display:inline-flex;

    align-items:center;

    justify-content:center;

    padding:11px 16px;

    border-radius:9px;

    text-decoration:none;

    font-size:12px;

    font-weight:800;

    transition:.2s;

    white-space:nowrap;
}


.hero-btn.primary {

    background:white;

    color:var(--navy-900);
}


.hero-btn.secondary {

    background:
        rgba(255,255,255,.08);

    color:white;

    border:
        1px solid
        rgba(255,255,255,.12);
}


.hero-btn:hover {

    transform:
        translateY(-2px);
}


/* =========================================================
   SECTION HEADING
========================================================= */

.section-heading {

    display:flex;

    align-items:end;

    justify-content:space-between;

    margin-bottom:14px;
}


.section-heading h2 {

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size:17px;

    font-weight:800;

    letter-spacing:-.2px;
}


.section-tag {

    display:block;

    color:
        var(--teal-600);

    font-size:9.5px;

    text-transform:uppercase;

    letter-spacing:1.2px;

    font-weight:800;

    margin-bottom:4px;
}


.section-link {

    color:
        var(--teal-600);

    font-size:12px;

    font-weight:750;

    text-decoration:none;
}


/* =========================================================
   STATISTICS
========================================================= */

.stats-grid {

    display:grid;

    grid-template-columns:
        repeat(4, minmax(0,1fr));

    gap:16px;

    margin-bottom:20px;
}


.stat-card {

    background:
        var(--surface);

    border:
        1px solid
        var(--border);

    border-radius:
        var(--radius);

    padding:20px;

    box-shadow:
        var(--shadow);

    display:flex;

    align-items:center;

    gap:14px;

    position:relative;

    overflow:hidden;

    animation:
        slideUp .5s ease both;

    transition:
        transform .25s ease,
        box-shadow .25s ease;
}


.stat-card:nth-child(2) {
    animation-delay:.06s;
}

.stat-card:nth-child(3) {
    animation-delay:.12s;
}

.stat-card:nth-child(4) {
    animation-delay:.18s;
}


.stat-card:hover {

    transform:
        translateY(-4px);

    box-shadow:
        var(--shadow-hover);
}


.stat-icon {

    width:46px;
    height:46px;

    flex-shrink:0;

    border-radius:12px;

    display:flex;

    align-items:center;
    justify-content:center;
}


.stat-icon svg {

    width:21px;
    height:21px;
}


.users-icon {

    background:
        var(--blue-light);

    color:
        var(--blue);
}


.destination-icon {

    background:
        var(--teal-100);

    color:
        var(--teal-600);
}


.booking-icon {

    background:
        var(--amber-light);

    color:
        var(--amber);
}


.provider-icon {

    background:
        var(--purple-light);

    color:
        var(--purple);
}


.revenue-icon {

    background:
        var(--gold-100);

    color:
        var(--gold-600);
}


.rate-icon {

    background:
        var(--green-light);

    color:
        var(--green);
}


.stat-label {

    display:block;

    color:
        var(--ink-400);

    font-size:10.5px;

    font-weight:650;

    text-transform:uppercase;

    letter-spacing:.4px;
}


.stat-number {

    display:block;

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size:
        var(--fs-stat);

    line-height:1.2;

    margin-top:4px;

    letter-spacing:-.8px;
}


.stat-description {

    display:block;

    color:
        var(--ink-400);

    font-size:10px;

    margin-top:3px;
}


/* secondary metrics row */

.metrics-grid {

    display:grid;

    grid-template-columns:
        repeat(3, minmax(0,1fr));

    gap:16px;

    margin-bottom:26px;
}


.metric-card {

    background:
        var(--surface);

    border:
        1px solid
        var(--border);

    border-radius:14px;

    padding:16px 18px;

    box-shadow:
        var(--shadow);

    display:flex;

    align-items:center;

    justify-content:space-between;

    animation:
        slideUp .5s ease both;
}


.metric-card .metric-text span {

    display:block;

    color:
        var(--ink-400);

    font-size:10.5px;

    font-weight:650;

    text-transform:uppercase;

    letter-spacing:.4px;
}


.metric-card .metric-text strong {

    display:block;

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size:22px;

    margin-top:3px;
}


.metric-ring {

    width:52px;
    height:52px;

    flex-shrink:0;
}


/* =========================================================
   CHARTS ROW
========================================================= */

.charts-grid {

    display:grid;

    grid-template-columns:
        1.55fr
        1fr;

    gap:18px;

    margin-bottom:26px;
}


.chart-card {

    background:
        var(--surface);

    border:
        1px solid
        var(--border);

    border-radius:
        var(--radius);

    box-shadow:
        var(--shadow);

    padding:20px 22px;

    animation:
        slideUp .55s ease both;
}


.chart-card-header {

    display:flex;

    align-items:flex-start;

    justify-content:space-between;

    margin-bottom:14px;
}


.chart-card-header h2 {

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size:15px;

    font-weight:800;
}


.chart-card-header p {

    color:
        var(--ink-400);

    font-size:11px;

    margin-top:3px;
}


.chart-legend-dot {

    display:inline-block;

    width:8px;
    height:8px;

    border-radius:50%;

    margin-right:6px;
}


.chart-key {

    display:flex;

    gap:16px;

    margin-top:12px;

    flex-wrap:wrap;
}


.chart-key span {

    font-size:10.5px;

    color:
        var(--ink-600);

    font-weight:650;
}


.chart-canvas-wrap {

    position:relative;

    height:230px;
}


.chart-canvas-wrap.donut {

    height:210px;
}


/* =========================================================
   OVERVIEW GRID
========================================================= */

.overview-grid {

    display:grid;

    grid-template-columns:
        1.25fr
        .75fr;

    gap:18px;

    margin-bottom:26px;
}


.panel {

    background:
        var(--surface);

    border:
        1px solid
        var(--border);

    border-radius:
        var(--radius);

    box-shadow:
        var(--shadow);

    overflow:hidden;

    animation:
        slideUp .55s ease both;
}


.panel-header {

    padding:
        18px 20px;

    border-bottom:
        1px solid
        var(--border);

    display:flex;

    align-items:center;

    justify-content:space-between;
}


.panel-header h2 {

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size:15px;

    font-weight:800;
}


.panel-header p {

    color:
        var(--ink-400);

    font-size:11px;

    margin-top:3px;
}


/* booking status */

.status-overview {

    padding:20px;
}


.status-big {

    display:flex;

    align-items:center;

    justify-content:space-between;

    margin-bottom:20px;
}


.status-big strong {

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size:34px;

    letter-spacing:-1px;
}


.status-big span {

    display:block;

    color:
        var(--ink-400);

    font-size:10.5px;

    margin-top:2px;
}


.status-items {

    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:10px;
}


.status-item {

    padding:13px;

    border-radius:10px;

    background:#FAFBFC;

    border:
        1px solid
        var(--border);
}


.status-item span {

    display:block;

    font-size:10.5px;

    color:
        var(--ink-400);
}


.status-item strong {

    display:block;

    font-size:21px;

    margin-top:3px;
}


.status-line {

    height:6px;

    background:#EEF1F4;

    border-radius:20px;

    margin-top:14px;

    overflow:hidden;
}


.status-line-fill {

    height:100%;

    border-radius:20px;

    transition:
        width 1.1s cubic-bezier(.2,.7,.2,1);

    width:0;
}


.fill-pending {
    background:var(--amber);
}


.fill-approved {
    background:var(--green);
}


.fill-rejected {
    background:var(--red);
}


/* people */

.people-grid {

    display:grid;

    grid-template-columns:
        repeat(2,1fr);

    gap:10px;

    padding:18px;
}


.person-box {

    padding:15px;

    border-radius:11px;

    background:#FAFBFC;

    border:
        1px solid
        var(--border);

    transition:.2s;
}


.person-box:hover {

    transform:
        translateY(-2px);

    border-color:
        #CDD6DF;
}


.person-box-top {

    display:flex;

    align-items:center;

    justify-content:space-between;
}


.person-box-icon {

    width:32px;
    height:32px;

    border-radius:8px;

    display:flex;

    align-items:center;
    justify-content:center;

    font-size:13px;

    font-weight:800;
}


.person-box strong {

    display:block;

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size:23px;

    margin-top:10px;
}


.person-box span {

    color:
        var(--ink-400);

    font-size:10.5px;
}


/* newest users list */

.newest-users {

    padding:6px 20px 16px;
}


.newest-user-row {

    display:flex;

    align-items:center;

    gap:11px;

    padding:
        11px 0;

    border-bottom:
        1px solid
        var(--border);
}


.newest-user-row:last-child {

    border-bottom:none;
}


.newest-user-avatar {

    width:32px;
    height:32px;

    border-radius:9px;

    background:
        var(--blue-light);

    color:
        var(--blue);

    display:flex;

    align-items:center;
    justify-content:center;

    font-size:11px;

    font-weight:800;

    flex-shrink:0;
}


.newest-user-info {

    flex:1;

    min-width:0;
}


.newest-user-info strong {

    display:block;

    font-size:12px;

    color:
        var(--ink-900);
}


.newest-user-info span {

    display:block;

    font-size:10.5px;

    color:
        var(--ink-400);

    white-space:nowrap;

    overflow:hidden;

    text-overflow:ellipsis;
}


.role-pill {

    font-size:9.5px;

    font-weight:800;

    padding:4px 9px;

    border-radius:20px;

    text-transform:capitalize;

    flex-shrink:0;
}


.role-pill.traveler {
    background:var(--blue-light);
    color:var(--blue);
}

.role-pill.driver {
    background:var(--teal-100);
    color:var(--teal-600);
}

.role-pill.guide {
    background:var(--purple-light);
    color:var(--purple);
}

.role-pill.admin {
    background:var(--gold-100);
    color:var(--gold-600);
}


/* =========================================================
   BOOKING TABLE
========================================================= */

.bookings-panel {

    margin-bottom:26px;
}


.table-wrap {

    overflow-x:auto;
}


table {

    width:100%;

    border-collapse:collapse;

    min-width:980px;
}


th {

    text-align:left;

    padding:
        12px 18px;

    background:
        #FAFBFC;

    color:
        var(--ink-400);

    font-size:9.5px;

    text-transform:uppercase;

    letter-spacing:.7px;

    font-weight:800;

    border-bottom:
        1px solid
        var(--border);
}


td {

    padding:
        15px 18px;

    border-bottom:
        1px solid
        var(--border);

    color:
        var(--ink-600);

    font-size:12px;

    vertical-align:middle;
}


tbody tr {

    transition:
        background .2s;
}


tbody tr:hover {

    background:
        #FBFCFD;
}


.booking-person {

    display:flex;

    align-items:center;

    gap:9px;
}


.booking-avatar {

    width:33px;
    height:33px;

    border-radius:9px;

    background:
        var(--teal-100);

    color:
        var(--teal-600);

    display:flex;

    align-items:center;
    justify-content:center;

    font-size:10.5px;

    font-weight:800;

    flex-shrink:0;
}


.booking-person strong {

    display:block;

    color:
        var(--ink-900);

    font-size:12px;
}


.booking-person span {

    display:block;

    color:
        var(--ink-400);

    font-size:9.5px;

    margin-top:2px;
}


.destination-name {

    color:
        var(--ink-900);

    font-weight:750;
}


.destination-location {

    color:
        var(--ink-400);

    font-size:9.5px;

    margin-top:2px;
}


.status-badge {

    display:inline-flex;

    align-items:center;

    padding:
        6px 9px;

    border-radius:6px;

    font-size:9px;

    text-transform:uppercase;

    letter-spacing:.5px;

    font-weight:800;
}


.status-pending {

    background:
        var(--amber-light);

    color:
        var(--amber);
}


.status-approved {

    background:
        var(--green-light);

    color:
        var(--green);
}


.status-rejected {

    background:
        var(--red-light);

    color:
        var(--red);
}


.status-default {

    background:
        #EEF1F4;

    color:
        var(--ink-600);
}


.cost {

    color:
        var(--ink-900);

    font-weight:800;
}


/* actions */

.actions {

    display:flex;

    gap:6px;
}


.action-btn {

    border:none;

    padding:
        8px 11px;

    border-radius:6px;

    font-family:inherit;

    font-size:9.5px;

    font-weight:800;

    cursor:pointer;

    transition:.2s;
}


.action-btn:hover {

    transform:
        translateY(-1px);
}


.approve-btn {

    background:
        var(--green-light);

    color:
        var(--green);
}


.reject-btn {

    background:
        var(--red-light);

    color:
        var(--red);
}


.view-btn {

    background:
        var(--blue-light);

    color:
        var(--blue);

    text-decoration:none;

    display:inline-flex;

    align-items:center;
}


/* =========================================================
   LOWER GRID
========================================================= */

.lower-grid {

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:18px;

    margin-bottom:26px;
}


/* destination list */

.destination-list {

    padding:6px 20px 15px;
}


.destination-item {

    display:flex;

    align-items:center;

    gap:12px;

    padding:
        13px 0;

    border-bottom:
        1px solid
        var(--border);
}


.destination-item:last-child {

    border-bottom:none;
}


.destination-rank {

    width:30px;
    height:30px;

    border-radius:8px;

    background:
        var(--teal-100);

    color:
        var(--teal-600);

    display:flex;

    align-items:center;
    justify-content:center;

    font-size:10.5px;

    font-weight:800;

    flex-shrink:0;
}


.destination-info {

    flex:1;

    min-width:0;
}


.destination-info strong {

    display:block;

    font-size:12px;

    color:
        var(--ink-900);
}


.destination-info span {

    display:block;

    font-size:9.5px;

    color:
        var(--ink-400);

    margin-top:2px;
}


.destination-bar {

    height:4px;

    background:#EEF1F4;

    border-radius:20px;

    margin-top:6px;

    overflow:hidden;
}


.destination-bar div {

    height:100%;

    background:var(--teal-500);

    border-radius:20px;

    transition:width 1s ease;

    width:0;
}


.destination-count {

    font-size:11px;

    font-weight:800;

    color:
        var(--ink-600);

    flex-shrink:0;
}


/* provider status */

.provider-status {

    padding:18px 20px;
}


.provider-block {

    margin-bottom:18px;
}


.provider-block:last-child {

    margin-bottom:0;
}


.provider-header {

    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:8px;
}


.provider-header strong {

    font-size:12px;
}


.provider-header span {

    font-size:10.5px;

    color:
        var(--ink-400);
}


.provider-progress {

    height:7px;

    background:
        #EEF1F4;

    border-radius:20px;

    overflow:hidden;
}


.provider-progress div {

    height:100%;

    border-radius:20px;

    transition:
        width 1.1s cubic-bezier(.2,.7,.2,1);

    width:0;
}


.driver-fill {
    background:var(--blue);
}


.guide-fill {
    background:var(--purple);
}


/* =========================================================
   QUICK ACTIONS
========================================================= */

.quick-actions {

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:14px;

    margin-bottom:26px;
}


.quick-action {

    background:
        white;

    border:
        1px solid
        var(--border);

    border-radius:
        13px;

    padding:16px;

    text-decoration:none;

    color:
        var(--ink-900);

    display:flex;

    align-items:center;

    gap:12px;

    box-shadow:
        0 7px 20px
        rgba(13,32,51,.04);

    transition:.25s;
}


.quick-action:hover {

    transform:
        translateY(-3px);

    box-shadow:
        var(--shadow-hover);
}


.quick-icon {

    width:36px;
    height:36px;

    border-radius:9px;

    display:flex;

    align-items:center;
    justify-content:center;

    font-size:14px;

    font-weight:800;
}


.quick-action strong {

    display:block;

    font-size:12px;
}


.quick-action span {

    display:block;

    color:
        var(--ink-400);

    font-size:10px;

    margin-top:2px;
}


/* =========================================================
   DATABASE CONTROL
========================================================= */

.database-grid {

    display:grid;

    grid-template-columns:
        repeat(5,1fr);

    gap:11px;

    margin-bottom:26px;
}


.database-card {

    background:white;

    border:
        1px solid
        var(--border);

    border-radius:11px;

    padding:15px;

    text-align:center;

    transition:.2s;
}


.database-card:hover {

    transform:
        translateY(-2px);

    box-shadow:
        var(--shadow-hover);
}


.database-card strong {

    display:block;

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size:21px;
}


.database-card span {

    display:block;

    color:
        var(--ink-400);

    font-size:9.5px;

    margin-top:3px;
}


/* =========================================================
   FOOTER
========================================================= */

footer {

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:
        6px 0 20px;

    color:
        var(--ink-400);

    font-size:10px;
}


/* =========================================================
   TOAST
========================================================= */

.toast {

    position:fixed;

    right:25px;

    bottom:25px;

    padding:
        13px 18px;

    background:
        var(--navy-950);

    color:white;

    border-radius:9px;

    font-size:11.5px;

    font-weight:650;

    box-shadow:
        0 15px 35px
        rgba(0,0,0,.18);

    transform:
        translateY(20px);

    opacity:0;

    pointer-events:none;

    transition:.25s;

    z-index:999;
}


.toast.show {

    transform:
        translateY(0);

    opacity:1;
}


.toast.success {

    border-left:
        3px solid
        var(--green);
}


.toast.error {

    border-left:
        3px solid
        var(--red);
}


/* =========================================================
   ANIMATIONS
========================================================= */

@keyframes slideUp {

    from {

        opacity:0;

        transform:
            translateY(14px);
    }

    to {

        opacity:1;

        transform:
            translateY(0);
    }
}


@keyframes pulse {

    0%,100% {

        opacity:1;

        transform:
            scale(1);
    }

    50% {

        opacity:.45;

        transform:
            scale(.82);
    }
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1200px) {

    .stats-grid {

        grid-template-columns:
            repeat(2,1fr);
    }

    .metrics-grid {

        grid-template-columns:
            1fr;
    }

    .charts-grid {

        grid-template-columns:
            1fr;
    }

    .overview-grid {

        grid-template-columns:
            1fr;
    }

    .quick-actions {

        grid-template-columns:
            repeat(2,1fr);
    }

    .database-grid {

        grid-template-columns:
            repeat(3,1fr);
    }
}


@media(max-width:850px) {

    .sidebar {

        width:76px;
    }

    .brand {

        justify-content:center;

        padding:0;
    }

    .brand > div:last-child,
    .nav-label,
    .nav-item span,
    .nav-badge,
    .database-status div {

        display:none;
    }

    .nav-item {

        justify-content:center;

        padding:13px;
    }

    .main {

        margin-left:76px;

        width:
            calc(100% - 76px);
    }

    .lower-grid {

        grid-template-columns:
            1fr;
    }

    .database-grid {

        grid-template-columns:
            repeat(2,1fr);
    }
}


@media(max-width:600px) {

    .topbar {

        padding:
            0 16px;
    }

    .live-datetime {

        display:none;
    }

    .content {

        padding:18px;
    }

    .hero {

        align-items:flex-start;

        flex-direction:column;
    }

    .hero h1 {

        font-size:26px;
    }

    .hero-meta {

        flex-wrap:wrap;

        gap:16px;
    }

    .stats-grid {

        grid-template-columns:
            1fr;
    }

    .quick-actions {

        grid-template-columns:
            1fr;
    }

    .database-grid {

        grid-template-columns:
            1fr 1fr;
    }

    .status-items {

        grid-template-columns:
            1fr;
    }

    footer {

        flex-direction:column;

        gap:5px;

        align-items:flex-start;
    }
}



/* =========================================================
   FINAL FONT SIZE BOOST
   ========================================================= */

body {
    font-size: 16px;
}

/* Sidebar */
.nav-item {
    font-size: 15px;
}

.nav-label {
    font-size: 11px;
}

.brand h2 {
    font-size: 18px;
}

.brand span {
    font-size: 11px;
}

/* Topbar */
.topbar-heading strong {
    font-size: 23px;
}

.topbar-heading span {
    font-size: 13px;
}

.live-time {
    font-size: 14px;
}

.live-date {
    font-size: 12px;
}

.admin-profile strong {
    font-size: 14px;
}

.admin-profile span {
    font-size: 11px;
}

/* Hero */
.hero h1 {
    font-size: 38px;
}

.hero p {
    font-size: 15px;
}

.hero-label {
    font-size: 11px;
}

.hero-meta div strong {
    font-size: 21px;
}

.hero-meta div span {
    font-size: 11px;
}

.hero-btn {
    font-size: 13px;
}

/* Section headings */
.section-heading h2 {
    font-size: 19px;
}

.section-tag {
    font-size: 11px;
}

.section-link {
    font-size: 13px;
}

/* Main statistics */
.stat-label {
    font-size: 12px;
}

.stat-number {
    font-size: 36px;
}

.stat-description {
    font-size: 11px;
}

/* Secondary metrics */
.metric-card .metric-text span {
    font-size: 12px;
}

.metric-card .metric-text strong {
    font-size: 24px;
}

/* Charts */
.chart-card-header h2 {
    font-size: 17px;
}

.chart-card-header p {
    font-size: 12px;
}

.chart-key span {
    font-size: 11px;
}

/* Booking overview */
.panel-header h2 {
    font-size: 17px;
}

.panel-header p {
    font-size: 12px;
}

.status-big strong {
    font-size: 36px;
}

.status-big span {
    font-size: 11px;
}

.status-item span {
    font-size: 12px;
}

.status-item strong {
    font-size: 23px;
}

/* People overview */
.person-box strong {
    font-size: 25px;
}

.person-box span {
    font-size: 12px;
}

.newest-user-info strong {
    font-size: 13px;
}

.newest-user-info span {
    font-size: 11px;
}

.role-pill {
    font-size: 10px;
}

/* Booking table */
th {
    font-size: 11px;
}

td {
    font-size: 13px;
}

.booking-person strong {
    font-size: 13px;
}

.booking-person span {
    font-size: 11px;
}

.destination-name {
    font-size: 13px;
}

.destination-location {
    font-size: 11px;
}

.status-badge {
    font-size: 10px;
}

.action-btn {
    font-size: 11px;
}

/* Popular destinations */
.destination-info strong {
    font-size: 13px;
}

.destination-info span {
    font-size: 11px;
}

.destination-count {
    font-size: 12px;
}

/* Service operations */
.provider-header strong {
    font-size: 13px;
}

.provider-header span {
    font-size: 11px;
}

/* Quick control */
.quick-action strong {
    font-size: 13px;
}

.quick-action span {
    font-size: 11px;
}

/* Database */
.database-card strong {
    font-size: 23px;
}

.database-card span {
    font-size: 11px;
}

/* Footer */
footer {
    font-size: 11px;
}

/* Toast */
.toast {
    font-size: 13px;
}




</style>

</head>


<body>


<div class="app">


<!-- =====================================================
     SIDEBAR
===================================================== -->

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
                Admin Control Center
            </span>

        </div>

    </div>


    <nav class="navigation">

        <div class="nav-label">
            Control Panel
        </div>


        <a
            href="index.php"
            class="nav-item active"
        >

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >

                <rect
                    x="3"
                    y="3"
                    width="7"
                    height="7"
                />

                <rect
                    x="14"
                    y="3"
                    width="7"
                    height="7"
                />

                <rect
                    x="3"
                    y="14"
                    width="7"
                    height="7"
                />

                <rect
                    x="14"
                    y="14"
                    width="7"
                    height="7"
                />

            </svg>

            <span>
                Dashboard
            </span>

        </a>


        <a
            href="users.php"
            class="nav-item"
        >

            <svg
                viewBox="0 0 24 24"
                fill="none"
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
                    d="M17 11a4 4 0 0 1 0-8"
                />

            </svg>

            <span>
                Users
            </span>

        </a>


        <a
            href="destinations.php"
            class="nav-item"
        >

            <svg
                viewBox="0 0 24 24"
                fill="none"
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

            <span>
                Destinations
            </span>

        </a>


        <a
            href="bookings.php"
            class="nav-item"
        >

            <svg
                viewBox="0 0 24 24"
                fill="none"
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

            <span>
                Bookings
            </span>

            <?php if ($pendingBookings > 0): ?>
                <span class="nav-badge"><?= e($pendingBookings) ?></span>
            <?php endif; ?>

        </a>


        <a
            href="service_providers.php"
            class="nav-item"
        >

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke-width="2"
            >

                <circle
                    cx="12"
                    cy="8"
                    r="4"
                />

                <path
                    d="M4 21a8 8 0 0 1 16 0"
                />

            </svg>

            <span>
                Service Providers
            </span>

        </a>


        <a
            href="booking_services.php"
            class="nav-item"
        >

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke-width="2"
            >

                <path
                    d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1.5 1.5"
                />

                <path
                    d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1.5-1.5"
                />

            </svg>

            <span>
                Booking Services
            </span>

        </a>

    </nav>


    <div class="sidebar-footer">

        <div class="database-status">

            <span class="status-dot <?= $dbOnline ? '' : 'offline' ?>"></span>

            <div>

                <strong>
                    <?= $dbOnline ? "Database Online" : "Database Error" ?>
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
===================================================== -->

<main class="main">


<!-- =====================================================
     TOPBAR
===================================================== -->

<header class="topbar">


    <div class="topbar-heading">

        <strong>
            Admin Control Center
        </strong>

        <span>
            Complete overview and management of Pocket Tour
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
                Loading...
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
                    System Admin
                </span>

            </div>

        </div>

    </div>

</header>



<!-- =====================================================
     CONTENT
===================================================== -->

<section class="content">


<!-- HERO -->

<div class="hero">

    <div class="hero-content">

        <div class="hero-label">
            SYSTEM OVERVIEW
        </div>

        <h1>
            Welcome to Pocket Tour Control Center
        </h1>

        <p>
            Monitor travelers, drivers, guides, bookings,
            destinations and service operations from one central
            administration panel.
        </p>

        <div class="hero-meta">

            <div>
                <strong><?= e($totalUsers) ?></strong>
                <span>Total People</span>
            </div>

            <div>
                <strong><?= number_format($approvalRate, 0) ?>%</strong>
                <span>Approval Rate</span>
            </div>

            <div>
                <strong>৳<?= number_format($totalBookingValue, 0) ?></strong>
                <span>Confirmed Revenue</span>
            </div>

        </div>

    </div>


    <div class="hero-actions">

        <a
            href="bookings.php"
            class="hero-btn primary"
        >
            Manage Bookings
        </a>

        <a
            href="users.php"
            class="hero-btn secondary"
        >
            Manage Users
        </a>

    </div>

</div>



<!-- =====================================================
     MAIN STATISTICS
===================================================== -->

<div class="section-heading">

    <div>

        <span class="section-tag">
            Live Statistics
        </span>

        <h2>
            System Overview
        </h2>

    </div>

</div>


<div class="stats-grid">


    <!-- USERS -->

    <div class="stat-card">

        <div class="stat-icon users-icon">

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >

                <path
                    d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"
                />

                <circle
                    cx="10"
                    cy="7"
                    r="4"
                />

            </svg>

        </div>

        <div>

            <span class="stat-label">
                Total Users
            </span>

            <strong
                class="stat-number counter"
                data-target="<?= e($totalUsers) ?>"
            >
                0
            </strong>

            <span class="stat-description">
                All registered accounts
            </span>

        </div>

    </div>


    <!-- DESTINATIONS -->

    <div class="stat-card">

        <div class="stat-icon destination-icon">

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

        </div>

        <div>

            <span class="stat-label">
                Destinations
            </span>

            <strong
                class="stat-number counter"
                data-target="<?= e($totalDestinations) ?>"
            >
                0
            </strong>

            <span class="stat-description">
                Available travel locations
            </span>

        </div>

    </div>


    <!-- BOOKINGS -->

    <div class="stat-card">

        <div class="stat-icon booking-icon">

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

        </div>

        <div>

            <span class="stat-label">
                Total Bookings
            </span>

            <strong
                class="stat-number counter"
                data-target="<?= e($totalBookings) ?>"
            >
                0
            </strong>

            <span class="stat-description">
                All travel requests
            </span>

        </div>

    </div>


    <!-- PROVIDERS -->

    <div class="stat-card">

        <div class="stat-icon provider-icon">

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >

                <circle
                    cx="12"
                    cy="8"
                    r="4"
                />

                <path
                    d="M4 21a8 8 0 0 1 16 0"
                />

            </svg>

        </div>

        <div>

            <span class="stat-label">
                Service Providers
            </span>

            <strong
                class="stat-number counter"
                data-target="<?= e($totalProviders) ?>"
            >
                0
            </strong>

            <span class="stat-description">
                Drivers and guides
            </span>

        </div>

    </div>


</div>


<!-- SECONDARY PERFORMANCE METRICS -->

<div class="metrics-grid">


    <div class="metric-card">

        <div class="metric-text">
            <span>Approved Revenue</span>
            <strong>৳<?= number_format($totalBookingValue, 0) ?></strong>
        </div>

        <div
            class="stat-icon revenue-icon"
            style="width:44px;height:44px;border-radius:11px;"
        >
            ৳
        </div>

    </div>


    <div class="metric-card">

        <div class="metric-text">
            <span>Avg. Booking Value</span>
            <strong>৳<?= number_format($avgBookingValue, 0) ?></strong>
        </div>

        <div
            class="stat-icon booking-icon"
            style="width:44px;height:44px;border-radius:11px;"
        >
            ~
        </div>

    </div>


    <div class="metric-card">

        <div class="metric-text">
            <span>Approval Rate</span>
            <strong><?= number_format($approvalRate, 1) ?>%</strong>
        </div>

        <div
            class="stat-icon rate-icon"
            style="width:44px;height:44px;border-radius:11px;"
        >
            ✓
        </div>

    </div>


</div>



<!-- =====================================================
     CHARTS
===================================================== -->

<div class="section-heading">

    <div>

        <span class="section-tag">
            Analytics
        </span>

        <h2>
            Trends &amp; Distribution
        </h2>

    </div>

</div>


<div class="charts-grid">


    <div class="chart-card">

        <div class="chart-card-header">

            <div>
                <h2>Booking Trend</h2>
                <p>Requests and approved revenue by travel month</p>
            </div>

        </div>

        <div class="chart-canvas-wrap">
            <canvas id="trendChart"></canvas>
        </div>

        <div class="chart-key">
            <span><span class="chart-legend-dot" style="background:var(--teal-500);"></span>Bookings</span>
            <span><span class="chart-legend-dot" style="background:var(--gold-500);"></span>Revenue (৳)</span>
        </div>

    </div>


    <div class="chart-card">

        <div class="chart-card-header">

            <div>
                <h2>People by Role</h2>
                <p>Traveler, driver, guide &amp; admin split</p>
            </div>

        </div>

        <div class="chart-canvas-wrap donut">
            <canvas id="roleChart"></canvas>
        </div>

    </div>


</div>



<!-- =====================================================
     PEOPLE + BOOKING OVERVIEW
===================================================== -->

<div class="overview-grid">


    <!-- BOOKING OVERVIEW -->

    <div class="panel">

        <div class="panel-header">

            <div>

                <h2>
                    Booking Overview
                </h2>

                <p>
                    Current trip request distribution
                </p>

            </div>

            <a
                href="bookings.php"
                class="section-link"
            >
                Manage →
            </a>

        </div>


        <div class="status-overview">

            <div class="status-big">

                <div>

                    <strong>
                        <?= e($totalBookings) ?>
                    </strong>

                    <span>
                        Total booking requests
                    </span>

                </div>


                <div style="
                    text-align:right;
                ">

                    <strong style="
                        font-size:21px;
                        color:var(--green);
                    ">
                        ৳<?= number_format(
                            $totalBookingValue,
                            0
                        ) ?>
                    </strong>

                    <span>
                        Approved booking value
                    </span>

                </div>

            </div>


            <div class="status-items">


                <div class="status-item">

                    <span>
                        Pending
                    </span>

                    <strong style="
                        color:var(--amber);
                    ">
                        <?= e($pendingBookings) ?>
                    </strong>

                    <div class="status-line">

                        <div
                            class="status-line-fill fill-pending"
                            data-width="<?= $totalBookings > 0
                                ? ($pendingBookings / $totalBookings * 100)
                                : 0
                            ?>"
                        ></div>

                    </div>

                </div>


                <div class="status-item">

                    <span>
                        Approved
                    </span>

                    <strong style="
                        color:var(--green);
                    ">
                        <?= e($approvedBookings) ?>
                    </strong>

                    <div class="status-line">

                        <div
                            class="status-line-fill fill-approved"
                            data-width="<?= $totalBookings > 0
                                ? ($approvedBookings / $totalBookings * 100)
                                : 0
                            ?>"
                        ></div>

                    </div>

                </div>


                <div class="status-item">

                    <span>
                        Rejected
                    </span>

                    <strong style="
                        color:var(--red);
                    ">
                        <?= e($rejectedBookings) ?>
                    </strong>

                    <div class="status-line">

                        <div
                            class="status-line-fill fill-rejected"
                            data-width="<?= $totalBookings > 0
                                ? ($rejectedBookings / $totalBookings * 100)
                                : 0
                            ?>"
                        ></div>

                    </div>

                </div>


            </div>

        </div>

    </div>



    <!-- PEOPLE OVERVIEW -->

    <div class="panel">

        <div class="panel-header">

            <div>

                <h2>
                    People Overview
                </h2>

                <p>
                    Users by account role
                </p>

            </div>

            <a
                href="users.php"
                class="section-link"
            >
                Manage →
            </a>

        </div>


        <div class="people-grid">


            <div class="person-box">

                <div class="person-box-top">

                    <span
                        class="person-box-icon"
                        style="
                            background:var(--blue-light);
                            color:var(--blue);
                        "
                    >
                        T
                    </span>

                </div>

                <strong>
                    <?= e($travelerCount) ?>
                </strong>

                <span>
                    Travelers
                </span>

            </div>


            <div class="person-box">

                <div class="person-box-top">

                    <span
                        class="person-box-icon"
                        style="
                            background:var(--teal-100);
                            color:var(--teal-600);
                        "
                    >
                        D
                    </span>

                </div>

                <strong>
                    <?= e($driverCount) ?>
                </strong>

                <span>
                    Drivers
                </span>

            </div>


            <div class="person-box">

                <div class="person-box-top">

                    <span
                        class="person-box-icon"
                        style="
                            background:var(--purple-light);
                            color:var(--purple);
                        "
                    >
                        G
                    </span>

                </div>

                <strong>
                    <?= e($guideCount) ?>
                </strong>

                <span>
                    Guides
                </span>

            </div>


            <div class="person-box">

                <div class="person-box-top">

                    <span
                        class="person-box-icon"
                        style="
                            background:var(--amber-light);
                            color:var(--amber);
                        "
                    >
                        A
                    </span>

                </div>

                <strong>
                    <?= e($adminCount) ?>
                </strong>

                <span>
                    Admins
                </span>

            </div>

        </div>


        <?php if (count($newestUsers) > 0): ?>

        <div
            class="panel-header"
            style="border-top:1px solid var(--border);"
        >
            <div>
                <h2 style="font-size:13px;">Newest Accounts</h2>
            </div>
        </div>

        <div class="newest-users">

            <?php foreach ($newestUsers as $nu):

                $ruRole = strtolower(trim($nu["role"]));
                $ruInitial = strtoupper(substr($nu["name"], 0, 1));

            ?>

            <div class="newest-user-row">

                <div class="newest-user-avatar">
                    <?= e($ruInitial) ?>
                </div>

                <div class="newest-user-info">
                    <strong><?= e($nu["name"]) ?></strong>
                    <span><?= e($nu["email"]) ?></span>
                </div>

                <span class="role-pill <?= e($ruRole) ?>">
                    <?= e($ruRole) ?>
                </span>

            </div>

            <?php endforeach; ?>

        </div>

        <?php endif; ?>

    </div>

</div>



<!-- =====================================================
     QUICK ACTIONS
===================================================== -->

<div class="section-heading">

    <div>

        <span class="section-tag">
            Administration
        </span>

        <h2>
            Quick Control
        </h2>

    </div>

</div>


<div class="quick-actions">


    <a
        href="users.php"
        class="quick-action"
    >

        <div
            class="quick-icon"
            style="
                background:var(--blue-light);
                color:var(--blue);
            "
        >
            +
        </div>

        <div>

            <strong>
                Manage Users
            </strong>

            <span>
                Travelers, drivers & guides
            </span>

        </div>

    </a>


    <a
        href="destinations.php"
        class="quick-action"
    >

        <div
            class="quick-icon"
            style="
                background:var(--teal-100);
                color:var(--teal-600);
            "
        >
            +
        </div>

        <div>

            <strong>
                Manage Destinations
            </strong>

            <span>
                Add, edit or remove places
            </span>

        </div>

    </a>


    <a
        href="bookings.php"
        class="quick-action"
    >

        <div
            class="quick-icon"
            style="
                background:var(--amber-light);
                color:var(--amber);
            "
        >
            B
        </div>

        <div>

            <strong>
                Manage Bookings
            </strong>

            <span>
                Review every travel request
            </span>

        </div>

    </a>


    <a
        href="service_providers.php"
        class="quick-action"
    >

        <div
            class="quick-icon"
            style="
                background:var(--purple-light);
                color:var(--purple);
            "
        >
            P
        </div>

        <div>

            <strong>
                Service Providers
            </strong>

            <span>
                Manage drivers & guides
            </span>

        </div>

    </a>


</div>



<!-- =====================================================
     RECENT BOOKINGS
===================================================== -->

<div class="panel bookings-panel">


    <div class="panel-header">

        <div>

            <h2>
                Recent Booking Requests
            </h2>

            <p>
                Review and control the latest travel requests
            </p>

        </div>

        <a
            href="bookings.php"
            class="section-link"
        >
            View All →
        </a>

    </div>


    <div class="table-wrap">

        <table>

            <thead>

                <tr>

                    <th>
                        Traveler
                    </th>

                    <th>
                        Destination
                    </th>

                    <th>
                        Date
                    </th>

                    <th>
                        Travelers
                    </th>

                    <th>
                        Admin Status
                    </th>

                    <th>
                        Driver
                    </th>

                    <th>
                        Guide
                    </th>

                    <th>
                        Cost
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>


            <tbody id="bookingTableBody">


            <?php if (count($recentBookings) > 0): ?>


                <?php foreach (
                    $recentBookings
                    as $booking
                ): ?>


                    <?php

                    $status =
                        strtolower(
                            trim(
                                $booking["status"]
                            )
                        );


                    $statusClass =
                        "status-default";


                    if (
                        $status === "pending"
                    ) {
                        $statusClass =
                            "status-pending";
                    }

                    elseif (
                        $status === "approved"
                    ) {
                        $statusClass =
                            "status-approved";
                    }

                    elseif (
                        $status === "rejected"
                    ) {
                        $statusClass =
                            "status-rejected";
                    }


                    $initials =
                        strtoupper(
                            substr(
                                $booking[
                                    "traveler_name"
                                ],
                                0,
                                1
                            )
                        );

                    ?>


                    <tr
                        id="booking-row-<?= e(
                            $booking["booking_id"]
                        ) ?>"
                    >


                        <td>

                            <div class="booking-person">

                                <div class="booking-avatar">
                                    <?= e($initials) ?>
                                </div>

                                <div>

                                    <strong>
                                        <?= e(
                                            $booking[
                                                "traveler_name"
                                            ]
                                        ) ?>
                                    </strong>

                                    <span>
                                        #<?= e(
                                            $booking[
                                                "booking_id"
                                            ]
                                        ) ?>
                                    </span>

                                </div>

                            </div>

                        </td>


                        <td>

                            <div class="destination-name">

                                <?= e(
                                    $booking[
                                        "destination_name"
                                    ]
                                ) ?>

                            </div>

                            <div class="destination-location">

                                <?= e(
                                    $booking[
                                        "location"
                                    ]
                                ) ?>

                            </div>

                        </td>


                        <td>

                            <?= e(
                                $booking[
                                    "travel_date"
                                ]
                            ) ?>

                        </td>


                        <td>

                            <?= e(
                                $booking[
                                    "persons"
                                ]
                            ) ?>

                            person(s)

                        </td>


                        <td>

                            <span
                                class="
                                    status-badge
                                    <?= e(
                                        $statusClass
                                    ) ?>
                                "
                            >
                                <?= e(
                                    ucfirst(
                                        $status
                                    )
                                ) ?>
                            </span>

                        </td>


                        <td>

                            <span
                                class="
                                    status-badge
                                    <?= e(
                                        strtolower(
                                            $booking[
                                                "driver_status"
                                            ]
                                        ) === "accepted"
                                            ? "status-approved"
                                            :
                                            (
                                                strtolower(
                                                    $booking[
                                                        "driver_status"
                                                    ]
                                                ) === "rejected"
                                                    ? "status-rejected"
                                                    : "status-pending"
                                            )
                                    ) ?>
                                "
                            >
                                <?= e(
                                    ucfirst(
                                        $booking[
                                            "driver_status"
                                        ]
                                    )
                                ) ?>
                            </span>

                        </td>


                        <td>

                            <span
                                class="
                                    status-badge
                                    <?= e(
                                        strtolower(
                                            $booking[
                                                "guide_status"
                                            ]
                                        ) === "accepted"
                                            ? "status-approved"
                                            :
                                            (
                                                strtolower(
                                                    $booking[
                                                        "guide_status"
                                                    ]
                                                ) === "rejected"
                                                    ? "status-rejected"
                                                    : "status-pending"
                                            )
                                    ) ?>
                                "
                            >
                                <?= e(
                                    ucfirst(
                                        $booking[
                                            "guide_status"
                                        ]
                                    )
                                ) ?>
                            </span>

                        </td>


                        <td>

                            <span class="cost">

                                ৳<?= number_format(
                                    (float)$booking[
                                        "total_cost"
                                    ],
                                    0
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <div
                                class="actions"
                                id="actions-<?= e(
                                    $booking[
                                        "booking_id"
                                    ]
                                ) ?>"
                            >


                                <?php if (
                                    $status === "pending"
                                ): ?>


                                    <button
                                        class="
                                            action-btn
                                            approve-btn
                                        "
                                        onclick="
                                            updateBookingStatus(
                                                <?= e(
                                                    $booking[
                                                        "booking_id"
                                                    ]
                                                ) ?>,
                                                'approved'
                                            )
                                        "
                                    >
                                        Approve
                                    </button>


                                    <button
                                        class="
                                            action-btn
                                            reject-btn
                                        "
                                        onclick="
                                            updateBookingStatus(
                                                <?= e(
                                                    $booking[
                                                        "booking_id"
                                                    ]
                                                ) ?>,
                                                'rejected'
                                            )
                                        "
                                    >
                                        Reject
                                    </button>


                                <?php else: ?>


                                    <span
                                        style="
                                            color:var(--ink-400);
                                            font-size:9.5px;
                                            font-weight:700;
                                        "
                                    >
                                        Reviewed
                                    </span>


                                <?php endif; ?>


                            </div>

                        </td>


                    </tr>


                <?php endforeach; ?>


            <?php else: ?>


                <tr>

                    <td
                        colspan="9"
                        style="
                            text-align:center;
                            padding:40px;
                            color:var(--ink-400);
                        "
                    >

                        No booking requests available.

                    </td>

                </tr>


            <?php endif; ?>


            </tbody>

        </table>

    </div>

</div>



<!-- =====================================================
     DESTINATIONS + PROVIDERS
===================================================== -->

<div class="lower-grid">


    <!-- DESTINATIONS -->

    <div class="panel">

        <div class="panel-header">

            <div>

                <h2>
                    Popular Destinations
                </h2>

                <p>
                    Destinations with booking activity
                </p>

            </div>

            <a
                href="destinations.php"
                class="section-link"
            >
                Manage →
            </a>

        </div>


        <div class="destination-list">


        <?php if (
            count($topDestinations) > 0
        ): ?>


            <?php

            $rank = 1;

            foreach (
                $topDestinations
                as $destination
            ):

                $dBarPct =
                    $maxDestinationBookings > 0
                        ? ((int)$destination["booking_count"] / $maxDestinationBookings * 100)
                        : 0;

            ?>


                <div class="destination-item">

                    <div class="destination-rank">
                        <?= $rank ?>
                    </div>

                    <div class="destination-info">

                        <strong>
                            <?= e(
                                $destination[
                                    "name"
                                ]
                            ) ?>
                        </strong>

                        <span>
                            <?= e(
                                $destination[
                                    "location"
                                ]
                            ) ?>
                        </span>

                        <div class="destination-bar">
                            <div data-width="<?= $dBarPct ?>"></div>
                        </div>

                    </div>

                    <div class="destination-count">

                        <?= e(
                            $destination[
                                "booking_count"
                            ]
                        ) ?>

                        bookings

                    </div>

                </div>


            <?php

                $rank++;

            endforeach;

            ?>


        <?php else: ?>


            <div style="
                padding:25px 0;
                text-align:center;
                color:var(--ink-400);
                font-size:12px;
            ">
                No destination activity yet.
            </div>


        <?php endif; ?>


        </div>

    </div>



    <!-- PROVIDERS -->

    <div class="panel">

        <div class="panel-header">

            <div>

                <h2>
                    Service Operations
                </h2>

                <p>
                    Driver and guide assignment status
                </p>

            </div>

            <a
                href="service_providers.php"
                class="section-link"
            >
                Manage →
            </a>

        </div>


        <div class="provider-status">


            <div class="provider-block">

                <div class="provider-header">

                    <strong>
                        Driver Operations
                    </strong>

                    <span>
                        <?= e(
                            $driverAccepted
                        ) ?>

                        accepted
                    </span>

                </div>


                <div class="provider-progress">

                    <div
                        class="driver-fill"
                        data-width="<?= $totalBookings > 0
                            ? ($driverAccepted / $totalBookings * 100)
                            : 0
                        ?>"
                    ></div>

                </div>


                <div style="
                    display:flex;
                    justify-content:space-between;
                    margin-top:7px;
                    color:var(--ink-400);
                    font-size:9.5px;
                ">

                    <span>
                        Pending:
                        <?= e(
                            $driverPending
                        ) ?>
                    </span>

                    <span>
                        Rejected:
                        <?= e(
                            $driverRejected
                        ) ?>
                    </span>

                </div>

            </div>



            <div class="provider-block">

                <div class="provider-header">

                    <strong>
                        Guide Operations
                    </strong>

                    <span>
                        <?= e(
                            $guideAccepted
                        ) ?>

                        accepted
                    </span>

                </div>


                <div class="provider-progress">

                    <div
                        class="guide-fill"
                        data-width="<?= $totalBookings > 0
                            ? ($guideAccepted / $totalBookings * 100)
                            : 0
                        ?>"
                    ></div>

                </div>


                <div style="
                    display:flex;
                    justify-content:space-between;
                    margin-top:7px;
                    color:var(--ink-400);
                    font-size:9.5px;
                ">

                    <span>
                        Pending:
                        <?= e(
                            $guidePending
                        ) ?>
                    </span>

                    <span>
                        Rejected:
                        <?= e(
                            $guideRejected
                        ) ?>
                    </span>

                </div>

            </div>


        </div>

    </div>


</div>



<!-- =====================================================
     DATABASE CONTROL
===================================================== -->

<div class="section-heading">

    <div>

        <span class="section-tag">
            Database
        </span>

        <h2>
            System Control
        </h2>

    </div>

</div>


<div class="database-grid">


    <a
        href="users.php"
        class="database-card"
        style="text-decoration:none;color:inherit;"
    >

        <strong>
            <?= e($totalUsers) ?>
        </strong>

        <span>
            Users
        </span>

    </a>


    <a
        href="destinations.php"
        class="database-card"
        style="text-decoration:none;color:inherit;"
    >

        <strong>
            <?= e($totalDestinations) ?>
        </strong>

        <span>
            Destinations
        </span>

    </a>


    <a
        href="bookings.php"
        class="database-card"
        style="text-decoration:none;color:inherit;"
    >

        <strong>
            <?= e($totalBookings) ?>
        </strong>

        <span>
            Bookings
        </span>

    </a>


    <a
        href="service_providers.php"
        class="database-card"
        style="text-decoration:none;color:inherit;"
    >

        <strong>
            <?= e($totalProviders) ?>
        </strong>

        <span>
            Service Providers
        </span>

    </a>


    <a
        href="booking_services.php"
        class="database-card"
        style="text-decoration:none;color:inherit;"
    >

        <strong>
            <?= e($totalBookingServices) ?>
        </strong>

        <span>
            Booking Services
        </span>

    </a>


</div>



<!-- =====================================================
     FOOTER
===================================================== -->

<footer>

    <span>
        © <?= date("Y") ?> Pocket Tour
    </span>

    <span>
        PHP + MySQL/MariaDB ·
        <?= $dbOnline
            ? "Database Connected"
            : "Database Error"
        ?>
    </span>

</footer>


</section>

</main>

</div>



<!-- =====================================================
     TOAST
===================================================== -->

<div
    class="toast"
    id="toast"
></div>



<script>

/* =========================================================
   ADMIN AUTH
========================================================= */

const currentUser =
    JSON.parse(
        localStorage.getItem(
            "user"
        ) || "null"
    );


if (!currentUser) {

    alert(
        "Please login as administrator."
    );

    window.location.href =
        "Frontend/login06.html";

}


const currentRole =
    String(
        currentUser?.role || ""
    )
    .trim()
    .toLowerCase();


if (
    currentUser &&
    currentRole !== "admin"
) {

    alert(
        "Access denied."
    );

    window.location.href =
        "Frontend/HOME1.html";

}


/* =========================================================
   LIVE DATE / TIME
========================================================= */

function updateDateTime() {

    const now =
        new Date();


    const time =
        now.toLocaleTimeString(
            "en-US",
            {
                hour:"2-digit",
                minute:"2-digit",
                second:"2-digit",
                hour12:true
            }
        );


    const date =
        now.toLocaleDateString(
            "en-US",
            {
                year:"numeric",
                month:"short",
                day:"numeric"
            }
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


/* =========================================================
   ONE ORCHESTRATED LOAD SEQUENCE:
   count-up numbers + progress bars filling in, once,
   on page load — this is the dashboard's single
   "premium" motion moment.
========================================================= */

function animateCounters() {

    document.querySelectorAll(".counter").forEach((el) => {

        const target =
            parseInt(el.dataset.target || "0", 10);

        const duration = 900;
        const start = performance.now();

        function tick(now) {

            const progress =
                Math.min((now - start) / duration, 1);

            const eased =
                1 - Math.pow(1 - progress, 3);

            el.textContent =
                Math.round(eased * target).toLocaleString();

            if (progress < 1) {
                requestAnimationFrame(tick);
            }
        }

        requestAnimationFrame(tick);
    });
}


function animateBars() {

    document.querySelectorAll("[data-width]").forEach((el) => {

        const width =
            parseFloat(el.dataset.width || "0");

        requestAnimationFrame(() => {
            el.style.width = width + "%";
        });
    });
}


window.addEventListener("load", () => {
    animateCounters();
    animateBars();
});


/* =========================================================
   CHARTS
========================================================= */

if (window.Chart) {

    Chart.defaults.font.family =
        "'DM Sans', 'Plus Jakarta Sans', Arial, sans-serif";

    Chart.defaults.color = "#8290A0";


    const trendLabels  = <?= $chartTrendLabels ?>;
    const trendCounts  = <?= $chartTrendCounts ?>;
    const trendRevenue = <?= $chartTrendRevenue ?>;

    const trendCtx =
        document.getElementById("trendChart");

    if (trendCtx) {

        new Chart(trendCtx, {

            type: "line",

            data: {

                labels: trendLabels,

                datasets: [
                    {
                        label: "Bookings",
                        data: trendCounts,
                        borderColor: "#0F8B8D",
                        backgroundColor: "rgba(15,139,141,.12)",
                        tension: .35,
                        fill: true,
                        pointRadius: 3,
                        pointBackgroundColor: "#0F8B8D",
                        yAxisID: "y"
                    },
                    {
                        label: "Revenue",
                        data: trendRevenue,
                        borderColor: "#D9A441",
                        backgroundColor: "rgba(217,164,65,.08)",
                        tension: .35,
                        fill: false,
                        pointRadius: 3,
                        pointBackgroundColor: "#D9A441",
                        yAxisID: "y1"
                    }
                ]

            },

            options: {

                responsive: true,
                maintainAspectRatio: false,

                animation: {
                    duration: 900,
                    easing: "easeOutCubic"
                },

                plugins: {
                    legend: { display: false }
                },

                scales: {

                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    },

                    y: {
                        position: "left",
                        grid: { color: "#EEF1F4" },
                        ticks: { font: { size: 11 } },
                        beginAtZero: true
                    },

                    y1: {
                        position: "right",
                        grid: { display: false },
                        ticks: {
                            font: { size: 11 },
                            callback: (v) => "৳" + v
                        },
                        beginAtZero: true
                    }

                }

            }

        });

    }


    const roleCtx =
        document.getElementById("roleChart");

    if (roleCtx) {

        new Chart(roleCtx, {

            type: "doughnut",

            data: {
                labels: <?= $chartRoleLabels ?>,
                datasets: [{
                    data: <?= $chartRoleData ?>,
                    backgroundColor: [
                        "#4777D8",
                        "#0F8B8D",
                        "#8067C8",
                        "#D9A441"
                    ],
                    borderWidth: 0,
                    hoverOffset: 6
                }]
            },

            options: {

                responsive: true,
                maintainAspectRatio: false,
                cutout: "68%",

                animation: {
                    duration: 900,
                    easing: "easeOutCubic"
                },

                plugins: {
                    legend: {
                        position: "bottom",
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8,
                            font: { size: 11 },
                            padding: 14
                        }
                    }
                }

            }

        });

    }

}


/* =========================================================
   TOAST
========================================================= */

function showToast(
    message,
    type = "success"
) {

    const toast =
        document.getElementById(
            "toast"
        );


    toast.textContent =
        message;


    toast.className =
        `toast ${type}`;


    setTimeout(
        () => {

            toast.classList.add(
                "show"
            );

        },
        30
    );


    setTimeout(
        () => {

            toast.classList.remove(
                "show"
            );

        },
        3000
    );

}


/* =========================================================
   UPDATE BOOKING STATUS
========================================================= */

async function updateBookingStatus(
    bookingId,
    status
) {


    const action =
        status === "approved"
            ? "approve"
            : "reject";


    const confirmed =
        confirm(
            `Are you sure you want to ${action} booking #${bookingId}?`
        );


    if (!confirmed) {

        return;
    }


    try {


        const response =
            await fetch(
                "api/admin_booking_status.php",
                {
                    method:"POST",

                    headers:{
                        "Content-Type":
                            "application/json"
                    },

                    body:
                        JSON.stringify({

                            booking_id:
                                bookingId,

                            status:
                                status,

                            admin_email:
                                currentUser.email
                        })
                }
            );


        const data =
            await response.json();


        if (!data.success) {

            showToast(
                data.msg ||
                "Unable to update booking.",
                "error"
            );

            return;
        }


        showToast(
            data.msg,
            "success"
        );


        const row =
            document.getElementById(
                `booking-row-${bookingId}`
            );


        const actions =
            document.getElementById(
                `actions-${bookingId}`
            );


        if (row) {

            const statusBadge =
                row.querySelector(
                    "td:nth-child(5) .status-badge"
                );


            if (statusBadge) {

                statusBadge.textContent =
                    status
                        .charAt(0)
                        .toUpperCase() +
                    status.slice(1);


                statusBadge.className =
                    `status-badge ${
                        status === "approved"
                            ? "status-approved"
                            : "status-rejected"
                    }`;
            }
        }


        if (actions) {

            actions.innerHTML = `

                <span style="
                    color:var(--ink-400);
                    font-size:9.5px;
                    font-weight:700;
                ">
                    Reviewed
                </span>

            `;
        }


    } catch (error) {

        console.error(
            "Booking status error:",
            error
        );


        showToast(
            "Unable to connect to the server.",
            "error"
        );
    }

}

</script>


</body>

</html>