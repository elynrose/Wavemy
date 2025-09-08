<?php
// get_products.php - Get available print products
header('Content-Type: application/json');
require_once 'config.php';

try {
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
    
    echo json_encode($products);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load products']);
}
?>
