<?php

require_once "../config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = "Checkout - SKZ Engineering";

$base_url = "/skz-engineering/";

$errors = [];
$order_success = false;
$order_id = null;


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
| Order Success Message
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['order_success'])) {

    $order_success = true;
    $order_id = $_SESSION['order_success'];

    unset($_SESSION['order_success']);
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

        $product_id = (int)$product_id;
        $quantity = (int)$quantity;

        if ($product_id <= 0 || $quantity <= 0) {
            unset($_SESSION['cart'][$product_id]);
            continue;
        }


        $stmt = $conn->prepare("
            SELECT
                id,
                product_name,
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


        if (!$product || $product['status'] !== 'Active') {

            unset($_SESSION['cart'][$product_id]);
            continue;
        }


        $stock = (int)$product['stock'];


        if ($stock <= 0) {

            unset($_SESSION['cart'][$product_id]);
            continue;
        }


        if ($quantity > $stock) {

            $quantity = $stock;

            $_SESSION['cart'][$product_id] = $stock;
        }


        $item_subtotal =
            (float)$product['price'] * $quantity;


        $product['quantity'] = $quantity;
        $product['item_subtotal'] = $item_subtotal;

        $cart_items[] = $product;

        $subtotal += $item_subtotal;
        $total_items += $quantity;
    }
}


/*
|--------------------------------------------------------------------------
| Shipping
|--------------------------------------------------------------------------
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


/*
|--------------------------------------------------------------------------
| Handle Order Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['place_order'])
    && !$order_success) {


    /*
    |--------------------------------------------------------------------------
    | Get Customer Information
    |--------------------------------------------------------------------------
    */

    $customer_name = trim($_POST['customer_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($customer_name === '') {

        $errors[] = "Please enter your full name.";

    } elseif (strlen($customer_name) < 2) {

        $errors[] = "Please enter a valid name.";
    }


    if ($email === '') {

        $errors[] = "Please enter your email address.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $errors[] = "Please enter a valid email address.";
    }


    if ($phone === '') {

        $errors[] = "Please enter your phone number.";

    } elseif (strlen($phone) < 7) {

        $errors[] = "Please enter a valid phone number.";
    }


    if ($address === '') {

        $errors[] = "Please enter your complete address.";
    }


    if ($city === '') {

        $errors[] = "Please enter your city.";
    }


    if ($payment_method === '') {

        $errors[] = "Please select a payment method.";

    } elseif (
        !in_array(
            $payment_method,
            ['Cash on Delivery', 'Bank Transfer'],
            true
        )
    ) {

        $errors[] = "Invalid payment method selected.";
    }


    /*
    |--------------------------------------------------------------------------
    | Make Sure Cart Is Not Empty
    |--------------------------------------------------------------------------
    */

    if (empty($cart_items)) {

        $errors[] = "Your cart is empty. Please add products before checkout.";
    }


    /*
    |--------------------------------------------------------------------------
    | Recalculate Cart From Database
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $cart_items = [];
        $subtotal = 0;
        $total_items = 0;


        foreach ($_SESSION['cart'] as $product_id => $quantity) {

            $product_id = (int)$product_id;
            $quantity = (int)$quantity;


            $stmt = $conn->prepare("
                SELECT
                    id,
                    product_name,
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


            if (!$product || $product['status'] !== 'Active') {

                $errors[] =
                    "One of the products in your cart is no longer available.";

                break;
            }


            $stock = (int)$product['stock'];


            if ($stock <= 0) {

                $errors[] =
                    htmlspecialchars($product['product_name'])
                    . " is currently out of stock.";

                break;
            }


            if ($quantity > $stock) {

                $errors[] =
                    "Only "
                    . $stock
                    . " unit(s) of "
                    . htmlspecialchars($product['product_name'])
                    . " are available.";

                break;
            }


            if ($quantity <= 0) {

                $errors[] = "Invalid product quantity.";
                break;
            }


            $item_subtotal =
                (float)$product['price'] * $quantity;


            $product['quantity'] = $quantity;
            $product['item_subtotal'] = $item_subtotal;


            $cart_items[] = $product;

            $subtotal += $item_subtotal;
            $total_items += $quantity;
        }


        /*
        |--------------------------------------------------------------------------
        | Recalculate Shipping
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {

            if ($subtotal >= $free_shipping_limit) {

                $shipping = 0;

            } else {

                $shipping = 250;
            }

            $total = $subtotal + $shipping;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Create Order
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $conn->begin_transaction();


            /*
            |--------------------------------------------------------------------------
            | Insert Order
            |--------------------------------------------------------------------------
            */

            $order_stmt = $conn->prepare("
                INSERT INTO orders
                (
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
                    status
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
            ");


            $order_stmt->bind_param(
                "ssssssddds",
                $customer_name,
                $email,
                $phone,
                $address,
                $city,
                $postal_code,
                $subtotal,
                $shipping,
                $total,
                $payment_method
            );


            if (!$order_stmt->execute()) {

                throw new Exception(
                    "Unable to create order."
                );
            }


            $new_order_id = $conn->insert_id;

            $order_stmt->close();


            /*
            |--------------------------------------------------------------------------
            | Insert Order Items + Update Stock
            |--------------------------------------------------------------------------
            */

            foreach ($cart_items as $item) {

                $product_id = (int)$item['id'];
                $product_name = $item['product_name'];
                $price = (float)$item['price'];
                $quantity = (int)$item['quantity'];
                $item_subtotal = (float)$item['item_subtotal'];


                /*
                |--------------------------------------------------------------------------
                | Insert Order Item
                |--------------------------------------------------------------------------
                */

                $item_stmt = $conn->prepare("
                    INSERT INTO order_items
                    (
                        order_id,
                        product_id,
                        product_name,
                        price,
                        quantity,
                        subtotal
                    )
                    VALUES
                    (?, ?, ?, ?, ?, ?)
                ");


                $item_stmt->bind_param(
                    "iisdid",
                    $new_order_id,
                    $product_id,
                    $product_name,
                    $price,
                    $quantity,
                    $item_subtotal
                );


                if (!$item_stmt->execute()) {

                    $item_stmt->close();

                    throw new Exception(
                        "Unable to save order items."
                    );
                }


                $item_stmt->close();


                /*
                |--------------------------------------------------------------------------
                | Reduce Product Stock
                |--------------------------------------------------------------------------
                */

                $stock_stmt = $conn->prepare("
                    UPDATE products
                    SET stock = stock - ?
                    WHERE id = ?
                      AND stock >= ?
                ");


                $stock_stmt->bind_param(
                    "iii",
                    $quantity,
                    $product_id,
                    $quantity
                );


                if (!$stock_stmt->execute()
                    || $stock_stmt->affected_rows !== 1) {

                    $stock_stmt->close();

                    throw new Exception(
                        "Product stock changed while placing the order."
                    );
                }


                $stock_stmt->close();
            }


            /*
            |--------------------------------------------------------------------------
            | Commit Transaction
            |--------------------------------------------------------------------------
            */

            $conn->commit();


            /*
            |--------------------------------------------------------------------------
            | Clear Cart
            |--------------------------------------------------------------------------
            */

            $_SESSION['cart'] = [];


            /*
            |--------------------------------------------------------------------------
            | Save Success Message
            |--------------------------------------------------------------------------
            */

            $_SESSION['order_success'] = $new_order_id;


            /*
            |--------------------------------------------------------------------------
            | Redirect
            |--------------------------------------------------------------------------
            */

            header("Location: checkout.php");
            exit;


        } catch (Exception $e) {

            $conn->rollback();

            $errors[] =
                "Your order could not be placed. Please try again.";

            /*
            |--------------------------------------------------------------------------
            | For Development
            |--------------------------------------------------------------------------
            |
            | If you need the exact database error while testing,
            | temporarily uncomment the line below.
            |
            */

            // $errors[] = $e->getMessage();
        }
    }
}


?>


<?php require_once "../includes/header.php"; ?>


<!-- =========================================================
     ORDER SUCCESS
========================================================== -->

<?php if ($order_success): ?>


    <section class="checkout-success-section">

        <div class="container">

            <div class="checkout-success">


                <div class="success-icon">
                    ✓
                </div>


                <h1>
                    Order Placed Successfully!
                </h1>


                <p>
                    Thank you for your order from
                    <strong>SKZ Engineering</strong>.
                </p>


                <div class="order-number">

                    Order Number:

                    <strong>
                        #<?php echo (int)$order_id; ?>
                    </strong>

                </div>


                <p class="success-message">

                    We have received your order and will contact you
                    shortly to confirm the details.

                </p>


                <div class="success-actions">

                    <a
                        href="shop.php"
                        class="btn btn-primary"
                    >
                        Continue Shopping
                    </a>


                    <a
                        href="index.php"
                        class="btn btn-secondary"
                    >
                        Back to Home
                    </a>

                </div>

            </div>

        </div>

    </section>


<?php elseif (empty($cart_items)): ?>


    <!-- =========================================================
         EMPTY CART
    ========================================================== -->

    <section class="checkout-section">

        <div class="container">

            <div class="empty-cart">


                <div class="empty-cart-icon">
                    🛒
                </div>


                <h2>
                    Your Cart is Empty
                </h2>


                <p>
                    Please add products to your cart before proceeding
                    to checkout.
                </p>


                <a
                    href="shop.php"
                    class="btn btn-primary"
                >
                    Start Shopping
                </a>

            </div>

        </div>

    </section>


<?php else: ?>


    <!-- =========================================================
         CHECKOUT PAGE
    ========================================================== -->

    <section class="page-banner">

        <div class="container">

            <h1>
                Checkout
            </h1>

            <p>
                Enter your details to complete your order.
            </p>

        </div>

    </section>


    <?php if (!empty($errors)): ?>

        <div class="alert alert-error">

            <strong>
                Please correct the following:
            </strong>

            <ul>

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?php echo $error; ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <section class="checkout-section">

        <div class="container">

            <form
                method="POST"
                action="checkout.php"
                class="checkout-form"
                id="checkoutForm"
            >


                <div class="checkout-layout">


                    <!-- =================================================
                         CUSTOMER INFORMATION
                    ================================================== -->

                    <div class="checkout-details">


                        <div class="checkout-box">

                            <h2>
                                Customer Information
                            </h2>


                            <div class="form-group">

                                <label for="customer_name">
                                    Full Name
                                    <span>*</span>
                                </label>

                                <input
                                    type="text"
                                    id="customer_name"
                                    name="customer_name"
                                    placeholder="Enter your full name"
                                    value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>"
                                    required
                                >

                            </div>


                            <div class="form-row">


                                <div class="form-group">

                                    <label for="email">
                                        Email Address
                                        <span>*</span>
                                    </label>

                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        placeholder="example@email.com"
                                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                        required
                                    >

                                </div>


                                <div class="form-group">

                                    <label for="phone">
                                        Phone Number
                                        <span>*</span>
                                    </label>

                                    <input
                                        type="tel"
                                        id="phone"
                                        name="phone"
                                        placeholder="+92 300 0000000"
                                        value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                        required
                                    >

                                </div>

                            </div>


                            <div class="form-group">

                                <label for="address">
                                    Complete Address
                                    <span>*</span>
                                </label>

                                <textarea
                                    id="address"
                                    name="address"
                                    rows="4"
                                    placeholder="House / Office / Street address"
                                    required
                                ><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>

                            </div>


                            <div class="form-row">


                                <div class="form-group">

                                    <label for="city">
                                        City
                                        <span>*</span>
                                    </label>

                                    <input
                                        type="text"
                                        id="city"
                                        name="city"
                                        placeholder="Enter city"
                                        value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>"
                                        required
                                    >

                                </div>


                                <div class="form-group">

                                    <label for="postal_code">
                                        Postal Code
                                    </label>

                                    <input
                                        type="text"
                                        id="postal_code"
                                        name="postal_code"
                                        placeholder="Optional"
                                        value="<?php echo htmlspecialchars($_POST['postal_code'] ?? ''); ?>"
                                    >

                                </div>

                            </div>

                        </div>


                        <!-- =================================================
                             PAYMENT
                        ================================================== -->

                        <div class="checkout-box">

                            <h2>
                                Payment Method
                            </h2>


                            <div class="payment-options">


                                <label class="payment-option">

                                    <input
                                        type="radio"
                                        name="payment_method"
                                        value="Cash on Delivery"
                                        <?php
                                        echo (
                                            ($_POST['payment_method'] ?? '')
                                            === 'Cash on Delivery'
                                        )
                                            ? 'checked'
                                            : '';
                                        ?>
                                        required
                                    >

                                    <span class="payment-option-content">

                                        <strong>
                                            Cash on Delivery
                                        </strong>

                                        <small>
                                            Pay when your order is delivered.
                                        </small>

                                    </span>

                                </label>

                        </div>


                    </div>


                    <!-- =================================================
                         ORDER SUMMARY
                    ================================================== -->

                    <aside class="checkout-summary">


                        <div class="checkout-box">

                            <h2>
                                Your Order
                            </h2>


                            <div class="checkout-products">


                                <?php foreach ($cart_items as $item): ?>

                                    <div class="checkout-product">


                                        <div class="checkout-product-image">

                                            <?php

                                            $image_path = !empty($item['image'])
                                                ? "../assets/images/products/" . $item['image']
                                                : "../assets/images/product-placeholder.jpg";

                                            ?>

                                            <img
                                                src="<?php echo htmlspecialchars($image_path); ?>"
                                                alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                onerror="this.onerror=null;this.src='../assets/images/product-placeholder.jpg';"
                                            >

                                        </div>


                                        <div class="checkout-product-info">

                                            <h3>

                                                <?php

                                                echo htmlspecialchars(
                                                    $item['product_name']
                                                );

                                                ?>

                                            </h3>


                                            <p>

                                                Qty:
                                                <?php echo (int)$item['quantity']; ?>

                                                ×

                                                Rs.
                                                <?php

                                                echo number_format(
                                                    (float)$item['price'],
                                                    2
                                                );

                                                ?>

                                            </p>

                                        </div>


                                        <strong>

                                            Rs.

                                            <?php

                                            echo number_format(
                                                (float)$item['item_subtotal'],
                                                2
                                            );

                                            ?>

                                        </strong>

                                    </div>

                                <?php endforeach; ?>

                            </div>


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


                            <button
                                type="submit"
                                name="place_order"
                                value="1"
                                class="btn btn-primary place-order-btn"
                            >
                                Place Order
                            </button>


                            <a
                                href="cart.php"
                                class="back-to-cart"
                            >
                                ← Back to Cart
                            </a>


                        </div>


                        <div class="checkout-security">

                            🔒

                            <strong>
                                Secure Checkout
                            </strong>

                            <p>
                                Your order information is securely
                                processed by SKZ Engineering.
                            </p>

                        </div>


                    </aside>

                </div>

            </form>

        </div>

    </section>


<?php endif; ?>


<?php require_once "../includes/footer.php"; ?>