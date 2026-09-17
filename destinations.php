<?php

require_once "config/database.php";

/* =========================================================
   HELPER
========================================================= */

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


/* =========================================================
   VARIABLES
========================================================= */

$message = "";
$message_type = "";

$search = isset($_GET["search"])
    ? trim($_GET["search"])
    : "";


/* =========================================================
   ADD DESTINATION
========================================================= */

if (isset($_POST["add_destination"])) {

    $name = trim($_POST["name"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $budget = trim($_POST["budget"] ?? "");

    if ($name === "" || $location === "") {

        $message = "Name and location are required.";
        $message_type = "error";

    } else {

        if ($budget === "") {

            $stmt = $conn->prepare("
                INSERT INTO destinations
                (name, location, description, budget)
                VALUES (?, ?, ?, NULL)
            ");

            $stmt->bind_param(
                "sss",
                $name,
                $location,
                $description
            );

        } else {

            $budget_value = (float)$budget;

            $stmt = $conn->prepare("
                INSERT INTO destinations
                (name, location, description, budget)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "sssd",
                $name,
                $location,
                $description,
                $budget_value
            );
        }

        if ($stmt->execute()) {

            $message = "Destination added successfully.";
            $message_type = "success";

        } else {

            $message = "Failed to add destination.";
            $message_type = "error";
        }

        $stmt->close();
    }
}


/* =========================================================
   DELETE DESTINATION
========================================================= */

if (isset($_GET["delete"])) {

    $destination_id = (int)$_GET["delete"];

    if ($destination_id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM destinations
            WHERE destination_id = ?
        ");

        $stmt->bind_param(
            "i",
            $destination_id
        );

        if ($stmt->execute()) {

            $message = "Destination deleted successfully.";
            $message_type = "success";

        } else {

            $message =
                "Unable to delete destination. It may be connected to existing bookings.";

            $message_type = "error";
        }

        $stmt->close();
    }
}


/* =========================================================
   UPDATE DESTINATION
========================================================= */

if (isset($_POST["update_destination"])) {

    $destination_id =
        (int)($_POST["destination_id"] ?? 0);

    $name =
        trim($_POST["name"] ?? "");

    $location =
        trim($_POST["location"] ?? "");

    $description =
        trim($_POST["description"] ?? "");

    $budget =
        trim($_POST["budget"] ?? "");


    if (
        $destination_id <= 0 ||
        $name === "" ||
        $location === ""
    ) {

        $message =
            "Name and location are required.";

        $message_type = "error";

    } else {

        if ($budget === "") {

            $stmt = $conn->prepare("
                UPDATE destinations
                SET
                    name = ?,
                    location = ?,
                    description = ?,
                    budget = NULL
                WHERE destination_id = ?
            ");

            $stmt->bind_param(
                "sssi",
                $name,
                $location,
                $description,
                $destination_id
            );

        } else {

            $budget_value = (float)$budget;

            $stmt = $conn->prepare("
                UPDATE destinations
                SET
                    name = ?,
                    location = ?,
                    description = ?,
                    budget = ?
                WHERE destination_id = ?
            ");

            $stmt->bind_param(
                "sssdi",
                $name,
                $location,
                $description,
                $budget_value,
                $destination_id
            );
        }

        if ($stmt->execute()) {

            $message =
                "Destination updated successfully.";

            $message_type = "success";

        } else {

            $message =
                "Failed to update destination.";

            $message_type = "error";
        }

        $stmt->close();
    }
}


/* =========================================================
   EDIT DESTINATION
========================================================= */

$edit_destination = null;

if (isset($_GET["edit"])) {

    $edit_id = (int)$_GET["edit"];

    if ($edit_id > 0) {

        $stmt = $conn->prepare("
            SELECT
                destination_id,
                name,
                location,
                description,
                budget
            FROM destinations
            WHERE destination_id = ?
        ");

        $stmt->bind_param(
            "i",
            $edit_id
        );

        $stmt->execute();

        $result_edit =
            $stmt->get_result();

        if ($result_edit->num_rows === 1) {

            $edit_destination =
                $result_edit->fetch_assoc();
        }

        $stmt->close();
    }
}


/* =========================================================
   SEARCH DESTINATIONS
========================================================= */

$sql = "
    SELECT
        destination_id,
        name,
        location,
        description,
        budget
    FROM destinations
    WHERE 1 = 1
";

$params = [];
$types = "";

if ($search !== "") {

    $sql .= "
        AND (
            name LIKE ?
            OR location LIKE ?
            OR description LIKE ?
        )
    ";

    $search_value =
        "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "sss";
}

$sql .= "
    ORDER BY destination_id DESC
";


$stmt = $conn->prepare($sql);

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
   DESTINATION STATISTICS
========================================================= */

$total_destinations = 0;
$budget_result = null;

$count_result = $conn->query("
    SELECT COUNT(*) AS total
    FROM destinations
");

if ($count_result) {

    $row =
        $count_result->fetch_assoc();

    $total_destinations =
        (int)$row["total"];
}


$budget_result = $conn->query("
    SELECT
        AVG(budget) AS average_budget,
        MIN(budget) AS minimum_budget,
        MAX(budget) AS maximum_budget
    FROM destinations
    WHERE budget IS NOT NULL
");

$average_budget = 0;
$minimum_budget = 0;
$maximum_budget = 0;

if ($budget_result) {

    $budget_row =
        $budget_result->fetch_assoc();

    $average_budget =
        (float)($budget_row["average_budget"] ?? 0);

    $minimum_budget =
        (float)($budget_row["minimum_budget"] ?? 0);

    $maximum_budget =
        (float)($budget_row["maximum_budget"] ?? 0);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Pocket Tour Destination Management">
<title>Pocket Tour | Destinations</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>

  :root{
    --navy-950:#0B1B2E;
    --navy-850:#102640;
    --navy-750:#16324F;
    --teal-500:#0F8B8D;
    --teal-600:#0C7274;
    --teal-100:#E3F4F3;
    --canvas:#F4F6F9;
    --surface:#FFFFFF;
    --ink-900:#141F2C;
    --ink-600:#4B5A6B;
    --ink-400:#8593A3;
    --border:#E4E8EE;
    --amber-500:#E2A63B;
    --amber-100:#FCF1DE;
    --blue-500:#3B6FE2;
    --blue-100:#E9EEFC;
    --green-500:#2FA84F;
    --green-100:#E6F6EA;
    --red-500:#E0524A;
    --red-100:#FBEAE9;
    --shadow:0 1px 2px rgba(11,27,46,0.04), 0 8px 20px -12px rgba(11,27,46,0.10);
    --radius:14px;
    color-scheme:light;
  }

  [data-theme="dark"]{
    --canvas:#0B141F;
    --surface:#101C29;
    --ink-900:#EDF1F5;
    --ink-600:#A9B6C4;
    --ink-400:#71808F;
    --border:#1E2C3B;
    --teal-100:#0E2A2B;
    --amber-100:#2A2314;
    --blue-100:#171F33;
    --green-100:#132A1B;
    --red-100:#2B1815;
    --shadow:0 1px 2px rgba(0,0,0,0.25), 0 10px 24px -14px rgba(0,0,0,0.55);
    color-scheme:dark;
  }

  *{ box-sizing:border-box; }

  html, body{
    margin:0;
    padding:0;
    background:var(--canvas);
    color:var(--ink-900);
    font-family:"Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, sans-serif;
    font-size:15px;
    -webkit-font-smoothing:antialiased;
    transition:background .25s ease, color .25s ease;
  }

  a{ color:inherit; text-decoration:none; }

  @media (prefers-reduced-motion: reduce){
    *{ transition-duration:0.001ms !important; animation-duration:0.001ms !important; }
  }

  /* ---------- layout ---------- */

  .app{
    display:grid;
    grid-template-columns:264px 1fr;
    min-height:100vh;
  }

  .sidebar{
    background:var(--navy-950);
    color:#DCE4EC;
    padding:28px 20px;
    display:flex;
    flex-direction:column;
    position:sticky;
    top:0;
    height:100vh;
  }

  .brand{
    display:flex;
    align-items:center;
    gap:12px;
    padding:0 8px 24px 8px;
    border-bottom:1px solid rgba(255,255,255,0.08);
    margin-bottom:20px;
  }

  .brand-logo{
    width:40px;
    height:40px;
    border-radius:11px;
    background:linear-gradient(135deg, var(--teal-500), #0A5F61);
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:800;
    font-size:17px;
    color:#fff;
    flex-shrink:0;
  }

  .brand h2{
    margin:0;
    font-size:16.5px;
    font-weight:700;
    color:#fff;
  }

  .brand span{
    font-size:12.5px;
    color:#8FA1B3;
  }

  .nav-label{
    padding:4px 12px 8px;
    font-size:11px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.6px;
    color:#5E7186;
  }

  .navigation{
    display:flex;
    flex-direction:column;
    gap:2px;
    flex:1;
  }

  .nav-item{
    display:flex;
    align-items:center;
    gap:12px;
    padding:11px 12px;
    border-radius:9px;
    font-size:14px;
    font-weight:500;
    color:#AAB9C8;
    border-left:2px solid transparent;
    transition:background .18s ease, color .18s ease, border-color .18s ease, transform .18s ease;
  }

  .nav-item svg{
    width:18px;
    height:18px;
    flex-shrink:0;
    stroke:currentColor;
  }

  .nav-item:hover{
    background:rgba(255,255,255,0.05);
    color:#fff;
    transform:translateX(2px);
  }

  .nav-item.active{
    background:rgba(15,139,141,0.16);
    color:#fff;
    border-left:2px solid var(--teal-500);
  }

  .sidebar-footer{
    padding-top:18px;
    border-top:1px solid rgba(255,255,255,0.08);
    display:flex;
    flex-direction:column;
    gap:14px;
  }

  .theme-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:2px 12px;
  }

  .theme-row span{
    font-size:13.5px;
    color:#AAB9C8;
    font-weight:500;
  }

  .switch{
    position:relative;
    width:40px;
    height:22px;
    flex-shrink:0;
  }

  .switch input{
    opacity:0;
    width:0;
    height:0;
    position:absolute;
  }

  .switch-track{
    position:absolute;
    inset:0;
    background:rgba(255,255,255,0.14);
    border-radius:999px;
    cursor:pointer;
    transition:background .2s ease;
  }

  .switch-track::before{
    content:"";
    position:absolute;
    width:16px;
    height:16px;
    left:3px;
    top:3px;
    background:#fff;
    border-radius:50%;
    transition:transform .2s ease;
  }

  .switch input:checked + .switch-track{
    background:var(--teal-500);
  }

  .switch input:checked + .switch-track::before{
    transform:translateX(18px);
  }

  .switch input:focus-visible + .switch-track{
    outline:2px solid var(--teal-500);
    outline-offset:2px;
  }

  .database-status{
    display:flex;
    align-items:center;
    gap:10px;
    padding:11px 12px;
    background:rgba(255,255,255,0.04);
    border-radius:10px;
  }

  .status-dot{
    width:8px;
    height:8px;
    border-radius:50%;
    background:var(--green-500);
    box-shadow:0 0 0 3px rgba(47,168,79,0.18);
    flex-shrink:0;
  }

  .database-status strong{
    display:block;
    font-size:13.5px;
    color:#fff;
    font-weight:600;
  }

  .database-status small{
    font-size:12px;
    color:#8FA1B3;
  }

  /* ---------- main ---------- */

  .main-content{
    padding:32px 40px 48px;
    max-width:1220px;
  }

  .topbar{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:26px;
    gap:20px;
    flex-wrap:wrap;
  }

  .topbar-heading strong{
    display:block;
    font-size:23px;
    font-weight:800;
  }

  .topbar-heading span{
    font-size:14px;
    color:var(--ink-600);
  }

  .topbar-right{
    display:flex;
    align-items:center;
    gap:16px;
  }

  .live-datetime{
    padding:10px 16px;
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:12px;
    box-shadow:var(--shadow);
    text-align:right;
  }

  .live-time{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:7px;
    font-size:15px;
    font-weight:700;
    font-family:"SF Mono", Menlo, Consolas, monospace;
  }

  .live-dot{
    width:7px;
    height:7px;
    border-radius:50%;
    background:var(--green-500);
    box-shadow:0 0 0 3px rgba(47,168,79,0.18);
    flex-shrink:0;
  }

  .live-date{
    margin-top:2px;
    font-size:12px;
    color:var(--ink-400);
    font-weight:600;
  }

  .admin-profile{
    display:flex;
    align-items:center;
    gap:11px;
    padding:8px 16px 8px 8px;
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:12px;
    box-shadow:var(--shadow);
  }

  .admin-avatar{
    width:38px;
    height:38px;
    border-radius:9px;
    background:linear-gradient(135deg, var(--teal-500), var(--blue-500));
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:700;
    font-size:15px;
  }

  .admin-profile strong{
    display:block;
    font-size:14px;
    font-weight:700;
  }

  .admin-profile span{
    font-size:12.5px;
    color:var(--ink-400);
  }

  /* ---------- page header ---------- */

  .page-header{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:20px;
    margin-bottom:24px;
  }

  .page-tag{
    display:block;
    margin-bottom:6px;
    color:var(--teal-500);
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.7px;
  }

  .page-header h1{
    color:var(--ink-900);
    font-size:28px;
    font-weight:800;
    letter-spacing:-.4px;
  }

  .page-header p{
    margin-top:6px;
    color:var(--ink-600);
    font-size:14px;
  }

  /* ---------- alert ---------- */

  .destination-alert{
    display:flex;
    align-items:center;
    gap:10px;
    padding:14px 16px;
    margin-bottom:20px;
    border-radius:10px;
    font-size:14px;
    font-weight:600;
  }

  .destination-alert.success{
    background:var(--green-100);
    color:var(--green-500);
    border:1px solid rgba(47,168,79,.2);
  }

  .destination-alert.error{
    background:var(--red-100);
    color:var(--red-500);
    border:1px solid rgba(224,82,74,.2);
  }

  /* ---------- stats ---------- */

  .destination-stats{
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:16px;
    margin-bottom:24px;
  }

  .destination-stat{
    position:relative;
    overflow:hidden;
    padding:19px 20px;
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    transition:transform .18s ease, box-shadow .18s ease;
  }

  .destination-stat:hover{
    transform:translateY(-3px);
    box-shadow:0 4px 8px rgba(11,27,46,0.06), 0 16px 28px -14px rgba(11,27,46,0.18);
  }

  .destination-stat::before{
    content:"";
    position:absolute;
    left:0; top:0; bottom:0;
    width:3px;
    background:var(--teal-500);
  }

  .destination-stat:nth-child(2)::before{ background:var(--blue-500); }
  .destination-stat:nth-child(3)::before{ background:var(--amber-500); }
  .destination-stat:nth-child(4)::before{ background:var(--green-500); }

  .destination-stat-label{
    display:block;
    color:var(--ink-400);
    font-size:12.5px;
    font-weight:650;
  }

  .destination-stat-value{
    display:block;
    margin-top:5px;
    color:var(--ink-900);
    font-size:25px;
    font-weight:800;
  }

  .destination-stat-description{
    display:block;
    margin-top:5px;
    color:var(--ink-400);
    font-size:12px;
  }

  /* ---------- form card ---------- */

  .destination-form-card{
    margin-bottom:22px;
    padding:24px 26px;
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:var(--radius);
    box-shadow:var(--shadow);
  }

  .destination-form-card h2{
    margin-bottom:18px;
    color:var(--ink-900);
    font-size:19px;
    font-weight:800;
  }

  .destination-form-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:16px;
  }

  .destination-form-group{
    display:flex;
    flex-direction:column;
    gap:7px;
  }

  .destination-form-group.full{
    grid-column:1 / -1;
  }

  .destination-form-group label{
    color:var(--ink-600);
    font-size:13px;
    font-weight:700;
  }

  .destination-form-group input,
  .destination-form-group textarea{
    width:100%;
    padding:11px 13px;
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:9px;
    outline:none;
    color:var(--ink-900);
    font-family:inherit;
    font-size:14px;
    transition:border-color .2s ease, box-shadow .2s ease;
  }

  .destination-form-group textarea{
    min-height:90px;
    resize:vertical;
  }

  .destination-form-group input::placeholder,
  .destination-form-group textarea::placeholder{
    color:var(--ink-400);
  }

  .destination-form-group input:focus,
  .destination-form-group textarea:focus{
    border-color:var(--teal-500);
    box-shadow:0 0 0 3px rgba(15,139,141,.14);
  }

  .destination-form-actions{
    display:flex;
    align-items:center;
    gap:10px;
    margin-top:18px;
  }

  .destination-button{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:11px 18px;
    border:none;
    border-radius:9px;
    font-size:13.5px;
    font-weight:700;
    cursor:pointer;
    transition:transform .18s ease, background .18s ease, box-shadow .18s ease;
  }

  .destination-button:hover{
    transform:translateY(-1px);
  }

  .destination-button.primary{
    background:var(--teal-500);
    color:#fff;
    box-shadow:0 8px 18px -8px rgba(15,139,141,.5);
  }

  .destination-button.primary:hover{
    background:var(--teal-600);
  }

  .destination-button.cancel{
    background:var(--canvas);
    color:var(--ink-600);
    border:1px solid var(--border);
  }

  .destination-button.cancel:hover{
    background:var(--border);
  }

  /* ---------- toolbar ---------- */

  .destination-toolbar{
    display:flex;
    align-items:center;
    gap:12px;
    margin-bottom:18px;
  }

  .destination-search{
    flex:1;
    min-width:0;
  }

  .destination-search input{
    width:100%;
    padding:11px 14px;
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:9px;
    outline:none;
    color:var(--ink-900);
    font-family:inherit;
    font-size:14px;
    transition:border-color .2s ease, box-shadow .2s ease;
  }

  .destination-search input:focus{
    border-color:var(--teal-500);
    box-shadow:0 0 0 3px rgba(15,139,141,.14);
  }

  .destination-search input::placeholder{
    color:var(--ink-400);
  }

  /* ---------- table card ---------- */

  .destination-table-card{
    overflow:hidden;
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:var(--radius);
    box-shadow:var(--shadow);
  }

  .destination-table-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    padding:18px 20px;
    border-bottom:1px solid var(--border);
  }

  .destination-table-header .tag{
    display:block;
    margin-bottom:4px;
    color:var(--teal-500);
    font-size:11.5px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.7px;
  }

  .destination-table-header h2{
    color:var(--ink-900);
    font-size:17px;
    font-weight:800;
  }

  .destination-count{
    padding:6px 12px;
    background:var(--canvas);
    border:1px solid var(--border);
    border-radius:999px;
    color:var(--ink-400);
    font-size:12.5px;
    font-weight:700;
    white-space:nowrap;
  }

  .destination-table-wrapper{
    width:100%;
    overflow-x:auto;
  }

  .destination-table{
    width:100%;
    border-collapse:collapse;
    min-width:820px;
  }

  .destination-table th{
    padding:14px 16px;
    background:var(--canvas);
    color:var(--ink-400);
    border-bottom:1px solid var(--border);
    font-size:12px;
    font-weight:800;
    text-align:left;
    text-transform:uppercase;
    letter-spacing:.5px;
  }

  .destination-table td{
    padding:16px;
    color:var(--ink-600);
    border-bottom:1px solid var(--border);
    font-size:14px;
    vertical-align:middle;
  }

  .destination-table tbody tr:last-child td{
    border-bottom:none;
  }

  .destination-table tbody tr{
    transition:background .18s ease;
  }

  .destination-table tbody tr:hover{
    background:var(--canvas);
  }

  .destination-id{
    color:var(--ink-400);
    font-size:12.5px;
    font-weight:700;
    font-family:"SF Mono", Menlo, Consolas, monospace;
  }

  .destination-name{
    color:var(--ink-900);
    font-size:14.5px;
    font-weight:800;
  }

  .destination-location{
    color:var(--teal-500);
    font-size:13.5px;
    font-weight:650;
  }

  .destination-description{
    max-width:300px;
    overflow:hidden;
    color:var(--ink-400);
    line-height:1.4;
    text-overflow:ellipsis;
    white-space:nowrap;
  }

  .destination-budget{
    color:var(--ink-900);
    font-size:14px;
    font-weight:800;
    white-space:nowrap;
  }

  .destination-actions{
    display:flex;
    align-items:center;
    gap:8px;
  }

  .destination-action{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:7px 12px;
    border-radius:7px;
    font-size:12.5px;
    font-weight:700;
    transition:transform .18s ease, background .18s ease, color .18s ease;
  }

  .destination-action:hover{
    transform:translateY(-1px);
  }

  .destination-action.edit{
    background:var(--blue-100);
    color:var(--blue-500);
  }

  .destination-action.edit:hover{
    background:var(--blue-500);
    color:#fff;
  }

  .destination-action.delete{
    background:var(--red-100);
    color:var(--red-500);
  }

  .destination-action.delete:hover{
    background:var(--red-500);
    color:#fff;
  }

  .destination-empty{
    padding:48px 20px !important;
    color:var(--ink-400) !important;
    text-align:center;
    font-size:14px;
  }

  footer{
    display:flex;
    justify-content:space-between;
    font-size:12.5px;
    color:var(--ink-400);
    padding:22px 4px 4px;
  }

  /* ---------- responsive ---------- */

  @media (max-width:1080px){
    .destination-stats{ grid-template-columns:repeat(2, minmax(0,1fr)); }
  }

  @media (max-width:980px){
    .app{ grid-template-columns:1fr; }
    .sidebar{ display:none; }
  }

  @media (max-width:760px){
    .page-header{ align-items:flex-start; flex-direction:column; }
    .destination-form-grid{ grid-template-columns:1fr; }
    .destination-form-group.full{ grid-column:auto; }
    .destination-toolbar{ flex-direction:column; align-items:stretch; }
    .destination-search{ width:100%; }
    .topbar-right{ width:100%; justify-content:space-between; }
  }

  @media (max-width:520px){
    .destination-stats{ grid-template-columns:1fr; }
    .destination-form-card{ padding:18px; }
    .destination-table-header{ align-items:flex-start; flex-direction:column; }
    .main-content{ padding:22px 18px 36px; }
  }

</style>
</head>

<body>

<div class="app">

  <!-- SIDEBAR -->
  <aside class="sidebar">

    <div class="brand">
      <div class="brand-logo">P</div>
      <div>
        <h2>Pocket Tour</h2>
        <span>Travel Booking Management</span>
      </div>
    </div>

    <nav class="navigation">

      <div class="nav-label">Management</div>

      <a href="index.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-7 9 7"/><path d="M5 10v9a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-4a1 1 0 0 1 1-1h0a1 1 0 0 1 1 1v4a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-9"/></svg>
        Dashboard
      </a>

      <a href="users.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Users
      </a>

      <a href="destinations.php" class="nav-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 6-9 12-9 12S3 16 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        Destinations
      </a>

      <a href="bookings.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        Bookings
      </a>

      <a href="service_providers.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 16l1.5-5h11L17 16"/><path d="M5.5 11L7 5h6l3 6"/><circle cx="7.5" cy="16.5" r="1.5"/><circle cx="16.5" cy="16.5" r="1.5"/></svg>
        Service Providers
      </a>

      <a href="booking_services.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1.5-1.5"/></svg>
        Booking Services
      </a>

    </nav>

    <div class="sidebar-footer">

      <div class="theme-row">
        <span>Dark mode</span>
        <label class="switch">
          <input type="checkbox" id="themeToggle" aria-label="Toggle dark mode">
          <span class="switch-track"></span>
        </label>
      </div>

      <div class="database-status">
        <span class="status-dot"></span>
        <div>
          <strong>Database Online</strong>
          <small>MySQL / MariaDB</small>
        </div>
      </div>

    </div>

  </aside>


  <!-- MAIN -->
  <main class="main-content">

    <!-- TOPBAR -->
    <header class="topbar">

      <div class="topbar-heading">
        <strong>Destination Management</strong>
        <span>Manage Pocket Tour travel destinations</span>
      </div>

      <div class="topbar-right">

        <div class="live-datetime">
          <div class="live-time">
            <span class="live-dot"></span>
            <span id="liveTime">00:00:00</span>
          </div>
          <div class="live-date" id="liveDate">Sep 13, 2026</div>
        </div>

        <div class="admin-profile">
          <div class="admin-avatar">A</div>
          <div>
            <strong>Administrator</strong>
            <span>Admin</span>
          </div>
        </div>

      </div>

    </header>


    <!-- PAGE HEADER -->
    <section class="page-header">
      <div>
        <span class="page-tag">Destination Management</span>
        <h1>Destinations</h1>
        <p>Manage tourist destinations, locations, descriptions and travel budgets.</p>
      </div>
    </section>


    <!-- ALERT -->
    <?php if ($message !== ""): ?>
      <div class="destination-alert <?= $message_type === "success" ? "success" : "error" ?>">
        <strong><?= $message_type === "success" ? "✓" : "!" ?></strong>
        <span><?= e($message) ?></span>
      </div>
    <?php endif; ?>


    <!-- STATISTICS -->
    <section class="destination-stats">

      <div class="destination-stat">
        <span class="destination-stat-label">Total Destinations</span>
        <strong class="destination-stat-value"><?= $total_destinations ?></strong>
        <span class="destination-stat-description">Registered travel destinations</span>
      </div>

      <div class="destination-stat">
        <span class="destination-stat-label">Average Budget</span>
        <strong class="destination-stat-value">৳<?= number_format($average_budget, 0) ?></strong>
        <span class="destination-stat-description">Average estimated budget</span>
      </div>

      <div class="destination-stat">
        <span class="destination-stat-label">Lowest Budget</span>
        <strong class="destination-stat-value">৳<?= number_format($minimum_budget, 0) ?></strong>
        <span class="destination-stat-description">Lowest available budget</span>
      </div>

      <div class="destination-stat">
        <span class="destination-stat-label">Highest Budget</span>
        <strong class="destination-stat-value">৳<?= number_format($maximum_budget, 0) ?></strong>
        <span class="destination-stat-description">Highest available budget</span>
      </div>

    </section>


    <!-- ADD / EDIT FORM -->
    <section class="destination-form-card" id="destination-form">

      <?php if ($edit_destination): ?>

        <h2>Edit Destination</h2>

        <form method="POST">

          <input type="hidden" name="destination_id" value="<?= e($edit_destination["destination_id"]) ?>">

          <div class="destination-form-grid">

            <div class="destination-form-group">
              <label>Destination Name *</label>
              <input type="text" name="name" value="<?= e($edit_destination["name"]) ?>" placeholder="Example: Sajek Valley" required>
            </div>

            <div class="destination-form-group">
              <label>Location *</label>
              <input type="text" name="location" value="<?= e($edit_destination["location"]) ?>" placeholder="Example: Rangamati, Bangladesh" required>
            </div>

            <div class="destination-form-group full">
              <label>Description</label>
              <textarea name="description" placeholder="Describe the destination..."><?= e($edit_destination["description"]) ?></textarea>
            </div>

            <div class="destination-form-group">
              <label>Estimated Budget (৳)</label>
              <input type="number" name="budget" min="0" step="0.01" value="<?= e($edit_destination["budget"]) ?>" placeholder="Example: 12000">
            </div>

          </div>

          <div class="destination-form-actions">
            <button type="submit" name="update_destination" class="destination-button primary">Update Destination</button>
            <a href="destinations.php" class="destination-button cancel">Cancel</a>
          </div>

        </form>

      <?php else: ?>

        <h2>Add New Destination</h2>

        <form method="POST">

          <div class="destination-form-grid">

            <div class="destination-form-group">
              <label>Destination Name *</label>
              <input type="text" name="name" placeholder="Example: Sajek Valley" required>
            </div>

            <div class="destination-form-group">
              <label>Location *</label>
              <input type="text" name="location" placeholder="Example: Rangamati, Bangladesh" required>
            </div>

            <div class="destination-form-group full">
              <label>Description</label>
              <textarea name="description" placeholder="Describe the destination..."></textarea>
            </div>

            <div class="destination-form-group">
              <label>Estimated Budget (৳)</label>
              <input type="number" name="budget" min="0" step="0.01" placeholder="Example: 12000">
            </div>

          </div>

          <div class="destination-form-actions">
            <button type="submit" name="add_destination" class="destination-button primary">+ Add Destination</button>
          </div>

        </form>

      <?php endif; ?>

    </section>


    <!-- SEARCH -->
    <form method="GET" class="destination-toolbar">

      <div class="destination-search">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by destination name, location or description...">
      </div>

      <button type="submit" class="destination-button primary">Search</button>

      <a href="destinations.php" class="destination-button cancel">Reset</a>

    </form>


    <!-- TABLE -->
    <section class="destination-table-card">

      <div class="destination-table-header">
        <div>
          <span class="tag">Database Records</span>
          <h2>Registered Destinations</h2>
        </div>
        <span class="destination-count"><?= $result->num_rows ?> result(s)</span>
      </div>

      <div class="destination-table-wrapper">

        <table class="destination-table">

          <thead>
            <tr>
              <th>ID</th>
              <th>Destination</th>
              <th>Location</th>
              <th>Description</th>
              <th>Budget</th>
              <th>Actions</th>
            </tr>
          </thead>

          <tbody>

          <?php if ($result->num_rows > 0): ?>

            <?php while ($row = $result->fetch_assoc()): ?>

              <tr>

                <td class="destination-id">#<?= e($row["destination_id"]) ?></td>

                <td><span class="destination-name"><?= e($row["name"]) ?></span></td>

                <td><span class="destination-location"><?= e($row["location"]) ?></span></td>

                <td>
                  <div class="destination-description" title="<?= e($row["description"]) ?>">
                    <?= e($row["description"]) ?: "No description" ?>
                  </div>
                </td>

                <td>
                  <?php if ($row["budget"] !== null): ?>
                    <span class="destination-budget">৳<?= number_format((float)$row["budget"], 2) ?></span>
                  <?php else: ?>
                    <span style="color:var(--ink-400); font-size:13px;">Not set</span>
                  <?php endif; ?>
                </td>

                <td>
                  <div class="destination-actions">

                    <a href="destinations.php?edit=<?= e($row["destination_id"]) ?>#destination-form" class="destination-action edit">
                      Edit
                    </a>

                    <a href="destinations.php?delete=<?= e($row["destination_id"]) ?>" class="destination-action delete"
                       onclick="return confirm('Are you sure you want to delete this destination?');">
                      Delete
                    </a>

                  </div>
                </td>

              </tr>

            <?php endwhile; ?>

          <?php else: ?>

            <tr>
              <td colspan="6" class="destination-empty">No destinations found.</td>
            </tr>

          <?php endif; ?>

          </tbody>

        </table>

      </div>

    </section>


    <!-- FOOTER -->
    <footer>
      <span>© <?= date("Y") ?> Pocket Tour</span>
      <span>PHP + MySQL/MariaDB · XAMPP</span>
    </footer>

  </main>

</div>


<!-- THEME -->
<script>
  const root = document.documentElement;
  const toggle = document.getElementById("themeToggle");
  const saved = localStorage.getItem("pocketTourTheme");

  if (saved === "dark") {
    root.setAttribute("data-theme", "dark");
    if (toggle) toggle.checked = true;
  }

  if (!saved) {
    const prefersDark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
    if (prefersDark) {
      root.setAttribute("data-theme", "dark");
      if (toggle) toggle.checked = true;
    }
  }

  if (toggle) {
    toggle.addEventListener("change", function () {
      if (this.checked) {
        root.setAttribute("data-theme", "dark");
        localStorage.setItem("pocketTourTheme", "dark");
      } else {
        root.removeAttribute("data-theme");
        localStorage.setItem("pocketTourTheme", "light");
      }
    });
  }
</script>


<!-- LIVE DATE & TIME -->
<script>
  function updateDateTime() {
    const now = new Date();

    const timeOptions = { hour: "2-digit", minute: "2-digit", second: "2-digit", hour12: true };
    const dateOptions = { year: "numeric", month: "short", day: "numeric" };

    const time = now.toLocaleTimeString("en-US", timeOptions);
    const date = now.toLocaleDateString("en-US", dateOptions);

    const timeElement = document.getElementById("liveTime");
    const dateElement = document.getElementById("liveDate");

    if (timeElement) timeElement.textContent = time;
    if (dateElement) dateElement.textContent = date;
  }

  updateDateTime();
  setInterval(updateDateTime, 1000);
</script>

</body>
</html>