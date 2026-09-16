<?php

require_once "../config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = "Contact Us - SKZ Engineering";

$success_message = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $subject = trim($_POST["subject"] ?? "");
    $message = trim($_POST["message"] ?? "");

    if ($name === "") {
        $error_message = "Please enter your name.";
    } elseif ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif ($message === "") {
        $error_message = "Please enter your message.";
    } else {

        $stmt = $conn->prepare("
            INSERT INTO contacts 
            (name, email, phone, subject, message, status)
            VALUES (?, ?, ?, ?, ?, 'New')
        ");

        if ($stmt) {

            $stmt->bind_param(
                "sssss",
                $name,
                $email,
                $phone,
                $subject,
                $message
            );

            if ($stmt->execute()) {
                $success_message = "Thank you! Your message has been sent successfully.";

                $name = "";
                $email = "";
                $phone = "";
                $subject = "";
                $message = "";
            } else {
                $error_message = "Something went wrong. Please try again.";
            }

            $stmt->close();

        } else {
            $error_message = "Unable to process your request.";
        }
    }
}

include "../includes/header.php";

?>

<style>

/* =========================================
   CONTACT PAGE
========================================= */

.contact-page {
    background: #f5f7fa;
    padding: 55px 20px 70px;
}

.contact-container {
    max-width: 1200px;
    margin: 0 auto;
}

/* Page Heading */

.contact-heading {
    text-align: center;
    margin-bottom: 45px;
}

.contact-heading h1 {
    margin: 0 0 12px;
    color: #0b1f3a;
    font-size: 42px;
    font-weight: 800;
}

.contact-heading p {
    margin: 0 auto;
    max-width: 650px;
    color: #687385;
    font-size: 16px;
    line-height: 1.7;
}

/* Main Grid */

.contact-grid {
    display: grid;
    grid-template-columns: 0.9fr 1.4fr;
    gap: 30px;
    align-items: stretch;
}

/* Left Contact Information */

.contact-info-box {
    background: #0b1f3a;
    border-radius: 12px;
    padding: 38px;
    color: #ffffff;
    box-shadow: 0 10px 30px rgba(11, 31, 58, 0.12);
}

.contact-info-box h2 {
    margin: 0 0 12px;
    font-size: 27px;
    font-weight: 700;
}

.contact-info-box > p {
    margin: 0 0 30px;
    color: #d9e1ec;
    font-size: 15px;
    line-height: 1.7;
}

/* Contact Item */

.contact-info-item {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 25px;
}

.contact-info-icon {
    width: 48px;
    height: 48px;
    min-width: 48px;
    border-radius: 8px;
    background: #f4c400;
    color: #0b1f3a;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 21px;
    font-weight: bold;
}

.contact-info-content h3 {
    margin: 0 0 5px;
    color: #ffffff;
    font-size: 16px;
}

.contact-info-content p,
.contact-info-content a {
    margin: 0;
    color: #d9e1ec;
    font-size: 14px;
    line-height: 1.6;
    text-decoration: none;
    word-break: break-word;
}

.contact-info-content a:hover {
    color: #f4c400;
}

/* Quick Contact */

.contact-quick-box {
    margin-top: 30px;
    padding-top: 25px;
    border-top: 1px solid rgba(255,255,255,0.15);
}

.contact-quick-box h3 {
    margin: 0 0 8px;
    font-size: 18px;
}

.contact-quick-box p {
    margin: 0;
    color: #d9e1ec;
    font-size: 14px;
    line-height: 1.6;
}

/* Form Box */

.contact-form-box {
    background: #ffffff;
    border-radius: 12px;
    padding: 38px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.07);
}

.contact-form-box h2 {
    margin: 0 0 8px;
    color: #0b1f3a;
    font-size: 27px;
}

.contact-form-box .form-description {
    margin: 0 0 28px;
    color: #737d8c;
    font-size: 14px;
}

/* Alerts */

.contact-alert {
    padding: 14px 16px;
    border-radius: 7px;
    margin-bottom: 22px;
    font-size: 14px;
    line-height: 1.5;
}

.contact-alert-success {
    background: #e8f7ed;
    border: 1px solid #b8e3c5;
    color: #1d6b35;
}

.contact-alert-error {
    background: #fff0f0;
    border: 1px solid #f0b8b8;
    color: #a42626;
}

/* Form */

.contact-form {
    width: 100%;
}

.contact-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.contact-form-group {
    margin-bottom: 20px;
}

.contact-form-group label {
    display: block;
    margin-bottom: 8px;
    color: #26354a;
    font-size: 14px;
    font-weight: 600;
}

.contact-form-group label span {
    color: #d93025;
}

.contact-form-group input,
.contact-form-group textarea,
.contact-form-group select {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #d9dee7;
    border-radius: 7px;
    padding: 13px 14px;
    background: #ffffff;
    color: #26354a;
    font-family: inherit;
    font-size: 14px;
    outline: none;
    transition: 0.2s ease;
}

.contact-form-group input {
    height: 47px;
}

.contact-form-group textarea {
    min-height: 145px;
    resize: vertical;
}

.contact-form-group input:focus,
.contact-form-group textarea:focus,
.contact-form-group select:focus {
    border-color: #0b1f3a;
    box-shadow: 0 0 0 3px rgba(11,31,58,0.08);
}

.contact-form-group input::placeholder,
.contact-form-group textarea::placeholder {
    color: #a2a9b4;
}

/* Submit Button */

.contact-submit-btn {
    width: 100%;
    border: none;
    border-radius: 7px;
    padding: 14px 22px;
    background: #f4c400;
    color: #0b1f3a;
    font-family: inherit;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: 0.2s ease;
}

.contact-submit-btn:hover {
    background: #dcae00;
    transform: translateY(-1px);
}

.contact-submit-btn:active {
    transform: translateY(0);
}

/* Bottom CTA */

.contact-bottom {
    margin-top: 35px;
    background: #ffffff;
    border-radius: 12px;
    padding: 28px 35px;
    text-align: center;
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
}

.contact-bottom h3 {
    margin: 0 0 8px;
    color: #0b1f3a;
    font-size: 22px;
}

.contact-bottom p {
    margin: 0;
    color: #737d8c;
    font-size: 14px;
    line-height: 1.6;
}

.contact-bottom strong {
    color: #0b1f3a;
}

/* =========================================
   TABLET
========================================= */

@media (max-width: 900px) {

    .contact-grid {
        grid-template-columns: 1fr;
    }

    .contact-info-box,
    .contact-form-box {
        padding: 30px;
    }

    .contact-heading h1 {
        font-size: 36px;
    }
}

/* =========================================
   MOBILE
========================================= */

@media (max-width: 600px) {

    .contact-page {
        padding: 35px 15px 50px;
    }

    .contact-heading {
        margin-bottom: 30px;
    }

    .contact-heading h1 {
        font-size: 30px;
    }

    .contact-heading p {
        font-size: 14px;
    }

    .contact-grid {
        gap: 20px;
    }

    .contact-info-box,
    .contact-form-box {
        padding: 24px 20px;
        border-radius: 9px;
    }

    .contact-info-box h2,
    .contact-form-box h2 {
        font-size: 23px;
    }

    .contact-form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }

    .contact-info-item {
        gap: 12px;
    }

    .contact-info-icon {
        width: 42px;
        height: 42px;
        min-width: 42px;
        font-size: 18px;
    }

    .contact-bottom {
        padding: 24px 20px;
    }

}

/* Small Mobile */

@media (max-width: 400px) {

    .contact-heading h1 {
        font-size: 27px;
    }

    .contact-info-box,
    .contact-form-box {
        padding: 20px 16px;
    }

}

</style>


<div class="contact-page">

    <div class="contact-container">

        <!-- Heading -->

        <div class="contact-heading">

            <h1>Contact Us</h1>

            <p>
                Have a question about our engineering products or need help
                with your order? Get in touch with SKZ Engineering.
                Our team will be happy to assist you.
            </p>

        </div>


        <!-- Main Contact Area -->

        <div class="contact-grid">


            <!-- Contact Information -->

            <div class="contact-info-box">

                <h2>Get In Touch</h2>

                <p>
                    Feel free to contact us for product information,
                    order inquiries, or any other questions.
                </p>


                <!-- Phone -->

                <div class="contact-info-item">

                    <div class="contact-info-icon">
                        ☎
                    </div>

                    <div class="contact-info-content">

                        <h3>Phone</h3>

                        <a href="tel:03264428866">
                            03264428866
                        </a>

                    </div>

                </div>


                <!-- Email -->

                <div class="contact-info-item">

                    <div class="contact-info-icon">
                        ✉
                    </div>

                    <div class="contact-info-content">

                        <h3>Email</h3>

                        <a href="mailto:skzengineerings@gmail.com">
                            skzengineerings@gmail.com
                        </a>

                    </div>

                </div>


                <!-- Address -->

                <div class="contact-info-item">

                    <div class="contact-info-icon">
                        📍
                    </div>

                    <div class="contact-info-content">

                        <h3>Business</h3>

                        <p>
                            Engineering Products & Industrial Supplies
                        </p>

                    </div>

                </div>


                <!-- Quick Contact -->

                <div class="contact-quick-box">

                    <h3>Need Help?</h3>

                    <p>
                        Send us a message and our team will get back to you
                        as soon as possible.
                    </p>

                </div>

            </div>


            <!-- Contact Form -->

            <div class="contact-form-box">

                <h2>Send Us A Message</h2>

                <p class="form-description">
                    Fill out the form below and we will get back to you.
                </p>


                <?php if ($success_message !== ""): ?>

                    <div class="contact-alert contact-alert-success">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>

                <?php endif; ?>


                <?php if ($error_message !== ""): ?>

                    <div class="contact-alert contact-alert-error">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    action=""
                    class="contact-form"
                    onsubmit="return validateContactForm(this);"
                >


                    <!-- Name + Email -->

                    <div class="contact-form-row">

                        <div class="contact-form-group">

                            <label>
                                Your Name <span>*</span>
                            </label>

                            <input
                                type="text"
                                name="name"
                                placeholder="Enter your name"
                                value="<?php echo htmlspecialchars($name ?? ''); ?>"
                                required
                            >

                        </div>


                        <div class="contact-form-group">

                            <label>
                                Email Address <span>*</span>
                            </label>

                            <input
                                type="email"
                                name="email"
                                placeholder="Enter your email"
                                value="<?php echo htmlspecialchars($email ?? ''); ?>"
                                required
                            >

                        </div>

                    </div>


                    <!-- Phone + Subject -->

                    <div class="contact-form-row">

                        <div class="contact-form-group">

                            <label>
                                Phone Number
                            </label>

                            <input
                                type="text"
                                name="phone"
                                placeholder="Enter your phone number"
                                value="<?php echo htmlspecialchars($phone ?? ''); ?>"
                            >

                        </div>


                        <div class="contact-form-group">

                            <label>
                                Subject
                            </label>

                            <input
                                type="text"
                                name="subject"
                                placeholder="What is this about?"
                                value="<?php echo htmlspecialchars($subject ?? ''); ?>"
                            >

                        </div>

                    </div>


                    <!-- Message -->

                    <div class="contact-form-group">

                        <label>
                            Message <span>*</span>
                        </label>

                        <textarea
                            name="message"
                            placeholder="Write your message here..."
                            required
                        ><?php echo htmlspecialchars($message ?? ''); ?></textarea>

                    </div>


                    <!-- Submit -->

                    <button
                        type="submit"
                        class="contact-submit-btn"
                    >
                        Send Message
                    </button>

                </form>

            </div>

        </div>


        <!-- Bottom CTA -->

        <div class="contact-bottom">

            <h3>We're Here To Help</h3>

            <p>
                For quick assistance, call us at
                <strong>03264428866</strong>
                or email us at
                <strong>skzengineerings@gmail.com</strong>
            </p>

        </div>

    </div>

</div>


<?php include "../includes/footer.php"; ?>