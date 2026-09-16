<?php

require_once "../config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
   ADMIN AUTH
========================================================= */

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

/* =========================================================
   VARIABLES
========================================================= */

$success_message = "";
$error_message = "";

$allowed_statuses = [
    "Pending",
    "Confirmed",
    "Processing",
    "Shipped",
    "Delivered",
    "Cancelled"
];

/* =========================================================
   UPDATE ORDER STATUS
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {

    $order_id = (int)($_POST["order_id"] ?? 0);
    $new_status = trim($_POST["status"] ?? "");

    if ($order_id <= 0 || !in_array($new_status, $allowed_statuses, true)) {

        $error_message = "Invalid order information.";

    } else {

        try {

            $conn->begin_transaction();

            /* Get current status */

            $stmt = $conn->prepare("
                SELECT id, status
                FROM orders
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->bind_param("i", $order_id);
            $stmt->execute();

            $result = $stmt->get_result();
            $order = $result->fetch_assoc();

            $stmt->close();

            if (!$order) {
                throw new Exception("Order not found.");
            }

            $old_status = $order["status"];

            /* =================================================
               NON-CANCELLED -> CANCELLED
               Restore stock
            ================================================= */

            if (
                $old_status !== "Cancelled" &&
                $new_status === "Cancelled"
            ) {

                $stmt = $conn->prepare("
                    SELECT product_id, quantity
                    FROM order_items
                    WHERE order_id = ?
                ");

                $stmt->bind_param("i", $order_id);
                $stmt->execute();

                $items = $stmt->get_result();

                while ($item = $items->fetch_assoc()) {

                    $product_id = (int)$item["product_id"];
                    $quantity = (int)$item["quantity"];

                    $stock_stmt = $conn->prepare("
                        UPDATE products
                        SET stock = stock + ?
                        WHERE id = ?
                    ");

                    $stock_stmt->bind_param(
                        "ii",
                        $quantity,
                        $product_id
                    );

                    $stock_stmt->execute();
                    $stock_stmt->close();
                }

                $stmt->close();
            }

            /* =================================================
               CANCELLED -> NON-CANCELLED
               Deduct stock again
            ================================================= */

            if (
                $old_status === "Cancelled" &&
                $new_status !== "Cancelled"
            ) {

                $stmt = $conn->prepare("
                    SELECT product_id, quantity
                    FROM order_items
                    WHERE order_id = ?
                ");

                $stmt->bind_param("i", $order_id);
                $stmt->execute();

                $items = $stmt->get_result();

                while ($item = $items->fetch_assoc()) {

                    $product_id = (int)$item["product_id"];
                    $quantity = (int)$item["quantity"];

                    $stock_stmt = $conn->prepare("
                        SELECT stock
                        FROM products
                        WHERE id = ?
                        LIMIT 1
                    ");

                    $stock_stmt->bind_param(
                        "i",
                        $product_id
                    );

                    $stock_stmt->execute();

                    $stock_result = $stock_stmt->get_result();
                    $product = $stock_result->fetch_assoc();

                    $stock_stmt->close();

                    if (!$product) {
                        throw new Exception(
                            "Product no longer exists."
                        );
                    }

                    if ((int)$product["stock"] < $quantity) {
                        throw new Exception(
                            "Not enough stock available to reopen this order."
                        );
                    }

                    $update_stock = $conn->prepare("
                        UPDATE products
                        SET stock = stock - ?
                        WHERE id = ?
                    ");

                    $update_stock->bind_param(
                        "ii",
                        $quantity,
                        $product_id
                    );

                    $update_stock->execute();
                    $update_stock->close();
                }

                $stmt->close();
            }

            /* Update status */

            $stmt = $conn->prepare("
                UPDATE orders
                SET status = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "si",
                $new_status,
                $order_id
            );

            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $success_message =
                "Order #" . $order_id . " status updated successfully.";

        } catch (Exception $e) {

            $conn->rollback();

            $error_message = $e->getMessage();
        }
    }
}

/* =========================================================
   SEARCH / FILTER
========================================================= */

$search = trim($_GET["search"] ?? "");
$status_filter = trim($_GET["status"] ?? "");

/* =========================================================
   STATS
========================================================= */

$total_orders = 0;
$pending_orders = 0;
$processing_orders = 0;
$delivered_orders = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM orders
");

if ($result) {
    $total_orders = (int)$result->fetch_assoc()["total"];
}

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM orders
    WHERE status = 'Pending'
");

if ($result) {
    $pending_orders = (int)$result->fetch_assoc()["total"];
}

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM orders
    WHERE status = 'Processing'
");

if ($result) {
    $processing_orders = (int)$result->fetch_assoc()["total"];
}

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM orders
    WHERE status = 'Delivered'
");

if ($result) {
    $delivered_orders = (int)$result->fetch_assoc()["total"];
}

/* =========================================================
   FETCH ORDERS
========================================================= */

$orders = [];

$sql = "
    SELECT
        id,
        customer_name,
        email,
        phone,
        address,
        city,
        postal_code,
        subtotal,
        shipping,
        total,
        payment_method,
        status,
        created_at
    FROM orders
    WHERE 1 = 1
";

$params = [];
$types = "";

if ($search !== "") {

    $sql .= "
        AND (
            CAST(id AS CHAR) LIKE ?
            OR customer_name LIKE ?
            OR email LIKE ?
            OR phone LIKE ?
        )
    ";

    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "ssss";
}

if (
    $status_filter !== "" &&
    in_array($status_filter, $allowed_statuses, true)
) {

    $sql .= " AND status = ? ";

    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param(
        $types,
        ...$params
    );
}

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$stmt->close();

/* =========================================================
   FETCH ORDER ITEMS
========================================================= */

foreach ($orders as &$order) {

    $order["items"] = [];

    $stmt = $conn->prepare("
        SELECT
            id,
            product_id,
            product_name,
            price,
            quantity,
            subtotal
        FROM order_items
        WHERE order_id = ?
        ORDER BY id ASC
    ");

    $order_id = (int)$order["id"];

    $stmt->bind_param(
        "i",
        $order_id
    );

    $stmt->execute();

    $items_result = $stmt->get_result();

    while ($item = $items_result->fetch_assoc()) {
        $order["items"][] = $item;
    }

    $stmt->close();
}

unset($order);

/* =========================================================
   STATUS CLASS
========================================================= */

function statusClass($status)
{
    switch ($status) {

        case "Pending":
            return "status-pending";

        case "Confirmed":
            return "status-confirmed";

        case "Processing":
            return "status-processing";

        case "Shipped":
            return "status-shipped";

        case "Delivered":
            return "status-delivered";

        case "Cancelled":
            return "status-cancelled";

        default:
            return "";
    }
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

<title>Orders - SKZ Engineering</title>

<style>

/* =========================================================
   RESET
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f7fb;
    color: #1f2937;
}

button,
input,
select {
    font-family: inherit;
}

a {
    text-decoration: none;
}

/* =========================================================
   LAYOUT
========================================================= */

.admin-layout {
    min-height: 100vh;
    display: flex;
}

/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {
    width: 250px;
    min-height: 100vh;
    background: #111827;
    color: #fff;
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    z-index: 100;
}

.sidebar-logo {
    height: 75px;
    display: flex;
    align-items: center;
    padding: 0 22px;
    border-bottom: 1px solid rgba(255,255,255,.08);
}

.sidebar-logo h2 {
    font-size: 19px;
}

.sidebar-logo span {
    display: block;
    margin-top: 4px;
    font-size: 11px;
    color: #9ca3af;
}

.sidebar-menu {
    padding: 20px 12px;
}

.sidebar-title {
    padding: 0 12px 10px;
    font-size: 10px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.sidebar-link {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 12px 14px;
    margin-bottom: 5px;
    border-radius: 8px;
    color: #d1d5db;
    font-size: 14px;
}

.sidebar-link:hover {
    background: #1f2937;
    color: #fff;
}

.sidebar-link.active {
    background: #2563eb;
    color: #fff;
}

.sidebar-icon {
    width: 22px;
    text-align: center;
}

/* =========================================================
   MAIN
========================================================= */

.main-area {
    width: calc(100% - 250px);
    margin-left: 250px;
    min-height: 100vh;
}

/* =========================================================
   TOPBAR
========================================================= */

.topbar {
    height: 75px;
    background: #fff;
    border-bottom: 1px solid #e5e7eb;
    padding: 0 30px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.topbar h1 {
    font-size: 22px;
    color: #111827;
}

.topbar p {
    margin-top: 4px;
    font-size: 13px;
    color: #6b7280;
}

.admin-user {
    display: flex;
    align-items: center;
    gap: 10px;
}

.admin-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #2563eb;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.admin-user strong {
    display: block;
    font-size: 13px;
}

.admin-user span {
    display: block;
    margin-top: 2px;
    font-size: 11px;
    color: #6b7280;
}

/* =========================================================
   CONTENT
========================================================= */

.page-content {
    padding: 30px;
}

/* =========================================================
   ALERT
========================================================= */

.alert {
    padding: 13px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
}

.alert-success {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

/* =========================================================
   STATS
========================================================= */

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.stat-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 20px;
}

.stat-title {
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 10px;
}

.stat-number {
    font-size: 28px;
    font-weight: 700;
    color: #111827;
}

/* =========================================================
   FILTER
========================================================= */

.filter-box {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 18px;
    margin-bottom: 20px;
}

.filter-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.search-input {
    flex: 1;
    min-width: 250px;
    height: 42px;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    padding: 0 13px;
    outline: none;
}

.search-input:focus {
    border-color: #2563eb;
}

.status-filter {
    min-width: 170px;
    height: 42px;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    padding: 0 10px;
    background: #fff;
}

.filter-btn {
    height: 42px;
    padding: 0 20px;
    border: 0;
    border-radius: 7px;
    background: #2563eb;
    color: #fff;
    cursor: pointer;
    font-weight: 600;
}

.reset-btn {
    height: 42px;
    padding: 0 20px;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    display: flex;
    align-items: center;
    color: #374151;
    background: #fff;
}

/* =========================================================
   TABLE
========================================================= */

.orders-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
}

.orders-heading {
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.orders-heading h2 {
    font-size: 17px;
}

.orders-heading span {
    font-size: 13px;
    color: #6b7280;
}

.table-wrapper {
    overflow-x: auto;
}

.orders-table {
    width: 100%;
    min-width: 1150px;
    border-collapse: collapse;
}

.orders-table th {
    background: #f9fafb;
    padding: 13px 15px;
    text-align: left;
    font-size: 11px;
    text-transform: uppercase;
    color: #6b7280;
    border-bottom: 1px solid #e5e7eb;
    white-space: nowrap;
}

.orders-table td {
    padding: 15px;
    border-bottom: 1px solid #f0f1f3;
    font-size: 13px;
    vertical-align: middle;
}

.order-number {
    font-weight: 700;
    color: #2563eb;
}

.customer-name {
    font-weight: 600;
    color: #111827;
}

.small-text {
    margin-top: 4px;
    color: #6b7280;
    font-size: 12px;
}

.order-total {
    font-weight: 700;
}

.order-date {
    color: #6b7280;
    white-space: nowrap;
}

/* =========================================================
   STATUS
========================================================= */

.status-badge {
    display: inline-flex;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-confirmed {
    background: #dbeafe;
    color: #1d4ed8;
}

.status-processing {
    background: #ede9fe;
    color: #6d28d9;
}

.status-shipped {
    background: #e0f2fe;
    color: #0369a1;
}

.status-delivered {
    background: #dcfce7;
    color: #166534;
}

.status-cancelled {
    background: #fee2e2;
    color: #b91c1c;
}

/* =========================================================
   VIEW BUTTON
========================================================= */

.view-btn {
    border: 0;
    background: #2563eb;
    color: #fff;
    padding: 8px 14px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
}

.view-btn:hover {
    background: #1d4ed8;
}

/* =========================================================
   UPDATE STATUS
========================================================= */

.update-form {
    display: flex;
    gap: 5px;
    margin-top: 7px;
}

.update-form select {
    height: 32px;
    border: 1px solid #d1d5db;
    border-radius: 5px;
    font-size: 11px;
    padding: 0 5px;
}

.update-form button {
    height: 32px;
    border: 0;
    border-radius: 5px;
    background: #111827;
    color: #fff;
    padding: 0 8px;
    font-size: 10px;
    cursor: pointer;
}

/* =========================================================
   EMPTY
========================================================= */

.empty-orders {
    padding: 60px 20px;
    text-align: center;
}

.empty-orders h3 {
    margin-bottom: 7px;
}

.empty-orders p {
    color: #6b7280;
    font-size: 13px;
}

/* =========================================================
   MODAL
========================================================= */

.order-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, .65);
    z-index: 9999;
    padding: 30px;
    overflow-y: auto;
}

.order-modal.show {
    display: flex;
    align-items: flex-start;
    justify-content: center;
}

.modal-box {
    width: 100%;
    max-width: 950px;
    margin: 20px auto;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
}

.modal-header {
    padding: 20px 24px;
    background: #111827;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-header h2 {
    font-size: 18px;
}

.modal-close {
    width: 35px;
    height: 35px;
    border: 0;
    border-radius: 6px;
    background: rgba(255,255,255,.1);
    color: #fff;
    font-size: 20px;
    cursor: pointer;
}

.modal-body {
    padding: 24px;
}

/* =========================================================
   MODAL CUSTOMER DETAILS
========================================================= */

.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
    margin-bottom: 25px;
}

.detail-box {
    border: 1px solid #e5e7eb;
    border-radius: 9px;
    padding: 17px;
}

.detail-box h3 {
    font-size: 14px;
    margin-bottom: 13px;
    color: #111827;
}

.detail-row {
    margin-bottom: 9px;
    font-size: 13px;
    line-height: 1.5;
}

.detail-label {
    color: #6b7280;
    display: inline-block;
    min-width: 90px;
}

.address-text {
    color: #374151;
    white-space: pre-line;
}

/* =========================================================
   PRODUCTS IN MODAL
========================================================= */

.products-section {
    margin-bottom: 25px;
}

.products-section h3 {
    font-size: 15px;
    margin-bottom: 12px;
}

.items-table {
    width: 100%;
    border-collapse: collapse;
}

.items-table th {
    background: #f9fafb;
    text-align: left;
    padding: 11px;
    font-size: 11px;
    color: #6b7280;
    border: 1px solid #e5e7eb;
}

.items-table td {
    padding: 11px;
    font-size: 13px;
    border: 1px solid #e5e7eb;
}

.item-name {
    font-weight: 600;
}

/* =========================================================
   SUMMARY
========================================================= */

.summary-box {
    width: 330px;
    margin-left: auto;
    border: 1px solid #e5e7eb;
    border-radius: 9px;
    padding: 16px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 7px 0;
    font-size: 13px;
}

.summary-total {
    margin-top: 8px;
    padding-top: 12px;
    border-top: 1px solid #e5e7eb;
    font-size: 17px;
    font-weight: 700;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1100px) {

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 750px) {

    .sidebar {
        width: 210px;
    }

    .main-area {
        width: calc(100% - 210px);
        margin-left: 210px;
    }

    .page-content {
        padding: 20px;
    }

    .detail-grid {
        grid-template-columns: 1fr;
    }

    .summary-box {
        width: 100%;
    }
}

@media (max-width: 600px) {

    .admin-layout {
        display: block;
    }

    .sidebar {
        position: static;
        width: 100%;
        min-height: auto;
    }

    .main-area {
        width: 100%;
        margin-left: 0;
    }

    .topbar {
        padding: 0 15px;
    }

    .admin-user span,
    .admin-user strong {
        display: none;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .page-content {
        padding: 15px;
    }

    .order-modal {
        padding: 10px;
    }

    .modal-body {
        padding: 15px;
    }
}

</style>

</head>

<body>

<div class="admin-layout">

<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">

    <div class="sidebar-logo">

        <div>

            <h2>SKZ Engineering</h2>

            <span>
                Admin Panel
            </span>

        </div>

    </div>

    <nav class="sidebar-menu">

        <div class="sidebar-title">
            Main Menu
        </div>

        <a
            href="dashboard.php"
            class="sidebar-link"
        >
            <span class="sidebar-icon">⌂</span>
            Dashboard
        </a>

        <a
            href="products.php"
            class="sidebar-link"
        >
            <span class="sidebar-icon">▣</span>
            Products
        </a>

        <a
            href="orders.php"
            class="sidebar-link active"
        >
            <span class="sidebar-icon">▤</span>
            Orders
        </a>

        <a
            href="../user/index.php"
            target="_blank"
            class="sidebar-link"
        >
            <span class="sidebar-icon">↗</span>
            View Website
        </a>

        <a
            href="logout.php"
            class="sidebar-link"
        >
            <span class="sidebar-icon">⇥</span>
            Logout
        </a>

    </nav>

</aside>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main-area">


<header class="topbar">

    <div>

        <h1>
            Orders
        </h1>

        <p>
            Manage customer orders
        </p>

    </div>


    <div class="admin-user">

        <div class="admin-avatar">

            <?php

            echo strtoupper(
                substr(
                    $_SESSION["admin_name"] ?? "A",
                    0,
                    1
                )
            );

            ?>

        </div>

        <div>

            <strong>
                <?php
                echo htmlspecialchars(
                    $_SESSION["admin_name"] ?? "Admin"
                );
                ?>
            </strong>

            <span>
                Administrator
            </span>

        </div>

    </div>

</header>


<section class="page-content">


<!-- ALERTS -->

<?php if ($success_message): ?>

    <div class="alert alert-success">
        <?php
        echo htmlspecialchars($success_message);
        ?>
    </div>

<?php endif; ?>


<?php if ($error_message): ?>

    <div class="alert alert-error">
        <?php
        echo htmlspecialchars($error_message);
        ?>
    </div>

<?php endif; ?>


<!-- STATS -->

<div class="stats-grid">

    <div class="stat-card">

        <div class="stat-title">
            Total Orders
        </div>

        <div class="stat-number">
            <?php echo $total_orders; ?>
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-title">
            Pending
        </div>

        <div class="stat-number">
            <?php echo $pending_orders; ?>
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-title">
            Processing
        </div>

        <div class="stat-number">
            <?php echo $processing_orders; ?>
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-title">
            Delivered
        </div>

        <div class="stat-number">
            <?php echo $delivered_orders; ?>
        </div>

    </div>

</div>


<!-- FILTER -->

<div class="filter-box">

    <form
        method="GET"
        action="orders.php"
        class="filter-form"
    >

        <input
            type="text"
            name="search"
            class="search-input"
            placeholder="Search order ID, customer, email or phone..."
            value="<?php echo htmlspecialchars($search); ?>"
        >

        <select
            name="status"
            class="status-filter"
        >

            <option value="">
                All Statuses
            </option>

            <?php foreach ($allowed_statuses as $status): ?>

                <option
                    value="<?php echo htmlspecialchars($status); ?>"
                    <?php
                    echo $status_filter === $status
                        ? "selected"
                        : "";
                    ?>
                >
                    <?php echo htmlspecialchars($status); ?>
                </option>

            <?php endforeach; ?>

        </select>

        <button
            type="submit"
            class="filter-btn"
        >
            Search
        </button>

        <a
            href="orders.php"
            class="reset-btn"
        >
            Reset
        </a>

    </form>

</div>


<!-- ORDERS -->

<div class="orders-card">

    <div class="orders-heading">

        <h2>
            All Orders
        </h2>

        <span>
            <?php echo count($orders); ?> order(s)
        </span>

    </div>


    <?php if (empty($orders)): ?>

        <div class="empty-orders">

            <h3>
                No Orders Found
            </h3>

            <p>
                No orders are available.
            </p>

        </div>

    <?php else: ?>


    <div class="table-wrapper">

        <table class="orders-table">

            <thead>

                <tr>

                    <th>
                        Order
                    </th>

                    <th>
                        Customer
                    </th>

                    <th>
                        Contact
                    </th>

                    <th>
                        Total
                    </th>

                    <th>
                        Payment
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Date
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php foreach ($orders as $order): ?>

                <tr>

                    <!-- ORDER ID -->

                    <td>

                        <div class="order-number">

                            #<?php
                            echo (int)$order["id"];
                            ?>

                        </div>

                    </td>


                    <!-- CUSTOMER -->

                    <td>

                        <div class="customer-name">

                            <?php
                            echo htmlspecialchars(
                                $order["customer_name"]
                            );
                            ?>

                        </div>

                        <div class="small-text">

                            <?php
                            echo htmlspecialchars(
                                $order["city"]
                            );
                            ?>

                        </div>

                    </td>


                    <!-- CONTACT -->

                    <td>

                        <div class="customer-name">

                            <?php
                            echo htmlspecialchars(
                                $order["phone"]
                            );
                            ?>

                        </div>

                        <div class="small-text">

                            <?php
                            echo htmlspecialchars(
                                $order["email"]
                            );
                            ?>

                        </div>

                    </td>


                    <!-- TOTAL -->

                    <td>

                        <div class="order-total">

                            Rs.
                            <?php
                            echo number_format(
                                (float)$order["total"],
                                2
                            );
                            ?>

                        </div>

                    </td>


                    <!-- PAYMENT -->

                    <td>

                        <?php
                        echo htmlspecialchars(
                            $order["payment_method"]
                        );
                        ?>

                    </td>


                    <!-- STATUS -->

                    <td>

                        <span
                            class="status-badge <?php echo statusClass($order["status"]); ?>"
                        >

                            <?php
                            echo htmlspecialchars(
                                $order["status"]
                            );
                            ?>

                        </span>


                        <!-- STATUS UPDATE -->

                        <form
                            method="POST"
                            action="orders.php"
                            class="update-form"
                        >

                            <input
                                type="hidden"
                                name="order_id"
                                value="<?php echo (int)$order["id"]; ?>"
                            >

                            <select name="status">

                                <?php foreach ($allowed_statuses as $status): ?>

                                    <option
                                        value="<?php echo htmlspecialchars($status); ?>"
                                        <?php
                                        echo $order["status"] === $status
                                            ? "selected"
                                            : "";
                                        ?>
                                    >
                                        <?php echo htmlspecialchars($status); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <button
                                type="submit"
                                name="update_status"
                                value="1"
                            >
                                Update
                            </button>

                        </form>

                    </td>


                    <!-- DATE -->

                    <td>

                        <div class="order-date">

                            <?php
                            echo date(
                                "d M Y",
                                strtotime(
                                    $order["created_at"]
                                )
                            );
                            ?>

                            <br>

                            <?php
                            echo date(
                                "h:i A",
                                strtotime(
                                    $order["created_at"]
                                )
                            );
                            ?>

                        </div>

                    </td>


                    <!-- VIEW -->

                    <td>

                        <button
                            type="button"
                            class="view-btn"
                            onclick="openOrder(<?php echo (int)$order['id']; ?>)"
                        >
                            View Order
                        </button>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

    <?php endif; ?>

</div>

</section>

</main>

</div>


<!-- =========================================================
     ORDER DETAIL MODAL
========================================================= -->

<div
    class="order-modal"
    id="orderModal"
    onclick="closeOutside(event)"
>

    <div
        class="modal-box"
        onclick="event.stopPropagation()"
    >

        <div class="modal-header">

            <h2 id="modalTitle">
                Order Details
            </h2>

            <button
                type="button"
                class="modal-close"
                onclick="closeOrder()"
            >
                ×
            </button>

        </div>


        <div class="modal-body">


            <!-- CUSTOMER + ADDRESS -->

            <div class="detail-grid">


                <div class="detail-box">

                    <h3>
                        Customer Information
                    </h3>

                    <div class="detail-row">

                        <span class="detail-label">
                            Name:
                        </span>

                        <span id="customerName">
                        </span>

                    </div>


                    <div class="detail-row">

                        <span class="detail-label">
                            Email:
                        </span>

                        <span id="customerEmail">
                        </span>

                    </div>


                    <div class="detail-row">

                        <span class="detail-label">
                            Phone:
                        </span>

                        <span id="customerPhone">
                        </span>

                    </div>

                </div>


                <div class="detail-box">

                    <h3>
                        Delivery Address
                    </h3>

                    <div class="detail-row address-text">

                        <span id="customerAddress">
                        </span>

                        <br>

                        <span id="customerCity">
                        </span>

                        <span id="customerPostal">
                        </span>

                    </div>

                </div>

            </div>


            <!-- PRODUCTS -->

            <div class="products-section">

                <h3>
                    Ordered Products
                </h3>


                <table class="items-table">

                    <thead>

                        <tr>

                            <th>
                                Product
                            </th>

                            <th>
                                Price
                            </th>

                            <th>
                                Quantity
                            </th>

                            <th>
                                Subtotal
                            </th>

                        </tr>

                    </thead>

                    <tbody id="itemsBody">
                    </tbody>

                </table>

            </div>


            <!-- ORDER INFO -->

            <div class="detail-grid">

                <div class="detail-box">

                    <h3>
                        Order Information
                    </h3>

                    <div class="detail-row">

                        <span class="detail-label">
                            Payment:
                        </span>

                        <span id="paymentMethod">
                        </span>

                    </div>

                    <div class="detail-row">

                        <span class="detail-label">
                            Status:
                        </span>

                        <span id="orderStatus">
                        </span>

                    </div>

                    <div class="detail-row">

                        <span class="detail-label">
                            Date:
                        </span>

                        <span id="orderDate">
                        </span>

                    </div>

                </div>


                <!-- SUMMARY -->

                <div class="summary-box">

                    <div class="summary-row">

                        <span>
                            Subtotal
                        </span>

                        <strong id="orderSubtotal">
                        </strong>

                    </div>


                    <div class="summary-row">

                        <span>
                            Shipping
                        </span>

                        <strong id="orderShipping">
                        </strong>

                    </div>


                    <div class="summary-row summary-total">

                        <span>
                            Total
                        </span>

                        <strong id="orderTotal">
                        </strong>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<script>

/* =========================================================
   ORDER DATA
========================================================= */

const orders = <?php echo json_encode(
    $orders,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES |
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
); ?>;


/* =========================================================
   OPEN ORDER
========================================================= */

function openOrder(orderId) {

    const order = orders.find(
        function(item) {
            return parseInt(item.id) === parseInt(orderId);
        }
    );

    if (!order) {
        alert("Order details not found.");
        return;
    }


    document.getElementById("modalTitle").textContent =
        "Order #" + order.id;


    /* Customer */

    document.getElementById("customerName").textContent =
        order.customer_name || "-";

    document.getElementById("customerEmail").textContent =
        order.email || "-";

    document.getElementById("customerPhone").textContent =
        order.phone || "-";


    /* Address */

    document.getElementById("customerAddress").textContent =
        order.address || "-";

    document.getElementById("customerCity").textContent =
        order.city || "-";

    document.getElementById("customerPostal").textContent =
        order.postal_code
            ? " - " + order.postal_code
            : "";


    /* Payment */

    document.getElementById("paymentMethod").textContent =
        order.payment_method || "-";


    /* Status */

    document.getElementById("orderStatus").textContent =
        order.status || "-";


    /* Date */

    const date = new Date(
        order.created_at.replace(" ", "T")
    );

    if (!isNaN(date.getTime())) {

        document.getElementById("orderDate").textContent =
            date.toLocaleString();

    } else {

        document.getElementById("orderDate").textContent =
            order.created_at || "-";
    }


    /* Products */

    const itemsBody =
        document.getElementById("itemsBody");

    itemsBody.innerHTML = "";


    if (
        !order.items ||
        order.items.length === 0
    ) {

        itemsBody.innerHTML = `
            <tr>
                <td colspan="4">
                    No products found for this order.
                </td>
            </tr>
        `;

    } else {

        order.items.forEach(
            function(item) {

                const row =
                    document.createElement("tr");

                row.innerHTML = `

                    <td>
                        <div class="item-name">
                            ${escapeHtml(item.product_name)}
                        </div>
                    </td>

                    <td>
                        Rs. ${formatMoney(item.price)}
                    </td>

                    <td>
                        ${item.quantity}
                    </td>

                    <td>
                        <strong>
                            Rs. ${formatMoney(item.subtotal)}
                        </strong>
                    </td>

                `;

                itemsBody.appendChild(row);
            }
        );
    }


    /* Summary */

    document.getElementById("orderSubtotal").textContent =
        "Rs. " + formatMoney(order.subtotal);

    document.getElementById("orderShipping").textContent =
        "Rs. " + formatMoney(order.shipping);

    document.getElementById("orderTotal").textContent =
        "Rs. " + formatMoney(order.total);


    /* Show */

    document
        .getElementById("orderModal")
        .classList
        .add("show");


    document.body.style.overflow = "hidden";
}


/* =========================================================
   CLOSE ORDER
========================================================= */

function closeOrder() {

    document
        .getElementById("orderModal")
        .classList
        .remove("show");

    document.body.style.overflow = "";
}


function closeOutside(event) {

    if (
        event.target.id === "orderModal"
    ) {
        closeOrder();
    }
}


/* =========================================================
   ESCAPE
========================================================= */

document.addEventListener(
    "keydown",
    function(event) {

        if (event.key === "Escape") {
            closeOrder();
        }

    }
);


/* =========================================================
   MONEY
========================================================= */

function formatMoney(value) {

    const number =
        parseFloat(value || 0);

    return number.toLocaleString(
        "en-PK",
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }
    );
}


/* =========================================================
   HTML ESCAPE
========================================================= */

function escapeHtml(value) {

    const div =
        document.createElement("div");

    div.textContent =
        value ?? "";

    return div.innerHTML;
}

</script>

</body>

</html>