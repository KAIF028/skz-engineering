<?php

require_once "../config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Admin Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Admin Information
|--------------------------------------------------------------------------
*/

$admin_name  = $_SESSION['admin_name'] ?? "Admin";
$admin_email = $_SESSION['admin_email'] ?? "";


/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

/* Total Products */

$total_products = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM products
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_products = (int) $row['total'];
}


/* Active Products */

$active_products = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM products
    WHERE status = 'Active'
");

if ($result) {
    $row = $result->fetch_assoc();
    $active_products = (int) $row['total'];
}


/* Total Orders */

$total_orders = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM orders
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_orders = (int) $row['total'];
}


/* Pending Orders */

$pending_orders = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM orders
    WHERE status = 'Pending'
");

if ($result) {
    $row = $result->fetch_assoc();
    $pending_orders = (int) $row['total'];
}


/* Total Revenue */

$total_revenue = 0;

$result = $conn->query("
    SELECT COALESCE(SUM(total), 0) AS revenue
    FROM orders
    WHERE status != 'Cancelled'
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_revenue = (float) $row['revenue'];
}


/* New Contact Messages */

$new_messages = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM contacts
    WHERE status = 'New'
");

if ($result) {
    $row = $result->fetch_assoc();
    $new_messages = (int) $row['total'];
}


/* Low Stock Products */

$low_stock_products = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM products
    WHERE stock <= 5
      AND status = 'Active'
");

if ($result) {
    $row = $result->fetch_assoc();
    $low_stock_products = (int) $row['total'];
}


/*
|--------------------------------------------------------------------------
| Recent Orders
|--------------------------------------------------------------------------
*/

$recent_orders = [];

$result = $conn->query("
    SELECT
        id,
        customer_name,
        email,
        total,
        payment_method,
        status,
        created_at
    FROM orders
    ORDER BY id DESC
    LIMIT 8
");

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $recent_orders[] = $row;

    }
}


/*
|--------------------------------------------------------------------------
| Recent Products
|--------------------------------------------------------------------------
*/

$recent_products = [];

$result = $conn->query("
    SELECT
        id,
        product_name,
        price,
        stock,
        image,
        status,
        created_at
    FROM products
    ORDER BY id DESC
    LIMIT 6
");

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $recent_products[] = $row;

    }
}


/*
|--------------------------------------------------------------------------
| Helper Function
|--------------------------------------------------------------------------
*/

function dashboard_product_image($image)
{
    if (!empty($image)) {

        $path = "../assets/images/products/" . $image;

        if (file_exists($path)) {
            return $path;
        }
    }

    return "../assets/images/product-placeholder.jpg";
}


/*
|--------------------------------------------------------------------------
| Order Status Class
|--------------------------------------------------------------------------
*/

function order_status_class($status)
{
    switch ($status) {

        case 'Confirmed':
            return 'status-confirmed';

        case 'Processing':
            return 'status-processing';

        case 'Shipped':
            return 'status-shipped';

        case 'Delivered':
            return 'status-delivered';

        case 'Cancelled':
            return 'status-cancelled';

        default:
            return 'status-pending';
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

    <title>
        Dashboard - SKZ Engineering Admin
    </title>

    <link
        rel="stylesheet"
        href="admin.css"
    >

</head>


<body class="admin-dashboard-page">


<div class="admin-layout">


    <!-- =====================================================
         SIDEBAR
         ===================================================== -->

    <aside
        class="admin-sidebar"
        id="adminSidebar"
    >

        <div class="admin-sidebar-header">

            <div class="admin-logo">

                SKZ

                <span>
                    ENGINEERING
                </span>

            </div>


            <button
                type="button"
                class="sidebar-close"
                id="sidebarClose"
            >
                &times;
            </button>

        </div>


        <nav class="admin-nav">


            <a
                href="dashboard.php"
                class="admin-nav-link active"
            >

                <span class="nav-icon">
                    ▣
                </span>

                <span>
                    Dashboard
                </span>

            </a>


            <a
                href="products.php"
                class="admin-nav-link"
            >

                <span class="nav-icon">
                    ▤
                </span>

                <span>
                    Products
                </span>

            </a>


            <a
                href="orders.php"
                class="admin-nav-link"
            >

                <span class="nav-icon">
                    ▥
                </span>

                <span>
                    Orders
                </span>

            </a>


            <a
                href="../user/index.php"
                class="admin-nav-link"
            >

                <span class="nav-icon">
                    ⌂
                </span>

                <span>
                    View Website
                </span>

            </a>


            <a
                href="logout.php"
                class="admin-nav-link logout-link"
            >

                <span class="nav-icon">
                    ↪
                </span>

                <span>
                    Logout
                </span>

            </a>

        </nav>

    </aside>


    <!-- =====================================================
         SIDEBAR OVERLAY
         ===================================================== -->

    <div
        class="admin-sidebar-overlay"
        id="sidebarOverlay"
    ></div>


    <!-- =====================================================
         MAIN CONTENT
         ===================================================== -->

    <main class="admin-main">


        <!-- =================================================
             TOPBAR
             ================================================= -->

        <header class="admin-topbar">


            <button
                type="button"
                class="sidebar-toggle"
                id="sidebarToggle"
            >
                ☰
            </button>


            <div class="admin-topbar-title">

                <h1>
                    Dashboard
                </h1>

                <p>
                    Welcome back,
                    <?php
                    echo htmlspecialchars($admin_name);
                    ?>
                </p>

            </div>


            <div class="admin-topbar-user">

                <div class="admin-user-avatar">

                    <?php

                    echo strtoupper(
                        substr(
                            $admin_name,
                            0,
                            1
                        )
                    );

                    ?>

                </div>


                <div class="admin-user-info">

                    <strong>

                        <?php

                        echo htmlspecialchars(
                            $admin_name
                        );

                        ?>

                    </strong>

                    <span>
                        Administrator
                    </span>

                </div>

            </div>

        </header>


        <!-- =================================================
             DASHBOARD CONTENT
             ================================================= -->

        <section class="admin-content">


            <!-- =================================================
                 STAT CARDS
                 ================================================= -->

            <div class="admin-stats-grid">


                <!-- Products -->

                <div class="admin-stat-card">

                    <div class="admin-stat-icon">
                        📦
                    </div>

                    <div class="admin-stat-content">

                        <span>
                            Total Products
                        </span>

                        <strong>
                            <?php
                            echo number_format(
                                $total_products
                            );
                            ?>
                        </strong>

                        <small>
                            <?php
                            echo number_format(
                                $active_products
                            );
                            ?>
                            active products
                        </small>

                    </div>

                </div>


                <!-- Orders -->

                <div class="admin-stat-card">

                    <div class="admin-stat-icon">
                        🛒
                    </div>

                    <div class="admin-stat-content">

                        <span>
                            Total Orders
                        </span>

                        <strong>
                            <?php
                            echo number_format(
                                $total_orders
                            );
                            ?>
                        </strong>

                        <small>
                            <?php
                            echo number_format(
                                $pending_orders
                            );
                            ?>
                            pending orders
                        </small>

                    </div>

                </div>


                <!-- Revenue -->

                <div class="admin-stat-card">

                    <div class="admin-stat-icon">
                        Rs.
                    </div>

                    <div class="admin-stat-content">

                        <span>
                            Total Revenue
                        </span>

                        <strong>
                            Rs.
                            <?php
                            echo number_format(
                                $total_revenue,
                                2
                            );
                            ?>
                        </strong>

                        <small>
                            Excluding cancelled orders
                        </small>

                    </div>

                </div>


                <!-- Messages -->

                <div class="admin-stat-card">

                    <div class="admin-stat-icon">
                        ✉
                    </div>

                    <div class="admin-stat-content">

                        <span>
                            New Messages
                        </span>

                        <strong>
                            <?php
                            echo number_format(
                                $new_messages
                            );
                            ?>
                        </strong>

                        <small>
                            Customer enquiries
                        </small>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 QUICK ACTIONS
                 ================================================= -->

            <div class="admin-quick-actions">

                <a
                    href="products.php"
                    class="admin-quick-action"
                >

                    <span>
                        ＋
                    </span>

                    <div>

                        <strong>
                            Add Product
                        </strong>

                        <small>
                            Add a new store product
                        </small>

                    </div>

                </a>


                <a
                    href="orders.php"
                    class="admin-quick-action"
                >

                    <span>
                        ▥
                    </span>

                    <div>

                        <strong>
                            Manage Orders
                        </strong>

                        <small>
                            View and update orders
                        </small>

                    </div>

                </a>


                <a
                    href="../user/index.php"
                    class="admin-quick-action"
                    target="_blank"
                >

                    <span>
                        ↗
                    </span>

                    <div>

                        <strong>
                            View Website
                        </strong>

                        <small>
                            Open customer website
                        </small>

                    </div>

                </a>

            </div>


            <!-- =================================================
                 MAIN DASHBOARD GRID
                 ================================================= -->

            <div class="admin-dashboard-grid">


                <!-- =================================================
                     RECENT ORDERS
                     ================================================= -->

                <div class="admin-dashboard-card">


                    <div class="admin-card-header">

                        <div>

                            <h2>
                                Recent Orders
                            </h2>

                            <p>
                                Latest customer orders
                            </p>

                        </div>


                        <a
                            href="orders.php"
                            class="admin-view-all"
                        >
                            View All
                        </a>

                    </div>


                    <?php if (!empty($recent_orders)): ?>


                        <div class="admin-orders-list">


                            <?php foreach ($recent_orders as $order): ?>


                                <div class="admin-order-row">


                                    <div class="admin-order-id">

                                        <strong>
                                            #<?php
                                            echo (int)$order['id'];
                                            ?>
                                        </strong>

                                        <span>
                                            <?php
                                            echo htmlspecialchars(
                                                $order['customer_name']
                                            );
                                            ?>
                                        </span>

                                    </div>


                                    <div class="admin-order-payment">

                                        <span>
                                            <?php
                                            echo htmlspecialchars(
                                                $order['payment_method']
                                            );
                                            ?>
                                        </span>

                                    </div>


                                    <div class="admin-order-total">

                                        <strong>
                                            Rs.
                                            <?php
                                            echo number_format(
                                                (float)$order['total'],
                                                2
                                            );
                                            ?>
                                        </strong>

                                    </div>


                                    <div>

                                        <span
                                            class="admin-order-status <?php
                                            echo order_status_class(
                                                $order['status']
                                            );
                                            ?>"
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                $order['status']
                                            );
                                            ?>

                                        </span>

                                    </div>

                                </div>


                            <?php endforeach; ?>


                        </div>


                    <?php else: ?>


                        <div class="admin-empty-small">

                            <span>
                                🛒
                            </span>

                            <p>
                                No orders yet.
                            </p>

                        </div>


                    <?php endif; ?>


                </div>


                <!-- =================================================
                     RECENT PRODUCTS
                     ================================================= -->

                <div class="admin-dashboard-card">


                    <div class="admin-card-header">

                        <div>

                            <h2>
                                Recent Products
                            </h2>

                            <p>
                                Latest products added
                            </p>

                        </div>


                        <a
                            href="products.php"
                            class="admin-view-all"
                        >
                            View All
                        </a>

                    </div>


                    <?php if (!empty($recent_products)): ?>


                        <div class="admin-recent-products">


                            <?php foreach ($recent_products as $product): ?>


                                <div class="admin-recent-product-row">


                                    <div class="admin-recent-product-image">

                                        <img
                                            src="<?php
                                            echo htmlspecialchars(
                                                dashboard_product_image(
                                                    $product['image']
                                                )
                                            );
                                            ?>"
                                            alt="<?php
                                            echo htmlspecialchars(
                                                $product['product_name']
                                            );
                                            ?>"
                                        >

                                    </div>


                                    <div class="admin-recent-product-info">

                                        <strong>

                                            <?php
                                            echo htmlspecialchars(
                                                $product['product_name']
                                            );
                                            ?>

                                        </strong>

                                        <span>

                                            Rs.
                                            <?php
                                            echo number_format(
                                                (float)$product['price'],
                                                2
                                            );
                                            ?>

                                        </span>

                                    </div>


                                    <div class="admin-recent-product-stock">

                                        <small>
                                            Stock
                                        </small>

                                        <strong
                                            class="<?php
                                            echo (int)$product['stock'] <= 5
                                                ? 'low-stock-text'
                                                : '';
                                            ?>"
                                        >

                                            <?php
                                            echo (int)$product['stock'];
                                            ?>

                                        </strong>

                                    </div>


                                </div>


                            <?php endforeach; ?>


                        </div>


                    <?php else: ?>


                        <div class="admin-empty-small">

                            <span>
                                📦
                            </span>

                            <p>
                                No products yet.
                            </p>

                        </div>


                    <?php endif; ?>


                </div>


            </div>


            <!-- =================================================
                 BOTTOM INFORMATION
                 ================================================= -->

            <div class="admin-dashboard-bottom-grid">


                <!-- Low Stock -->

                <div class="admin-info-card">

                    <div class="admin-info-card-icon">
                        ⚠
                    </div>

                    <div>

                        <span>
                            Low Stock Products
                        </span>

                        <strong>
                            <?php
                            echo number_format(
                                $low_stock_products
                            );
                            ?>
                        </strong>

                        <small>
                            Products with 5 or fewer units
                        </small>

                    </div>

                </div>


                <!-- Active Products -->

                <div class="admin-info-card">

                    <div class="admin-info-card-icon">
                        ✓
                    </div>

                    <div>

                        <span>
                            Active Products
                        </span>

                        <strong>
                            <?php
                            echo number_format(
                                $active_products
                            );
                        ?>
                        </strong>

                        <small>
                            Currently available in store
                        </small>

                    </div>

                </div>


                <!-- Messages -->

                <div class="admin-info-card">

                    <div class="admin-info-card-icon">
                        ✉
                    </div>

                    <div>

                        <span>
                            Customer Messages
                        </span>

                        <strong>
                            <?php
                            echo number_format(
                                $new_messages
                            );
                            ?>
                        </strong>

                        <small>
                            New enquiries waiting
                        </small>

                    </div>

                </div>

            </div>


        </section>

    </main>

</div>


<script src="../script.js"></script>

</body>

</html>