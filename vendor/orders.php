<?php
// vendor/orders.php
require_once '../includes/session.php';
require_once '../config/database.php';
requireVendor();

$database = new Database();
$db = $database->getConnection();

// Get vendor profile
$stmt = $db->prepare("SELECT vp.* FROM vendor_profiles vp WHERE vp.user_id = ?");
$stmt->execute([getUserId()]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
$vendor_id = $vendor['vendor_id'];

$message = '';
$error = '';

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $order_id = intval($_POST['order_id'] ?? 0);
        $new_status = $_POST['status'] ?? '';
        
        $valid_statuses = ['pending', 'confirmed', 'ready', 'completed', 'cancelled'];
        
        if (in_array($new_status, $valid_statuses)) {
            $stmt = $db->prepare("UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE order_id = ? AND vendor_id = ?");
            if ($stmt->execute([$new_status, $order_id, $vendor_id])) {
                $message = 'Order status updated successfully!';
            } else {
                $error = 'Failed to update order status';
            }
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query based on filters
$query = "SELECT o.*, 
          u.full_name as customer_name, 
          u.phone_number as customer_phone,
          u.email as customer_email
          FROM orders o 
          JOIN customer_profiles cp ON o.customer_id = cp.customer_id 
          JOIN users u ON cp.user_id = u.user_id 
          WHERE o.vendor_id = ?";

$params = [$vendor_id];

if ($status_filter !== 'all') {
    $query .= " AND o.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (u.full_name ILIKE ? OR u.phone_number ILIKE ? OR CAST(o.order_id AS TEXT) ILIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY o.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order statistics
$stmt = $db->prepare("SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed,
    COUNT(CASE WHEN status = 'ready' THEN 1 END) as ready,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled
    FROM orders WHERE vendor_id = ?");
$stmt->execute([$vendor_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - MarketConnect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center">
                <h1 class="text-xl md:text-2xl font-bold text-green-600">🏪 MarketConnect</h1>
                <span class="ml-2 md:ml-4 px-2 md:px-3 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded-full">VENDOR</span>
            </div>
            
            <!-- Mobile Menu Button -->
            <button id="mobile-menu-button" class="md:hidden text-gray-700">
                <i class="fas fa-bars text-2xl"></i>
            </button>
            
            <!-- Desktop Menu -->
            <div class="hidden md:flex items-center space-x-4 lg:space-x-6">
                <a href="products.php" class="text-gray-700 hover:text-green-600">
                    <i class="fas fa-box"></i><span class="hidden lg:inline"> Products</span>
                </a>
                <a href="orders.php" class="text-gray-700 hover:text-green-600">
                    <i class="fas fa-shopping-cart"></i><span class="hidden lg:inline"> Orders</span>
                </a>
                <a href="messages.php" class="text-gray-700 hover:text-green-600">
                    <i class="fas fa-comments"></i><span class="hidden lg:inline"> Messages</span>
                </a>
                <a href="../index.php" class="text-green-600 hover:text-green-800 font-semibold">
                    <i class="fas fa-store-alt"></i><span class="hidden lg:inline"> Marketplace</span>
                </a>
                <span class="text-gray-700 hidden lg:inline"><?php echo htmlspecialchars($vendor['business_name']); ?></span>
                <a href="../logout.php" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-sign-out-alt"></i><span class="hidden lg:inline"> Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Mobile Menu -->
        <div id="mobile-menu" class="mobile-menu md:hidden">
            <a href="products.php" class="block py-2 text-gray-700 hover:bg-gray-50">
                <i class="fas fa-box mr-2"></i> Products
            </a>
            <a href="orders.php" class="block py-2 text-gray-700 hover:bg-gray-50">
                <i class="fas fa-shopping-cart mr-2"></i> Orders
            </a>
            <a href="messages.php" class="block py-2 text-gray-700 hover:bg-gray-50">
                <i class="fas fa-comments mr-2"></i> Messages
            </a>
            <a href="../index.php" class="block py-2 text-green-600 hover:bg-green-50">
                <i class="fas fa-store-alt mr-2"></i> Browse Marketplace
            </a>
            <div class="py-2 px-4 bg-gray-100 text-gray-700 text-sm">
                <?php echo htmlspecialchars($vendor['business_name']); ?>
            </div>
            <a href="../logout.php" class="block py-2 text-red-600 hover:bg-red-50">
                <i class="fas fa-sign-out-alt mr-2"></i> Logout
            </a>
        </div>
    </div>
</nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Order Management</h1>
            <p class="text-gray-600 mt-1">View and manage your customer orders</p>
        </div>

        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4 md:gap-6 mb-8 stats-grid">
            <a href="?status=all" class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition <?php echo $status_filter === 'all' ? 'ring-2 ring-green-500' : ''; ?>">
                <p class="text-sm text-gray-500">All Orders</p>
                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total']; ?></p>
            </a>
            <a href="?status=pending" class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition <?php echo $status_filter === 'pending' ? 'ring-2 ring-yellow-500' : ''; ?>">
                <p class="text-sm text-gray-500">Pending</p>
                <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['pending']; ?></p>
            </a>
            <a href="?status=confirmed" class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition <?php echo $status_filter === 'confirmed' ? 'ring-2 ring-blue-500' : ''; ?>">
                <p class="text-sm text-gray-500">Confirmed</p>
                <p class="text-2xl font-bold text-blue-600"><?php echo $stats['confirmed']; ?></p>
            </a>
            <a href="?status=ready" class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition <?php echo $status_filter === 'ready' ? 'ring-2 ring-purple-500' : ''; ?>">
                <p class="text-sm text-gray-500">Ready</p>
                <p class="text-2xl font-bold text-purple-600"><?php echo $stats['ready']; ?></p>
            </a>
            <a href="?status=completed" class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition <?php echo $status_filter === 'completed' ? 'ring-2 ring-green-500' : ''; ?>">
                <p class="text-sm text-gray-500">Completed</p>
                <p class="text-2xl font-bold text-green-600"><?php echo $stats['completed']; ?></p>
            </a>
            <a href="?status=cancelled" class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition <?php echo $status_filter === 'cancelled' ? 'ring-2 ring-red-500' : ''; ?>">
                <p class="text-sm text-gray-500">Cancelled</p>
                <p class="text-2xl font-bold text-red-600"><?php echo $stats['cancelled']; ?></p>
            </a>
        </div>

        <!-- Search Bar -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form method="GET" class="flex gap-4">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                <div class="flex-1">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                        placeholder="Search by order ID, customer name, or phone..." 
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>
                <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if ($search || $status_filter !== 'all'): ?>
                    <a href="orders.php" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Orders List -->
        <div class="bg-white rounded-lg shadow">
            <?php if (empty($orders)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">No orders found</p>
                    <?php if ($search || $status_filter !== 'all'): ?>
                        <a href="orders.php" class="text-green-600 hover:underline mt-2 inline-block">View all orders</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($orders as $order): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-semibold text-gray-900">#<?php echo $order['order_id']; ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($order['customer_email']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <i class="fas fa-phone text-gray-400"></i> <?php echo htmlspecialchars($order['customer_phone']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-lg font-semibold text-green-600">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
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
                                    <span class="px-3 py-1 <?php echo $colorClass; ?> text-xs font-semibold rounded-full">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($order['created_at'])); ?><br>
                                    <span class="text-xs"><?php echo date('h:i A', strtotime($order['created_at'])); ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <button onclick='viewOrder(<?php echo json_encode($order); ?>)' 
                                        class="text-blue-600 hover:text-blue-800 mr-3">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php if ($order['status'] !== 'completed' && $order['status'] !== 'cancelled'): ?>
                                        <button onclick='updateStatus(<?php echo $order['order_id']; ?>, "<?php echo $order['status']; ?>")' 
                                            class="text-green-600 hover:text-green-800">
                                            <i class="fas fa-edit"></i> Update
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- View Order Modal -->
    <div id="viewOrderModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 max-w-2xl w-full mx-4 max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold">Order Details</h2>
                <button onclick="closeViewModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <div id="orderDetails"></div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="statusModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 max-w-md w-full mx-4">
            <h2 class="text-2xl font-bold mb-6">Update Order Status</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" id="statusOrderId">
                
                <div class="mb-6">
                    <label class="block text-gray-700 font-semibold mb-3">Select New Status:</label>
                    <div class="space-y-2">
                        <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="status" value="pending" class="mr-3">
                            <span class="flex items-center">
                                <span class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></span>
                                Pending
                            </span>
                        </label>
                        <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="status" value="confirmed" class="mr-3">
                            <span class="flex items-center">
                                <span class="w-3 h-3 bg-blue-500 rounded-full mr-2"></span>
                                Confirmed
                            </span>
                        </label>
                        <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="status" value="ready" class="mr-3">
                            <span class="flex items-center">
                                <span class="w-3 h-3 bg-purple-500 rounded-full mr-2"></span>
                                Ready for Pickup
                            </span>
                        </label>
                        <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="status" value="completed" class="mr-3">
                            <span class="flex items-center">
                                <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                                Completed
                            </span>
                        </label>
                        <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="status" value="cancelled" class="mr-3">
                            <span class="flex items-center">
                                <span class="w-3 h-3 bg-red-500 rounded-full mr-2"></span>
                                Cancelled
                            </span>
                        </label>
                    </div>
                </div>
                
                <div class="flex space-x-4">
                    <button type="submit" class="flex-1 bg-green-600 text-white py-3 rounded-lg hover:bg-green-700">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                    <button type="button" onclick="closeStatusModal()" class="flex-1 bg-gray-300 text-gray-700 py-3 rounded-lg hover:bg-gray-400">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function viewOrder(order) {
            const html = `
                <div class="space-y-4">
                    <div class="border-b pb-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-semibold">Order #${order.order_id}</h3>
                                <p class="text-sm text-gray-500">${new Date(order.created_at).toLocaleString()}</p>
                            </div>
                            <span class="px-3 py-1 ${getStatusColor(order.status)} text-xs font-semibold rounded-full">
                                ${order.status.toUpperCase()}
                            </span>
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="font-semibold mb-2">Customer Information</h4>
                        <p class="text-sm"><i class="fas fa-user text-gray-400 w-5"></i> ${order.customer_name}</p>
                        <p class="text-sm"><i class="fas fa-phone text-gray-400 w-5"></i> ${order.customer_phone}</p>
                        <p class="text-sm"><i class="fas fa-envelope text-gray-400 w-5"></i> ${order.customer_email}</p>
                    </div>
                    
                    <div>
                        <h4 class="font-semibold mb-2">Order Details</h4>
                        <div class="bg-gray-50 p-4 rounded">
                            <div class="flex justify-between mb-2">
                                <span class="font-semibold">Total Amount:</span>
                                <span class="text-xl font-bold text-green-600">₱${parseFloat(order.total_amount).toFixed(2)}</span>
                            </div>
                            ${order.delivery_address ? `
                                <div class="mt-2 pt-2 border-t">
                                    <p class="text-sm font-semibold">Delivery Address:</p>
                                    <p class="text-sm text-gray-600">${order.delivery_address}</p>
                                </div>
                            ` : ''}
                            ${order.notes ? `
                                <div class="mt-2 pt-2 border-t">
                                    <p class="text-sm font-semibold">Order Notes:</p>
                                    <p class="text-sm text-gray-600">${order.notes}</p>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('orderDetails').innerHTML = html;
            document.getElementById('viewOrderModal').classList.remove('hidden');
        }
        
        function updateStatus(orderId, currentStatus) {
            document.getElementById('statusOrderId').value = orderId;
            
            // Pre-select current status
            const radios = document.querySelectorAll('input[name="status"]');
            radios.forEach(radio => {
                if (radio.value === currentStatus) {
                    radio.checked = true;
                }
            });
            
            document.getElementById('statusModal').classList.remove('hidden');
        }
        
        function closeViewModal() {
            document.getElementById('viewOrderModal').classList.add('hidden');
        }
        
        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
        }
        
        function getStatusColor(status) {
            const colors = {
                'pending': 'bg-yellow-100 text-yellow-800',
                'confirmed': 'bg-blue-100 text-blue-800',
                'ready': 'bg-purple-100 text-purple-800',
                'completed': 'bg-green-100 text-green-800',
                'cancelled': 'bg-red-100 text-red-800'
            };
            return colors[status] || 'bg-gray-100 text-gray-800';
        }
        
        // Close modals on outside click
        document.getElementById('viewOrderModal').addEventListener('click', function(e) {
            if (e.target === this) closeViewModal();
        });
        
        document.getElementById('statusModal').addEventListener('click', function(e) {
            if (e.target === this) closeStatusModal();
        });
    </script>
    <script src="../js/mobile-nav.js"></script>
</body>
</html>