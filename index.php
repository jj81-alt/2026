<?php
// index.php (Customer Homepage)
require_once 'includes/session.php';
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Test connection
    if (!$db) {
        throw new Exception("Database connection failed");
    }

    // Get all active vendors with location
    $stmt = $db->query("SELECT vp.*, u.full_name, 
        (SELECT COUNT(*) FROM products WHERE vendor_id = vp.vendor_id AND is_available = true) as product_count
        FROM vendor_profiles vp 
        JOIN users u ON vp.user_id = u.user_id 
        WHERE vp.is_active = true AND vp.location_lat IS NOT NULL AND vp.location_lng IS NOT NULL
        ORDER BY vp.is_featured DESC, vp.rating_average DESC");
    $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get categories
    $stmt = $db->query("SELECT * FROM categories ORDER BY category_name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage() . "<br>Check your config/database.php file");
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MarketConnect - Your Local Public Market Online</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        #map { height: 500px; border-radius: 0.5rem; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-green-600">🏪 MarketConnect</h1>
                    <p class="ml-4 text-sm text-gray-500 hidden md:block">Your Local Public Market, Now Online</p>
                </div>
                <div class="flex items-center space-x-6">
                    <?php if (isLoggedIn()): ?>
                        <?php if (getUserType() === 'vendor'): ?>
                            <a href="vendor/dashboard.php" class="text-gray-700 hover:text-green-600">
                                <i class="fas fa-store"></i> My Store
                            </a>
                        <?php elseif (getUserType() === 'admin'): ?>
                            <a href="admin/dashboard.php" class="text-gray-700 hover:text-green-600">
                                <i class="fas fa-cog"></i> Admin
                            </a>
                        <?php else: ?>
                            <a href="customer/orders.php" class="text-gray-700 hover:text-green-600">
                                <i class="fas fa-shopping-bag"></i> My Orders
                            </a>
                            <a href="customer/favorites.php" class="text-gray-700 hover:text-green-600">
                                <i class="fas fa-heart"></i> Favorites
                            </a>
                        <?php endif; ?>
                        <span class="text-gray-700">Hi, <?php echo htmlspecialchars(getUserName()); ?>!</span>
                        <a href="logout.php" class="text-red-600 hover:text-red-800">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="text-gray-700 hover:text-green-600">Login</a>
                        <a href="register.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-green-500 to-green-600 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="text-center">
                <h1 class="text-4xl font-bold mb-4">Discover Fresh Products from Your Local Public Market</h1>
                <p class="text-xl text-green-100 mb-6">Browse, negotiate, and order from trusted vendors near you</p>
                <div class="max-w-2xl mx-auto">
                    <div class="flex">
                        <input type="text" id="searchInput" placeholder="Search for products or vendors..." 
                            class="flex-1 px-6 py-3 rounded-l-lg text-gray-900 focus:outline-none">
                        <button onclick="searchVendors()" class="bg-green-700 px-8 py-3 rounded-r-lg hover:bg-green-800 transition">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Categories -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Shop by Category</h2>
            <div class="grid grid-cols-2 md:grid-cols-5 lg:grid-cols-10 gap-4">
                <?php foreach ($categories as $category): ?>
                <div class="bg-white rounded-lg shadow p-4 text-center hover:shadow-lg transition cursor-pointer" 
                    onclick="filterByCategory('<?php echo htmlspecialchars($category['category_name']); ?>')">
                    <div class="text-3xl mb-2"><?php echo $category['icon']; ?></div>
                    <p class="text-xs font-semibold text-gray-700"><?php echo htmlspecialchars($category['category_name']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Interactive Map -->
        <div class="mb-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold text-gray-800">Find Vendors Near You</h2>
                <button onclick="locateMe()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                    <i class="fas fa-location-arrow"></i> Locate Me
                </button>
            </div>
            <div id="map" class="shadow-lg"></div>
        </div>

        <!-- Featured Vendors -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Featured Vendors</h2>
            <div id="vendorList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($vendors as $vendor): ?>
                <div class="bg-white rounded-lg shadow hover:shadow-xl transition vendor-card" 
                    data-category="<?php echo htmlspecialchars($vendor['category']); ?>"
                    data-name="<?php echo htmlspecialchars($vendor['business_name']); ?>">
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <h3 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($vendor['business_name']); ?></h3>
                                <p class="text-sm text-gray-500">
                                    <i class="fas fa-map-marker-alt text-green-600"></i> 
                                    <?php echo htmlspecialchars($vendor['market_name']); ?>
                                    <?php if ($vendor['stall_number']): ?>
                                        - Stall <?php echo htmlspecialchars($vendor['stall_number']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php if ($vendor['is_featured']): ?>
                                <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded">⭐ Featured</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($vendor['description']): ?>
                            <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars(substr($vendor['description'], 0, 80)); ?>...</p>
                        <?php endif; ?>
                        
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center text-yellow-500">
                                <i class="fas fa-star"></i>
                                <span class="ml-1 text-gray-700 font-semibold"><?php echo number_format($vendor['rating_average'], 1); ?></span>
                                <span class="ml-1 text-gray-500 text-sm">(<?php echo $vendor['total_reviews']; ?>)</span>
                            </div>
                            <div class="text-sm text-gray-600">
                                <i class="fas fa-box text-green-600"></i> <?php echo $vendor['product_count']; ?> products
                            </div>
                        </div>
                        
                        <?php if ($vendor['category']): ?>
                            <div class="mb-3">
                                <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded"><?php echo htmlspecialchars($vendor['category']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="flex space-x-2">
                            <a href="vendor_view.php?id=<?php echo $vendor['vendor_id']; ?>" 
                                class="flex-1 bg-green-600 text-white text-center py-2 rounded hover:bg-green-700 transition">
                                View Store
                            </a>
                            <?php if (isLoggedIn() && getUserType() === 'customer'): ?>
                                <button onclick="toggleFavorite(<?php echo $vendor['vendor_id']; ?>)" 
                                    class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300 transition">
                                    <i class="far fa-heart"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        // Initialize map
        const map = L.map('map').setView([6.9214, 125.0853], 13); // Polomolok, South Cotabato
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Custom marker icon
        const greenIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });

        // Add vendor markers
        const vendors = <?php echo json_encode($vendors); ?>;
        vendors.forEach(vendor => {
            if (vendor.location_lat && vendor.location_lng) {
                const marker = L.marker([vendor.location_lat, vendor.location_lng], {icon: greenIcon})
                    .addTo(map)
                    .bindPopup(`
                        <div class="text-center">
                            <h3 class="font-bold text-lg">${vendor.business_name}</h3>
                            <p class="text-sm text-gray-600">${vendor.market_name}</p>
                            ${vendor.stall_number ? `<p class="text-xs text-gray-500">Stall ${vendor.stall_number}</p>` : ''}
                            <div class="mt-2">
                                <span class="text-yellow-500">⭐ ${vendor.rating_average}</span>
                            </div>
                            <a href="vendor_view.php?id=${vendor.vendor_id}" 
                                class="inline-block mt-2 bg-green-600 text-white px-4 py-1 rounded text-sm hover:bg-green-700">
                                View Store
                            </a>
                        </div>
                    `);
            }
        });

        // Locate user
        function locateMe() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(position => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    map.setView([lat, lng], 15);
                    L.marker([lat, lng]).addTo(map)
                        .bindPopup('You are here!').openPopup();
                }, () => {
                    alert('Unable to retrieve your location');
                });
            } else {
                alert('Geolocation is not supported by your browser');
            }
        }

        // Search functionality
        function searchVendors() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.vendor-card');
            
            cards.forEach(card => {
                const name = card.dataset.name.toLowerCase();
                const category = card.dataset.category.toLowerCase();
                
                if (name.includes(query) || category.includes(query)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Filter by category
        function filterByCategory(category) {
            const cards = document.querySelectorAll('.vendor-card');
            
            cards.forEach(card => {
                if (card.dataset.category === category) {
                    card.style.display = 'block';
                    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Toggle favorite
        function toggleFavorite(vendorId) {
            fetch('api/toggle_favorite.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({vendor_id: vendorId})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Favorite updated!');
                } else {
                    alert('Please login to add favorites');
                }
            });
        }

        // Enter key search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchVendors();
            }
        });
    </script>
</body>
</html>