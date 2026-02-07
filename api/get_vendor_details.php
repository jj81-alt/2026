<?php
// api/get_vendor_details.php
// API to fetch vendor details, products, and photos for the map modal

header('Content-Type: application/json');
require_once '../config/database.php';

// Get vendor ID from request
$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;

if ($vendor_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid vendor ID'
    ]);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get vendor details
    $stmt = $db->prepare("
        SELECT 
            vp.*,
            u.full_name,
            u.email,
            u.phone_number
        FROM vendor_profiles vp
        JOIN users u ON vp.user_id = u.user_id
        WHERE vp.vendor_id = ? AND u.is_active = true
    ");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendor) {
        echo json_encode([
            'success' => false,
            'message' => 'Vendor not found'
        ]);
        exit();
    }
    
    // Get vendor products (active only)
    $stmt = $db->prepare("
        SELECT 
            product_id,
            product_name,
            description,
            price,
            unit,
            stock_quantity,
            is_available,
            image_url,
            category_id
        FROM products
        WHERE vendor_id = ? AND is_available = true
        ORDER BY product_name ASC
        LIMIT 50
    ");
    $stmt->execute([$vendor_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get vendor photos (if you have a vendor_photos table)
    // Note: Adjust this query based on your actual database schema
    $photos = [];
    
    // Option 1: If you have a dedicated vendor_photos table
    $stmt = $db->prepare("
        SELECT 
            photo_id,
            photo_url,
            caption,
            created_at
        FROM vendor_photos
        WHERE vendor_id = ?
        ORDER BY created_at DESC
        LIMIT 20
    ");
    
    // Try to execute, catch any errors if table doesn't exist
    try {
        $stmt->execute([$vendor_id]);
        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table might not exist, that's okay
        $photos = [];
    }
    
    // Option 2: If photos are stored as product images, get unique product images
    if (empty($photos)) {
        $stmt = $db->prepare("
            SELECT DISTINCT
                product_id as photo_id,
                image_url as photo_url,
                product_name as caption,
                created_at
            FROM products
            WHERE vendor_id = ? AND image_url IS NOT NULL AND image_url != ''
            ORDER BY created_at DESC
            LIMIT 20
        ");
        try {
            $stmt->execute([$vendor_id]);
            $productPhotos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($productPhotos)) {
                $photos = $productPhotos;
            }
        } catch (PDOException $e) {
            // If this also fails, leave photos empty
        }
    }
    
    // Format the response
    $response = [
        'success' => true,
        'vendor' => [
            'vendor_id' => $vendor['vendor_id'],
            'business_name' => $vendor['business_name'],
            'description' => $vendor['description'],
            'market_name' => $vendor['market_name'],
            'stall_number' => $vendor['stall_number'],
            'category' => $vendor['category'],
            'rating_average' => floatval($vendor['rating_average']),
            'total_reviews' => intval($vendor['total_reviews']),
            'location_lat' => floatval($vendor['location_lat']),
            'location_lng' => floatval($vendor['location_lng']),
            'is_featured' => $vendor['is_featured'],
            'is_verified' => $vendor['verified'] ?? false,
            'phone_number' => $vendor['phone_number'],
            'email' => $vendor['email']
        ],
        'products' => array_map(function($product) {
            return [
                'product_id' => $product['product_id'],
                'product_name' => $product['product_name'],
                'description' => $product['description'],
                'price' => floatval($product['price']),
                'unit' => $product['unit'],
                'stock_quantity' => intval($product['stock_quantity']),
                'is_available' => $product['is_available'],
                'image_url' => $product['image_url']
            ];
        }, $products),
        'photos' => array_map(function($photo) {
            return [
                'photo_id' => $photo['photo_id'],
                'photo_url' => $photo['photo_url'],
                'caption' => $photo['caption'] ?? null,
                'created_at' => $photo['created_at']
            ];
        }, $photos),
        'stats' => [
            'total_products' => count($products),
            'total_photos' => count($photos)
        ]
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Database error in get_vendor_details.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error in get_vendor_details.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred',
        'error' => $e->getMessage()
    ]);
}
?>