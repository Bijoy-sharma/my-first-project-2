<?php

require_once "config/database.php";

$message = "";
$message_type = "";

/* =========================
   ADD USER
========================= */
if (isset($_POST["add_user"])) {

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $role = $_POST["role"];
    $phone = trim($_POST["phone"]);

    if ($name === "" || $email === "" || $role === "") {
        $message = "Please fill in all required fields.";
        $message_type = "error";
    } else {

        $stmt = $conn->prepare(
            "INSERT INTO users (name, email, role, phone)
             VALUES (?, ?, ?, ?)"
        );

        $stmt->bind_param("ssss", $name, $email, $role, $phone);

        if ($stmt->execute()) {
            $message = "User added successfully.";
            $message_type = "success";
        } else {
            $message = "Failed to add user. Email may already exist.";
            $message_type = "error";
        }

        $stmt->close();
    }
}


/* =========================
   DELETE USER
========================= */
if (isset($_GET["delete"])) {

    $user_id = intval($_GET["delete"]);

    $stmt = $conn->prepare(
        "DELETE FROM users WHERE user_id = ?"
    );

    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $message = "User deleted successfully.";
        $message_type = "success";
    } else {
        $message = "Unable to delete user. This user may be connected to existing bookings.";
        $message_type = "error";
    }

    $stmt->close();
}


/* =========================
   UPDATE USER
========================= */
if (isset($_POST["update_user"])) {

    $user_id = intval($_POST["user_id"]);
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $role = $_POST["role"];
    $phone = trim($_POST["phone"]);

    $stmt = $conn->prepare(
        "UPDATE users
         SET name = ?, email = ?, role = ?, phone = ?
         WHERE user_id = ?"
    );

    $stmt->bind_param(
        "ssssi",
        $name,
        $email,
        $role,
        $phone,
        $user_id
    );

    if ($stmt->execute()) {
        $message = "User updated successfully.";
        $message_type = "success";
    } else {
        $message = "Failed to update user.";
        $message_type = "error";
    }

    $stmt->close();
}


/* =========================
   SEARCH + FILTER
========================= */

$search = isset($_GET["search"])
    ? trim($_GET["search"])
    : "";

$role_filter = isset($_GET["role"])
    ? $_GET["role"]
    : "";

$sql = "SELECT * FROM users WHERE 1=1";

$params = [];
$types = "";

if ($search !== "") {
    $sql .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";

    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "sss";
}

if ($role_filter !== "") {
    $sql .= " AND role = ?";

    $params[] = $role_filter;
    $types .= "s";
}

$sql .= " ORDER BY user_id DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();


/* =========================
   EDIT USER
========================= */

$edit_user = null;

if (isset($_GET["edit"])) {

    $edit_id = intval($_GET["edit"]);

    $stmt_edit = $conn->prepare(
        "SELECT * FROM users WHERE user_id = ?"
    );

    $stmt_edit->bind_param("i", $edit_id);

    $stmt_edit->execute();

    $edit_result = $stmt_edit->get_result();

    if ($edit_result->num_rows > 0) {
        $edit_user = $edit_result->fetch_assoc();
    }

    $stmt_edit->close();
}


/* =========================
   TOTAL USERS
========================= */

$total_users_result = $conn->query(
    "SELECT COUNT(*) AS total FROM users"
);

$total_users = $total_users_result->fetch_assoc()["total"];


/* =========================
   ROLE COUNTS
========================= */

$role_counts = [
    "Traveler" => 0,
    "Driver" => 0,
    "Guide" => 0,
    "Admin" => 0
];

$role_result = $conn->query(
    "SELECT role, COUNT(*) AS total
     FROM users
     GROUP BY role"
);

while ($row = $role_result->fetch_assoc()) {

    if (isset($role_counts[$row["role"]])) {
        $role_counts[$row["role"]] = $row["total"];
    }
}


/* =========================
   ESCAPE FUNCTION
========================= */

function e($value)
{
    return htmlspecialchars($value ?? "", ENT_QUOTES, "UTF-8");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Users | Pocket Tour</title>
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
    --purple-500:#7C5CE0;
    --purple-100:#EFEAFB;
    --shadow-card:0 1px 2px rgba(11,27,46,0.04), 0 8px 20px -12px rgba(11,27,46,0.10);
    --radius:14px;
    color-scheme: light;
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
    --purple-100:#1D1830;
    --shadow-card:0 1px 2px rgba(0,0,0,0.25), 0 10px 24px -14px rgba(0,0,0,0.55);
    color-scheme: dark;
  }

  *{ box-sizing:border-box; }

  html, body{
    margin:0;
    padding:0;
    background:var(--canvas);
    color:var(--ink-900);
    font-family:"Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, sans-serif;
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
    padding:0 8px 26px 8px;
    border-bottom:1px solid rgba(255,255,255,0.08);
    margin-bottom:22px;
  }

  .brand-icon{
    width:38px;
    height:38px;
    border-radius:10px;
    background:linear-gradient(135deg, var(--teal-500), #0A5F61);
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:800;
    font-size:16px;
    color:#fff;
    flex-shrink:0;
  }

  .brand strong{
    display:block;
    font-size:17px;
    font-weight:800;
    color:#fff;
  }

  .brand span{
    font-size:14px;
    color:#8FA1B3;
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
    padding:10px 12px;
    border-radius:9px;
    font-size:13.5px;
    font-weight:500;
    color:#AAB9C8;
    border-left:2px solid transparent;
    transition:background .18s ease, color .18s ease, border-color .18s ease, transform .18s ease;
  }

  .nav-item svg{
    width:17px;
    height:17px;
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

  .database-status{
    display:flex;
    align-items:center;
    gap:10px;
    padding:10px 12px;
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
    font-size:12.5px;
    color:#fff;
    font-weight:600;
  }

  .database-status small{
    font-size:11px;
    color:#8FA1B3;
  }

  .theme-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:2px 12px;
  }

  .theme-row span{
    font-size:12.5px;
    color:#AAB9C8;
    font-weight:500;
  }

  .switch{
    position:relative;
    width:38px;
    height:21px;
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
    width:15px;
    height:15px;
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
    transform:translateX(17px);
  }

  .switch input:focus-visible + .switch-track{
    outline:2px solid var(--teal-500);
    outline-offset:2px;
  }

  /* ---------- main ---------- */

  .main-content{
    padding:32px 40px 48px;
    max-width:1180px;
  }

  .topbar{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:28px;
    gap:20px;
  }

  .topbar > div:first-child strong{
    display:block;
    font-size:22px;
    font-weight:800;
  }

  .topbar > div:first-child span{
    font-size:13.5px;
    color:var(--ink-600);
  }

  .topbar{

    display:flex;

    align-items:center;

    justify-content:space-between;

    margin-bottom:28px;

    gap:20px;

}

/* =========================================================
   TOPBAR RIGHT
   ========================================================= */

.topbar-heading {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.topbar-heading strong {
    display: block;

    font-size: 22px;
    font-weight: 800;
}

.topbar-heading span {
    font-size: 13.5px;
    color: var(--ink-600);
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-shrink: 0;
}


/* =========================================================
   LIVE DATE & TIME
   ========================================================= */

.live-datetime {
    display: flex;
    align-items: center;
    gap: 10px;

    padding-right: 16px;

    border-right: 1px solid var(--border);

    white-space: nowrap;
}

.live-time {
    display: flex;
    align-items: center;
    gap: 7px;

    color: var(--ink-900);

    font-size: 12px;
    font-weight: 750;
}

.live-dot {
    width: 7px;
    height: 7px;

    flex-shrink: 0;

    border-radius: 50%;

    background: var(--green-500);

    box-shadow:
        0 0 0 3px rgba(47,168,79,0.12);

    animation: livePulse 2s infinite;
}

.live-date {
    padding-left: 10px;

    border-left: 1px solid var(--border);

    color: var(--ink-400);

    font-size: 11px;
    font-weight: 600;
}

@keyframes livePulse {

    0%,
    100% {
        opacity: 1;
        transform: scale(1);
    }

    50% {
        opacity: 0.45;
        transform: scale(0.85);
    }
}

.topbar > div:first-child strong{

    display:block;

    font-size:22px;

    font-weight:800;

}

.topbar > div:first-child span{

    font-size:13.5px;

    color:var(--ink-600);

}


/* =========================================================
   CLEAN LIVE DATE & TIME
   ========================================================= */

.topbar-right{

    display:flex;

    align-items:center;

    gap:16px;

}

.live-datetime{

    display:flex;

    align-items:center;

    gap:10px;

    padding-right:16px;

    border-right:1px solid var(--border);

    white-space:nowrap;

}

.live-time{

    display:flex;

    align-items:center;

    gap:7px;

    color:var(--ink-900);

    font-size:12.5px;

    font-weight:700;

}

.live-dot{

    width:7px;

    height:7px;

    flex-shrink:0;

    border-radius:50%;

    background:var(--green-500);

    box-shadow:
        0 0 0 3px rgba(47,168,79,0.12);

    animation:livePulse 2s infinite;

}

.live-date{

    padding-left:10px;

    border-left:1px solid var(--border);

    color:var(--ink-400);

    font-size:11.5px;

    font-weight:600;

}

@keyframes livePulse{

    0%,
    100%{
        opacity:1;
        transform:scale(1);
    }

    50%{
        opacity:.45;
        transform:scale(.85);
    }

}

  .admin-profile{
    display:flex;
    align-items:center;
    gap:11px;
    padding:8px 14px 8px 8px;
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:12px;
    box-shadow:var(--shadow-card);
  }

  .admin-avatar{
    width:36px;
    height:36px;
    border-radius:9px;
    background:linear-gradient(135deg, var(--teal-500), var(--blue-500));
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:700;
    font-size:14px;
  }

  .admin-profile strong{
    display:block;
    font-size:13.5px;
    font-weight:700;
  }

  .admin-profile span{
    font-size:11.5px;
    color:var(--ink-400);
  }

  /* ---------- page header ---------- */

  .page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:20px;
    margin-bottom:26px;
  }

  .page-title h1{
    margin:0 0 6px;
    font-size:27px;
    font-weight:800;
  }

  .page-title p{
    margin:0;
    font-size:13.5px;
    color:var(--ink-600);
  }

  .add-user-button{
    border:none;
    background:var(--teal-500);
    color:#fff;
    padding:12px 20px;
    border-radius:10px;
    font-weight:700;
    font-size:13.5px;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    gap:8px;
    box-shadow:0 8px 18px -8px rgba(15,139,141,0.55);
    transition:background .18s ease, transform .18s ease, box-shadow .18s ease;
  }

  .add-user-button:hover{
    background:var(--teal-600);
    transform:translateY(-2px);
    box-shadow:0 10px 22px -8px rgba(15,139,141,0.68);
  }

  /* ---------- message banner ---------- */

  .message{
    padding:13px 18px;
    border-radius:10px;
    margin-bottom:20px;
    font-weight:600;
    font-size:13.5px;
    display:flex;
    align-items:center;
    gap:10px;
    border:1px solid transparent;
  }

  .message-success{
    background:var(--green-100);
    color:var(--green-500);
    border-color:rgba(47,168,79,0.25);
  }

  .message-error{
    background:var(--red-100);
    color:var(--red-500);
    border-color:rgba(224,82,74,0.25);
  }

  /* ---------- mini stats ---------- */

  .user-mini-stats{
    display:grid;
    grid-template-columns:repeat(4, 1fr);
    gap:16px;
    margin-bottom:26px;
  }

  .mini-card{
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:var(--radius);
    padding:18px 20px;
    box-shadow:var(--shadow-card);
    position:relative;
    overflow:hidden;
    transition:transform .18s ease, box-shadow .18s ease;
  }

  .mini-card::before{
    content:"";
    position:absolute;
    left:0; top:0; bottom:0;
    width:3px;
  }

  .mini-card:nth-child(1)::before{ background:var(--teal-500); }
  .mini-card:nth-child(2)::before{ background:var(--blue-500); }
  .mini-card:nth-child(3)::before{ background:var(--green-500); }
  .mini-card:nth-child(4)::before{ background:var(--amber-500); }

  .mini-card:hover{
    transform:translateY(-3px);
    box-shadow:0 4px 8px rgba(11,27,46,0.06), 0 16px 28px -14px rgba(11,27,46,0.18);
  }

  .mini-card span{
    display:block;
    color:var(--ink-400);
    font-size:12.5px;
    font-weight:600;
    margin-bottom:8px;
  }

  .mini-card strong{
    font-size:24px;
    font-weight:800;
  }

  /* ---------- toolbar ---------- */

  .users-toolbar{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:20px;
  }

  .users-toolbar input,
  .users-toolbar select,
  .user-form input,
  .user-form select{
    border:1px solid var(--border);
    background:var(--surface);
    color:var(--ink-900);
    border-radius:9px;
    padding:11px 13px;
    outline:none;
    font-family:inherit;
    font-size:13.5px;
    transition:border-color .16s ease, box-shadow .16s ease;
  }

  .users-toolbar input:focus,
  .users-toolbar select:focus,
  .user-form input:focus,
  .user-form select:focus{
    border-color:var(--teal-500);
    box-shadow:0 0 0 3px rgba(15,139,141,0.14);
  }

  .users-toolbar input{
    min-width:280px;
    flex:1;
  }

  .users-toolbar select{
    min-width:150px;
  }

  .toolbar-button{
    border:none;
    border-radius:9px;
    padding:11px 20px;
    background:var(--navy-950);
    color:#fff;
    font-weight:700;
    font-size:13.5px;
    cursor:pointer;
    transition:background .18s ease, transform .18s ease;
  }

  .toolbar-button:hover{
    background:var(--navy-750);
    transform:translateY(-1px);
  }

  .reset-button{
    display:inline-flex;
    align-items:center;
    padding:11px 18px;
    border-radius:9px;
    background:var(--surface);
    border:1px solid var(--border);
    color:var(--ink-600);
    font-weight:600;
    font-size:13.5px;
    transition:background .16s ease, color .16s ease;
  }

  .reset-button:hover{
    background:var(--canvas);
    color:var(--ink-900);
  }

  /* ---------- form ---------- */

  .user-form{
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:16px;
    padding:26px;
    margin-bottom:24px;
    box-shadow:var(--shadow-card);
  }

  .user-form h2{
    margin-top:0;
    margin-bottom:20px;
    font-size:18px;
    font-weight:800;
  }

  .form-grid{
    display:grid;
    grid-template-columns:repeat(2, 1fr);
    gap:16px;
  }

  .form-group{
    display:flex;
    flex-direction:column;
    gap:7px;
  }

  .form-group label{
    font-size:12.5px;
    font-weight:700;
    color:var(--ink-600);
  }

  .form-group input,
  .form-group select{
    width:100%;
  }

  .form-actions{
    margin-top:22px;
    display:flex;
    gap:10px;
  }

  .save-button{
    border:none;
    background:var(--teal-500);
    color:#fff;
    padding:11px 22px;
    border-radius:9px;
    font-weight:700;
    font-size:13.5px;
    cursor:pointer;
    transition:background .18s ease, transform .18s ease;
  }

  .save-button:hover{
    background:var(--teal-600);
    transform:translateY(-1px);
  }

  .cancel-button{
    text-decoration:none;
    padding:11px 22px;
    border-radius:9px;
    background:var(--canvas);
    border:1px solid var(--border);
    color:var(--ink-600);
    font-weight:700;
    font-size:13.5px;
    transition:background .16s ease;
  }

  .cancel-button:hover{
    background:var(--border);
  }

  /* ---------- table ---------- */

  .users-table-wrapper{
    overflow-x:auto;
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:16px;
    box-shadow:var(--shadow-card);
  }

  .users-table{
    width:100%;
    border-collapse:collapse;
    min-width:850px;
  }

  .users-table th{
    text-align:left;
    padding:15px 16px;
    font-size:12.5px;
    font-weight:700;
    color:var(--ink-400);
    background:var(--canvas);
    border-bottom:1px solid var(--border);
  }

  .users-table td{
    padding:15px 16px;
    border-bottom:1px solid var(--border);
    font-size:13.5px;
  }

  .users-table tbody tr{
    transition:background .15s ease;
  }

  .users-table tbody tr:hover{
    background:var(--canvas);
  }

  .users-table tr:last-child td{
    border-bottom:none;
  }

  .user-id{
    font-weight:800;
    color:var(--ink-400);
    font-family:"SF Mono", Menlo, Consolas, monospace;
    font-size:12.5px;
  }

  .role-badge{
    display:inline-flex;
    padding:6px 11px;
    border-radius:999px;
    font-size:11.5px;
    font-weight:700;
  }

  .role-traveler{ background:var(--blue-100); color:var(--blue-500); }
  .role-driver{ background:var(--green-100); color:var(--green-500); }
  .role-guide{ background:var(--amber-100); color:var(--amber-500); }
  .role-admin{ background:var(--purple-100); color:var(--purple-500); }

  .action-buttons{
    display:flex;
    gap:8px;
  }

  .action-button{
    padding:7px 12px;
    border-radius:7px;
    text-decoration:none;
    font-size:12px;
    font-weight:700;
    transition:background .15s ease, transform .15s ease;
  }

  .edit-button{
    background:var(--blue-100);
    color:var(--blue-500);
  }

  .edit-button:hover{
    background:var(--blue-500);
    color:#fff;
    transform:translateY(-1px);
  }

  .delete-button{
    background:var(--red-100);
    color:var(--red-500);
  }

  .delete-button:hover{
    background:var(--red-500);
    color:#fff;
    transform:translateY(-1px);
  }

  .empty-users{
    text-align:center;
    padding:48px 20px;
    color:var(--ink-400);
    font-size:13.5px;
  }

  @media (max-width: 980px){
    .app{ grid-template-columns:1fr; }
    .sidebar{ display:none; }
    .user-mini-stats{ grid-template-columns:repeat(2,1fr); }
    .form-grid{ grid-template-columns:1fr; }
    .page-header{ align-items:flex-start; flex-direction:column; }
  }

 @media (max-width: 760px){

    .topbar-right{
        gap:12px;
    }

    .live-datetime{
        padding-right:12px;
    }

}

@media (max-width: 620px){

    .topbar{
        align-items:flex-start;
    }

    .topbar-right{
        flex-direction:column;
        align-items:flex-end;
        gap:8px;
    }

    .live-datetime{
        border-right:none;
        padding-right:0;
    }

}

@media (max-width: 480px){

    .topbar-right{
        align-items:flex-start;
    }

    .live-datetime{
        align-items:flex-start;
    }

}
</style>
</head>

<body>

<div class="app">

  <!-- =========================
       SIDEBAR
  ========================= -->

  <aside class="sidebar">

    <div class="brand">
      <div class="brand-icon">P</div>
      <div>
        <strong>Pocket Tour</strong>
        <span>Travel Booking Management</span>
      </div>
    </div>

    <nav class="navigation">

      <a href="index.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-7 9 7"/><path d="M5 10v9a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-4a1 1 0 0 1 1-1h0a1 1 0 0 1 1 1v4a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-9"/></svg>
        Dashboard
      </a>

      <a href="users.php" class="nav-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Users
      </a>

      <a href="destinations.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 6-9 12-9 12s-9-6-9-12a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
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


  <!-- =========================
       MAIN CONTENT
  ========================= -->

  <main class="main-content">

    <!-- TOPBAR -->
  <header class="topbar">

    <div class="topbar-heading">

        <strong>
            User Management
        </strong>

        <span>
            Manage Pocket Tour users
        </span>

    </div>


    <div class="topbar-right">

        <!-- LIVE DATE & TIME -->

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


        <!-- ADMIN PROFILE -->

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
    <section class="page-header">

      <div class="page-title">
        <h1>Users</h1>
        <p>Manage travelers, drivers, guides and administrators.</p>
      </div>

      <a href="#user-form" class="add-user-button">+ Add New User</a>

    </section>


    <!-- MESSAGE -->
    <?php if ($message !== ""): ?>
      <div class="message message-<?php echo e($message_type); ?>">
        <?php echo e($message); ?>
      </div>
    <?php endif; ?>


    <!-- USER STATISTICS -->
    <section class="user-mini-stats">

      <div class="mini-card">
        <span>Total Users</span>
        <strong><?php echo $total_users; ?></strong>
      </div>

      <div class="mini-card">
        <span>Travelers</span>
        <strong><?php echo $role_counts["Traveler"]; ?></strong>
      </div>

      <div class="mini-card">
        <span>Drivers</span>
        <strong><?php echo $role_counts["Driver"]; ?></strong>
      </div>

      <div class="mini-card">
        <span>Guides</span>
        <strong><?php echo $role_counts["Guide"]; ?></strong>
      </div>

    </section>


    <!-- ADD / EDIT FORM -->
    <section class="user-form" id="user-form">

      <?php if ($edit_user): ?>

        <h2>Edit User</h2>

        <form method="POST">

          <input type="hidden" name="user_id" value="<?php echo e($edit_user["user_id"]); ?>">

          <div class="form-grid">

            <div class="form-group">
              <label>Full Name *</label>
              <input type="text" name="name" required value="<?php echo e($edit_user["name"]); ?>">
            </div>

            <div class="form-group">
              <label>Email *</label>
              <input type="email" name="email" required value="<?php echo e($edit_user["email"]); ?>">
            </div>

            <div class="form-group">
              <label>Role *</label>
              <select name="role" required>
                <option value="Traveler" <?php echo $edit_user["role"] === "Traveler" ? "selected" : ""; ?>>Traveler</option>
                <option value="Driver" <?php echo $edit_user["role"] === "Driver" ? "selected" : ""; ?>>Driver</option>
                <option value="Guide" <?php echo $edit_user["role"] === "Guide" ? "selected" : ""; ?>>Guide</option>
                <option value="Admin" <?php echo $edit_user["role"] === "Admin" ? "selected" : ""; ?>>Admin</option>
              </select>
            </div>

            <div class="form-group">
              <label>Phone</label>
              <input type="text" name="phone" value="<?php echo e($edit_user["phone"]); ?>">
            </div>

          </div>

          <div class="form-actions">
            <button type="submit" name="update_user" class="save-button">Update User</button>
            <a href="users.php" class="cancel-button">Cancel</a>
          </div>

        </form>

      <?php else: ?>

        <h2>Add New User</h2>

        <form method="POST">

          <div class="form-grid">

            <div class="form-group">
              <label>Full Name *</label>
              <input type="text" name="name" placeholder="Enter full name" required>
            </div>

            <div class="form-group">
              <label>Email *</label>
              <input type="email" name="email" placeholder="example@email.com" required>
            </div>

            <div class="form-group">
              <label>Role *</label>
              <select name="role" required>
                <option value="">Select Role</option>
                <option value="Traveler">Traveler</option>
                <option value="Driver">Driver</option>
                <option value="Guide">Guide</option>
                <option value="Admin">Admin</option>
              </select>
            </div>

            <div class="form-group">
              <label>Phone</label>
              <input type="text" name="phone" placeholder="01XXXXXXXXX">
            </div>

          </div>

          <div class="form-actions">
            <button type="submit" name="add_user" class="save-button">Add User</button>
          </div>

        </form>

      <?php endif; ?>

    </section>


    <!-- SEARCH / FILTER -->
    <form method="GET" class="users-toolbar">

      <input type="text" name="search" placeholder="Search by name, email or phone..." value="<?php echo e($search); ?>">

      <select name="role">
        <option value="">All Roles</option>
        <option value="Traveler" <?php echo $role_filter === "Traveler" ? "selected" : ""; ?>>Traveler</option>
        <option value="Driver" <?php echo $role_filter === "Driver" ? "selected" : ""; ?>>Driver</option>
        <option value="Guide" <?php echo $role_filter === "Guide" ? "selected" : ""; ?>>Guide</option>
        <option value="Admin" <?php echo $role_filter === "Admin" ? "selected" : ""; ?>>Admin</option>
      </select>

      <button type="submit" class="toolbar-button">Search</button>

      <a href="users.php" class="reset-button">Reset</a>

    </form>


    <!-- USERS TABLE -->
    <section class="users-table-wrapper">

      <table class="users-table">

        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Phone</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody>

        <?php if ($result->num_rows > 0): ?>

          <?php while ($row = $result->fetch_assoc()): ?>

            <tr>

              <td class="user-id">#<?php echo e($row["user_id"]); ?></td>

              <td><strong><?php echo e($row["name"]); ?></strong></td>

              <td><?php echo e($row["email"]); ?></td>

              <td>
                <?php $role_class = strtolower($row["role"]); ?>
                <span class="role-badge role-<?php echo e($role_class); ?>">
                  <?php echo e($row["role"]); ?>
                </span>
              </td>

              <td><?php echo e($row["phone"]); ?></td>

              <td>
                <div class="action-buttons">

                  <a href="users.php?edit=<?php echo $row["user_id"]; ?>#user-form" class="action-button edit-button">
                    Edit
                  </a>

                  <a href="users.php?delete=<?php echo $row["user_id"]; ?>" class="action-button delete-button"
                     onclick="return confirm('Are you sure you want to delete this user?');">
                    Delete
                  </a>

                </div>
              </td>

            </tr>

          <?php endwhile; ?>

        <?php else: ?>

          <tr>
            <td colspan="6" class="empty-users">No users found.</td>
          </tr>

        <?php endif; ?>

        </tbody>

      </table>

    </section>

  </main>

</div>

<script>
  const toggle = document.getElementById('themeToggle');
  const root = document.documentElement;
  const saved = localStorage.getItem('pocketTourTheme');

  if (saved === 'dark') {
    root.setAttribute('data-theme', 'dark');
    toggle.checked = true;
  }

  toggle.addEventListener('change', () => {
    if (toggle.checked) {
      root.setAttribute('data-theme', 'dark');
      localStorage.setItem('pocketTourTheme', 'dark');
    } else {
      root.removeAttribute('data-theme');
      localStorage.setItem('pocketTourTheme', 'light');
    }
  });
</script>


<script>

function updateDateTime(){

    const now = new Date();

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

    const time = now.toLocaleTimeString(
        "en-US",
        timeOptions
    );

    const date = now.toLocaleDateString(
        "en-US",
        dateOptions
    );

    const timeElement =
        document.getElementById("liveTime");

    const dateElement =
        document.getElementById("liveDate");

    if(timeElement){
        timeElement.textContent = time;
    }

    if(dateElement){
        dateElement.textContent = date;
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