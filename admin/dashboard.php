<?php
// admin/dashboard.php
require_once '../includes/session.php';
require_once '../config/database.php';

// Check if user is admin
if (!isLoggedIn() || getUserType() !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get statistics
$stats = [];

// Total users by type
$stmt = $db->query("SELECT user_type, COUNT(*) as count FROM users WHERE is_active = true GROUP BY user_type");
$userStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($userStats as $stat) {
    $stats[$stat['user_type'] . '_count'] = $stat['count'];
}

// Total vendors
$stmt = $db->query("SELECT COUNT(*) as count FROM vendor_profiles WHERE is_active = true");
$stats['active_vendors'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total products
$stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE is_available = true");
$stats['active_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total orders
$stmt = $db->query("SELECT COUNT(*) as count FROM orders");
$stats['total_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Revenue this month (commission-based)
$stmt = $db->query("SELECT COALESCE(SUM(total_amount * 0.05), 0) as revenue 
                    FROM orders 
                    WHERE status = 'completed' 
                    AND EXTRACT(MONTH FROM created_at) = EXTRACT(MONTH FROM CURRENT_DATE)
                    AND EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE)");
$stats['monthly_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'];

// Subscription revenue (example: vendors on paid plans)
$stmt = $db->query("SELECT COUNT(*) as count FROM subscriptions WHERE status = 'active'");
$stats['active_subscriptions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Recent vendors (pending approval)
$stmt = $db->query("SELECT vp.*, u.full_name, u.email, u.created_at as registered_at 
                    FROM vendor_profiles vp 
                    JOIN users u ON vp.user_id = u.user_id 
                    WHERE vp.is_verified = false 
                    ORDER BY u.created_at DESC 
                    LIMIT 10");
$pending_vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent orders
$stmt = $db->query("SELECT o.*, u.full_name as customer_name, vp.business_name 
                    FROM orders o
                    JOIN users u ON o.customer_id = u.user_id
                    JOIN vendor_profiles vp ON o.vendor_id = vp.vendor_id
                    ORDER BY o.created_at DESC
                    LIMIT 10");
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top vendors by revenue
$stmt = $db->query("SELECT vp.business_name, vp.vendor_id, COUNT(o.order_id) as order_count, 
                    COALESCE(SUM(o.total_amount), 0) as total_sales
                    FROM vendor_profiles vp
                    LEFT JOIN orders o ON vp.vendor_id = o.vendor_id AND o.status = 'completed'
                    WHERE vp.is_active = true
                    GROUP BY vp.vendor_id, vp.business_name
                    ORDER BY total_sales DESC
                    LIMIT 10");
$top_vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Activity logs
$stmt = $db->query("SELECT al.*, u.full_name 
                    FROM activity_logs al
                    JOIN users u ON al.user_id = u.user_id
                    ORDER BY al.created_at DESC
                    LIMIT 15");
$activity_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MarketConnect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/mobile-responsive.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <!-- Mobile Sidebar Toggle -->
    <button id="admin-sidebar-toggle" class="admin-mobile-toggle lg:hidden">
        <i class="fas fa-bars text-xl"></i>
    </button>
    
    <!-- Sidebar Overlay -->
    <div id="sidebar-overlay" class="sidebar-overlay"></div>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-64 bg-green-800 text-white flex-shrink-0">
            <div class="p-6">
                <h1 class="text-2xl font-bold">🏪 MarketConnect</h1>
                <p class="text-green-200 text-sm">Admin Panel</p>
            </div>
            <nav class="mt-6">
                <a href="dashboard.php" class="flex items-center px-6 py-3 bg-green-700 border-l-4 border-green-400">
                    <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
                </a>
                <a href="vendors.php" class="flex items-center px-6 py-3 hover:bg-green-700 transition">
                    <i class="fas fa-store mr-3"></i> Vendors
                </a>
                <a href="customers.php" class="flex items-center px-6 py-3 hover:bg-green-700 transition">
                    <i class="fas fa-users mr-3"></i> Customers
                </a>
                <a href="products.php" class="flex items-center px-6 py-3 hover:bg-green-700 transition">
                    <i class="fas fa-box mr-3"></i> Products
                </a>
                <a href="orders.php" class="flex items-center px-6 py-3 hover:bg-green-700 transition">
                    <i class="fas fa-shopping-cart mr-3"></i> Orders
                </a>
                <a href="subscriptions.php" class="flex items-center px-6 py-3 hover:bg-green-700 transition">
                    <i class="fas fa-crown mr-3"></i> Subscriptions
                </a>
                <a href="reports.php" class="flex items-center px-6 py-3 hover:bg-green-700 transition">
                    <i class="fas fa-chart-bar mr-3"></i> Reports
                </a>
                <a href="settings.php" class="flex items-center px-6 py-3 hover:bg-green-700 transition">
                    <i class="fas fa-cog mr-3"></i> Settings
                </a>
            </nav>
            <div class="absolute bottom-0 w-64 p-6">
                <a href="../logout.php" class="flex items-center text-red-200 hover:text-white transition">
                    <i class="fas fa-sign-out-alt mr-3"></i> Logout
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm">
                <div class="flex items-center justify-between px-8 py-4">
                    <h2 class="text-2xl font-bold text-gray-800">Dashboard Overview</h2>
                    <div class="flex items-center space-x-4">
                        <button class="relative text-gray-600 hover:text-gray-800">
                            <i class="fas fa-bell text-xl"></i>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                <?php echo count($pending_vendors); ?>
                            </span>
                        </button>
                        <div class="flex items-center space-x-2">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode(getUserName()); ?>&background=10b981&color=fff" 
                                class="w-10 h-10 rounded-full">
                            <span class="font-semibold text-gray-700"><?php echo htmlspecialchars(getUserName()); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <main class="flex-1 overflow-y-auto p-8">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
                    <!-- Total Vendors -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm font-semibold">Active Vendors</p>
                                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['active_vendors']); ?></p>
                            </div>
                            <div class="bg-blue-100 rounded-full p-4">
                                <i class="fas fa-store text-blue-600 text-2xl"></i>
                            </div>
                        </div>
                        <p class="text-sm text-green-600 mt-2">
                            <i class="fas fa-arrow-up"></i> <?php echo count($pending_vendors); ?> pending approval
                        </p>
                    </div>

                    <!-- Total Customers -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm font-semibold">Total Customers</p>
                                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['customer_count'] ?? 0); ?></p>
                            </div>
                            <div class="bg-green-100 rounded-full p-4">
                                <i class="fas fa-users text-green-600 text-2xl"></i>
                            </div>
                        </div>
                        <p class="text-sm text-gray-500 mt-2">Registered users</p>
                    </div>

                    <!-- Total Products -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm font-semibold">Active Products</p>
                                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['active_products']); ?></p>
                            </div>
                            <div class="bg-purple-100 rounded-full p-4">
                                <i class="fas fa-box text-purple-600 text-2xl"></i>
                            </div>
                        </div>
                        <p class="text-sm text-gray-500 mt-2">Listed in marketplace</p>
                    </div>

                    <!-- Monthly Revenue -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm font-semibold">Monthly Revenue</p>
                                <p class="text-3xl font-bold text-gray-800">₱<?php echo number_format($stats['monthly_revenue'], 2); ?></p>
                            </div>
                            <div class="bg-yellow-100 rounded-full p-4">
                                <i class="fas fa-coins text-yellow-600 text-2xl"></i>
                            </div>
                        </div>
                        <p class="text-sm text-gray-500 mt-2">
                            <?php echo $stats['active_subscriptions']; ?> active subscriptions
                        </p>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Revenue Chart -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Revenue Overview</h3>
                        <canvas id="revenueChart"></canvas>
                    </div>

                    <!-- Orders Chart -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Order Statistics</h3>
                        <canvas id="ordersChart"></canvas>
                    </div>
                </div>

                <!-- Tables Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Pending Vendors -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800">Pending Vendor Approvals</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Business</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Owner</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($pending_vendors as $vendor): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($vendor['business_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600">
                                            <?php echo htmlspecialchars($vendor['full_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600">
                                            <?php echo date('M d, Y', strtotime($vendor['registered_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm">
                                            <button onclick="approveVendor(<?php echo $vendor['vendor_id']; ?>)" 
                                                class="text-green-600 hover:text-green-800 mr-2">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button onclick="viewVendor(<?php echo $vendor['vendor_id']; ?>)" 
                                                class="text-blue-600 hover:text-blue-800">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Top Vendors -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800">Top Performing Vendors</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Business</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Orders</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($top_vendors as $vendor): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($vendor['business_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600">
                                            <?php echo number_format($vendor['order_count']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-semibold text-green-600">
                                            ₱<?php echo number_format($vendor['total_sales'], 2); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-bold text-gray-800">Recent Activity</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <?php foreach ($activity_logs as $log): ?>
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                                        <i class="fas fa-circle text-green-600 text-xs"></i>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm text-gray-800">
                                        <span class="font-semibold"><?php echo htmlspecialchars($log['full_name']); ?></span>
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Commission Revenue',
                    data: [12000, 15000, 18000, 22000, 25000, 28000],
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Subscription Revenue',
                    data: [5000, 6000, 7000, 8500, 9000, 10000],
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Orders Chart
        const ordersCtx = document.getElementById('ordersChart').getContext('2d');
        new Chart(ordersCtx, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Orders',
                    data: [45, 52, 48, 61, 55, 72, 68],
                    backgroundColor: 'rgba(16, 185, 129, 0.8)'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        function approveVendor(vendorId) {
            if (confirm('Approve this vendor?')) {
                fetch('api/approve_vendor.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({vendor_id: vendorId})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error approving vendor');
                    }
                });
            }
        }

        function viewVendor(vendorId) {
            window.location.href = 'vendor_details.php?id=' + vendorId;
        }
    </script>
    <script src="../js/mobile-nav.js"></script>
</body>
</html>