<?php

$page_title = "SKZ Engineering - Quality Engineering Products";

require_once "../config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   ADD PRODUCT TO CART
   ========================================================= */

if (isset($_GET['add_to_cart'])) {

    $product_id = (int) $_GET['add_to_cart'];

    if ($product_id > 0) {

        $stmt = $conn->prepare(
            "SELECT id, product_name, price, stock
             FROM products
             WHERE id = ? AND status = 'Active'
             LIMIT 1"
        );

        $stmt->bind_param("i", $product_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($product = $result->fetch_assoc()) {

            if ((int)$product['stock'] > 0) {

                if (!isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }

                if (isset($_SESSION['cart'][$product_id])) {

                    if (
                        $_SESSION['cart'][$product_id]
                        < (int)$product['stock']
                    ) {
                        $_SESSION['cart'][$product_id]++;
                    }

                } else {

                    $_SESSION['cart'][$product_id] = 1;

                }

                $_SESSION['cart_message'] =
                    htmlspecialchars($product['product_name'])
                    . " has been added to your cart.";

            } else {

                $_SESSION['cart_message'] =
                    "Sorry, this product is currently out of stock.";

            }

        }

        $stmt->close();
    }


    /*
     * Redirect to remove ?add_to_cart from URL.
     */
    header("Location: index.php");
    exit;
}


/* =========================================================
   CART MESSAGE
   ========================================================= */

$cart_message = "";

if (isset($_SESSION['cart_message'])) {

    $cart_message = $_SESSION['cart_message'];

    unset($_SESSION['cart_message']);
}


/* =========================================================
   FETCH FEATURED PRODUCTS
   ========================================================= */

$products = [];

$product_query = "
    SELECT
        id,
        product_name,
        description,
        price,
        image,
        category,
        stock,
        sku
    FROM products
    WHERE status = 'Active'
    ORDER BY created_at DESC
    LIMIT 8
";

$product_result = $conn->query($product_query);

if ($product_result) {

    while ($row = $product_result->fetch_assoc()) {
        $products[] = $row;
    }

}


/* =========================================================
   INCLUDE WEBSITE HEADER
   ========================================================= */

include "../includes/header.php";

?>


<!-- =====================================================
     CART MESSAGE
     ===================================================== -->

<?php if (!empty($cart_message)): ?>

    <div class="container" style="padding-top: 15px;">

        <div class="alert alert-success">
            <?php echo $cart_message; ?>
        </div>

    </div>

<?php endif; ?>


<!-- =====================================================
     HERO SECTION
     ===================================================== -->

<section class="hero">

    <div class="hero-content">

        <div class="hero-text">

            <h1>
                Quality
                <span>Engineering Products</span>
            </h1>

            <p>
                Discover reliable engineering products, machine parts,
                hardware tools and industrial supplies designed for
                professionals and businesses.
            </p>

            <a
                href="shop.php"
                class="btn btn-primary"
            >
                Shop Now
            </a>

        </div>

    </div>

</section>


<!-- =====================================================
     FEATURES
     ===================================================== -->

<section class="features-strip">

    <div class="feature-item">

        <div class="feature-icon">
            ✓
        </div>

        <div class="feature-text">

            <strong>Quality Products</strong>

            <span>
                Reliable engineering supplies
            </span>

        </div>

    </div>


    <div class="feature-item">

        <div class="feature-icon">
            ⚙
        </div>

        <div class="feature-text">

            <strong>Engineering Solutions</strong>

            <span>
                Products for professional use
            </span>

        </div>

    </div>


    <div class="feature-item">

        <div class="feature-icon">
            🚚
        </div>

        <div class="feature-text">

            <strong>Fast Delivery</strong>

            <span>
                Convenient order processing
            </span>

        </div>

    </div>


    <div class="feature-item">

        <div class="feature-icon">
            ☎
        </div>

        <div class="feature-text">

            <strong>Customer Support</strong>

            <span>
                We're here to help
            </span>

        </div>

    </div>

</section>


<!-- =====================================================
     PRODUCTS SECTION
     ===================================================== -->

<section class="section">

    <div class="container">

        <div class="section-header">

            <div>

                <h2 class="section-title">
                    Featured Products
                </h2>

            </div>

            <a
                href="shop.php"
                class="view-all"
            >
                View All Products →
            </a>

        </div>


        <?php if (!empty($products)): ?>

            <div class="product-grid">

                <?php foreach ($products as $product): ?>

                    <?php

                    $product_id =
                        (int)$product['id'];

                    $product_name =
                        htmlspecialchars(
                            $product['product_name']
                        );

                    $description =
                        htmlspecialchars(
                            $product['description'] ?? ''
                        );

                    $category =
                        htmlspecialchars(
                            $product['category'] ?? 'Engineering'
                        );

                    $price =
                        number_format(
                            (float)$product['price'],
                            2
                        );

                    $stock =
                        (int)$product['stock'];

                    $image =
                        !empty($product['image'])
                            ? "../assets/images/products/"
                              . htmlspecialchars($product['image'])
                            : "../assets/images/product-placeholder.jpg";

                    ?>

                    <div class="product-card">


                        <!-- Product Image -->

                        <a
                            href="shop.php?product=<?php echo $product_id; ?>"
                            class="product-image"
                        >

                            <img
                                src="<?php echo $image; ?>"
                                alt="<?php echo $product_name; ?>"
                                loading="lazy"
                                onerror="this.src='../assets/images/product-placeholder.jpg';"
                            >

                        </a>


                        <!-- Product Information -->

                        <div class="product-info">

                            <span class="product-category">
                                <?php echo $category; ?>
                            </span>


                            <a
                                href="shop.php?product=<?php echo $product_id; ?>"
                                class="product-name"
                            >
                                <?php echo $product_name; ?>
                            </a>


                            <div class="product-price">
                                Rs. <?php echo $price; ?>
                            </div>


                            <div class="product-actions">

                                <?php if ($stock > 0): ?>

                                    <a
                                        href="index.php?add_to_cart=<?php echo $product_id; ?>"
                                        class="btn btn-primary add-to-cart"
                                    >
                                        Add to Cart
                                    </a>

                                <?php else: ?>

                                    <button
                                        type="button"
                                        class="btn btn-outline"
                                        disabled
                                    >
                                        Out of Stock
                                    </button>

                                <?php endif; ?>

                                <a
                                    href="shop.php?product=<?php echo $product_id; ?>"
                                    class="btn btn-outline"
                                >
                                    View
                                </a>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>


        <?php else: ?>

            <div class="empty-state">

                <div class="empty-state-icon">
                    ⚙
                </div>

                <h2>
                    Products Coming Soon
                </h2>

                <p>
                    We're currently preparing our engineering
                    product collection.
                </p>

                <a
                    href="contactus.php"
                    class="btn btn-primary"
                >
                    Contact Us
                </a>

            </div>

        <?php endif; ?>

    </div>

</section>


<!-- =====================================================
     WHY SKZ ENGINEERING
     ===================================================== -->

<section
    class="section"
    style="background: #ffffff;"
>

    <div class="container">

        <div class="section-header">

            <div>

                <h2 class="section-title">
                    Why Choose SKZ Engineering?
                </h2>

            </div>

        </div>


        <div class="category-grid">


            <div class="category-card">

                <div
                    class="category-card-image"
                    style="
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        background:#111c28;
                        color:#fdbb19;
                        font-size:40px;
                    "
                >
                    ⚙
                </div>

                <h3>
                    Engineering Quality
                </h3>

            </div>


            <div class="category-card">

                <div
                    class="category-card-image"
                    style="
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        background:#111c28;
                        color:#fdbb19;
                        font-size:40px;
                    "
                >
                    ✓
                </div>

                <h3>
                    Reliable Products
                </h3>

            </div>


            <div class="category-card">

                <div
                    class="category-card-image"
                    style="
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        background:#111c28;
                        color:#fdbb19;
                        font-size:40px;
                    "
                >
                    ⚡
                </div>

                <h3>
                    Professional Solutions
                </h3>

            </div>


            <div class="category-card">

                <div
                    class="category-card-image"
                    style="
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        background:#111c28;
                        color:#fdbb19;
                        font-size:40px;
                    "
                >
                    🚚
                </div>

                <h3>
                    Easy Ordering
                </h3>

            </div>


        </div>

    </div>

</section>


<!-- =====================================================
     CTA
     ===================================================== -->

<section
    class="section"
    style="background:#f5f6f7;"
>

    <div class="container">

        <div
            style="
                background:#111c28;
                border-radius:6px;
                padding:40px;
                text-align:center;
                color:#ffffff;
            "
        >

            <h2
                style="
                    font-size:26px;
                    margin-bottom:10px;
                "
            >
                Need Engineering Products?
            </h2>

            <p
                style="
                    color:#cbd1d6;
                    font-size:12px;
                    max-width:600px;
                    margin:0 auto 20px;
                "
            >
                Browse our collection of engineering products,
                machine parts and industrial supplies.
            </p>

            <a
                href="shop.php"
                class="btn btn-primary"
            >
                Explore Products
            </a>

        </div>

    </div>

</section>


<?php

/* =========================================================
   INCLUDE WEBSITE FOOTER
   ========================================================= */

include "../includes/footer.php";

?>