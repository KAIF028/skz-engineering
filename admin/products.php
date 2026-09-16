<?php

require_once "../config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| ADMIN AUTHENTICATION
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$page_title = "Products - SKZ Engineering";
$success_message = "";
$error_message = "";

/*
|--------------------------------------------------------------------------
| IMAGE HELPERS
|--------------------------------------------------------------------------
*/
function uploadProductImage($file)
{
    if (!isset($file) || !is_array($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $max_size = 5 * 1024 * 1024;

    if ((int)$file['size'] > $max_size) {
        return false;
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $allowed_extensions, true)) {
        return false;
    }

    $upload_dir = "../assets/images/products/";

    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true) && !is_dir($upload_dir)) {
            return false;
        }
    }

    $new_name = "product_" . time() . "_" . bin2hex(random_bytes(5)) . "." . $extension;
    $destination = $upload_dir . $new_name;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return false;
    }

    return $new_name;
}

function deleteProductImage($image)
{
    if (empty($image)) {
        return;
    }

    $file = "../assets/images/products/" . basename($image);

    if (is_file($file)) {
        @unlink($file);
    }
}

/*
|--------------------------------------------------------------------------
| ADD / UPDATE PRODUCT
|--------------------------------------------------------------------------
| Uses one hidden form_action field so the submit button name can never
| get confused by JavaScript.
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $form_action = $_POST['form_action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | ADD PRODUCT
    |--------------------------------------------------------------------------
    */
    if ($form_action === 'add') {

        $product_name = trim($_POST['product_name'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $price        = trim($_POST['price'] ?? '');
        $category     = trim($_POST['category'] ?? '');
        $stock        = trim($_POST['stock'] ?? '');
        $sku          = trim($_POST['sku'] ?? '');
        $status       = $_POST['status'] ?? 'Active';

        if ($product_name === '') {

            $error_message = "Product name is required.";

        } elseif ($price === '' || !is_numeric($price) || (float)$price < 0) {

            $error_message = "Please enter a valid price.";

        } elseif ($stock === '' || !is_numeric($stock) || (int)$stock < 0) {

            $error_message = "Please enter a valid stock quantity.";

        } elseif (!in_array($status, ['Active', 'Inactive'], true)) {

            $error_message = "Invalid product status.";

        } else {

            $price = (float)$price;
            $stock = (int)$stock;
            $sku = ($sku === '') ? null : $sku;

            $image = uploadProductImage($_FILES['image'] ?? null);

            if ($image === false) {

                $error_message = "Invalid image. Use JPG, JPEG, PNG, WEBP or GIF up to 5MB.";

            } else {

                try {

                    $stmt = $conn->prepare("
                        INSERT INTO products
                        (
                            product_name,
                            description,
                            price,
                            image,
                            category,
                            stock,
                            sku,
                            status
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    if (!$stmt) {
                        throw new Exception("Prepare failed: " . $conn->error);
                    }

                    $stmt->bind_param(
                        "ssdssiss",
                        $product_name,
                        $description,
                        $price,
                        $image,
                        $category,
                        $stock,
                        $sku,
                        $status
                    );

                    if ($stmt->execute()) {

                        $success_message = "Product added successfully.";

                    } else {

                        $mysql_error = $stmt->error;

                        if ($image !== null) {
                            deleteProductImage($image);
                        }

                        if ($stmt->errno == 1062) {
                            $error_message = "This SKU already exists. Please use another SKU.";
                        } else {
                            $error_message = "Database Error: " . $mysql_error;
                        }
                    }

                    $stmt->close();

                } catch (Throwable $e) {

                    if ($image !== null) {
                        deleteProductImage($image);
                    }

                    $error_message = "Product could not be added: " . $e->getMessage();
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE PRODUCT
    |--------------------------------------------------------------------------
    */
    if ($form_action === 'update') {

        $id           = (int)($_POST['product_id'] ?? 0);
        $product_name = trim($_POST['product_name'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $price        = trim($_POST['price'] ?? '');
        $category     = trim($_POST['category'] ?? '');
        $stock        = trim($_POST['stock'] ?? '');
        $sku          = trim($_POST['sku'] ?? '');
        $status       = $_POST['status'] ?? 'Active';

        if ($id <= 0) {

            $error_message = "Invalid product.";

        } elseif ($product_name === '') {

            $error_message = "Product name is required.";

        } elseif ($price === '' || !is_numeric($price) || (float)$price < 0) {

            $error_message = "Please enter a valid price.";

        } elseif ($stock === '' || !is_numeric($stock) || (int)$stock < 0) {

            $error_message = "Please enter a valid stock quantity.";

        } elseif (!in_array($status, ['Active', 'Inactive'], true)) {

            $error_message = "Invalid product status.";

        } else {

            $price = (float)$price;
            $stock = (int)$stock;
            $sku = ($sku === '') ? null : $sku;

            try {

                $stmt = $conn->prepare("
                    SELECT image
                    FROM products
                    WHERE id = ?
                    LIMIT 1
                ");

                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }

                $stmt->bind_param("i", $id);
                $stmt->execute();

                $result = $stmt->get_result();
                $existing_product = $result->fetch_assoc();

                $stmt->close();

                if (!$existing_product) {

                    $error_message = "Product not found.";

                } else {

                    $new_image = uploadProductImage($_FILES['image'] ?? null);

                    if ($new_image === false) {

                        $error_message = "Invalid image. Use JPG, JPEG, PNG, WEBP or GIF up to 5MB.";

                    } else {

                        if ($new_image !== null) {

                            $stmt = $conn->prepare("
                                UPDATE products
                                SET
                                    product_name = ?,
                                    description = ?,
                                    price = ?,
                                    image = ?,
                                    category = ?,
                                    stock = ?,
                                    sku = ?,
                                    status = ?
                                WHERE id = ?
                            ");

                            if (!$stmt) {
                                throw new Exception("Prepare failed: " . $conn->error);
                            }

                            $stmt->bind_param(
                                "ssdssissi",
                                $product_name,
                                $description,
                                $price,
                                $new_image,
                                $category,
                                $stock,
                                $sku,
                                $status,
                                $id
                            );

                        } else {

                            $stmt = $conn->prepare("
                                UPDATE products
                                SET
                                    product_name = ?,
                                    description = ?,
                                    price = ?,
                                    category = ?,
                                    stock = ?,
                                    sku = ?,
                                    status = ?
                                WHERE id = ?
                            ");

                            if (!$stmt) {
                                throw new Exception("Prepare failed: " . $conn->error);
                            }

                            $stmt->bind_param(
                                "ssdssisi",
                                $product_name,
                                $description,
                                $price,
                                $category,
                                $stock,
                                $sku,
                                $status,
                                $id
                            );
                        }

                        if ($stmt->execute()) {

                            if ($new_image !== null && !empty($existing_product['image'])) {
                                deleteProductImage($existing_product['image']);
                            }

                            $success_message = "Product updated successfully.";

                        } else {

                            if ($new_image !== null) {
                                deleteProductImage($new_image);
                            }

                            if ($stmt->errno == 1062) {
                                $error_message = "This SKU already exists. Please use another SKU.";
                            } else {
                                $error_message = "Database Error: " . $stmt->error;
                            }
                        }

                        $stmt->close();
                    }
                }

            } catch (Throwable $e) {

                $error_message = "Product could not be updated: " . $e->getMessage();
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE PRODUCT
    |--------------------------------------------------------------------------
    */
    if (isset($_POST['delete_product'])) {

        $id = (int)($_POST['product_id'] ?? 0);

        if ($id <= 0) {

            $error_message = "Invalid product.";

        } else {

            try {

                $stmt = $conn->prepare("
                    SELECT image
                    FROM products
                    WHERE id = ?
                    LIMIT 1
                ");

                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }

                $stmt->bind_param("i", $id);
                $stmt->execute();

                $result = $stmt->get_result();
                $product = $result->fetch_assoc();

                $stmt->close();

                if (!$product) {

                    $error_message = "Product not found.";

                } else {

                    $stmt = $conn->prepare("
                        SELECT COUNT(*) AS total
                        FROM order_items
                        WHERE product_id = ?
                    ");

                    if (!$stmt) {
                        throw new Exception("Prepare failed: " . $conn->error);
                    }

                    $stmt->bind_param("i", $id);
                    $stmt->execute();

                    $result = $stmt->get_result();
                    $usage = $result->fetch_assoc();

                    $stmt->close();

                    if ((int)$usage['total'] > 0) {

                        $stmt = $conn->prepare("
                            UPDATE products
                            SET status = 'Inactive'
                            WHERE id = ?
                        ");

                        if (!$stmt) {
                            throw new Exception("Prepare failed: " . $conn->error);
                        }

                        $stmt->bind_param("i", $id);

                        if ($stmt->execute()) {
                            $success_message = "This product is already used in an order, so it was moved to Inactive instead of being deleted.";
                        } else {
                            $error_message = "Database Error: " . $stmt->error;
                        }

                        $stmt->close();

                    } else {

                        $stmt = $conn->prepare("
                            DELETE FROM products
                            WHERE id = ?
                        ");

                        if (!$stmt) {
                            throw new Exception("Prepare failed: " . $conn->error);
                        }

                        $stmt->bind_param("i", $id);

                        if ($stmt->execute()) {

                            deleteProductImage($product['image']);
                            $success_message = "Product deleted successfully.";

                        } else {

                            $error_message = "Database Error: " . $stmt->error;
                        }

                        $stmt->close();
                    }
                }

            } catch (Throwable $e) {

                $error_message = "Delete failed: " . $e->getMessage();
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | TOGGLE STATUS
    |--------------------------------------------------------------------------
    */
    if (isset($_POST['toggle_status'])) {

        $id = (int)($_POST['product_id'] ?? 0);

        if ($id > 0) {

            try {

                $stmt = $conn->prepare("
                    UPDATE products
                    SET status =
                        CASE
                            WHEN status = 'Active' THEN 'Inactive'
                            ELSE 'Active'
                        END
                    WHERE id = ?
                ");

                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }

                $stmt->bind_param("i", $id);

                if ($stmt->execute()) {
                    $success_message = "Product status updated.";
                } else {
                    $error_message = "Database Error: " . $stmt->error;
                }

                $stmt->close();

            } catch (Throwable $e) {

                $error_message = "Status update failed: " . $e->getMessage();
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| SEARCH / FILTER
|--------------------------------------------------------------------------
*/
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$category_filter = trim($_GET['category'] ?? '');

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
        sku,
        status,
        created_at,
        updated_at
    FROM products
    WHERE 1 = 1
";

$params = [];
$types = "";

if ($search !== '') {

    $sql .= "
        AND (
            product_name LIKE ?
            OR sku LIKE ?
            OR category LIKE ?
        )
    ";

    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "sss";
}

if (in_array($status_filter, ['Active', 'Inactive'], true)) {

    $sql .= " AND status = ? ";

    $params[] = $status_filter;
    $types .= "s";
}

if ($category_filter !== '') {

    $sql .= " AND category = ? ";

    $params[] = $category_filter;
    $types .= "s";
}

$sql .= " ORDER BY id DESC";

try {

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    $stmt->close();

} catch (Throwable $e) {

    $error_message = "Unable to load products: " . $e->getMessage();
}

/*
|--------------------------------------------------------------------------
| CATEGORIES
|--------------------------------------------------------------------------
*/
$categories = [];

try {

    $category_result = $conn->query("
        SELECT DISTINCT category
        FROM products
        WHERE category IS NOT NULL
        AND category != ''
        ORDER BY category ASC
    ");

    if ($category_result) {
        while ($row = $category_result->fetch_assoc()) {
            $categories[] = $row['category'];
        }
    }

} catch (Throwable $e) {
    // Keep page usable even if categories fail.
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= htmlspecialchars($page_title) ?></title>

<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

html,
body {
    min-height: 100%;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f7fa;
    color: #17212b;
    line-height: 1.5;
}

button,
input,
select,
textarea {
    font-family: inherit;
}

button {
    cursor: pointer;
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

.admin-sidebar {
    width: 250px;
    min-height: 100vh;
    background: #111c28;
    color: #fff;
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    z-index: 100;
    overflow-y: auto;
}

.admin-brand {
    min-height: 82px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid rgba(255,255,255,.08);
}

.admin-brand-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: #fdbb19;
    color: #111c28;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    font-weight: 800;
}

.admin-brand h2 {
    font-size: 15px;
    margin-bottom: 2px;
}

.admin-brand span {
    color: #9ba7b4;
    font-size: 12px;
}

.admin-nav {
    padding: 20px 12px;
}

.admin-nav a {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 46px;
    padding: 0 14px;
    margin-bottom: 6px;
    border-radius: 10px;
    color: #cbd5df;
    font-size: 14px;
    font-weight: 600;
    transition: .2s ease;
}

.admin-nav a span {
    width: 22px;
    text-align: center;
}

.admin-nav a:hover {
    background: rgba(255,255,255,.07);
    color: #fff;
}

.admin-nav a.active {
    background: #fdbb19;
    color: #111c28;
}

.admin-nav .logout-link {
    margin-top: 25px;
    color: #ffb4b0;
}

.admin-nav .logout-link:hover {
    background: rgba(217,48,37,.12);
    color: #ff8179;
}


/* =========================================================
   MAIN
========================================================= */

.admin-main {
    width: calc(100% - 250px);
    margin-left: 250px;
    min-height: 100vh;
}

.admin-topbar {
    min-height: 88px;
    background: #fff;
    border-bottom: 1px solid #e4e8ed;
    padding: 18px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.admin-topbar h1 {
    font-size: 27px;
    color: #111c28;
    margin-bottom: 3px;
}

.admin-topbar p {
    color: #6d7780;
    font-size: 13px;
}

.admin-user {
    display: flex;
    align-items: center;
    gap: 10px;
}

.admin-user-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #111c28;
    color: #fdbb19;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
}

.admin-user strong {
    display: block;
    font-size: 14px;
}

.admin-user small {
    color: #7b8794;
    font-size: 12px;
}

.admin-content {
    padding: 30px;
    max-width: 1500px;
    margin: 0 auto;
}


/* =========================================================
   ALERTS
========================================================= */

.admin-alert {
    padding: 14px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
    font-weight: 600;
}

.admin-alert.success {
    background: #eaf8ef;
    color: #17733d;
    border: 1px solid #bde7ca;
}

.admin-alert.error {
    background: #fff0ef;
    color: #b42318;
    border: 1px solid #f4c5c1;
}


/* =========================================================
   BUTTONS
========================================================= */

.admin-btn {
    min-height: 42px;
    border: 0;
    border-radius: 9px;
    padding: 0 17px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    font-size: 13px;
    font-weight: 700;
    transition: .2s ease;
}

.admin-btn.primary {
    background: #111c28;
    color: #fff;
}

.admin-btn.primary:hover {
    background: #1d3042;
}

.admin-btn.secondary {
    background: #eef1f4;
    color: #344454;
}

.admin-btn.secondary:hover {
    background: #e0e5ea;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.products-page-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 20px;
}

.products-page-head h2 {
    font-size: 22px;
    color: #17212b;
    margin-bottom: 3px;
}

.products-page-head p {
    color: #77828d;
    font-size: 13px;
}


/* =========================================================
   FILTERS
========================================================= */

.product-filters {
    background: #fff;
    border: 1px solid #e3e7eb;
    border-radius: 13px;
    padding: 20px;
    margin-bottom: 22px;
}

.product-filter-form {
    display: grid;
    grid-template-columns: minmax(240px, 1fr) 180px 200px auto;
    align-items: end;
    gap: 14px;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.filter-group label {
    font-size: 12px;
    font-weight: 700;
    color: #465363;
}

.filter-group input,
.filter-group select {
    width: 100%;
    height: 42px;
    border: 1px solid #d4dbe2;
    border-radius: 8px;
    padding: 0 12px;
    background: #fff;
    color: #253342;
    outline: none;
    font-size: 13px;
}

.filter-group input:focus,
.filter-group select:focus {
    border-color: #fdbb19;
    box-shadow: 0 0 0 3px rgba(253,187,25,.13);
}

.filter-actions {
    display: flex;
    gap: 8px;
}


/* =========================================================
   TABLE
========================================================= */

.admin-card {
    background: #fff;
    border: 1px solid #e3e7eb;
    border-radius: 13px;
    overflow: hidden;
}

.admin-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

.admin-table th {
    background: #f8fafb;
    color: #687482;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .04em;
    text-align: left;
    padding: 14px 18px;
    border-bottom: 1px solid #e4e8ec;
}

.admin-table td {
    padding: 14px 18px;
    border-bottom: 1px solid #edf0f2;
    vertical-align: middle;
    font-size: 13px;
}

.admin-table tbody tr:hover {
    background: #fafbfc;
}

.admin-table tbody tr:last-child td {
    border-bottom: 0;
}


/* =========================================================
   PRODUCT
========================================================= */

.product-table-info {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 210px;
}

.product-thumb {
    width: 52px;
    height: 52px;
    flex-shrink: 0;
    border-radius: 9px;
    background: #f1f3f5;
    overflow: hidden;
    border: 1px solid #e2e6ea;
}

.product-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-table-info strong {
    display: block;
    color: #253342;
    font-size: 13px;
    margin-bottom: 3px;
}

.product-table-info small {
    display: block;
    color: #89939e;
    font-size: 11px;
}

.muted {
    color: #a2aab2;
}


/* =========================================================
   BADGES
========================================================= */

.stock-badge,
.status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 27px;
    padding: 0 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.stock-badge.good {
    background: #eaf8ef;
    color: #188348;
}

.stock-badge.low {
    background: #fff7df;
    color: #a36b00;
}

.stock-badge.out {
    background: #fff0ef;
    color: #c62828;
}

.status-badge.active {
    background: #eaf8ef;
    color: #188348;
}

.status-badge.inactive {
    background: #f0f2f4;
    color: #687482;
}


/* =========================================================
   ACTIONS
========================================================= */

.product-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

.inline-form {
    display: inline-flex;
    margin: 0;
}

.table-action {
    width: 34px;
    height: 34px;
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    background: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    transition: .2s;
}

.table-action.edit:hover {
    background: #eef5ff;
    border-color: #b9d4ff;
}

.table-action.status:hover {
    background: #fff8e5;
    border-color: #f6d878;
}

.table-action.delete:hover {
    background: #fff0ef;
    border-color: #f2bbb7;
}


/* =========================================================
   EMPTY
========================================================= */

.admin-empty {
    padding: 70px 20px;
    text-align: center;
}

.empty-icon {
    font-size: 45px;
    margin-bottom: 12px;
}

.admin-empty h3 {
    font-size: 19px;
    margin-bottom: 6px;
}

.admin-empty p {
    color: #78838e;
    font-size: 13px;
    margin-bottom: 18px;
}


/* =========================================================
   MODAL
========================================================= */

.admin-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 23, 42, .72);
    overflow-y: auto;
}

.admin-modal.show {
    display: flex;
}

.admin-modal-overlay {
    position: absolute;
    inset: 0;
}

.admin-modal-box {
    position: relative;
    z-index: 2;
    width: 100%;
    max-width: 650px;
    max-height: calc(100vh - 40px);
    background: #fff;
    border-radius: 24px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 25px 70px rgba(0,0,0,.28);
    animation: modalOpen .2s ease-out;
}

@keyframes modalOpen {
    from {
        opacity: 0;
        transform: translateY(15px) scale(.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}


/* =========================================================
   MODAL HEADER
========================================================= */

.admin-modal-header {
    flex-shrink: 0;
    padding: 28px 40px 22px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    border-bottom: 1px solid #e4e8ed;
}

.admin-modal-header h2 {
    font-size: 30px;
    line-height: 1.2;
    color: #111c28;
    margin: 0 0 7px;
}

.admin-modal-header p {
    color: #657384;
    font-size: 16px;
}

.modal-close {
    width: 56px;
    height: 56px;
    flex-shrink: 0;
    border: 0;
    border-radius: 15px;
    background: #f1f3f6;
    color: #476076;
    font-size: 32px;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: .2s;
}

.modal-close:hover {
    background: #e5e8ec;
    color: #111c28;
}


/* =========================================================
   MODAL BODY
========================================================= */

#productForm {
    min-height: 0;
    display: flex;
    flex-direction: column;
}

.admin-modal-body {
    flex: 1;
    min-height: 0;
    padding: 28px 40px;
    overflow-y: auto;
}

.admin-modal-body::-webkit-scrollbar {
    width: 7px;
}

.admin-modal-body::-webkit-scrollbar-track {
    background: transparent;
}

.admin-modal-body::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}

.product-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group.full {
    grid-column: 1 / -1;
}

.form-group label {
    font-size: 16px;
    font-weight: 700;
    color: #344054;
}

.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    border: 1px solid #cbd5df;
    border-radius: 14px;
    background: #fff;
    color: #263445;
    font-size: 16px;
    outline: none;
    transition: .2s;
}

.form-group input,
.form-group select {
    height: 52px;
    padding: 0 18px;
}

.form-group textarea {
    min-height: 130px;
    padding: 15px 18px;
    resize: vertical;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    border-color: #3975ff;
    box-shadow: 0 0 0 4px rgba(57,117,255,.12);
}

.form-group input::placeholder,
.form-group textarea::placeholder {
    color: #858585;
}


/* =========================================================
   PRICE
========================================================= */

.input-prefix {
    height: 52px;
    display: flex;
    align-items: center;
    border: 1px solid #cbd5df;
    border-radius: 14px;
    overflow: hidden;
    background: #fff;
    transition: .2s;
}

.input-prefix:focus-within {
    border-color: #3975ff;
    box-shadow: 0 0 0 4px rgba(57,117,255,.12);
}

.input-prefix span {
    padding-left: 18px;
    color: #727b86;
    font-size: 16px;
    white-space: nowrap;
}

.input-prefix input {
    height: 100%;
    border: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    padding-left: 9px;
}


/* =========================================================
   FILE
========================================================= */

.form-group input[type="file"] {
    height: auto;
    padding: 13px 14px;
    cursor: pointer;
    background: #f8fafc;
}

.form-help {
    color: #7a8693;
    font-size: 12px;
}


/* =========================================================
   MODAL FOOTER
========================================================= */

.admin-modal-footer {
    flex-shrink: 0;
    padding: 18px 40px;
    border-top: 1px solid #e4e8ed;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
}

.admin-modal-footer .admin-btn {
    min-width: 115px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1100px) {

    .product-filter-form {
        grid-template-columns: 1fr 1fr;
    }

    .filter-actions {
        grid-column: 1 / -1;
    }
}

@media (max-width: 850px) {

    .admin-sidebar {
        width: 210px;
    }

    .admin-main {
        width: calc(100% - 210px);
        margin-left: 210px;
    }

    .admin-content {
        padding: 22px;
    }

    .admin-topbar {
        padding: 18px 22px;
    }
}

@media (max-width: 700px) {

    .admin-sidebar {
        position: static;
        width: 100%;
        min-height: auto;
    }

    .admin-layout {
        display: block;
    }

    .admin-main {
        width: 100%;
        margin-left: 0;
    }

    .admin-nav {
        display: flex;
        overflow-x: auto;
        padding: 10px;
        gap: 5px;
    }

    .admin-nav a {
        flex-shrink: 0;
        margin: 0;
    }

    .admin-nav .logout-link {
        margin-top: 0;
    }

    .admin-topbar {
        flex-direction: column;
        align-items: flex-start;
    }

    .admin-user {
        width: 100%;
    }

    .products-page-head {
        flex-direction: column;
        align-items: flex-start;
    }

    .product-filter-form {
        grid-template-columns: 1fr;
    }

    .filter-actions {
        grid-column: auto;
    }

    .admin-content {
        padding: 16px;
    }

    .admin-modal {
        padding: 10px;
    }

    .admin-modal-box {
        max-height: calc(100vh - 20px);
        border-radius: 18px;
    }

    .admin-modal-header {
        padding: 20px;
    }

    .admin-modal-header h2 {
        font-size: 24px;
    }

    .admin-modal-header p {
        font-size: 14px;
    }

    .modal-close {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        font-size: 28px;
    }

    .admin-modal-body {
        padding: 22px 20px;
    }

    .product-form-grid {
        grid-template-columns: 1fr;
        gap: 18px;
    }

    .form-group.full {
        grid-column: auto;
    }

    .admin-modal-footer {
        padding: 15px 20px;
        flex-direction: column-reverse;
        align-items: stretch;
    }

    .admin-modal-footer .admin-btn {
        width: 100%;
    }
}

@media (max-width: 450px) {

    .admin-brand {
        padding: 14px;
    }

    .admin-topbar h1 {
        font-size: 23px;
    }

    .products-page-head h2 {
        font-size: 20px;
    }

    .admin-modal-header {
        padding: 17px;
    }

    .admin-modal-body {
        padding: 18px 17px;
    }

    .admin-modal-footer {
        padding: 13px 17px;
    }
}

</style>

</head>

<body>

<div class="admin-layout">

    <!-- SIDEBAR -->

    <aside class="admin-sidebar">

        <div class="admin-brand">

            <div class="admin-brand-icon">
                SKZ
            </div>

            <div>
                <h2>SKZ Engineering</h2>
                <span>Admin Panel</span>
            </div>

        </div>

        <nav class="admin-nav">

            <a href="dashboard.php">
                <span>📊</span>
                Dashboard
            </a>

            <a href="products.php" class="active">
                <span>📦</span>
                Products
            </a>

            <a href="orders.php">
                <span>🛒</span>
                Orders
            </a>

            <a href="../user/index.php" target="_blank">
                <span>🌐</span>
                View Website
            </a>

            <a href="logout.php" class="logout-link">
                <span>🚪</span>
                Logout
            </a>

        </nav>

    </aside>


    <!-- MAIN -->

    <main class="admin-main">

        <header class="admin-topbar">

            <div>

                <h1>Products</h1>

                <p>Manage your SKZ Engineering products</p>

            </div>

            <div class="admin-user">

                <div class="admin-user-avatar">
                    <?= htmlspecialchars(strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1))) ?>
                </div>

                <div>
                    <strong>
                        <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?>
                    </strong>

                    <small>Administrator</small>
                </div>

            </div>

        </header>


        <div class="admin-content">

            <?php if ($success_message): ?>

                <div class="admin-alert success">
                    <?= htmlspecialchars($success_message) ?>
                </div>

            <?php endif; ?>


            <?php if ($error_message): ?>

                <div class="admin-alert error">
                    <?= htmlspecialchars($error_message) ?>
                </div>

            <?php endif; ?>


            <div class="products-page-head">

                <div>

                    <h2>All Products</h2>

                    <p>
                        <?= count($products) ?> product(s) found
                    </p>

                </div>

                <button
                    type="button"
                    class="admin-btn primary"
                    onclick="openProductModal('add')"
                >
                    + Add Product
                </button>

            </div>


            <!-- FILTERS -->

            <div class="product-filters">

                <form
                    method="GET"
                    action="products.php"
                    class="product-filter-form"
                >

                    <div class="filter-group">

                        <label>Search</label>

                        <input
                            type="text"
                            name="search"
                            placeholder="Search product, SKU or category..."
                            value="<?= htmlspecialchars($search) ?>"
                        >

                    </div>


                    <div class="filter-group">

                        <label>Status</label>

                        <select name="status">

                            <option value="">All Status</option>

                            <option
                                value="Active"
                                <?= $status_filter === 'Active' ? 'selected' : '' ?>
                            >
                                Active
                            </option>

                            <option
                                value="Inactive"
                                <?= $status_filter === 'Inactive' ? 'selected' : '' ?>
                            >
                                Inactive
                            </option>

                        </select>

                    </div>


                    <div class="filter-group">

                        <label>Category</label>

                        <select name="category">

                            <option value="">All Categories</option>

                            <?php foreach ($categories as $category): ?>

                                <option
                                    value="<?= htmlspecialchars($category) ?>"
                                    <?= $category_filter === $category ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($category) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="filter-actions">

                        <button
                            type="submit"
                            class="admin-btn primary"
                        >
                            Filter
                        </button>

                        <a
                            href="products.php"
                            class="admin-btn secondary"
                        >
                            Reset
                        </a>

                    </div>

                </form>

            </div>


            <!-- PRODUCTS -->

            <div class="admin-card products-table-card">

                <?php if (empty($products)): ?>

                    <div class="admin-empty">

                        <div class="empty-icon">
                            📦
                        </div>

                        <h3>No Products Found</h3>

                        <p>
                            There are no products matching your search.
                        </p>

                        <button
                            type="button"
                            class="admin-btn primary"
                            onclick="openProductModal('add')"
                        >
                            Add First Product
                        </button>

                    </div>

                <?php else: ?>

                    <div class="admin-table-wrapper">

                        <table class="admin-table">

                            <thead>

                                <tr>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>

                            </thead>

                            <tbody>

                            <?php foreach ($products as $product): ?>

                                <tr>

                                    <td>

                                        <div class="product-table-info">

                                            <div class="product-thumb">

                                                <?php if (!empty($product['image'])): ?>

                                                    <img
                                                        src="../assets/images/products/<?= htmlspecialchars($product['image']) ?>"
                                                        alt="<?= htmlspecialchars($product['product_name']) ?>"
                                                    >

                                                <?php else: ?>

                                                    <img
                                                        src="../assets/images/product-placeholder.jpg"
                                                        alt="Product"
                                                    >

                                                <?php endif; ?>

                                            </div>

                                            <div>

                                                <strong>
                                                    <?= htmlspecialchars($product['product_name']) ?>
                                                </strong>

                                                <small>
                                                    ID #<?= (int)$product['id'] ?>
                                                </small>

                                            </div>

                                        </div>

                                    </td>


                                    <td>
                                        <?php if (!empty($product['sku'])): ?>
                                            <?= htmlspecialchars($product['sku']) ?>
                                        <?php else: ?>
                                            <span class="muted">—</span>
                                        <?php endif; ?>
                                    </td>


                                    <td>
                                        <?php if (!empty($product['category'])): ?>
                                            <?= htmlspecialchars($product['category']) ?>
                                        <?php else: ?>
                                            <span class="muted">—</span>
                                        <?php endif; ?>
                                    </td>


                                    <td>
                                        <strong>
                                            Rs. <?= number_format((float)$product['price'], 2) ?>
                                        </strong>
                                    </td>


                                    <td>

                                        <?php

                                        $stock = (int)$product['stock'];

                                        if ($stock <= 0) {
                                            $stock_class = 'out';
                                            $stock_text = 'Out of Stock';
                                        } elseif ($stock <= 5) {
                                            $stock_class = 'low';
                                            $stock_text = $stock . ' left';
                                        } else {
                                            $stock_class = 'good';
                                            $stock_text = (string)$stock;
                                        }

                                        ?>

                                        <span class="stock-badge <?= $stock_class ?>">
                                            <?= htmlspecialchars($stock_text) ?>
                                        </span>

                                    </td>


                                    <td>

                                        <span class="status-badge <?= strtolower($product['status']) ?>">
                                            <?= htmlspecialchars($product['status']) ?>
                                        </span>

                                    </td>


                                    <td>

                                        <div class="product-actions">

                                            <button
                                                type="button"
                                                class="table-action edit"
                                                title="Edit"
                                                onclick='openProductModal("edit", <?= json_encode([
                                                    "id" => (int)$product["id"],
                                                    "product_name" => $product["product_name"],
                                                    "description" => $product["description"],
                                                    "price" => $product["price"],
                                                    "category" => $product["category"],
                                                    "stock" => $product["stock"],
                                                    "sku" => $product["sku"],
                                                    "status" => $product["status"]
                                                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                            >
                                                ✏️
                                            </button>


                                            <form
                                                method="POST"
                                                class="inline-form"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="product_id"
                                                    value="<?= (int)$product['id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="toggle_status"
                                                    class="table-action status"
                                                    title="Toggle Status"
                                                >
                                                    <?= $product['status'] === 'Active' ? '⏸️' : '▶️' ?>
                                                </button>

                                            </form>


                                            <form
                                                method="POST"
                                                class="inline-form delete-product-form"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="product_id"
                                                    value="<?= (int)$product['id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="delete_product"
                                                    class="table-action delete"
                                                    title="Delete"
                                                >
                                                    🗑️
                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </main>

</div>


<!-- =========================================================
     PRODUCT MODAL
========================================================= -->

<div
    class="admin-modal"
    id="productModal"
    aria-hidden="true"
>

    <div class="admin-modal-overlay"></div>

    <div
        class="admin-modal-box"
        role="dialog"
        aria-modal="true"
        aria-labelledby="productModalTitle"
    >

        <div class="admin-modal-header">

            <div>

                <h2 id="productModalTitle">
                    Add Product
                </h2>

                <p>
                    Enter product information below
                </p>

            </div>

            <button
                type="button"
                class="modal-close"
                onclick="closeProductModal()"
                aria-label="Close"
            >
                ×
            </button>

        </div>


        <form
            method="POST"
            enctype="multipart/form-data"
            id="productForm"
        >

            <!-- IMPORTANT: This controls Add vs Update -->

            <input
                type="hidden"
                name="form_action"
                id="form_action"
                value="add"
            >

            <input
                type="hidden"
                name="product_id"
                id="product_id"
                value=""
            >


            <div class="admin-modal-body">

                <div class="product-form-grid">


                    <div class="form-group full">

                        <label for="product_name">
                            Product Name *
                        </label>

                        <input
                            type="text"
                            name="product_name"
                            id="product_name"
                            required
                            maxlength="200"
                            placeholder="Enter product name"
                        >

                    </div>


                    <div class="form-group full">

                        <label for="description">
                            Description
                        </label>

                        <textarea
                            name="description"
                            id="description"
                            rows="4"
                            placeholder="Enter product description"
                        ></textarea>

                    </div>


                    <div class="form-group">

                        <label for="price">
                            Price *
                        </label>

                        <div class="input-prefix">

                            <span>Rs.</span>

                            <input
                                type="number"
                                name="price"
                                id="price"
                                step="0.01"
                                min="0"
                                required
                                placeholder="0.00"
                            >

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="stock">
                            Stock *
                        </label>

                        <input
                            type="number"
                            name="stock"
                            id="stock"
                            min="0"
                            required
                            placeholder="0"
                        >

                    </div>


                    <div class="form-group">

                        <label for="category">
                            Category
                        </label>

                        <input
                            type="text"
                            name="category"
                            id="category"
                            maxlength="100"
                            placeholder="e.g. Power Tools"
                        >

                    </div>


                    <div class="form-group">

                        <label for="sku">
                            SKU
                        </label>

                        <input
                            type="text"
                            name="sku"
                            id="sku"
                            maxlength="100"
                            placeholder="e.g. SKZ-DRL-001"
                        >

                    </div>


                    <div class="form-group">

                        <label for="product_status">
                            Status
                        </label>

                        <select
                            name="status"
                            id="product_status"
                        >

                            <option value="Active">
                                Active
                            </option>

                            <option value="Inactive">
                                Inactive
                            </option>

                        </select>

                    </div>


                    <div class="form-group">

                        <label for="product_image">
                            Product Image
                        </label>

                        <input
                            type="file"
                            name="image"
                            id="product_image"
                            accept=".jpg,.jpeg,.png,.webp,.gif"
                        >

                        <small class="form-help">
                            JPG, JPEG, PNG, WEBP or GIF. Max 5MB.
                        </small>

                    </div>

                </div>

            </div>


            <div class="admin-modal-footer">

                <button
                    type="button"
                    class="admin-btn secondary"
                    onclick="closeProductModal()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    id="productSubmitButton"
                    class="admin-btn primary"
                >
                    Add Product
                </button>

            </div>

        </form>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| OPEN PRODUCT MODAL
|--------------------------------------------------------------------------
*/

function openProductModal(mode, product = null) {

    const modal = document.getElementById("productModal");
    const form = document.getElementById("productForm");

    const formAction = document.getElementById("form_action");
    const productId = document.getElementById("product_id");

    const title = document.getElementById("productModalTitle");
    const submitButton = document.getElementById("productSubmitButton");

    const productName = document.getElementById("product_name");
    const description = document.getElementById("description");
    const price = document.getElementById("price");
    const stock = document.getElementById("stock");
    const category = document.getElementById("category");
    const sku = document.getElementById("sku");
    const status = document.getElementById("product_status");
    const image = document.getElementById("product_image");

    if (!modal || !form) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | RESET FORM
    |--------------------------------------------------------------------------
    */

    form.reset();

    productId.value = "";

    formAction.value = "add";

    status.value = "Active";


    /*
    |--------------------------------------------------------------------------
    | ADD MODE
    |--------------------------------------------------------------------------
    */

    if (mode === "add") {

        title.textContent = "Add Product";

        submitButton.textContent = "Add Product";

        formAction.value = "add";

        productId.value = "";

        status.value = "Active";

    }


    /*
    |--------------------------------------------------------------------------
    | EDIT MODE
    |--------------------------------------------------------------------------
    */

    if (mode === "edit" && product) {

        title.textContent = "Edit Product";

        submitButton.textContent = "Update Product";

        formAction.value = "update";

        productId.value = product.id || "";

        productName.value = product.product_name || "";

        description.value = product.description || "";

        price.value = product.price || "";

        stock.value = product.stock || "";

        category.value = product.category || "";

        sku.value = product.sku || "";

        status.value = product.status || "Active";

        /*
        Image input is intentionally empty during edit.
        User can select a new image only if needed.
        */

        image.value = "";

    }


    /*
    |--------------------------------------------------------------------------
    | SHOW MODAL
    |--------------------------------------------------------------------------
    */

    modal.classList.add("show");

    modal.setAttribute("aria-hidden", "false");

    document.body.style.overflow = "hidden";


    setTimeout(function () {

        productName.focus();

    }, 100);

}


/*
|--------------------------------------------------------------------------
| CLOSE PRODUCT MODAL
|--------------------------------------------------------------------------
*/

function closeProductModal() {

    const modal = document.getElementById("productModal");

    if (!modal) {
        return;
    }

    modal.classList.remove("show");

    modal.setAttribute("aria-hidden", "true");

    document.body.style.overflow = "";

}


/*
|--------------------------------------------------------------------------
| DOM READY
|--------------------------------------------------------------------------
*/

document.addEventListener("DOMContentLoaded", function () {

    const modal = document.getElementById("productModal");

    if (!modal) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | CLOSE OVERLAY
    |--------------------------------------------------------------------------
    */

    const overlay = modal.querySelector(".admin-modal-overlay");

    if (overlay) {

        overlay.addEventListener("click", function () {
            closeProductModal();
        });

    }


    /*
    |--------------------------------------------------------------------------
    | ESCAPE
    |--------------------------------------------------------------------------
    */

    document.addEventListener("keydown", function (event) {

        if (event.key === "Escape") {
            closeProductModal();
        }

    });


    /*
    |--------------------------------------------------------------------------
    | DELETE CONFIRMATION
    |--------------------------------------------------------------------------
    */

    document.querySelectorAll(".delete-product-form").forEach(function (form) {

        form.addEventListener("submit", function (event) {

            const confirmed = confirm(
                "Are you sure you want to delete this product?"
            );

            if (!confirmed) {
                event.preventDefault();
            }

        });

    });

});

</script>

</body>
</html>
