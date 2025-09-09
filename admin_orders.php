<?php
// admin_orders.php - Admin orders management page
require_once 'config.php';

// Get admin user ID from URL parameter
$adminUserId = $_GET['user_id'] ?? '';

if (!$adminUserId) {
    header('Location: index.html?admin_required=1');
    exit;
}

// Check if user is admin
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    $adminCheck = $pdo->prepare("SELECT * FROM admin_users WHERE firebase_uid = :uid AND is_admin = 1");
    $adminCheck->execute([':uid' => $adminUserId]);
    $adminUser = $adminCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminUser) {
        http_response_code(403);
        echo "Access denied. Admin privileges required.";
        exit;
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo "Database error";
    exit;
}

// Get all orders
try {
    $stmt = $pdo->query("
        SELECT 
            o.*,
            COUNT(*) OVER() as total_orders
        FROM orders o
        ORDER BY o.created_at DESC
        LIMIT 50
    ");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $orders = [];
    $error = 'Database error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Orders - MemoWindow</title>
    <style>
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Helvetica, Arial, sans-serif;
            background: #f5f7fb;
            color: #0b0d12;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: auto;
        }
        .header {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            text-align: center;
            border: 1px solid #e6e9f2;
        }
        .header h1 {
            margin: 0 0 8px 0;
            color: #0b0d12;
            font-size: 28px;
        }
        .nav-link {
            display: inline-block;
            background: #2a4df5;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 500;
            margin: 5px;
        }
        .order-card {
            background: white;
            border: 1px solid #e6e9f2;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .order-title {
            font-size: 18px;
            font-weight: 600;
            color: #0b0d12;
            margin: 0;
        }
        .order-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-processing { background: #dbeafe; color: #1e40af; }
        .status-shipped { background: #e0e7ff; color: #3730a3; }
        .status-delivered { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        
        .order-details {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 16px;
            align-items: center;
            margin-bottom: 16px;
        }
        .order-image {
            width: 80px;
            height: 50px;
            border-radius: 8px;
            border: 1px solid #e6e9f2;
            object-fit: cover;
            background: white;
        }
        .order-info h4 {
            margin: 0 0 4px 0;
            color: #0b0d12;
            font-size: 14px;
        }
        .order-info p {
            margin: 0;
            color: #6b7280;
            font-size: 12px;
        }
        .order-price {
            font-size: 16px;
            font-weight: 600;
            color: #0b0d12;
            text-align: right;
        }
        .order-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #f3f4f6;
            font-size: 12px;
            color: #6b7280;
        }
        .cancel-btn {
            background: #dc2626;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            margin-left: 10px;
        }
        .cancel-btn:hover {
            background: #b91c1c;
        }
        .cancel-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Admin Orders Management</h1>
            <p>Manage all orders in the system</p>
            <a href="admin.php?user_id=<?php echo urlencode($adminUserId); ?>" class="nav-link">← Back to Admin Dashboard</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="order-card" style="text-align: center; color: #dc2626;">
                <h3>Error Loading Orders</h3>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php elseif (empty($orders)): ?>
            <div class="order-card" style="text-align: center;">
                <h3>No Orders Found</h3>
                <p>There are no orders in the system yet.</p>
            </div>
        <?php else: ?>
            <div style="margin-bottom: 16px; color: #6b7280; font-size: 14px;">
                Showing <?php echo count($orders); ?> orders
            </div>
            
            <?php foreach ($orders as $order): 
                $statusClass = 'status-' . strtolower($order['status']);
            ?>
            <div class="order-card">
                <div class="order-header">
                    <div>
                        <h2 class="order-title"><?php echo htmlspecialchars($order['memory_title'] ?: 'Untitled Memory'); ?></h2>
                        <p style="margin: 4px 0 0 0; color: #6b7280; font-size: 14px;">
                            Order #<?php echo substr($order['stripe_session_id'], -8); ?> | User: <?php echo htmlspecialchars($order['user_id']); ?>
                        </p>
                    </div>
                    <span class="order-status <?php echo $statusClass; ?>">
                        <?php echo ucfirst($order['status']); ?>
                    </span>
                </div>
                
                <div class="order-details">
                    <img src="<?php echo htmlspecialchars($order['memory_image_url']); ?>" 
                         alt="MemoryWave" class="order-image">
                    
                    <div class="order-info">
                        <h4><?php echo htmlspecialchars($order['product_name']); ?></h4>
                        <p>Quantity: <?php echo htmlspecialchars($order['quantity']); ?></p>
                        <p>Unit Price: $<?php echo number_format($order['unit_price'], 2); ?></p>
                        <p>Customer: <?php echo htmlspecialchars($order['customer_name']); ?> (<?php echo htmlspecialchars($order['customer_email']); ?>)</p>
                        <?php if ($order['printful_order_id']): ?>
                            <p>Printful Order: <?php echo htmlspecialchars($order['printful_order_id']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="order-price">
                        $<?php echo number_format($order['total_price'], 2); ?>
                    </div>
                </div>
                
                <div class="order-meta">
                    <span>Ordered: <?php echo date('M j, Y \a\t g:i A', strtotime($order['created_at'])); ?></span>
                    <span>Product: <?php echo htmlspecialchars($order['product_name']); ?></span>
                    <?php if (in_array($order['status'], ['pending', 'paid'])): ?>
                        <button onclick="cancelOrder(<?php echo $order['id']; ?>)" 
                                class="cancel-btn">
                            Cancel Order
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        function cancelOrder(orderId) {
            if (!confirm('Are you sure you want to cancel this order? This action cannot be undone.')) {
                return;
            }
            
            const adminUserId = '<?php echo htmlspecialchars($adminUserId); ?>';
            
            fetch('admin_cancel_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `order_id=${orderId}&admin_user_id=${adminUserId}&reason=Admin cancelled order`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Order cancelled successfully!');
                    location.reload(); // Refresh the page to show updated order list
                } else {
                    alert('Error cancelling order: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error cancelling order. Please try again.');
            });
        }
    </script>
</body>
</html>
