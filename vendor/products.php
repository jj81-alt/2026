<?php
// vendor/products.php
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

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $product_name = trim($_POST['product_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $unit = trim($_POST['unit'] ?? '');
        $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
        $category_id = intval($_POST['category_id'] ?? 0);
        
        if (!empty($product_name) && $price > 0) {
            $stmt = $db->prepare("INSERT INTO products (vendor_id, category_id, product_name, description, price, unit, stock_quantity, is_available) VALUES (?, ?, ?, ?, ?, ?, ?, true)");
            if ($stmt->execute([$vendor_id, $category_id, $product_name, $description, $price, $unit, $stock_quantity])) {
                $message = 'Product added successfully!';
            } else {
                $error = 'Failed to add product';
            }
        }
    } elseif ($action === 'update') {
        $product_id = intval($_POST['product_id'] ?? 0);
        $product_name = trim($_POST['product_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $unit = trim($_POST['unit'] ?? '');
        $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        
        $stmt = $db->prepare("UPDATE products SET product_name = ?, description = ?, price = ?, unit = ?, stock_quantity = ?, is_available = ? WHERE product_id = ? AND vendor_id = ?");
        if ($stmt->execute([$product_name, $description, $price, $unit, $stock_quantity, $is_available, $product_id, $vendor_id])) {
            $message = 'Product updated successfully!';
        }
    } elseif ($action === 'delete') {
        $product_id = intval($_POST['product_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM products WHERE product_id = ? AND vendor_id = ?");
        if ($stmt->execute([$product_id, $vendor_id])) {
            $message = 'Product deleted successfully!';
        }
    }
}

// Get all products
$stmt = $db->prepare("SELECT p.* FROM products p WHERE p.vendor_id = ? ORDER BY p.created_at DESC");
$stmt->execute([$vendor_id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories
$categories = []; // Temporarily disable categories
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - MarketConnect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
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
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Manage Products</h1>
            <button onclick="showAddModal()" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700">
                <i class="fas fa-plus"></i> Add New Product
            </button>
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

        <!-- Products Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4 md:gap-6 mb-8 stats-grid">
            <?php foreach ($products as $product): ?>
            <div class="bg-white rounded-lg shadow hover:shadow-lg transition">
                <div class="p-6">
                    <div class="flex justify-between items-start mb-3">
                        <h3 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                        <?php if ($product['is_available']): ?>
                            <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Available</span>
                        <?php else: ?>
                            <span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded">Unavailable</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($product['description']): ?>
                        <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars(substr($product['description'], 0, 80)); ?>...</p>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <p class="text-2xl font-bold text-green-600">₱<?php echo number_format($product['price'], 2); ?></p>
                        <p class="text-sm text-gray-500">per <?php echo htmlspecialchars($product['unit']); ?></p>
                    </div>
                    
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-sm text-gray-500">Stock:</p>
                            <p class="font-semibold <?php echo $product['stock_quantity'] < 10 ? 'text-red-600' : 'text-gray-800'; ?>">
                                <?php echo $product['stock_quantity']; ?> <?php echo htmlspecialchars($product['unit']); ?>
                            </p>
                        </div>
                        <?php if ($product['category_name']): ?>
                            <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">
                                <?php echo htmlspecialchars($product['category_name']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex space-x-2">
                        <button onclick='editProduct(<?php echo json_encode($product); ?>)' 
                            class="flex-1 bg-blue-600 text-white py-2 rounded hover:bg-blue-700">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <form method="POST" onsubmit="return confirm('Delete this product?')" class="flex-1">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                            <button type="submit" class="w-full bg-red-600 text-white py-2 rounded hover:bg-red-700">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($products)): ?>
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">No products yet. Add your first product!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="productModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 max-w-2xl w-full mx-4 max-h-screen overflow-y-auto">
            <h2 id="modalTitle" class="text-2xl font-bold mb-6">Add New Product</h2>
            
            <form method="POST" id="productForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="product_id" id="productId">
                
                <div class="grid md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Product Name *</label>
                        <input type="text" name="product_name" id="productName" required
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Category</label>
                        <select name="category_id" id="categoryId"
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                            <option value="">Select category...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 font-semibold mb-2">Description</label>
                    <textarea name="description" id="description" rows="3"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500"></textarea>
                </div>
                
                <div class="grid md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Price (₱) *</label>
                        <input type="number" name="price" id="price" step="0.01" required
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Unit *</label>
                        <input type="text" name="unit" id="unit" placeholder="kg, pcs, bundle" required
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Stock Quantity *</label>
                        <input type="number" name="stock_quantity" id="stockQuantity" required
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    </div>
                </div>
                
                <div id="availabilityDiv" class="mb-6 hidden">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_available" id="isAvailable" class="mr-2">
                        <span class="text-gray-700">Product is available for sale</span>
                    </label>
                </div>
                
                <div class="flex space-x-4">
                    <button type="submit" class="flex-1 bg-green-600 text-white py-3 rounded-lg hover:bg-green-700">
                        <i class="fas fa-save"></i> Save Product
                    </button>
                    <button type="button" onclick="closeModal()" class="flex-1 bg-gray-300 text-gray-700 py-3 rounded-lg hover:bg-gray-400">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Product';
            document.getElementById('formAction').value = 'add';
            document.getElementById('productForm').reset();
            document.getElementById('availabilityDiv').classList.add('hidden');
            document.getElementById('productModal').classList.remove('hidden');
        }

        function editProduct(product) {
            document.getElementById('modalTitle').textContent = 'Edit Product';
            document.getElementById('formAction').value = 'update';
            document.getElementById('productId').value = product.product_id;
            document.getElementById('productName').value = product.product_name;
            document.getElementById('description').value = product.description || '';
            document.getElementById('price').value = product.price;
            document.getElementById('unit').value = product.unit;
            document.getElementById('stockQuantity').value = product.stock_quantity;
            document.getElementById('categoryId').value = product.category_id || '';
            document.getElementById('isAvailable').checked = product.is_available;
            document.getElementById('availabilityDiv').classList.remove('hidden');
            document.getElementById('productModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('productModal').classList.add('hidden');
        }

        // Close modal on outside click
        document.getElementById('productModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
    <script src="../js/mobile-nav.js"></script>
</body>
</html>