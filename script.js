/* =========================================================
   SKZ ENGINEERING - MAIN JAVASCRIPT
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    /* =====================================================
       1. MOBILE NAVIGATION
       ===================================================== */

    const navContainer = document.querySelector(".nav-container");

    if (navContainer) {
        navContainer.addEventListener("click", function (event) {

            const link = event.target.closest("a");

            if (!link) {
                return;
            }

            // Allow normal navigation
        });
    }


    /* =====================================================
       2. QUANTITY CONTROLS
       ===================================================== */

    const quantityControls = document.querySelectorAll(".quantity-control");

    quantityControls.forEach(function (control) {

        const minusButton = control.querySelector(".quantity-minus");
        const plusButton = control.querySelector(".quantity-plus");
        const input = control.querySelector("input");

        if (!input) {
            return;
        }

        if (minusButton) {
            minusButton.addEventListener("click", function () {

                let value = parseInt(input.value) || 1;
                const min = parseInt(input.min) || 1;

                if (value > min) {
                    value--;
                    input.value = value;
                    input.dispatchEvent(new Event("change"));
                }
            });
        }

        if (plusButton) {
            plusButton.addEventListener("click", function () {

                let value = parseInt(input.value) || 1;
                const max = parseInt(input.max) || 9999;

                if (value < max) {
                    value++;
                    input.value = value;
                    input.dispatchEvent(new Event("change"));
                }
            });
        }

        input.addEventListener("input", function () {

            let value = parseInt(input.value);

            const min = parseInt(input.min) || 1;
            const max = parseInt(input.max) || 9999;

            if (isNaN(value) || value < min) {
                value = min;
            }

            if (value > max) {
                value = max;
            }

            input.value = value;
        });
    });


    /* =====================================================
       3. AUTO HIDE ALERTS
       ===================================================== */

    const alerts = document.querySelectorAll(".alert");

    alerts.forEach(function (alert) {

        setTimeout(function () {

            alert.style.opacity = "0";
            alert.style.transition = "opacity 0.4s ease";

            setTimeout(function () {
                alert.remove();
            }, 400);

        }, 5000);

    });


    /* =====================================================
       4. DELETE CONFIRMATION
       ===================================================== */

    const deleteButtons = document.querySelectorAll(
        ".delete-btn, .delete-product, .delete-order"
    );

    deleteButtons.forEach(function (button) {

        button.addEventListener("click", function (event) {

            const message =
                button.dataset.confirm ||
                "Are you sure you want to delete this item?";

            if (!confirm(message)) {
                event.preventDefault();
            }
        });

    });


    /* =====================================================
       5. ADD TO CART BUTTON FEEDBACK
       ===================================================== */

    const addToCartButtons = document.querySelectorAll(
        ".add-to-cart"
    );

    addToCartButtons.forEach(function (button) {

        button.addEventListener("click", function () {

            const originalText = button.innerHTML;

            button.innerHTML = "Added ✓";
            button.disabled = true;

            setTimeout(function () {

                button.innerHTML = originalText;
                button.disabled = false;

            }, 1200);

        });

    });


    /* =====================================================
       6. SEARCH FORM
       ===================================================== */

    const searchForms = document.querySelectorAll(".search-form");

    searchForms.forEach(function (form) {

        form.addEventListener("submit", function (event) {

            const input = form.querySelector("input[name='search']");

            if (!input) {
                return;
            }

            input.value = input.value.trim();

            /*
             * If search is empty, let shop.php load normally.
             * PHP will decide what products to display.
             */
        });

    });


    /* =====================================================
       7. CHECKOUT FORM VALIDATION
       ===================================================== */

    const checkoutForm = document.querySelector("#checkout-form");

    if (checkoutForm) {

        checkoutForm.addEventListener("submit", function (event) {

            let valid = true;

            const requiredFields = checkoutForm.querySelectorAll(
                "[required]"
            );

            requiredFields.forEach(function (field) {

                field.classList.remove("input-error");

                if (!field.value.trim()) {

                    valid = false;
                    field.classList.add("input-error");

                }

            });

            const emailField =
                checkoutForm.querySelector("input[type='email']");

            if (
                emailField &&
                emailField.value.trim() !== ""
            ) {

                const emailPattern =
                    /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                if (!emailPattern.test(emailField.value.trim())) {

                    valid = false;
                    emailField.classList.add("input-error");

                }
            }

            if (!valid) {

                event.preventDefault();

                showMessage(
                    "Please fill in all required fields correctly.",
                    "danger"
                );

                const firstError =
                    checkoutForm.querySelector(".input-error");

                if (firstError) {
                    firstError.focus();
                }
            }

        });

    }


    /* =====================================================
       8. CONTACT FORM VALIDATION
       ===================================================== */

    const contactForm = document.querySelector("#contact-form");

    if (contactForm) {

        contactForm.addEventListener("submit", function (event) {

            let valid = true;

            const requiredFields =
                contactForm.querySelectorAll("[required]");

            requiredFields.forEach(function (field) {

                field.classList.remove("input-error");

                if (!field.value.trim()) {

                    valid = false;
                    field.classList.add("input-error");

                }

            });

            const emailField =
                contactForm.querySelector("input[type='email']");

            if (
                emailField &&
                emailField.value.trim() !== ""
            ) {

                const emailPattern =
                    /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                if (!emailPattern.test(emailField.value.trim())) {

                    valid = false;
                    emailField.classList.add("input-error");

                }
            }

            if (!valid) {

                event.preventDefault();

                showMessage(
                    "Please complete the required fields.",
                    "danger"
                );

            }

        });

    }


    /* =====================================================
       9. PRODUCT IMAGE PREVIEW
       ===================================================== */

    const productImageInput =
        document.querySelector("#product-image");

    const productImagePreview =
        document.querySelector("#product-image-preview");

    if (productImageInput && productImagePreview) {

        productImageInput.addEventListener("change", function () {

            const file = this.files[0];

            if (!file) {
                productImagePreview.src = "";
                productImagePreview.style.display = "none";
                return;
            }

            if (!file.type.startsWith("image/")) {

                showMessage(
                    "Please select a valid image file.",
                    "danger"
                );

                this.value = "";
                return;
            }

            const reader = new FileReader();

            reader.onload = function (event) {

                productImagePreview.src =
                    event.target.result;

                productImagePreview.style.display =
                    "block";

            };

            reader.readAsDataURL(file);

        });

    }


    /* =====================================================
       10. CONFIRM ORDER BUTTON
       ===================================================== */

    const orderButtons =
        document.querySelectorAll(".place-order-btn");

    orderButtons.forEach(function (button) {

        button.addEventListener("click", function (event) {

            const form = button.closest("form");

            if (!form) {
                return;
            }

            if (!form.checkValidity()) {
                return;
            }

            const confirmed = confirm(
                "Are you sure you want to place this order?"
            );

            if (!confirmed) {
                event.preventDefault();
            }

        });

    });


    /* =====================================================
       11. CART ITEM QUANTITY UPDATE
       ===================================================== */

    const cartQuantityInputs =
        document.querySelectorAll(".cart-quantity");

    cartQuantityInputs.forEach(function (input) {

        input.addEventListener("change", function () {

            let quantity = parseInt(this.value) || 1;

            const min = parseInt(this.min) || 1;
            const max = parseInt(this.max) || 9999;

            if (quantity < min) {
                quantity = min;
            }

            if (quantity > max) {
                quantity = max;
            }

            this.value = quantity;

            /*
             * The actual cart update will be handled
             * by PHP when we build cart.php.
             */
        });

    });


    /* =====================================================
       12. SMOOTH SCROLL
       ===================================================== */

    const smoothLinks =
        document.querySelectorAll('a[href^="#"]');

    smoothLinks.forEach(function (link) {

        link.addEventListener("click", function (event) {

            const targetId =
                this.getAttribute("href");

            if (
                !targetId ||
                targetId === "#"
            ) {
                return;
            }

            const target =
                document.querySelector(targetId);

            if (!target) {
                return;
            }

            event.preventDefault();

            target.scrollIntoView({
                behavior: "smooth",
                block: "start"
            });

        });

    });


    /* =====================================================
       13. PASSWORD SHOW / HIDE
       ===================================================== */

    const passwordToggles =
        document.querySelectorAll(".password-toggle");

    passwordToggles.forEach(function (toggle) {

        toggle.addEventListener("click", function () {

            const input =
                document.querySelector(
                    toggle.dataset.target
                );

            if (!input) {
                return;
            }

            if (input.type === "password") {

                input.type = "text";
                toggle.innerHTML = "Hide";

            } else {

                input.type = "password";
                toggle.innerHTML = "Show";

            }

        });

    });


    /* =====================================================
       14. ADMIN SIDEBAR
       ===================================================== */

    const adminMenuToggle =
        document.querySelector(".admin-menu-toggle");

    const adminSidebar =
        document.querySelector(".admin-sidebar");

    if (adminMenuToggle && adminSidebar) {

        adminMenuToggle.addEventListener("click", function () {

            adminSidebar.classList.toggle("open");

        });

    }


    /* =====================================================
       15. NUMBER INPUT VALIDATION
       ===================================================== */

    const numberInputs =
        document.querySelectorAll(
            "input[type='number']"
        );

    numberInputs.forEach(function (input) {

        input.addEventListener("input", function () {

            const min =
                this.hasAttribute("min")
                    ? parseFloat(this.min)
                    : null;

            const max =
                this.hasAttribute("max")
                    ? parseFloat(this.max)
                    : null;

            let value = parseFloat(this.value);

            if (isNaN(value)) {
                return;
            }

            if (min !== null && value < min) {
                this.value = min;
            }

            if (max !== null && value > max) {
                this.value = max;
            }

        });

    });


    /* =====================================================
       16. SCROLL TO TOP
       ===================================================== */

    const scrollTopButton =
        document.querySelector(".scroll-top");

    if (scrollTopButton) {

        window.addEventListener("scroll", function () {

            if (window.scrollY > 300) {
                scrollTopButton.classList.add("show");
            } else {
                scrollTopButton.classList.remove("show");
            }

        });

        scrollTopButton.addEventListener("click", function () {

            window.scrollTo({
                top: 0,
                behavior: "smooth"
            });

        });

    }

});


/* =========================================================
   GLOBAL MESSAGE FUNCTION
   ========================================================= */

function showMessage(message, type = "success") {

    const existingMessage =
        document.querySelector(".js-message");

    if (existingMessage) {
        existingMessage.remove();
    }

    const messageBox =
        document.createElement("div");

    messageBox.className =
        "alert alert-" + type + " js-message";

    messageBox.textContent = message;

    const main =
        document.querySelector(".main-content");

    if (main) {

        main.insertBefore(
            messageBox,
            main.firstChild
        );

    } else {

        document.body.prepend(messageBox);

    }

    setTimeout(function () {

        messageBox.style.opacity = "0";
        messageBox.style.transition =
            "opacity 0.4s ease";

        setTimeout(function () {
            messageBox.remove();
        }, 400);

    }, 4000);
}


/* =========================================================
   CART COUNT UPDATE
   ========================================================= */

function updateCartCount(count) {

    const cartLinks =
        document.querySelectorAll(".cart-link");

    cartLinks.forEach(function (cartLink) {

        let badge =
            cartLink.querySelector(".cart-count");

        if (count > 0) {

            if (!badge) {

                badge =
                    document.createElement("span");

                badge.className =
                    "cart-count";

                cartLink.appendChild(badge);

            }

            badge.textContent = count;

        } else {

            if (badge) {
                badge.remove();
            }

        }

    });

}


/* =========================================================
   PRICE FORMATTER
   ========================================================= */

function formatPrice(price) {

    const number =
        Number(price);

    if (isNaN(number)) {
        return "0.00";
    }

    return number.toFixed(2);
}






/* =========================================================
   ADMIN LOGIN
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    const loginForm = document.getElementById("adminLoginForm");
    const emailInput = document.getElementById("email");
    const passwordInput = document.getElementById("password");

    const emailError = document.getElementById("emailError");
    const passwordError = document.getElementById("passwordError");

    const passwordToggle = document.getElementById("passwordToggle");

    const loginButton = document.getElementById("loginButton");
    const loginButtonText = document.getElementById("loginButtonText");


    /*
    |--------------------------------------------------------------------------
    | Password Show / Hide
    |--------------------------------------------------------------------------
    */

    if (passwordToggle && passwordInput) {

        passwordToggle.addEventListener("click", function () {

            if (passwordInput.type === "password") {

                passwordInput.type = "text";

                passwordToggle.textContent = "Hide";

                passwordToggle.setAttribute(
                    "aria-label",
                    "Hide password"
                );

            } else {

                passwordInput.type = "password";

                passwordToggle.textContent = "Show";

                passwordToggle.setAttribute(
                    "aria-label",
                    "Show password"
                );
            }

        });
    }


    /*
    |--------------------------------------------------------------------------
    | Clear Error
    |--------------------------------------------------------------------------
    */

    function clearLoginErrors() {

        if (emailError) {
            emailError.textContent = "";
        }

        if (passwordError) {
            passwordError.textContent = "";
        }

        if (emailInput) {
            emailInput.style.borderColor = "";
        }

        if (passwordInput) {
            passwordInput.style.borderColor = "";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Login Validation
    |--------------------------------------------------------------------------
    */

    if (loginForm) {

        loginForm.addEventListener("submit", function (event) {

            clearLoginErrors();

            let valid = true;

            const email = emailInput
                ? emailInput.value.trim()
                : "";

            const password = passwordInput
                ? passwordInput.value
                : "";


            /*
            |--------------------------------------------------------------
            | Email Validation
            |--------------------------------------------------------------
            */

            if (email === "") {

                if (emailError) {
                    emailError.textContent =
                        "Please enter your email address.";
                }

                if (emailInput) {
                    emailInput.style.borderColor = "#dc3545";
                }

                valid = false;

            } else {

                const emailPattern =
                    /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                if (!emailPattern.test(email)) {

                    if (emailError) {
                        emailError.textContent =
                            "Please enter a valid email address.";
                    }

                    if (emailInput) {
                        emailInput.style.borderColor = "#dc3545";
                    }

                    valid = false;
                }
            }


            /*
            |--------------------------------------------------------------
            | Password Validation
            |--------------------------------------------------------------
            */

            if (password === "") {

                if (passwordError) {
                    passwordError.textContent =
                        "Please enter your password.";
                }

                if (passwordInput) {
                    passwordInput.style.borderColor = "#dc3545";
                }

                valid = false;
            }


            /*
            |--------------------------------------------------------------
            | Stop Submit
            |--------------------------------------------------------------
            */

            if (!valid) {

                event.preventDefault();

                return;
            }


            /*
            |--------------------------------------------------------------
            | Loading State
            |--------------------------------------------------------------
            */

            if (loginButton) {

                loginButton.disabled = true;
            }

            if (loginButtonText) {

                loginButtonText.textContent =
                    "Signing in...";
            }

        });


        /*
        |--------------------------------------------------------------------------
        | Remove Error While Typing
        |--------------------------------------------------------------------------
        */

        if (emailInput) {

            emailInput.addEventListener("input", function () {

                if (emailError) {
                    emailError.textContent = "";
                }

                emailInput.style.borderColor = "";

            });
        }


        if (passwordInput) {

            passwordInput.addEventListener("input", function () {

                if (passwordError) {
                    passwordError.textContent = "";
                }

                passwordInput.style.borderColor = "";

            });
        }

    }

});





// =========================================================
// ADMIN DASHBOARD SIDEBAR
// =========================================================

document.addEventListener("DOMContentLoaded", function () {

    const sidebar = document.getElementById("adminSidebar");
    const sidebarToggle = document.getElementById("sidebarToggle");
    const sidebarClose = document.getElementById("sidebarClose");
    const sidebarOverlay = document.getElementById("sidebarOverlay");


    // Open sidebar
    if (sidebarToggle && sidebar) {

        sidebarToggle.addEventListener("click", function () {

            sidebar.classList.add("sidebar-open");

            if (sidebarOverlay) {
                sidebarOverlay.classList.add("active");
            }

        });

    }


    // Close sidebar
    function closeSidebar() {

        if (sidebar) {
            sidebar.classList.remove("sidebar-open");
        }

        if (sidebarOverlay) {
            sidebarOverlay.classList.remove("active");
        }

    }


    if (sidebarClose) {

        sidebarClose.addEventListener("click", function () {
            closeSidebar();
        });

    }


    if (sidebarOverlay) {

        sidebarOverlay.addEventListener("click", function () {
            closeSidebar();
        });

    }


    // Close sidebar after clicking a navigation link on mobile
    const adminNavLinks = document.querySelectorAll(".admin-nav-link");

    adminNavLinks.forEach(function (link) {

        link.addEventListener("click", function () {

            if (window.innerWidth <= 900) {
                closeSidebar();
            }

        });

    });


    // Close sidebar automatically when resizing to desktop
    window.addEventListener("resize", function () {

        if (window.innerWidth > 900) {
            closeSidebar();
        }

    });

});
/* =========================================================
   ADMIN PRODUCTS
========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    const productModal = document.getElementById("productModal");
    const productForm = document.getElementById("productForm");

    if (!productModal || !productForm) {
        return;
    }

    const modalTitle = document.getElementById("productModalTitle");
    const submitButton = document.getElementById("productSubmitButton");

    const productId = document.getElementById("product_id");
    const productName = document.getElementById("product_name");
    const description = document.getElementById("description");
    const price = document.getElementById("price");
    const category = document.getElementById("category");
    const stock = document.getElementById("stock");
    const sku = document.getElementById("sku");
    const status = document.getElementById("product_status");
    const imageInput = document.getElementById("product_image");


    window.openProductModal = function (mode, product = null) {

        productForm.reset();

        productId.value = "";

        if (imageInput) {
            imageInput.value = "";
        }


        if (mode === "edit" && product) {

            modalTitle.textContent = "Edit Product";

            submitButton.textContent = "Update Product";

            submitButton.name = "update_product";


            productId.value = product.id || "";

            productName.value = product.product_name || "";

            description.value = product.description || "";

            price.value = product.price || "";

            category.value = product.category || "";

            stock.value = product.stock || "";

            sku.value = product.sku || "";

            status.value = product.status || "Active";

        } else {

            modalTitle.textContent = "Add Product";

            submitButton.textContent = "Add Product";

            submitButton.name = "add_product";

            status.value = "Active";
        }


        productModal.classList.add("show");

        document.body.classList.add("modal-open");


        setTimeout(function () {

            productName.focus();

        }, 100);

    };


    window.closeProductModal = function () {

        productModal.classList.remove("show");

        document.body.classList.remove("modal-open");

    };


    const overlay =
        productModal.querySelector(".admin-modal-overlay");

    if (overlay) {

        overlay.addEventListener("click", function () {

            closeProductModal();

        });

    }


    const closeButton =
        productModal.querySelector(".modal-close");

    if (closeButton) {

        closeButton.addEventListener("click", function () {

            closeProductModal();

        });

    }


    document.addEventListener("keydown", function (event) {

        if (event.key === "Escape") {

            if (productModal.classList.contains("show")) {

                closeProductModal();

            }

        }

    });


    /*
    |--------------------------------------------------------------------------
    | Delete Confirmation
    |--------------------------------------------------------------------------
    */

    const deleteForms =
        document.querySelectorAll(".delete-product-form");

    deleteForms.forEach(function (form) {

        form.addEventListener("submit", function (event) {

            const confirmed = confirm(
                "Are you sure you want to delete this product?"
            );

            if (!confirmed) {

                event.preventDefault();

            }

        });

    });


    /*
    |--------------------------------------------------------------------------
    | Image Validation
    |--------------------------------------------------------------------------
    */

    if (imageInput) {

        imageInput.addEventListener("change", function () {

            const file = this.files[0];

            if (!file) {
                return;
            }

            const allowedTypes = [
                "image/jpeg",
                "image/png",
                "image/webp",
                "image/gif"
            ];

            if (!allowedTypes.includes(file.type)) {

                alert(
                    "Please select JPG, PNG, WEBP or GIF image."
                );

                this.value = "";

                return;
            }


            if (file.size > 5 * 1024 * 1024) {

                alert(
                    "Image size must be less than 5MB."
                );

                this.value = "";

            }

        });

    }


    /*
    |--------------------------------------------------------------------------
    | Product Form Validation
    |--------------------------------------------------------------------------
    */

    productForm.addEventListener("submit", function (event) {

        const name = productName.value.trim();

        const priceValue = parseFloat(price.value);

        const stockValue = parseInt(stock.value);


        if (!name) {

            event.preventDefault();

            alert("Please enter product name.");

            productName.focus();

            return;

        }


        if (
            isNaN(priceValue) ||
            priceValue < 0
        ) {

            event.preventDefault();

            alert("Please enter a valid price.");

            price.focus();

            return;

        }


        if (
            isNaN(stockValue) ||
            stockValue < 0
        ) {

            event.preventDefault();

            alert("Please enter a valid stock quantity.");

            stock.focus();

            return;

        }


        submitButton.disabled = true;

        submitButton.textContent =
            submitButton.name === "update_product"
                ? "Updating..."
                : "Adding...";

    });

});



/* =========================================================
   ADMIN ORDERS
========================================================= */
