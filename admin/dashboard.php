<?php
// admin/dashboard.php
require_once '../includes/session.php';
require_once '../config/database.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Get statistics
$stats = [];

// Total vendors
$stmt = $db->query("SELECT COUNT(*) as count FROM vendor_profiles WHERE is_active = true");
$stats['total_vendors'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total customers
$stmt = $db->query("SELECT COUNT(*) as count FROM customer_profiles");
$stats['total_customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total products
$stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE is_available = true");
$stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total orders today
$stmt = $db->query("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = CURRENT_DATE");
$stats['orders_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Revenue this month
$stmt = $db->query("SELECT COALESCE(SUM(commission_amount), 0) as revenue FROM orders WHERE EXTRACT(MONTH FROM created_at) = EXTRACT(MONTH FROM CURRENT_DATE) AND EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE)");
$stats['monthly_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'];

// Recent vendors
$stmt = $db->query("SELECT vp.*, u.email, u.full_name FROM vendor_profiles vp JOIN users u ON vp.user_id = u.user_id ORDER BY vp.created_at DESC LIMIT 5");
$recent_vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent orders
$stmt = $db->query("SELECT o.*, u.full_name as customer_name, vp.business_name FROM orders o JOIN customer_profiles cp ON o.customer_id = cp.customer_id JOIN users u ON cp.user_id = u.user_id JOIN vendor_profiles vp ON o.vendor_id = vp.vendor_id ORDER BY o.created_at DESC LIMIT 10");
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MarketConnect</title>
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
                    <span class="ml-4 px-3 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">ADMIN</span>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-700">Welcome, <?php echo htmlspecialchars(getUserName()); ?></span>
                    <a href="../logout.php" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-100 rounded-md p-3">
                        <i class="fas fa-store text-green-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Vendors</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_vendors']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-blue-100 rounded-md p-3">
                        <i class="fas fa-users text-blue-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Customers</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_customers']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-purple-100 rounded-md p-3">
                        <i class="fas fa-box text-purple-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Products</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_products']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-yellow-100 rounded-md p-3">
                        <i class="fas fa-shopping-cart text-yellow-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Orders Today</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['orders_today']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-100 rounded-md p-3">
                        <i class="fas fa-peso-sign text-green-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Monthly Revenue</p>
                        <p class="text-2xl font-semibold text-gray-900">₱<?php echo number_format($stats['monthly_revenue'], 2); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <a href="vendors.php" class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition text-center">
                <i class="fas fa-store text-3xl text-green-600 mb-2"></i>
                <p class="font-semibold">Manage Vendors</p>
            </a>
            <a href="customers.php" class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition text-center">
                <i class="fas fa-users text-3xl text-blue-600 mb-2"></i>
                <p class="font-semibold">Manage Customers</p>
            </a>
            <a href="orders.php" class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition text-center">
                <i class="fas fa-shopping-cart text-3xl text-purple-600 mb-2"></i>
                <p class="font-semibold">View Orders</p>
            </a>
            <a href="reports.php" class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition text-center">
                <i class="fas fa-chart-line text-3xl text-yellow-600 mb-2"></i>
                <p class="font-semibold">Reports</p>
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Vendors -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">Recent Vendors</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <?php foreach ($recent_vendors as $vendor): ?>
                        <div class="flex items-center justify-between border-b pb-3">
                            <div>
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($vendor['business_name']); ?></p>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($vendor['market_name']); ?></p>
                                <p class="text-xs text-gray-400"><?php echo date('M d, Y', strtotime($vendor['created_at'])); ?></p>
                            </div>
                            <div>
                                <?php if ($vendor['verified']): ?>
                                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Verified</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full">Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">Recent Orders</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <?php foreach ($recent_orders as $order): ?>
                        <div class="flex items-center justify-between border-b pb-3">
                            <div>
                                <p class="font-semibold text-gray-800">#<?php echo $order['order_id']; ?> - <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($order['business_name']); ?></p>
                                <p class="text-xs text-gray-400">₱<?php echo number_format($order['total_amount'], 2); ?></p>
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
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>