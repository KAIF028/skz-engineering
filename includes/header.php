<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_url = "/skz-engineering/";

$cart_count = 0;

if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $quantity) {
        $cart_count += (int)$quantity;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?php echo isset($page_title) ? htmlspecialchars($page_title) : "SKZ Engineering"; ?>
    </title>

    <meta name="description" content="SKZ Engineering - Quality engineering products, machine parts, hardware tools and industrial supplies.">

    <link rel="stylesheet" href="<?php echo $base_url; ?>style.css">
</head>

<body>

<header class="site-header">

    <div class="header-container">

        <!-- Logo -->
        <a href="<?php echo $base_url; ?>user/index.php" class="logo">
            <span class="logo-main">SKZ</span>
            <span class="logo-sub">ENGINEERING</span>
        </a>


        <!-- Search -->
        <form class="search-form" action="<?php echo $base_url; ?>user/shop.php" method="GET">

            <input
                type="text"
                name="search"
                placeholder="Search for products..."
                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
            >

            <button type="submit" aria-label="Search">
                🔍
            </button>

        </form>


        <!-- Account & Cart -->
        <div class="header-actions">

            <a href="#" class="account-link">
                <span class="action-icon">♙</span>
                <span>My Account</span>
            </a>

            <a href="<?php echo $base_url; ?>user/cart.php" class="cart-link">

                <span class="action-icon">🛒</span>

                <span>Cart</span>

                <?php if ($cart_count > 0): ?>
                    <span class="cart-count">
                        <?php echo $cart_count; ?>
                    </span>
                <?php endif; ?>

            </a>

        </div>

    </div>


    <!-- Navigation -->
    <nav class="main-navigation">

        <div class="nav-container">

            <a href="<?php echo $base_url; ?>user/index.php"
               class="<?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">
                Home
            </a>

            <a href="<?php echo $base_url; ?>user/shop.php"
               class="<?php echo basename($_SERVER['PHP_SELF']) === 'shop.php' ? 'active' : ''; ?>">
                Shop
            </a>

            <a href="<?php echo $base_url; ?>user/contactus.php"
               class="<?php echo basename($_SERVER['PHP_SELF']) === 'contactus.php' ? 'active' : ''; ?>">
                Contact Us
            </a>

        </div>

    </nav>

</header>


<main class="main-content">