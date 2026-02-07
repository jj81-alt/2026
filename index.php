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

    // Get all active vendors with location - FIXED QUERY
    $stmt = $db->query("SELECT vp.*, u.full_name, u.is_active,
        (SELECT COUNT(*) FROM products WHERE vendor_id = vp.vendor_id AND is_available = true) as product_count
        FROM vendor_profiles vp 
        JOIN users u ON vp.user_id = u.user_id 
        WHERE u.is_active = true 
        AND vp.location_lat IS NOT NULL 
        AND vp.location_lng IS NOT NULL
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
    <link rel="stylesheet" href="css/mobile-responsive.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="css/map-modal.css">
    <style>
        #map { 
            height: 500px; 
            border-radius: 0.5rem; 
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        
        /* Polomolok Watermark Overlay */
        .map-watermark {
            position: absolute;
            top: 15px;
            left: 60px;
            z-index: 1000;
            pointer-events: none;
            font-size: 1.5rem;
            font-weight: 700;
            color: rgba(0, 0, 0, 0.85);
            text-transform: uppercase;
            letter-spacing: 0.25em;
            transform: rotate(0deg);
            transition: opacity 1.5s ease-in-out;
            user-select: none;
            font-family: 'Georgia', 'Times New Roman', serif;
            text-shadow: 
                -1px -1px 0 rgba(255, 255, 255, 0.9),
                1px -1px 0 rgba(255, 255, 255, 0.9),
                -1px 1px 0 rgba(255, 255, 255, 0.9),
                1px 1px 0 rgba(255, 255, 255, 0.9),
                0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .map-watermark.hidden {
            opacity: 0;
        }
        
        .category-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .category-card:active {
            transform: translateY(-2px);
        }
        
        .vendor-card {
            transition: all 0.3s ease;
        }
        
        .vendor-card:hover {
            transform: translateY(-5px);
        }
        
        .pulse-dot {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: .5;
            }
        }
        @keyframes pulse {
    0%, 100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.8;
        transform: scale(0.95);
    }
}

.animate-pulse {
    animation: pulse 0.5s ease-in-out;
}
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <!-- Navigation -->
<nav class="bg-white shadow-lg sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center">
                <h1 class="text-xl md:text-2xl font-bold text-green-600">🏪 MarketConnect</h1>
                <p class="ml-4 text-sm text-gray-500 hidden lg:block">Your Local Public Market, Now Online</p>
            </div>
            
            <!-- Mobile menu button -->
            <button id="mobile-menu-button" class="md:hidden mobile-menu-button text-gray-700 hover:text-green-600">
                <i class="fas fa-bars text-2xl"></i>
            </button>
            
            <!-- Desktop menu -->
            <div class="hidden md:flex items-center space-x-4 lg:space-x-6">
                <?php if (isLoggedIn()): ?>
                    <?php if (getUserType() === 'vendor'): ?>
                        <a href="vendor/dashboard.php" class="text-gray-700 hover:text-green-600 transition">
                            <i class="fas fa-store"></i><span class="hidden lg:inline"> My Store</span>
                        </a>
                    <?php elseif (getUserType() === 'admin'): ?>
                        <a href="admin/dashboard.php" class="text-gray-700 hover:text-green-600 transition">
                            <i class="fas fa-cog"></i><span class="hidden lg:inline"> Admin</span>
                        </a>
                    <?php else: ?>
                        <a href="customer/orders.php" class="text-gray-700 hover:text-green-600 transition">
                            <i class="fas fa-shopping-bag"></i><span class="hidden lg:inline"> Orders</span>
                        </a>
                        <a href="customer/favorites.php" class="text-gray-700 hover:text-green-600 transition">
                            <i class="fas fa-heart"></i><span class="hidden lg:inline"> Favorites</span>
                        </a>
                    <?php endif; ?>
                    <span class="text-gray-700 font-medium hidden lg:inline">Hi, <?php echo htmlspecialchars(getUserName()); ?>!</span>
                    <a href="logout.php" class="text-red-600 hover:text-red-800 transition">
                        <i class="fas fa-sign-out-alt"></i><span class="hidden lg:inline"> Logout</span>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="text-gray-700 hover:text-green-600 transition">
                        <i class="fas fa-sign-in-alt"></i><span class="hidden lg:inline"> Login</span>
                    </a>
                    <a href="register.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition shadow-md">
                        <i class="fas fa-user-plus"></i><span class="hidden lg:inline"> Sign Up</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Mobile menu -->
        <div id="mobile-menu" class="mobile-menu md:hidden">
            <?php if (isLoggedIn()): ?>
                <?php if (getUserType() === 'vendor'): ?>
                    <a href="vendor/dashboard.php" class="block py-2 text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-store mr-2"></i> My Store
                    </a>
                <?php elseif (getUserType() === 'admin'): ?>
                    <a href="admin/dashboard.php" class="block py-2 text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-cog mr-2"></i> Admin
                    </a>
                <?php else: ?>
                    <a href="customer/orders.php" class="block py-2 text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-shopping-bag mr-2"></i> My Orders
                    </a>
                    <a href="customer/favorites.php" class="block py-2 text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-heart mr-2"></i> Favorites
                    </a>
                <?php endif; ?>
                <div class="py-2 px-4 bg-gray-100 text-gray-700 font-medium">
                    Hi, <?php echo htmlspecialchars(getUserName()); ?>!
                </div>
                <a href="logout.php" class="block py-2 text-red-600 hover:bg-red-50">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            <?php else: ?>
                <a href="login.php" class="block py-2 text-gray-700 hover:bg-gray-50">
                    <i class="fas fa-sign-in-alt mr-2"></i> Login
                </a>
                <a href="register.php" class="block py-2 text-green-600 hover:bg-green-50 font-semibold">
                    <i class="fas fa-user-plus mr-2"></i> Sign Up
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-green-500 to-green-600 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="text-center">
                <h1 class="text-5xl font-bold mb-4">Discover Fresh Products from Your Local Public Market</h1>
                <p class="text-xl text-green-100 mb-8">Browse, negotiate, and order from trusted vendors near you</p>
                <div class="max-w-2xl mx-auto">
                    <div class="flex shadow-lg rounded-lg overflow-hidden">
                        <input type="text" id="searchInput" placeholder="Search for products or vendors..." 
                            class="flex-1 px-6 py-4 text-gray-900 focus:outline-none focus:ring-2 focus:ring-green-300">
                        <button onclick="searchVendors()" class="bg-green-700 px-8 py-4 hover:bg-green-800 transition font-semibold">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <!-- Categories Section -->
        <div class="mb-12">
            <div class="flex justify-between items-center mb-6">
    <h2 class="text-3xl font-bold text-gray-800">
        <i class="fas fa-th-large text-green-600"></i> Shop by Category
    </h2>
    <button onclick="showAllVendors()" class="text-green-600 hover:text-green-800 font-semibold transition">
        Show All
    </button>
</div>

            <div class="grid grid-cols-2 md:grid-cols-5 lg:grid-cols-5 gap-4">
                <?php foreach ($categories as $category): ?>
                <div class="category-card bg-white rounded-xl shadow-md p-6 text-center hover:shadow-xl" 
                    onclick="filterByCategory('<?php echo htmlspecialchars($category['category_name']); ?>')">
                    <div class="mb-3">
                        <i class="fas <?php echo htmlspecialchars($category['icon']); ?> text-5xl text-green-600"></i>
                    </div>
                    <p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($category['category_name']); ?></p>
                    <?php if ($category['description']): ?>
                        <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($category['description']); ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Interactive Map -->
        <div class="mb-12">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-3xl font-bold text-gray-800">
                    <i class="fas fa-map-marked-alt text-green-600"></i> Find Vendors Near You
                </h2>
                <div class="flex gap-3">
                    <button onclick="locateMe()" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition shadow-md">
                        <i class="fas fa-location-arrow"></i> Locate Me
                    </button>
                    <button onclick="openMapModal()" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition shadow-md">
                        <i class="fas fa-expand"></i> Maximize Map
                    </button>
                </div>
            </div>
            <div id="map">
                <!-- Polomolok Watermark Overlay -->
                <div class="map-watermark" id="mapWatermark">Polomolok</div>
            </div>
        </div>

        <!-- Featured Vendors -->
        <div class="mb-12">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-3xl font-bold text-gray-800">
                    <i class="fas fa-star text-yellow-500"></i> Featured Vendors
                </h2>
                <div class="text-sm text-gray-600">
                    <span class="pulse-dot inline-block w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                    <?php echo count($vendors); ?> vendors available
                </div>
            </div>
            
            <?php if (empty($vendors)): ?>
                <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border-2 border-yellow-200 rounded-xl p-8 text-center">
                    <i class="fas fa-store-slash text-yellow-600 text-6xl mb-4"></i>
                    <h3 class="text-2xl font-bold text-yellow-900 mb-3">No Vendors Available Yet</h3>
                    <p class="text-yellow-700 mb-6 text-lg">Vendors need to update their location information to appear on the map.</p>
                    <?php if (isLoggedIn() && getUserType() === 'vendor'): ?>
                        <a href="vendor/dashboard.php" class="inline-block bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 transition shadow-md font-semibold">
                            <i class="fas fa-map-marker-alt"></i> Update My Location
                        </a>
                    <?php else: ?>
                        <a href="register.php" class="inline-block bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 transition shadow-md font-semibold">
                            <i class="fas fa-user-plus"></i> Become a Vendor
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div id="vendorList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($vendors as $vendor): ?>
                    <div class="vendor-card bg-white rounded-xl shadow-md hover:shadow-2xl overflow-hidden" 
                        data-category="<?php echo htmlspecialchars($vendor['category'] ?? ''); ?>"
                        data-name="<?php echo htmlspecialchars($vendor['business_name']); ?>">
                        
                        <!-- Card Header with Featured Badge -->
                        <div class="bg-gradient-to-r from-green-500 to-green-600 p-4 relative">
                            <?php if ($vendor['is_featured']): ?>
                                <span class="absolute top-3 right-3 bg-yellow-400 text-yellow-900 text-xs px-3 py-1 rounded-full font-bold shadow-md">
                                    <i class="fas fa-star"></i> Featured
                                </span>
                            <?php endif; ?>
                            <h3 class="text-xl font-bold text-white mb-1"><?php echo htmlspecialchars($vendor['business_name']); ?></h3>
                            <p class="text-green-100 text-sm">
                                <i class="fas fa-map-marker-alt"></i> 
                                <?php echo htmlspecialchars($vendor['market_name']); ?>
                                <?php if ($vendor['stall_number']): ?>
                                    <span class="ml-1">• Stall <?php echo htmlspecialchars($vendor['stall_number']); ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <!-- Card Body -->
                        <div class="p-6">
                            <?php if ($vendor['description']): ?>
                                <p class="text-gray-600 mb-4 text-sm leading-relaxed">
                                    <?php echo htmlspecialchars(substr($vendor['description'], 0, 100)); ?>
                                    <?php if (strlen($vendor['description']) > 100): ?>...<?php endif; ?>
                                </p>
                            <?php endif; ?>
                            
                            <!-- Stats Row -->
                            <div class="flex items-center justify-between mb-4 bg-gray-50 p-3 rounded-lg">
                                <div class="flex items-center text-yellow-500">
                                    <i class="fas fa-star"></i>
                                    <span class="ml-2 text-gray-800 font-bold"><?php echo number_format($vendor['rating_average'], 1); ?></span>
                                    <span class="ml-1 text-gray-500 text-sm">(<?php echo $vendor['total_reviews']; ?>)</span>
                                </div>
                                <div class="text-sm text-gray-600">
                                    <i class="fas fa-box text-green-600"></i> 
                                    <span class="font-semibold"><?php echo $vendor['product_count']; ?></span> products
                                </div>
                            </div>
                            
                            <!-- Category Badge -->
                            <?php if ($vendor['category']): ?>
                                <div class="mb-4">
                                    <span class="inline-block bg-green-100 text-green-800 text-xs px-3 py-1 rounded-full font-semibold">
                                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($vendor['category']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Action Buttons -->
                            <div class="flex space-x-2">
                                <a href="vendor_view.php?id=<?php echo $vendor['vendor_id']; ?>" 
                                    class="flex-1 bg-green-600 text-white text-center py-3 rounded-lg hover:bg-green-700 transition font-semibold shadow-md">
                                    <i class="fas fa-store"></i> View Store
                                </a>
                                <?php if (isLoggedIn() && getUserType() === 'customer'): ?>
                                    <button onclick="toggleFavorite(<?php echo $vendor['vendor_id']; ?>)" 
                                        class="bg-gray-100 text-gray-700 px-4 py-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition shadow-md">
                                        <i class="far fa-heart"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p class="text-gray-400">© 2026 MarketConnect - Connecting Communities, One Market at a Time</p>
            <p class="text-gray-500 text-sm mt-2">Built with ❤️ for local vendors and customers</p>
        </div>
    </footer>

    <!-- Map Modal -->
    <div id="mapModal" class="map-modal hidden">
        <div class="map-modal-content">
            <!-- Modal Header -->
            <div class="map-modal-header">
                <div class="flex items-center gap-4">
                    <h2 class="text-2xl font-bold text-white">
                        <i class="fas fa-map-marked-alt"></i> Explore Vendors Map
                    </h2>
                    <div class="flex-1 max-w-md">
                        <input type="text" id="modalSearchInput" placeholder="Search stores..." 
                            class="w-full px-4 py-2 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-green-300">
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="modalLocateMe()" class="bg-white text-green-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition">
                        <i class="fas fa-location-arrow"></i> Locate Me
                    </button>
                    <button onclick="closeMapModal()" class="text-white hover:text-gray-200 text-2xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="map-modal-body">
                <!-- Map Container -->
                <div id="modalMap" class="modal-map-container">
                    <!-- Polomolok Watermark Overlay -->
                    <div class="modal-map-watermark" id="modalMapWatermark">Polomolok</div>
                </div>

                <!-- Store Details Sidebar -->
                <div id="storeSidebar" class="store-sidebar hidden">
                    <div class="store-sidebar-content">
                        <div class="flex justify-between items-start mb-4">
                            <h3 id="storeTitle" class="text-2xl font-bold text-gray-800"></h3>
                            <button onclick="closeStoreSidebar()" class="text-gray-500 hover:text-gray-700">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>

                        <div id="storeDetails" class="space-y-4">
                            <!-- Store details will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ========================================
        // 🎯 MAP CONFIGURATION - ADJUST HERE
        // ========================================
        const MAP_CENTER_LAT = 6.218658;      // Polomolok Public Market Latitude
        const MAP_CENTER_LNG = 125.062795;    // Polomolok Public Market Longitude
        const MAP_INITIAL_ZOOM = 15;          // Initial zoom level (10-19)
        const WATERMARK_HIDE_ZOOM = 16;       // Hide watermark when zoom >= this level
        
        // Zoom Level Guide:
        // 13 = City-wide view (entire Polomolok)
        // 14 = District view (multiple barangays)
        // 15 = Neighborhood view (Barangay Poblacion) ✅ DEFAULT
        // 16 = Street-level view (detailed)
        // 17 = Building-level view (very detailed)
        // ========================================
        
        // Initialize map - Focused on Polomolok Public Market, Barangay Poblacion
        const map = L.map('map').setView([MAP_CENTER_LAT, MAP_CENTER_LNG], MAP_INITIAL_ZOOM);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);
        
        // Watermark visibility control based on zoom level
        const watermark = document.getElementById('mapWatermark');
        
        function updateWatermarkVisibility() {
            const currentZoom = map.getZoom();
            if (currentZoom >= WATERMARK_HIDE_ZOOM) {
                watermark.classList.add('hidden');
            } else {
                watermark.classList.remove('hidden');
            }
        }
        
        // Listen for zoom changes
        map.on('zoomend', updateWatermarkVisibility);
        
        // Initial check
        updateWatermarkVisibility();

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
        
        if (vendors.length === 0) {
            // Show message on map if no vendors
            L.popup()
                .setLatLng([MAP_CENTER_LAT, MAP_CENTER_LNG])
                .setContent('<div class="text-center p-2"><strong class="text-lg">📍 No vendors with location data yet</strong><br><small class="text-gray-600">Vendors need to set their location</small></div>')
                .openOn(map);
        } else {
            vendors.forEach(vendor => {
                if (vendor.location_lat && vendor.location_lng) {
                    const marker = L.marker([vendor.location_lat, vendor.location_lng], {icon: greenIcon})
                        .addTo(map)
                        .bindPopup(`
                            <div class="text-center p-3" style="min-width: 200px;">
                                <h3 class="font-bold text-lg mb-1">${vendor.business_name}</h3>
                                <p class="text-sm text-gray-600 mb-1">${vendor.market_name}</p>
                                ${vendor.stall_number ? `<p class="text-xs text-gray-500 mb-2">Stall ${vendor.stall_number}</p>` : ''}
                                <div class="mb-3">
                                    <span class="text-yellow-500 font-semibold">⭐ ${vendor.rating_average}</span>
                                    <span class="text-gray-500 text-xs">(${vendor.total_reviews} reviews)</span>
                                </div>
                                <a href="vendor_view.php?id=${vendor.vendor_id}" 
                                    class="inline-block bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 transition font-semibold">
                                    <i class="fas fa-store"></i> View Store
                                </a>
                            </div>
                        `);
                }
            });
        }

        // Locate user
        function locateMe() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(position => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    map.setView([lat, lng], 15);
                    
                    const userMarker = L.marker([lat, lng], {
                        icon: L.icon({
                            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                            iconSize: [25, 41],
                            iconAnchor: [12, 41],
                            popupAnchor: [1, -34],
                            shadowSize: [41, 41]
                        })
                    }).addTo(map)
                        .bindPopup('<strong>📍 You are here!</strong>').openPopup();
                }, () => {
                    alert('⚠️ Unable to retrieve your location. Please check your browser permissions.');
                });
            } else {
                alert('❌ Geolocation is not supported by your browser');
            }
        }

        // Search functionality
// Search functionality - UPDATED
function searchVendors() {
    const query = document.getElementById('searchInput').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.vendor-card');
    const filterStatus = document.getElementById('filterStatus');
    const filterText = document.getElementById('filterText');
    
    if (cards.length === 0) return;
    
    if (!query) {
        showAllVendors();
        return;
    }
    
    let found = false;
    let foundCount = 0;
    
    cards.forEach(card => {
        const name = card.dataset.name.toLowerCase();
        const category = card.dataset.category.toLowerCase();
        
        if (name.includes(query) || category.includes(query)) {
            card.style.display = 'block';
            found = true;
            foundCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    if (found) {
        // Show filter status
        filterStatus.classList.remove('hidden');
        filterText.textContent = `${foundCount} vendor${foundCount !== 1 ? 's' : ''} found for "${query}"`;
        
        // Scroll to vendors section
        document.getElementById('vendorList').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        alert('🔍 No vendors found matching "' + query + '"');
        showAllVendors();
    }
}

// Show all vendors - UPDATED
// Show all vendors - FIXED VERSION
function showAllVendors() {
    const cards = document.querySelectorAll('.vendor-card');
    const searchInput = document.getElementById('searchInput');
    
    // Show all vendor cards
    cards.forEach(card => {
        card.style.display = 'block';
    });
    
    // Clear the search input
    if (searchInput) {
        searchInput.value = '';
    }
    
    // Optional: Scroll to vendors section smoothly
    const vendorSection = document.querySelector('.vendor-card');
    if (vendorSection) {
        vendorSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    
    // Console log for debugging (optional - you can remove this)
    console.log('Showing all ' + cards.length + ' vendors');
}

// Filter by category - UPDATED
function filterByCategory(category) {
    const cards = document.querySelectorAll('.vendor-card');
    const filterStatus = document.getElementById('filterStatus');
    const filterText = document.getElementById('filterText');
    
    if (cards.length === 0) {
        alert('ℹ️ No vendors available in this category yet');
        return;
    }
    
    let found = false;
    let foundCount = 0;
    
    cards.forEach(card => {
        if (card.dataset.category === category) {
            card.style.display = 'block';
            found = true;
            foundCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    if (found) {
        // Show filter status
        filterStatus.classList.remove('hidden');
        filterText.textContent = `${foundCount} vendor${foundCount !== 1 ? 's' : ''} in "${category}"`;
        
        // Scroll to vendors section
        document.getElementById('vendorList').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        alert('ℹ️ No vendors found in the "' + category + '" category');
        showAllVendors();
    }
}

    </script>
    <script src="js/map-modal.js"></script>
    <script src="js/mobile-nav.js"></script>
</body>
</html>