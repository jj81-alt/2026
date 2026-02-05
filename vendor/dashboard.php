<?php
// vendor/dashboard.php
require_once '../includes/session.php';
require_once '../config/database.php';
requireVendor();

$database = new Database();
$db = $database->getConnection();

// Get vendor profile
$stmt = $db->prepare("SELECT vp.* FROM vendor_profiles vp WHERE vp.user_id = ?");
$stmt->execute([getUserId()]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    die("Vendor profile not found");
}

$vendor_id = $vendor['vendor_id'];

// Get statistics
$stats = [];

// Total products
$stmt = $db->prepare("SELECT COUNT(*) as count FROM products WHERE vendor_id = ?");
$stmt->execute([$vendor_id]);
$stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Active products
$stmt = $db->prepare("SELECT COUNT(*) as count FROM products WHERE vendor_id = ? AND is_available = true");
$stmt->execute([$vendor_id]);
$stats['active_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total orders
$stmt = $db->prepare("SELECT COUNT(*) as count FROM orders WHERE vendor_id = ?");
$stmt->execute([$vendor_id]);
$stats['total_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Pending orders
$stmt = $db->prepare("SELECT COUNT(*) as count FROM orders WHERE vendor_id = ? AND status IN ('pending', 'confirmed')");
$stmt->execute([$vendor_id]);
$stats['pending_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Today's sales
$stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE vendor_id = ? AND DATE(created_at) = CURRENT_DATE");
$stmt->execute([$vendor_id]);
$stats['today_sales'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Recent orders
$stmt = $db->prepare("SELECT o.*, u.full_name as customer_name, u.phone_number FROM orders o JOIN customer_profiles cp ON o.customer_id = cp.customer_id JOIN users u ON cp.user_id = u.user_id WHERE o.vendor_id = ? ORDER BY o.created_at DESC LIMIT 10");
$stmt->execute([$vendor_id]);
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Low stock products
$stmt = $db->prepare("SELECT * FROM products WHERE vendor_id = ? AND stock_quantity < 10 AND is_available = true ORDER BY stock_quantity ASC LIMIT 5");
$stmt->execute([$vendor_id]);
$low_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard - MarketConnect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-green-600">🏪 MarketConnect</h1>
                    <span class="ml-4 px-3 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded-full">VENDOR</span>
                </div>
                <div class="flex items-center space-x-6">
                    <a href="products.php" class="text-gray-700 hover:text-green-600">
                        <i class="fas fa-box"></i> Products
                    </a>
                    <a href="orders.php" class="text-gray-700 hover:text-green-600">
                        <i class="fas fa-shopping-cart"></i> Orders
                    </a>
                    <a href="messages.php" class="text-gray-700 hover:text-green-600">
                        <i class="fas fa-comments"></i> Messages
                    </a>
                    <span class="text-gray-700"><?php echo htmlspecialchars($vendor['business_name']); ?></span>
                    <a href="../logout.php" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Business Info Banner -->
        <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow-lg p-6 mb-8 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($vendor['business_name']); ?></h2>
                    <p class="text-green-100 mt-1">
                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($vendor['market_name']); ?> 
                        <?php if ($vendor['stall_number']): ?>
                            - Stall <?php echo htmlspecialchars($vendor['stall_number']); ?>
                        <?php endif; ?>
                    </p>
                    <div class="mt-2 flex items-center space-x-4">
                        <span class="flex items-center">
                            <i class="fas fa-star text-yellow-300"></i>
                            <span class="ml-1"><?php echo number_format($vendor['rating_average'], 1); ?> (<?php echo $vendor['total_reviews']; ?> reviews)</span>
                        </span>
                        <?php if ($vendor['verified']): ?>
                            <span class="bg-white text-green-600 px-2 py-1 rounded text-xs font-semibold">
                                <i class="fas fa-check-circle"></i> Verified
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm text-green-100">Subscription</p>
                    <p class="text-xl font-bold uppercase"><?php echo $vendor['subscription_type']; ?></p>
                    <?php if ($vendor['subscription_expires_at']): ?>
                        <p class="text-xs text-green-100">Expires: <?php echo date('M d, Y', strtotime($vendor['subscription_expires_at'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-purple-100 rounded-md p-3">
                        <i class="fas fa-box text-purple-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Products</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_products']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-100 rounded-md p-3">
                        <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Active Products</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['active_products']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-blue-100 rounded-md p-3">
                        <i class="fas fa-shopping-cart text-blue-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Orders</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_orders']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-yellow-100 rounded-md p-3">
                        <i class="fas fa-clock text-yellow-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Pending Orders</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['pending_orders']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-100 rounded-md p-3">
                        <i class="fas fa-peso-sign text-green-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Today's Sales</p>
                        <p class="text-2xl font-semibold text-gray-900">₱<?php echo number_format($stats['today_sales'], 2); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <a href="products.php?action=add" class="bg-green-600 text-white rounded-lg shadow p-4 hover:bg-green-700 transition text-center">
                <i class="fas fa-plus-circle text-3xl mb-2"></i>
                <p class="font-semibold">Add Product</p>
            </a>
            <a href="orders.php" class="bg-blue-600 text-white rounded-lg shadow p-4 hover:bg-blue-700 transition text-center">
                <i class="fas fa-list text-3xl mb-2"></i>
                <p class="font-semibold">View Orders</p>
            </a>
            <a href="messages.php" class="bg-purple-600 text-white rounded-lg shadow p-4 hover:bg-purple-700 transition text-center">
                <i class="fas fa-comments text-3xl mb-2"></i>
                <p class="font-semibold">Messages</p>
            </a>
            <a href="profile.php" class="bg-gray-600 text-white rounded-lg shadow p-4 hover:bg-gray-700 transition text-center">
                <i class="fas fa-cog text-3xl mb-2"></i>
                <p class="font-semibold">Settings</p>
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Orders -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-800">Recent Orders</h2>
                    <a href="orders.php" class="text-green-600 hover:text-green-800 text-sm">View All</a>
                </div>
                <div class="p-6">
                    <?php if (empty($recent_orders)): ?>
                        <p class="text-gray-500 text-center py-8">No orders yet</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recent_orders as $order): ?>
                            <div class="border-b pb-3">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-semibold text-gray-800">#<?php echo $order['order_id']; ?> - <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                        <p class="text-sm text-gray-500">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($order['phone_number']); ?>
                                        </p>
                                        <p class="text-sm font-semibold text-green-600">₱<?php echo number_format($order['total_amount'], 2); ?></p>
                                        <p class="text-xs text-gray-400"><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></p>
                                    </div>
                                    <div>
                                        <?php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'confirmed' => 'bg-blue-100 text-blue-800',
                                            'ready' => 'bg-purple-100 text-purple-800',
                                            'completed' => 'bg-green-100 text-green-800',
                                            'cancelled' => 'bg-red-100 text-red-800'
                                        ];
                                        $colorClass = $statusColors[$order['status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-2 py-1 <?php echo $colorClass; ?> text-xs rounded-full"><?php echo ucfirst($order['status']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Low Stock Alert -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-800">
                        <i class="fas fa-exclamation-triangle text-yellow-500"></i> Low Stock Alert
                    </h2>
                    <a href="products.php" class="text-green-600 hover:text-green-800 text-sm">Manage Products</a>
                </div>
                <div class="p-6">
                    <?php if (empty($low_stock)): ?>
                        <p class="text-gray-500 text-center py-8">All products are well stocked! 👍</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($low_stock as $product): ?>
                            <div class="flex justify-between items-center border-b pb-3">
                                <div>
                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($product['product_name']); ?></p>
                                    <p class="text-sm text-gray-500">₱<?php echo number_format($product['price'], 2); ?> / <?php echo htmlspecialchars($product['unit']); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-red-600 font-semibold"><?php echo $product['stock_quantity']; ?> left</p>
                                    <a href="products.php?action=edit&id=<?php echo $product['product_id']; ?>" class="text-xs text-blue-600 hover:underline">Update Stock</a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>