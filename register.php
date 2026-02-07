<?php
// register.php
require_once 'includes/session.php';
require_once 'config/database.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_type = $_POST['user_type'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($user_type) || empty($full_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif (!in_array($user_type, ['customer', 'vendor'])) {
        $error = 'Please select account type';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        // Vendor-specific validation
        if ($user_type === 'vendor') {
            $business_name = trim($_POST['business_name'] ?? '');
            $market_name = trim($_POST['market_name'] ?? '');
            $category = $_POST['category'] ?? '';
            
            if (empty($business_name) || empty($market_name) || empty($category)) {
                $error = 'Please fill in all business information';
            }
        }
        
        if (!$error) {
            try {
                $database = new Database();
                $db = $database->getConnection();
                
                // Check if email exists
                $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->fetch()) {
                    $error = 'Email already registered';
                } else {
                    // Create user
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    $db->beginTransaction();
                    
                    $stmt = $db->prepare("INSERT INTO users (email, password_hash, user_type, full_name, phone_number, email_verified) VALUES (?, ?, ?, ?, ?, true) RETURNING user_id");
                    $stmt->execute([$email, $password_hash, $user_type, $full_name, $phone]);
                    $user_id = $stmt->fetch(PDO::FETCH_ASSOC)['user_id'];
                    
                    // Create profile based on type
                    if ($user_type === 'vendor') {
                        $stmt = $db->prepare("INSERT INTO vendor_profiles (user_id, business_name, market_name, category) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$user_id, $business_name, $market_name, $category]);
                    } elseif ($user_type === 'customer') {
                        $stmt = $db->prepare("INSERT INTO customer_profiles (user_id) VALUES (?)");
                        $stmt->execute([$user_id]);
                    }
                    
                    $db->commit();
                    
                    $success = 'Registration successful! You can now login.';
                }
            } catch (Exception $e) {
                if (isset($db)) {
                    $db->rollBack();
                }
                $error = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}

// Get categories for vendor registration
try {
    $database = new Database();
    $db = $database->getConnection();
    $stmt = $db->query("SELECT category_name FROM categories ORDER BY category_name");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MarketConnect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Custom styles for radio button visual state */
        .radio-option {
            cursor: pointer;
            border: 2px solid #d1d5db;
            transition: all 0.3s ease;
        }
        .radio-option:hover {
            border-color: #10b981;
            background-color: #f0fdf4;
        }
        .radio-option.selected {
            border-color: #10b981;
            background-color: #dcfce7;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-green-100 min-h-screen py-8">
    <div class="max-w-2xl w-full mx-auto px-4">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-green-600 mb-2">🏪 MarketConnect</h1>
            <p class="text-gray-600">Join our marketplace community today!</p>
        </div>

        <div class="bg-white rounded-lg shadow-xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Create Account</h2>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    <a href="login.php" class="underline font-semibold">Login now</a>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm">
                <!-- Account Type -->
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        <i class="fas fa-user-tag text-green-600"></i> I am a: <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="radio-option rounded-lg p-4 text-center" onclick="selectUserType('customer')">
                            <input type="radio" name="user_type" value="customer" id="customer" required class="sr-only">
                            <i class="fas fa-shopping-bag text-3xl text-green-600 mb-2"></i>
                            <p class="font-semibold">Customer</p>
                            <p class="text-xs text-gray-500">Browse and buy</p>
                        </div>
                        <div class="radio-option rounded-lg p-4 text-center" onclick="selectUserType('vendor')">
                            <input type="radio" name="user_type" value="vendor" id="vendor" required class="sr-only">
                            <i class="fas fa-store text-3xl text-green-600 mb-2"></i>
                            <p class="font-semibold">Vendor</p>
                            <p class="text-xs text-gray-500">Sell products</p>
                        </div>
                    </div>
                </div>

                <!-- Basic Info -->
                <div class="grid md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="full_name" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                            placeholder="Juan Dela Cruz" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Phone Number
                        </label>
                        <input type="tel" name="phone_number"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                            placeholder="+639123456789" value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        Email Address <span class="text-red-500">*</span>
                    </label>
                    <input type="email" name="email" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                        placeholder="your@email.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <!-- Vendor-specific fields -->
                <div id="vendorFields" style="display: none;">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <h3 class="font-semibold text-blue-900 mb-3">
                            <i class="fas fa-store"></i> Business Information
                        </h3>
                        
                        <div class="mb-3">
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                Business Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="business_name" id="businessName"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                                placeholder="Fresh Fruits Stall" value="<?php echo isset($_POST['business_name']) ? htmlspecialchars($_POST['business_name']) : ''; ?>">
                        </div>

                        <div class="mb-3">
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                Public Market Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="market_name" id="marketName"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                                placeholder="Central Public Market" value="<?php echo isset($_POST['market_name']) ? htmlspecialchars($_POST['market_name']) : ''; ?>">
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                Product Category <span class="text-red-500">*</span>
                            </label>
                            <select name="category" id="category"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500">
                                <option value="">Select category...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" 
                                        <?php echo (isset($_POST['category']) && $_POST['category'] === $cat) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Password -->
                <div class="grid md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Password <span class="text-red-500">*</span>
                        </label>
                        <input type="password" name="password" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                            placeholder="Min. 6 characters">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Confirm Password <span class="text-red-500">*</span>
                        </label>
                        <input type="password" name="confirm_password" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                            placeholder="Re-enter password">
                    </div>
                </div>

                <button type="submit" 
                    class="w-full bg-green-600 text-white font-bold py-3 px-4 rounded-lg hover:bg-green-700 transition duration-300">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-gray-600">Already have an account? 
                    <a href="login.php" class="text-green-600 hover:text-green-800 font-semibold">Login here</a>
                </p>
            </div>
        </div>

        <div class="text-center mt-6">
            <a href="index.php" class="text-green-600 hover:text-green-800">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>

    <script>
        function selectUserType(type) {
            // Update radio button
            document.getElementById(type).checked = true;
            
            // Update visual state
            document.querySelectorAll('.radio-option').forEach(el => {
                el.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            
            // Toggle vendor fields
            toggleVendorFields(type);
        }

        function toggleVendorFields(userType) {
            const vendorFields = document.getElementById('vendorFields');
            const isVendor = userType === 'vendor';
            
            vendorFields.style.display = isVendor ? 'block' : 'none';
            
            // Toggle required attribute
            document.getElementById('businessName').required = isVendor;
            document.getElementById('marketName').required = isVendor;
            document.getElementById('category').required = isVendor;
        }

        // Restore selection on page load if form was submitted with errors
        window.addEventListener('DOMContentLoaded', function() {
            const selectedType = document.querySelector('input[name="user_type"]:checked');
            if (selectedType) {
                const parentDiv = selectedType.closest('.radio-option');
                if (parentDiv) {
                    parentDiv.classList.add('selected');
                    toggleVendorFields(selectedType.value);
                }
            }
        });

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const userType = document.querySelector('input[name="user_type"]:checked');
            
            if (!userType) {
                e.preventDefault();
                alert('Please select account type (Customer or Vendor)');
                return false;
            }
            
            if (userType.value === 'vendor') {
                const businessName = document.getElementById('businessName').value.trim();
                const marketName = document.getElementById('marketName').value.trim();
                const category = document.getElementById('category').value;
                
                if (!businessName || !marketName || !category) {
                    e.preventDefault();
                    alert('Please fill in all business information');
                    return false;
                }
            }
            
            const password = document.querySelector('input[name="password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters');
                return false;
            }
        });
    </script>
</body>
</html>