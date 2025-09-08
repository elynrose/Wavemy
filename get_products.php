<?php
// get_products.php - Get available print products from database
header('Content-Type: application/json');
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    // Get active products from database, fallback to config if table doesn't exist
    try {
        $stmt = $pdo->query("
            SELECT 
                product_key as id,
                printful_id,
                name,
                description,
                price,
                size,
                material
            FROM print_products 
            WHERE is_active = 1 
            ORDER BY sort_order ASC, name ASC
        ");
        $dbProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($dbProducts)) {
            // Use database products
            $products = array_map(function($product) {
                return [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'price' => intval($product['price']),
                    'price_formatted' => formatPrice($product['price']),
                    'size' => $product['size'],
                    'material' => $product['material'],
                    'printful_id' => $product['printful_id']
                ];
            }, $dbProducts);
        } else {
            throw new Exception('No products in database');
        }
        
    } catch (Exception $e) {
        // Fallback to config file products
        $products = [];
        foreach ($GLOBALS['PRINT_PRODUCTS'] as $id => $product) {
            $products[] = [
                'id' => $id,
                'name' => $product['name'],
                'description' => $product['description'],
                'price' => $product['price'],
                'price_formatted' => formatPrice($product['price']),
                'size' => $product['size'],
                'material' => $product['material'],
                'printful_id' => $product['printful_id']
            ];
        }
    }
    
    echo json_encode($products);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load products']);
}
?>
