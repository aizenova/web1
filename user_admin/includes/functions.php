<?php

// Redirect to a page
function redirect($page) {
    header("Location: " . $page);
    exit();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Flash message system
function flash($name = '', $message = '') {
    if (!empty($name)) {
        if (!empty($message)) {
            $_SESSION[$name] = $message;
        } else {
            if (isset($_SESSION[$name])) {
                $message = $_SESSION[$name];
                unset($_SESSION[$name]);
                return $message;
            }
        }
    }
}

// Generate a random confirmation code of given length
function generateConfirmationCode($length = 6) {
    try {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        } else {
            // Fallback to less secure method
            $characters = '0123456789abcdef';
            $charactersLength = strlen($characters);
            $randomString = '';
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
            return $randomString;
        }
    } catch (Exception $e) {
        // Fallback to less secure method on error
        $characters = '0123456789abcdef';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}

// Send email using PHP mail function with error suppression and handling
function sendEmail($to, $subject, $message, $headers = '') {
    // Suppress warnings from mail() function
    $result = @mail($to, $subject, $message, $headers);
    if (!$result) {
        // Log or handle the error as needed
        error_log("Failed to send email to $to");
    }
    return $result;
}
