<?php

require_once "../config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = "Shop - SKZ Engineering";

$base_url = "/skz-engineering/";

$success_message = "";
$error_message = "";

/*
|--------------------------------------------------------------------------
| Add To Cart
|--------------------------------------------------------------------------
*/

if (isset($_GET['add_to_cart'])) {

    $product_id = (int) $_GET['add_to_cart'];

    if ($product_id > 0) {

        $stmt = $conn->prepare("
            SELECT id, product_name, stock, status
            FROM products
            WHERE id = ? AND status = 'Active'
            LIMIT 1
        ");

        $stmt->bind_param("i", $product_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $product = $result->fetch_assoc();

        $stmt->close();

        if (!$product) {

            $error_message = "Product not found.";

        } elseif ((int)$product['stock'] <= 0) {

            $error_message = "Sorry, this product is currently out of stock.";

        } else {

            if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }

            $current_quantity = isset($_SESSION['cart'][$product_id])
                ? (int) $_SESSION['cart'][$product_id]
                : 0;

            if ($current_quantity >= (int)$product['stock']) {

                $error_message = "You cannot add more than the available stock.";

            } else {

                $_SESSION['cart'][$product_id] = $current_quantity + 1;

                $success_message = htmlspecialchars($product['product_name'])
                    . " has been added to your cart.";
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Product Detail
|--------------------------------------------------------------------------
*/

$single_product = null;

if (isset($_GET['product'])) {

    $product_id = (int) $_GET['product'];

    if ($product_id > 0) {

        $stmt = $conn->prepare("
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
            WHERE id = ? AND status = 'Active'
            LIMIT 1
        ");

        $stmt->bind_param("i", $product_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $single_product = $result->fetch_assoc();

        $stmt->close();

        if (!$single_product) {
            $error_message = "Product not found.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| Search / Category / Sorting
|--------------------------------------------------------------------------
*/

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : "";

$category = isset($_GET['category'])
    ? trim($_GET['category'])
    : "";

$sort = isset($_GET['sort'])
    ? trim($_GET['sort'])
    : "latest";

/*
|--------------------------------------------------------------------------
| Categories
|--------------------------------------------------------------------------
*/

$categories = [];

$category_result = $conn->query("
    SELECT DISTINCT category
    FROM products
    WHERE status = 'Active'
      AND category IS NOT NULL
      AND category != ''
    ORDER BY category ASC
");

if ($category_result) {

    while ($row = $category_result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}

/*
|--------------------------------------------------------------------------
| Products
|--------------------------------------------------------------------------
*/

$products = [];

$sql = "
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
";

$params = [];
$types = "";

/*
|--------------------------------------------------------------------------
| Search Filter
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $sql .= "
        AND (
            product_name LIKE ?
            OR description LIKE ?
            OR category LIKE ?
            OR sku LIKE ?
        )
    ";

    $search_term = "%" . $search . "%";

    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;

    $types .= "ssss";
}

/*
|--------------------------------------------------------------------------
| Category Filter
|--------------------------------------------------------------------------
*/

if ($category !== "") {

    $sql .= " AND category = ? ";

    $params[] = $category;
    $types .= "s";
}

/*
|--------------------------------------------------------------------------
| Sorting
|--------------------------------------------------------------------------
*/

switch ($sort) {

    case "price_low":
        $sql .= " ORDER BY price ASC";
        break;

    case "price_high":
        $sql .= " ORDER BY price DESC";
        break;

    case "name_asc":
        $sql .= " ORDER BY product_name ASC";
        break;

    case "name_desc":
        $sql .= " ORDER BY product_name DESC";
        break;

    default:
        $sql .= " ORDER BY created_at DESC";
        break;
}

/*
|--------------------------------------------------------------------------
| Execute Products Query
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

$stmt->close();

?>

<?php require_once "../includes/header.php"; ?>


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


<?php if ($single_product): ?>

    <!-- =========================================================
         PRODUCT DETAIL
    ========================================================== -->

    <section class="product-detail-section">

        <div class="container">

            <div class="product-detail">

                <!-- Product Image -->

                <div class="product-detail-image">

                    <?php
                    $product_image = !empty($single_product['image'])
                        ? "../assets/images/products/" . $single_product['image']
                        : "../assets/images/product-placeholder.jpg";
                    ?>

                    <img
                        src="<?php echo htmlspecialchars($product_image); ?>"
                        alt="<?php echo htmlspecialchars($single_product['product_name']); ?>"
                        onerror="this.onerror=null;this.src='../assets/images/product-placeholder.jpg';"
                    >

                </div>


                <!-- Product Information -->

                <div class="product-detail-info">

                    <span class="product-category">

                        <?php
                        echo htmlspecialchars(
                            $single_product['category'] ?: "Engineering Product"
                        );
                        ?>

                    </span>


                    <h1>
                        <?php
                        echo htmlspecialchars($single_product['product_name']);
                        ?>
                    </h1>


                    <?php if (!empty($single_product['sku'])): ?>

                        <p class="product-sku">
                            SKU:
                            <?php echo htmlspecialchars($single_product['sku']); ?>
                        </p>

                    <?php endif; ?>


                    <div class="product-detail-price">

                        Rs.
                        <?php
                        echo number_format(
                            (float)$single_product['price'],
                            2
                        );
                        ?>

                    </div>


                    <div class="product-description">

                        <?php
                        echo nl2br(
                            htmlspecialchars(
                                $single_product['description'] ?: "Quality engineering product from SKZ Engineering."
                            )
                        );
                        ?>

                    </div>


                    <div class="product-stock">

                        <?php if ((int)$single_product['stock'] > 0): ?>

                            <span class="in-stock">
                                ✓ In Stock
                            </span>

                            <span>
                                <?php echo (int)$single_product['stock']; ?>
                                available
                            </span>

                        <?php else: ?>

                            <span class="out-of-stock">
                                Out of Stock
                            </span>

                        <?php endif; ?>

                    </div>


                    <?php if ((int)$single_product['stock'] > 0): ?>

                        <div class="product-detail-actions">

                            <a
                                href="shop.php?add_to_cart=<?php echo (int)$single_product['id']; ?>"
                                class="btn btn-primary"
                            >
                                Add to Cart
                            </a>

                            <a
                                href="cart.php"
                                class="btn btn-secondary"
                            >
                                View Cart
                            </a>

                        </div>

                    <?php endif; ?>


                    <div class="back-to-shop">

                        <a href="shop.php">
                            ← Back to Shop
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </section>


<?php else: ?>


    <!-- =========================================================
         SHOP PAGE
    ========================================================== -->

    <section class="page-banner">

        <div class="container">

            <h1>Shop</h1>

            <p>
                Browse our quality engineering products,
                tools, machine parts and industrial supplies.
            </p>

        </div>

    </section>


    <section class="shop-section">

        <div class="container">

            <div class="shop-layout">


                <!-- =================================================
                     SIDEBAR
                ================================================== -->

                <aside class="shop-sidebar">

                    <div class="sidebar-box">

                        <h3>Shop By Category</h3>

                        <ul class="category-filter">

                            <li>

                                <a
                                    href="shop.php"
                                    class="<?php echo $category === '' ? 'active' : ''; ?>"
                                >
                                    All Products
                                </a>

                            </li>


                            <?php foreach ($categories as $cat): ?>

                                <li>

                                    <a
                                        href="shop.php?category=<?php echo urlencode($cat); ?>"
                                        class="<?php echo $category === $cat ? 'active' : ''; ?>"
                                    >
                                        <?php echo htmlspecialchars($cat); ?>
                                    </a>

                                </li>

                            <?php endforeach; ?>

                        </ul>

                    </div>


                    <!-- Search Filter -->

                    <div class="sidebar-box">

                        <h3>Search Products</h3>

                        <form
                            action="shop.php"
                            method="GET"
                            class="sidebar-search-form"
                        >

                            <input
                                type="text"
                                name="search"
                                placeholder="Product name..."
                                value="<?php echo htmlspecialchars($search); ?>"
                            >

                            <?php if ($category !== ""): ?>

                                <input
                                    type="hidden"
                                    name="category"
                                    value="<?php echo htmlspecialchars($category); ?>"
                                >

                            <?php endif; ?>

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Search
                            </button>

                        </form>

                    </div>


                    <!-- Reset -->

                    <?php if ($search !== "" || $category !== "" || $sort !== "latest"): ?>

                        <div class="sidebar-box">

                            <a
                                href="shop.php"
                                class="btn btn-secondary btn-full"
                            >
                                Reset Filters
                            </a>

                        </div>

                    <?php endif; ?>

                </aside>


                <!-- =================================================
                     PRODUCTS AREA
                ================================================== -->

                <div class="shop-products">


                    <div class="shop-toolbar">

                        <div class="shop-results">

                            <?php if ($search !== ""): ?>

                                <strong>
                                    Search:
                                </strong>

                                "<?php echo htmlspecialchars($search); ?>"

                            <?php elseif ($category !== ""): ?>

                                <strong>
                                    Category:
                                </strong>

                                <?php echo htmlspecialchars($category); ?>

                            <?php else: ?>

                                <strong>
                                    All Products
                                </strong>

                            <?php endif; ?>

                            <span>
                                — <?php echo count($products); ?> products
                            </span>

                        </div>


                        <form
                            action="shop.php"
                            method="GET"
                            class="sort-form"
                        >

                            <?php if ($search !== ""): ?>

                                <input
                                    type="hidden"
                                    name="search"
                                    value="<?php echo htmlspecialchars($search); ?>"
                                >

                            <?php endif; ?>


                            <?php if ($category !== ""): ?>

                                <input
                                    type="hidden"
                                    name="category"
                                    value="<?php echo htmlspecialchars($category); ?>"
                                >

                            <?php endif; ?>


                            <label for="sort">
                                Sort:
                            </label>

                            <select
                                name="sort"
                                id="sort"
                                onchange="this.form.submit()"
                            >

                                <option
                                    value="latest"
                                    <?php echo $sort === "latest" ? "selected" : ""; ?>
                                >
                                    Latest
                                </option>

                                <option
                                    value="price_low"
                                    <?php echo $sort === "price_low" ? "selected" : ""; ?>
                                >
                                    Price: Low to High
                                </option>

                                <option
                                    value="price_high"
                                    <?php echo $sort === "price_high" ? "selected" : ""; ?>
                                >
                                    Price: High to Low
                                </option>

                                <option
                                    value="name_asc"
                                    <?php echo $sort === "name_asc" ? "selected" : ""; ?>
                                >
                                    Name: A to Z
                                </option>

                                <option
                                    value="name_desc"
                                    <?php echo $sort === "name_desc" ? "selected" : ""; ?>
                                >
                                    Name: Z to A
                                </option>

                            </select>

                        </form>

                    </div>


                    <?php if (!empty($products)): ?>


                        <div class="product-grid">

                            <?php foreach ($products as $product): ?>

                                <div class="product-card">


                                    <!-- Product Image -->

                                    <div class="product-image">

                                        <?php
                                        $image_path = !empty($product['image'])
                                            ? "../assets/images/products/" . $product['image']
                                            : "../assets/images/product-placeholder.jpg";
                                        ?>

                                        <img
                                            src="<?php echo htmlspecialchars($image_path); ?>"
                                            alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                            onerror="this.onerror=null;this.src='../assets/images/product-placeholder.jpg';"
                                        >

                                    </div>


                                    <!-- Product Content -->

                                    <div class="product-content">


                                        <span class="product-category">

                                            <?php
                                            echo htmlspecialchars(
                                                $product['category'] ?: "Engineering"
                                            );
                                            ?>

                                        </span>


                                        <h3>

                                            <?php
                                            echo htmlspecialchars(
                                                $product['product_name']
                                            );
                                            ?>

                                        </h3>


                                        <div class="product-price">

                                            Rs.
                                            <?php
                                            echo number_format(
                                                (float)$product['price'],
                                                2
                                            );
                                            ?>

                                        </div>


                                        <?php if ((int)$product['stock'] > 0): ?>

                                            <span class="stock-status in-stock">
                                                In Stock
                                            </span>

                                        <?php else: ?>

                                            <span class="stock-status out-of-stock">
                                                Out of Stock
                                            </span>

                                        <?php endif; ?>


                                        <div class="product-actions">


                                            <?php if ((int)$product['stock'] > 0): ?>

                                                <a
                                                    href="shop.php?add_to_cart=<?php echo (int)$product['id']; ?>"
                                                    class="btn btn-primary add-to-cart"
                                                >
                                                    Add to Cart
                                                </a>

                                            <?php else: ?>

                                                <button
                                                    type="button"
                                                    class="btn btn-primary"
                                                    disabled
                                                >
                                                    Out of Stock
                                                </button>

                                            <?php endif; ?>


                                            <a
                                                href="shop.php?product=<?php echo (int)$product['id']; ?>"
                                                class="btn btn-secondary"
                                            >
                                                View
                                            </a>

                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>


                    <?php else: ?>


                        <!-- No Products -->

                        <div class="no-products">

                            <div class="no-products-icon">
                                🔍
                            </div>

                            <h2>
                                No Products Found
                            </h2>

                            <p>
                                We couldn't find any products matching
                                your search or selected category.
                            </p>

                            <a
                                href="shop.php"
                                class="btn btn-primary"
                            >
                                View All Products
                            </a>

                        </div>


                    <?php endif; ?>

                </div>

            </div>

        </div>

    </section>


<?php endif; ?>


<?php require_once "../includes/footer.php"; ?>