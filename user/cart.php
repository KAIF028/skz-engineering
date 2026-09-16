<?php

require_once "../config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = "Shopping Cart - SKZ Engineering";

$base_url = "/skz-engineering/";

$success_message = "";
$error_message = "";

/*
|--------------------------------------------------------------------------
| Initialize Cart
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}


/*
|--------------------------------------------------------------------------
| Remove Item
|--------------------------------------------------------------------------
*/

if (isset($_GET['remove'])) {

    $remove_id = (int) $_GET['remove'];

    if ($remove_id > 0 && isset($_SESSION['cart'][$remove_id])) {

        unset($_SESSION['cart'][$remove_id]);

        $success_message = "Product removed from your cart.";
    }
}


/*
|--------------------------------------------------------------------------
| Update Single Product Quantity
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_cart'])
) {

    $product_id = isset($_POST['product_id'])
        ? (int) $_POST['product_id']
        : 0;

    $quantity = isset($_POST['quantity'])
        ? (int) $_POST['quantity']
        : 1;


    if ($product_id <= 0) {

        $error_message = "Invalid product.";

    } elseif (!isset($_SESSION['cart'][$product_id])) {

        $error_message = "Product is not in your cart.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Get Current Stock
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT id, product_name, stock, status
            FROM products
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $product_id);

        $stmt->execute();

        $result = $stmt->get_result();

        $product = $result->fetch_assoc();

        $stmt->close();


        if (!$product || $product['status'] !== 'Active') {

            unset($_SESSION['cart'][$product_id]);

            $error_message = "This product is no longer available.";

        } elseif ((int) $product['stock'] <= 0) {

            unset($_SESSION['cart'][$product_id]);

            $error_message = "This product is currently out of stock.";

        } elseif ($quantity <= 0) {

            unset($_SESSION['cart'][$product_id]);

            $success_message = "Product removed from your cart.";

        } elseif ($quantity > (int) $product['stock']) {

            $_SESSION['cart'][$product_id] = (int) $product['stock'];

            $error_message =
                "Only " . (int) $product['stock']
                . " unit(s) of "
                . htmlspecialchars($product['product_name'])
                . " are available.";

        } else {

            $_SESSION['cart'][$product_id] = $quantity;

            $success_message = "Cart updated successfully.";
        }
    }
}


/*
|--------------------------------------------------------------------------
| Clear Entire Cart
|--------------------------------------------------------------------------
*/

if (isset($_GET['clear_cart'])) {

    $_SESSION['cart'] = [];

    $success_message = "Your cart has been cleared.";
}


/*
|--------------------------------------------------------------------------
| Fetch Cart Products
|--------------------------------------------------------------------------
*/

$cart_items = [];

$subtotal = 0;

$total_items = 0;


if (!empty($_SESSION['cart'])) {

    foreach ($_SESSION['cart'] as $product_id => $quantity) {

        $product_id = (int) $product_id;
        $quantity = (int) $quantity;

        if ($product_id <= 0 || $quantity <= 0) {
            unset($_SESSION['cart'][$product_id]);
            continue;
        }


        $stmt = $conn->prepare("
            SELECT
                id,
                product_name,
                description,
                price,
                image,
                category,
                stock,
                sku,
                status
            FROM products
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $product_id);

        $stmt->execute();

        $result = $stmt->get_result();

        $product = $result->fetch_assoc();

        $stmt->close();


        /*
        |--------------------------------------------------------------------------
        | Product Not Available
        |--------------------------------------------------------------------------
        */

        if (!$product || $product['status'] !== 'Active') {

            unset($_SESSION['cart'][$product_id]);
            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Check Stock
        |--------------------------------------------------------------------------
        */

        $available_stock = (int) $product['stock'];

        if ($available_stock <= 0) {

            unset($_SESSION['cart'][$product_id]);
            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Correct Quantity If Greater Than Stock
        |--------------------------------------------------------------------------
        */

        if ($quantity > $available_stock) {

            $quantity = $available_stock;

            $_SESSION['cart'][$product_id] = $available_stock;
        }


        /*
        |--------------------------------------------------------------------------
        | Calculate Item Subtotal
        |--------------------------------------------------------------------------
        */

        $item_subtotal =
            (float) $product['price'] * $quantity;


        /*
        |--------------------------------------------------------------------------
        | Add To Cart Array
        |--------------------------------------------------------------------------
        */

        $product['quantity'] = $quantity;

        $product['item_subtotal'] = $item_subtotal;

        $cart_items[] = $product;


        /*
        |--------------------------------------------------------------------------
        | Cart Totals
        |--------------------------------------------------------------------------
        */

        $subtotal += $item_subtotal;

        $total_items += $quantity;
    }
}


/*
|--------------------------------------------------------------------------
| Shipping
|--------------------------------------------------------------------------
|
| Free shipping for orders Rs. 5,000 or above.
| Otherwise Rs. 250 shipping.
|
*/

$free_shipping_limit = 5000;

if ($subtotal <= 0) {

    $shipping = 0;

} elseif ($subtotal >= $free_shipping_limit) {

    $shipping = 0;

} else {

    $shipping = 250;
}


$total = $subtotal + $shipping;

?>


<?php require_once "../includes/header.php"; ?>


<!-- =========================================================
     CART PAGE
========================================================== -->

<section class="page-banner">

    <div class="container">

        <h1>Shopping Cart</h1>

        <p>
            Review your selected products before checkout.
        </p>

    </div>

</section>


<?php if ($success_message !== ""): ?>

    <div class="alert alert-success">

        <?php echo $success_message; ?>

    </div>

<?php endif; ?>


<?php if ($error_message !== ""): ?>

    <div class="alert alert-error">

        <?php echo $error_message; ?>

    </div>

<?php endif; ?>


<section class="cart-section">

    <div class="container">


        <?php if (!empty($cart_items)): ?>


            <div class="cart-layout">


                <!-- =================================================
                     CART ITEMS
                ================================================== -->

                <div class="cart-items">


                    <div class="cart-header">

                        <h2>
                            Your Cart
                        </h2>

                        <span>
                            <?php echo $total_items; ?>
                            item<?php echo $total_items != 1 ? 's' : ''; ?>
                        </span>

                    </div>


                    <?php foreach ($cart_items as $item): ?>


                        <div class="cart-item">


                            <!-- Product Image -->

                            <div class="cart-item-image">

                                <?php

                                $image_path = !empty($item['image'])
                                    ? "../assets/images/products/" . $item['image']
                                    : "../assets/images/product-placeholder.jpg";

                                ?>

                                <img src="<?php echo htmlspecialchars($image_path); ?>"
                                    alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                    onerror="this.onerror=null;this.src='../assets/images/product-placeholder.jpg';">

                            </div>


                            <!-- Product Details -->

                            <div class="cart-item-details">


                                <span class="product-category">

                                    <?php

                                    echo htmlspecialchars(
                                        $item['category'] ?: "Engineering"
                                    );

                                    ?>

                                </span>


                                <h3>

                                    <a href="shop.php?product=<?php echo (int) $item['id']; ?>">

                                        <?php

                                        echo htmlspecialchars(
                                            $item['product_name']
                                        );

                                        ?>

                                    </a>

                                </h3>


                                <?php if (!empty($item['sku'])): ?>

                                    <p class="cart-sku">

                                        SKU:
                                        <?php echo htmlspecialchars($item['sku']); ?>

                                    </p>

                                <?php endif; ?>


                                <div class="cart-item-price">

                                    Rs.

                                    <?php

                                    echo number_format(
                                        (float) $item['price'],
                                        2
                                    );

                                    ?>

                                    / unit

                                </div>

                            </div>


                            <!-- Quantity -->

                            <div class="cart-item-quantity">

                                <form method="POST" action="cart.php" class="quantity-form">

                                    <input type="hidden" name="product_id" value="<?php echo (int) $item['id']; ?>">

                                    <div class="quantity-control">

                                        <button type="button" class="quantity-minus" aria-label="Decrease quantity">
                                            −
                                        </button>

                                        <input type="number" name="quantity" value="<?php echo (int) $item['quantity']; ?>"
                                            min="1" max="<?php echo (int) $item['stock']; ?>" class="quantity-input"
                                            inputmode="numeric">

                                        <button type="button" class="quantity-plus" aria-label="Increase quantity">
                                            +
                                        </button>

                                    </div>


                                    <button type="submit" name="update_cart" class="update-cart-btn">
                                        Update
                                    </button>

                                </form>

                            </div>


                            <!-- Item Total -->

                            <div class="cart-item-total">

                                <strong>

                                    Rs.

                                    <?php

                                    echo number_format(
                                        (float) $item['item_subtotal'],
                                        2
                                    );

                                    ?>

                                </strong>


                                <a href="cart.php?remove=<?php echo (int) $item['id']; ?>" class="remove-item"
                                    onclick="return confirm('Are you sure you want to remove this product from your cart?');">
                                    Remove
                                </a>

                            </div>

                        </div>


                    <?php endforeach; ?>


                    <!-- Cart Actions -->

                    <div class="cart-actions">


                        <a href="shop.php" class="btn btn-secondary">
                            ← Continue Shopping
                        </a>


                        <a href="cart.php?clear_cart=1" class="btn btn-danger"
                            onclick="return confirm('Are you sure you want to clear your entire cart?');">
                            Clear Cart
                        </a>

                    </div>


                </div>


                <!-- =================================================
                     CART SUMMARY
                ================================================== -->

                <aside class="cart-summary">


                    <h2>
                        Order Summary
                    </h2>


                    <div class="summary-row">

                        <span>
                            Subtotal
                        </span>

                        <strong>

                            Rs.

                            <?php

                            echo number_format(
                                $subtotal,
                                2
                            );

                            ?>

                        </strong>

                    </div>


                    <div class="summary-row">

                        <span>
                            Shipping
                        </span>

                        <strong>

                            <?php if ($shipping == 0): ?>

                                Free

                            <?php else: ?>

                                Rs.

                                <?php

                                echo number_format(
                                    $shipping,
                                    2
                                );

                                ?>

                            <?php endif; ?>

                        </strong>

                    </div>


                    <?php if ($subtotal > 0 && $subtotal < $free_shipping_limit): ?>

                        <div class="shipping-note">

                            Add Rs.

                            <?php

                            echo number_format(
                                $free_shipping_limit - $subtotal,
                                2
                            );

                            ?>

                            more to get
                            <strong>FREE SHIPPING</strong>.

                        </div>

                    <?php elseif ($subtotal >= $free_shipping_limit): ?>

                        <div class="shipping-note">

                            ✓ You qualify for
                            <strong>FREE SHIPPING</strong>.

                        </div>

                    <?php endif; ?>


                    <div class="summary-total">

                        <span>
                            Total
                        </span>

                        <strong>

                            Rs.

                            <?php

                            echo number_format(
                                $total,
                                2
                            );

                            ?>

                        </strong>

                    </div>


                    <a href="checkout.php" class="btn btn-primary checkout-btn">
                        Proceed to Checkout
                    </a>


                    <div class="secure-checkout">

                        🔒 Secure Checkout

                    </div>

                </aside>

            </div>


        <?php else: ?>


            <!-- =================================================
                 EMPTY CART
            ================================================== -->

            <div class="empty-cart">


                <div class="empty-cart-icon">
                    🛒
                </div>


                <h2>
                    Your Cart is Empty
                </h2>


                <p>
                    You haven't added any products to your cart yet.
                </p>


                <a href="shop.php" class="btn btn-primary">
                    Start Shopping
                </a>

            </div>


        <?php endif; ?>


    </div>

</section>


<?php require_once "../includes/footer.php"; ?>