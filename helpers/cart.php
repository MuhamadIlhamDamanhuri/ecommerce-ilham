<?php
// helpers/cart.php - Shared cart functions for user module
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize cart session if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

/**
 * Add product to cart
 * @param int $product_id
 * @param int $quantity
 * @param array $product_data (name, price, stock, image)
 * @return array ['success' => bool, 'message' => string]
 */
function addToCart($product_id, $quantity, $product_data) {
    if ($quantity <= 0) {
        return ['success' => false, 'message' => '❌ Quantity harus lebih dari 0'];
    }
    
    // Check stock
    if ($quantity > $product_data['stock']) {
        return ['success' => false, 'message' => '❌ Stok tidak mencukupi. Tersedia: ' . $product_data['stock']];
    }
    
    // Check if product already in cart
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['product_id'] == $product_id) {
            $new_qty = $item['quantity'] + $quantity;
            if ($new_qty > $product_data['stock']) {
                return ['success' => false, 'message' => '❌ Stok tidak mencukupi untuk quantity tersebut'];
            }
            $item['quantity'] = $new_qty;
            return ['success' => true, 'message' => '✅ Quantity updated: ' . $product_data['name']];
        }
    }
    
    // Add new item to cart
    $_SESSION['cart'][] = [
        'product_id' => $product_id,
        'name' => $product_data['name'],
        'price' => $product_data['price'],
        'quantity' => $quantity,
        'image' => $product_data['image'] ?? null,
        'stock' => $product_data['stock'],
        'added_at' => time()
    ];
    
    return ['success' => true, 'message' => '✅ ' . $product_data['name'] . ' ditambahkan ke keranjang'];
}

/**
 * Update cart item quantity
 * @param int $product_id
 * @param int $quantity (0 to remove)
 * @return array ['success' => bool, 'message' => string]
 */
function updateCartItem($product_id, $quantity) {
    foreach ($_SESSION['cart'] as $key => &$item) {
        if ($item['product_id'] == $product_id) {
            if ($quantity <= 0) {
                unset($_SESSION['cart'][$key]);
                $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index array
                return ['success' => true, 'message' => '✅ Produk dihapus dari keranjang'];
            }
            
            // Check stock from database
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = :id");
                $stmt->execute(['id' => $product_id]);
                $stock = $stmt->fetchColumn();
                
                if ($quantity > $stock) {
                    return ['success' => false, 'message' => '❌ Stok hanya tersedia: ' . $stock];
                }
                
                $item['quantity'] = $quantity;
                $item['stock'] = $stock; // Update stock reference
                return ['success' => true, 'message' => '✅ Keranjang diupdate'];
            } catch (PDOException $e) {
                return ['success' => false, 'message' => '❌ Error: ' . $e->getMessage()];
            }
        }
    }
    return ['success' => false, 'message' => '❌ Item tidak ditemukan di keranjang'];
}

/**
 * Remove product from cart
 * @param int $product_id
 * @return array ['success' => bool, 'message' => string]
 */
function removeFromCart($product_id) {
    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['product_id'] == $product_id) {
            unset($_SESSION['cart'][$key]);
            $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index array
            return ['success' => true, 'message' => '✅ Produk dihapus dari keranjang'];
        }
    }
    return ['success' => false, 'message' => '❌ Item tidak ditemukan'];
}

/**
 * Clear entire cart
 * @return array ['success' => bool, 'message' => string]
 */
function clearCart() {
    $_SESSION['cart'] = [];
    return ['success' => true, 'message' => '✅ Keranjang dikosongkan'];
}

/**
 * Calculate cart total
 * @return float
 */
function getCartTotal() {
    $total = 0;
    foreach ($_SESSION['cart'] as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    return $total;
}

/**
 * Get cart item count
 * @return int
 */
function getCartItemCount() {
    $count = 0;
    foreach ($_SESSION['cart'] as $item) {
        $count += $item['quantity'];
    }
    return $count;
}

/**
 * Process checkout - create transaction
 * @param int $user_id
 * @param string $payment_method
 * @param string $shipping_address
 * @param string $notes
 * @return array ['success' => bool, 'message' => string, 'order_number' => string|null]
 */
function processCheckout($user_id, $payment_method, $shipping_address, $notes = '') {
    if (empty($_SESSION['cart'])) {
        return ['success' => false, 'message' => '❌ Keranjang kosong', 'order_number' => null];
    }
    
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // Generate unique order number
        $order_number = 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Calculate total
        $total_amount = getCartTotal();
        
        // Insert transaction
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                user_id, order_number, total_amount, payment_method, 
                payment_status, order_status, shipping_address, notes
            ) VALUES (
                :user_id, :order_number, :total, :payment, 
                'pending', 'pending', :address, :notes
            )
        ");
        $stmt->execute([
            'user_id' => $user_id,
            'order_number' => $order_number,
            'total' => $total_amount,
            'payment' => $payment_method,
            'address' => $shipping_address,
            'notes' => $notes
        ]);
        
        $transaction_id = $pdo->lastInsertId();
        
        // Insert transaction details & update stock
        foreach ($_SESSION['cart'] as $item) {
            $subtotal = $item['price'] * $item['quantity'];
            
            $stmt = $pdo->prepare("
                INSERT INTO transaction_details (transaction_id, product_id, quantity, price, subtotal)
                VALUES (:trans_id, :prod_id, :qty, :price, :subtotal)
            ");
            $stmt->execute([
                'trans_id' => $transaction_id,
                'prod_id' => $item['product_id'],
                'qty' => $item['quantity'],
                'price' => $item['price'],
                'subtotal' => $subtotal
            ]);
            
            // Update product stock
            $stmt = $pdo->prepare("UPDATE products SET stock = stock - :qty WHERE id = :id");
            $stmt->execute(['qty' => $item['quantity'], 'id' => $item['product_id']]);
        }
        
        $pdo->commit();
        
        // Clear cart after successful checkout
        clearCart();
        
        return ['success' => true, 'message' => '✅ Pesanan berhasil dibuat!', 'order_number' => $order_number];
        
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => '❌ Error checkout: ' . $e->getMessage(), 'order_number' => null];
    }
}

/**
 * Cancel order (only if pending)
 * @param int $order_id
 * @param int $user_id
 * @return array ['success' => bool, 'message' => string]
 */
function cancelOrder($order_id, $user_id) {
    try {
        $pdo = getDBConnection();
        
        // Check if order exists and belongs to user and is pending
        $stmt = $pdo->prepare("
            SELECT order_status FROM transactions 
            WHERE id = :id AND user_id = :uid
        ");
        $stmt->execute(['id' => $order_id, 'uid' => $user_id]);
        $order = $stmt->fetch();
        
        if (!$order) {
            return ['success' => false, 'message' => '❌ Pesanan tidak ditemukan'];
        }
        
        if ($order['order_status'] !== 'pending') {
            return ['success' => false, 'message' => '❌ Pesanan tidak dapat dibatalkan (sudah diproses)'];
        }
        
        // Update order status
        $stmt = $pdo->prepare("
            UPDATE transactions 
            SET order_status = 'cancelled', payment_status = 'cancelled', updated_at = NOW() 
            WHERE id = :id
        ");
        $stmt->execute(['id' => $order_id]);
        
        return ['success' => true, 'message' => '✅ Pesanan berhasil dibatalkan'];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => '❌ Error: ' . $e->getMessage()];
    }
}
?>