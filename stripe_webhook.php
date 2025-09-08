<?php
// stripe_webhook.php - Handle Stripe payment completion and send order to Printful
require_once 'config.php';

// Set webhook endpoint secret (you'll get this from Stripe dashboard)
$endpoint_secret = 'whsec_your_stripe_webhook_secret_here';

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    require_once 'vendor/autoload.php';
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
    
    // Verify webhook signature
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    
    // Handle the checkout.session.completed event
    if ($event['type'] == 'checkout.session.completed') {
        $session = $event['data']['object'];
        
        // Extract metadata
        $memoryId = $session['metadata']['memory_id'];
        $productId = $session['metadata']['product_id'];
        $userId = $session['metadata']['user_id'];
        $imageUrl = $session['metadata']['image_url'];
        $printfulProductId = $session['metadata']['printful_product_id'];
        
        // Get customer details
        $customerEmail = $session['customer_details']['email'];
        $customerName = $session['customer_details']['name'];
        $shippingAddress = $session['shipping_details']['address'];
        
        // Create Printful order
        $printfulOrder = createPrintfulOrder([
            'memory_id' => $memoryId,
            'product_id' => $productId,
            'printful_product_id' => $printfulProductId,
            'image_url' => $imageUrl,
            'customer_email' => $customerEmail,
            'customer_name' => $customerName,
            'shipping_address' => $shippingAddress,
            'stripe_session_id' => $session['id'],
            'amount_paid' => $session['amount_total']
        ]);
        
        // Save order to database
        saveOrderToDatabase([
            'stripe_session_id' => $session['id'],
            'user_id' => $userId,
            'memory_id' => $memoryId,
            'product_id' => $productId,
            'printful_order_id' => $printfulOrder['id'] ?? null,
            'customer_email' => $customerEmail,
            'customer_name' => $customerName,
            'amount_paid' => $session['amount_total'],
            'status' => 'paid'
        ]);
        
        http_response_code(200);
        echo json_encode(['status' => 'success']);
    }
    
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// Function to create Printful order
function createPrintfulOrder($orderData) {
    // According to Printful API docs, the correct format is:
    $printfulData = [
        'recipient' => [
            'name' => $orderData['customer_name'],
            'email' => $orderData['customer_email'],
            'address1' => $orderData['shipping_address']['line1'],
            'address2' => $orderData['shipping_address']['line2'] ?? '',
            'city' => $orderData['shipping_address']['city'],
            'state_code' => $orderData['shipping_address']['state'],
            'country_code' => $orderData['shipping_address']['country'],
            'zip' => $orderData['shipping_address']['postal_code'],
        ],
        'items' => [[
            'sync_variant_id' => intval($orderData['printful_product_id']), // Use sync_variant_id for your template products
            'quantity' => 1,
            'files' => [[
                'url' => $orderData['image_url'],
                'type' => 'default'
            ]]
        ]],
        'external_id' => substr('mw_' . $orderData['stripe_session_id'], 0, 32) // Printful external ID limit is 32 chars
    ];
    
    // Use the correct API endpoint format from documentation
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, PRINTFUL_API_URL . 'orders');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($printfulData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . PRINTFUL_API_KEY,
        'Content-Type: application/json',
        'X-PF-Store-Id: ' . PRINTFUL_STORE_ID // Add store ID as header instead
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $responseData = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return $responseData;
    } else {
        // Log the detailed error for debugging
        error_log("Printful API Error (HTTP $httpCode): " . $response);
        throw new Exception('Printful API error (' . $httpCode . '): ' . ($responseData['error']['message'] ?? $response));
    }
}

// Function to save order to database
function saveOrderToDatabase($orderData) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        
        // Create orders table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `orders` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `stripe_session_id` VARCHAR(255) NOT NULL,
                `user_id` VARCHAR(255) NOT NULL,
                `memory_id` INT UNSIGNED NOT NULL,
                `product_id` VARCHAR(100) NOT NULL,
                `printful_order_id` VARCHAR(255) NULL,
                `customer_email` VARCHAR(255) NOT NULL,
                `customer_name` VARCHAR(255) NOT NULL,
                `amount_paid` INT NOT NULL,
                `status` VARCHAR(50) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_memory_id` (`memory_id`),
                INDEX `idx_stripe_session` (`stripe_session_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        
        // Insert order record
        $stmt = $pdo->prepare("
            INSERT INTO `orders` 
            (`stripe_session_id`, `user_id`, `memory_id`, `product_id`, `printful_order_id`, 
             `customer_email`, `customer_name`, `amount_paid`, `status`) 
            VALUES (:stripe_session_id, :user_id, :memory_id, :product_id, :printful_order_id,
                    :customer_email, :customer_name, :amount_paid, :status)
        ");
        
        $stmt->execute([
            ':stripe_session_id' => $orderData['stripe_session_id'],
            ':user_id' => $orderData['user_id'],
            ':memory_id' => $orderData['memory_id'],
            ':product_id' => $orderData['product_id'],
            ':printful_order_id' => $orderData['printful_order_id'],
            ':customer_email' => $orderData['customer_email'],
            ':customer_name' => $orderData['customer_name'],
            ':amount_paid' => $orderData['amount_paid'],
            ':status' => $orderData['status']
        ]);
        
        return $pdo->lastInsertId();
        
    } catch (PDOException $e) {
        throw new Exception('Database error: ' . $e->getMessage());
    }
}
?>
