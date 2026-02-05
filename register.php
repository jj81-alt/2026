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
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
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
                    $business_name = $_POST['business_name'] ?? '';
                    $market_name = $_POST['market_name'] ?? '';
                    $category = $_POST['category'] ?? '';
                    
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

// Get categories for vendor registration
$database = new Database();
$db = $database->getConnection();
$stmt = $db->query("SELECT category_name FROM categories ORDER BY category_name");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MarketConnect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                        <i class="fas fa-user-tag text-green-600"></i> I am a:
                    </label>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="cursor-pointer">
                            <input type="radio" name="user_type" value="customer" class="hidden peer" required onchange="toggleVendorFields()">
                            <div class="border-2 border-gray-300 peer-checked:border-green-600 peer-checked:bg-green-50 rounded-lg p-4 text-center transition">
                                <i class="fas fa-shopping-bag text-3xl text-green-600 mb-2"></i>
                                <p class="font-semibold">Customer</p>
                                <p class="text-xs text-gray-500">Browse and buy</p>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="user_type" value="vendor" class="hidden peer" onchange="toggleVendorFields()">
                            <div class="border-2 border-gray-300 peer-checked:border-green-600 peer-checked:bg-green-50 rounded-lg p-4 text-center transition">
                                <i class="fas fa-store text-3xl text-green-600 mb-2"></i>
                                <p class="font-semibold">Vendor</p>
                                <p class="text-xs text-gray-500">Sell products</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Basic Info -->
                <div class="grid md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Full Name *
                        </label>
                        <input type="text" name="full_name" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                            placeholder="Juan Dela Cruz">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Phone Number
                        </label>
                        <input type="tel" name="phone_number"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                            placeholder="+639123456789">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        Email Address *
                    </label>
                    <input type="email" name="email" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                        placeholder="your@email.com">
                </div>

                <!-- Vendor-specific fields -->
                <div id="vendorFields" style="display: none;">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <h3 class="font-semibold text-blue-900 mb-3">
                            <i class="fas fa-store"></i> Business Information
                        </h3>
                        
                        <div class="mb-3">
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                Business Name *
                            </label>
                            <input type="text" name="business_name" id="businessName"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                                placeholder="Fresh Fruits Stall">
                        </div>

                        <div class="mb-3">
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                Public Market Name *
                            </label>
                            <input type="text" name="market_name" id="marketName"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                                placeholder="Central Public Market">
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                Product Category *
                            </label>
                            <select name="category" id="category"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500">
                                <option value="">Select category...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Password -->
                <div class="grid md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Password *
                        </label>
                        <input type="password" name="password" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                            placeholder="Min. 6 characters">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Confirm Password *
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
        function toggleVendorFields() {
            const userType = document.querySelector('input[name="user_type"]:checked')?.value;
            const vendorFields = document.getElementById('vendorFields');
            const isVendor = userType === 'vendor';
            
            vendorFields.style.display = isVendor ? 'block' : 'none';
            
            // Toggle required attribute
            document.getElementById('businessName').required = isVendor;
            document.getElementById('marketName').required = isVendor;
            document.getElementById('category').required = isVendor;
        }
    </script>
</body>
</html>