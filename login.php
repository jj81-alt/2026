<?php
// login.php
require_once 'includes/session.php';
require_once 'config/database.php';

if (isLoggedIn()) {
    $type = getUserType();
    header("Location: " . ($type === 'admin' ? 'admin/dashboard.php' : ($type === 'vendor' ? 'vendor/dashboard.php' : 'index.php')));
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($email) && !empty($password)) {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = true");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            setUserSession($user);
            
            // Redirect based on user type
            $redirect = match($user['user_type']) {
                'admin' => 'admin/dashboard.php',
                'vendor' => 'vendor/dashboard.php',
                'customer' => 'index.php',
                default => 'index.php'
            };
            
            header("Location: $redirect");
            exit();
        } else {
            $error = 'Invalid email or password';
        }
    } else {
        $error = 'Please fill in all fields';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MarketConnect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-green-50 to-green-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-green-600 mb-2">🏪 MarketConnect</h1>
            <p class="text-gray-600">Welcome back! Please login to your account</p>
        </div>

        <div class="bg-white rounded-lg shadow-xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Login</h2>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="email">
                        <i class="fas fa-envelope text-green-600"></i> Email Address
                    </label>
                    <input type="email" id="email" name="email" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                        placeholder="your@email.com">
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                        <i class="fas fa-lock text-green-600"></i> Password
                    </label>
                    <input type="password" id="password" name="password" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                        placeholder="Enter your password">
                </div>

                <button type="submit" 
                    class="w-full bg-green-600 text-white font-bold py-3 px-4 rounded-lg hover:bg-green-700 transition duration-300">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-gray-600">Don't have an account? 
                    <a href="register.php" class="text-green-600 hover:text-green-800 font-semibold">Sign up here</a>
                </p>
            </div>

            <div class="mt-6 pt-6 border-t border-gray-200">
                <p class="text-sm text-gray-500 text-center mb-3">Demo Accounts:</p>
                <div class="space-y-2 text-xs">
                    <div class="bg-gray-50 p-2 rounded">
                        <strong>Admin:</strong> admin@marketconnect.ph / admin123
                    </div>
                    <div class="bg-gray-50 p-2 rounded">
                        <strong>Vendor:</strong> vendor@test.com / vendor123
                    </div>
                    <div class="bg-gray-50 p-2 rounded">
                        <strong>Customer:</strong> customer@test.com / customer123
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-6">
            <a href="index.php" class="text-green-600 hover:text-green-800">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>
</body>
</html>