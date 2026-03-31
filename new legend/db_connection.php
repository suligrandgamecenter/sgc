<?php
/**
 * SULYGRAND GAME CENTER - DATABASE CONNECTION
 * MySQL Database Connection File
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'sulygrand_game_center');
define('DB_USER', 'root');          // Change this to your MySQL username
define('DB_PASS', '');              // Change this to your MySQL password
define('DB_CHARSET', 'utf8mb4');

// Price per hour for games (Regular)
define('PC_PRICE_REGULAR', 3000);
define('PC_PRICE_VIP', 5000);
define('PS_PRICE_REGULAR', 4500);
define('PS_PRICE_VIP', 7000);

// Configuration: 20 PC (8 VIP), 11 PS (1 VIP)
define('NUM_PC', 20);
define('NUM_PC_VIP', 8);
define('NUM_PS', 11);
define('NUM_PS_VIP', 1);

/**
 * Get database connection using PDO
 * @return PDO|null
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            return null;
        }
    }
    
    return $pdo;
}

/**
 * Check if a station is VIP
 * @param string $type (PC or PS)
 * @param int $stationNumber
 * @return bool
 */
function isVIPStation($type, $stationNumber) {
    if ($type === 'PC') {
        return $stationNumber <= NUM_PC_VIP;  // PC 1-8 are VIP
    } elseif ($type === 'PS') {
        return $stationNumber <= NUM_PS_VIP;  // PS 1 is VIP
    }
    return false;
}

/**
 * Get price per hour for a station
 * @param string $type
 * @param int $stationNumber
 * @return float
 */
function getStationPrice($type, $stationNumber) {
    if ($type === 'PC') {
        return isVIPStation($type, $stationNumber) ? PC_PRICE_VIP : PC_PRICE_REGULAR;
    } elseif ($type === 'PS') {
        return isVIPStation($type, $stationNumber) ? PS_PRICE_VIP : PS_PRICE_REGULAR;
    }
    return 0;
}

/**
 * Get all food menu items
 * @return array
 */
function getFoodMenu() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT * FROM food_menu ORDER BY name");
    return $stmt->fetchAll();
}

/**
 * Add new food item to menu
 * @param string $name
 * @param float $price
 * @return bool
 */
function addFoodItem($name, $price) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("INSERT INTO food_menu (name, price) VALUES (?, ?)");
    return $stmt->execute([$name, $price]);
}

/**
 * Delete food item from menu
 * @param int $id
 * @return bool
 */
function deleteFoodItem($id) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("DELETE FROM food_menu WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Get game session by type and number
 * @param string $type (PC or PS)
 * @param int $stationNumber
 * @return array|null
 */
function getGameSession($type, $stationNumber) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM game_sessions WHERE station_type = ? AND station_number = ?");
    $stmt->execute([$type, $stationNumber]);
    return $stmt->fetch();
}

/**
 * Start game session
 * @param string $type
 * @param int $stationNumber
 * @return bool
 */
function startGameSession($type, $stationNumber) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE game_sessions SET start_time = NOW(), is_running = TRUE WHERE station_type = ? AND station_number = ?");
    return $stmt->execute([$type, $stationNumber]);
}

/**
 * Pause game session
 * @param string $type
 * @param int $stationNumber
 * @return bool
 */
function pauseGameSession($type, $stationNumber) {
    $pdo = getDBConnection();
    
    // Get current session
    $session = getGameSession($type, $stationNumber);
    if (!$session || !$session['is_running']) return false;
    
    // Calculate elapsed time
    $elapsed = $session['elapsed_time'] + time() - strtotime($session['start_time']);
    
    $stmt = $pdo->prepare("UPDATE game_sessions SET elapsed_time = ?, is_running = FALSE, start_time = NULL WHERE station_type = ? AND station_number = ?");
    return $stmt->execute([$elapsed, $type, $stationNumber]);
}

/**
 * Resume game session
 * @param string $type
 * @param int $stationNumber
 * @return bool
 */
function resumeGameSession($type, $stationNumber) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE game_sessions SET start_time = NOW(), is_running = TRUE WHERE station_type = ? AND station_number = ?");
    return $stmt->execute([$type, $stationNumber]);
}

/**
 * Reset game session
 * @param string $type
 * @param int $stationNumber
 * @return bool
 */
function resetGameSession($type, $stationNumber) {
    $pdo = getDBConnection();
    
    // Delete food orders
    $session = getGameSession($type, $stationNumber);
    if ($session) {
        $stmt = $pdo->prepare("DELETE FROM game_food_orders WHERE session_id = ?");
        $stmt->execute([$session['id']]);
    }
    
    $stmt = $pdo->prepare("UPDATE game_sessions SET start_time = NULL, elapsed_time = 0, is_running = FALSE, food_cost = 0, total_cost = 0, paid_amount = 0 WHERE station_type = ? AND station_number = ?");
    return $stmt->execute([$type, $stationNumber]);
}

/**
 * Add food to game session
 * @param string $type
 * @param int $stationNumber
 * @param string $foodName
 * @param float $foodPrice
 * @return bool
 */
function addFoodToGameSession($type, $stationNumber, $foodName, $foodPrice) {
    $pdo = getDBConnection();
    
    $session = getGameSession($type, $stationNumber);
    if (!$session) return false;
    
    $stmt = $pdo->prepare("INSERT INTO game_food_orders (session_id, food_name, food_price) VALUES (?, ?, ?)");
    $result = $stmt->execute([$session['id'], $foodName, $foodPrice]);
    
    if ($result) {
        // Update food cost
        $stmt = $pdo->prepare("UPDATE game_sessions SET food_cost = food_cost + ? WHERE id = ?");
        $stmt->execute([$foodPrice, $session['id']]);
    }
    
    return $result;
}

/**
 * Delete food from game session
 * @param int $orderId
 * @return bool
 */
function deleteFoodFromGameSession($orderId) {
    $pdo = getDBConnection();
    
    // Get the order first
    $stmt = $pdo->query("SELECT * FROM game_food_orders WHERE id = $orderId");
    $order = $stmt->fetch();
    
    if (!$order) return false;
    
    // Delete and update cost
    $stmt = $pdo->prepare("DELETE FROM game_food_orders WHERE id = ?");
    $result = $stmt->execute([$orderId]);
    
    if ($result) {
        $stmt = $pdo->prepare("UPDATE game_sessions SET food_cost = food_cost - ? WHERE id = ?");
        $stmt->execute([$order['food_price'], $order['session_id']]);
    }
    
    return $result;
}

/**
 * Get all food orders for a session
 * @param string $type
 * @param int $stationNumber
 * @return array
 */
function getGameFoodOrders($type, $stationNumber) {
    $pdo = getDBConnection();
    $session = getGameSession($type, $stationNumber);
    
    if (!$session) return [];
    
    $stmt = $pdo->prepare("SELECT * FROM game_food_orders WHERE session_id = ? ORDER BY ordered_at DESC");
    $stmt->execute([$session['id']]);
    return $stmt->fetchAll();
}

/**
 * Save payment for game session
 * @param string $type
 * @param int $stationNumber
 * @param float $amount
 * @return bool
 */
function saveGamePayment($type, $stationNumber, $amount) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("UPDATE game_sessions SET paid_amount = paid_amount + ? WHERE station_type = ? AND station_number = ?");
    $result = $stmt->execute([$amount, $type, $stationNumber]);
    
    if ($result) {
        addIncome('game', $amount);
    }
    
    return $result;
}

/**
 * Calculate game cost based on elapsed time and station type
 * @param int $elapsedSeconds
 * @param string $type (PC or PS)
 * @param int $stationNumber
 * @return float
 */
function calculateGameCost($elapsedSeconds, $type, $stationNumber) {
    $hours = $elapsedSeconds / 3600;
    $price = getStationPrice($type, $stationNumber);
    return floor($hours * $price);
}

/**
 * Get current game cost for a session
 * @param string $type
 * @param int $stationNumber
 * @return float
 */
function getCurrentGameCost($type, $stationNumber) {
    $session = getGameSession($type, $stationNumber);
    if (!$session) return 0;
    
    $elapsed = $session['elapsed_time'];
    if ($session['is_running'] && $session['start_time']) {
        $elapsed += time() - strtotime($session['start_time']);
    }
    
    return calculateGameCost($elapsed, $type, $stationNumber);
}

/**
 * Get all active sessions (running or has elapsed time)
 * @return array
 */
function getActiveSessions() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT * FROM game_sessions WHERE is_running = TRUE OR elapsed_time > 0");
    return $stmt->fetchAll();
}

/**
 * Get total expected income from all active sessions
 * @return float
 */
function getTotalExpectedIncome() {
    $pdo = getDBConnection();
    $sessions = getActiveSessions();
    
    $total = 0;
    foreach ($sessions as $session) {
        $elapsed = $session['elapsed_time'];
        if ($session['is_running'] && $session['start_time']) {
            $elapsed += time() - strtotime($session['start_time']);
        }
        
        $total += calculateGameCost($elapsed, $session['station_type'], $session['station_number']);
        $total += $session['food_cost'];
    }
    
    return $total;
}

/**
 * Get game counts for a type
 * @param string $type (PC or PS)
 * @return array
 */
function getGameCounts($type) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM game_sessions WHERE station_type = ?");
    $stmt->execute([$type]);
    $total = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as started FROM game_sessions WHERE station_type = ? AND (is_running = TRUE OR elapsed_time > 0 OR food_cost > 0)");
    $stmt->execute([$type]);
    $started = $stmt->fetch()['started'];
    
    return [
        'started' => $started,
        'notStarted' => $total - $started
    ];
}

/**
 * Add income (daily and monthly)
 * @param string $type (game or cafe)
 * @param float $amount
 * @return bool
 */
function addIncome($type, $amount) {
    $pdo = getDBConnection();
    $today = date('Y-m-d');
    $year = date('Y');
    $month = (int)date('m');
    
    // Update daily income
    $stmt = $pdo->prepare("INSERT INTO income_daily (income_type, record_date, amount) VALUES (?, ?, ?) 
                           ON DUPLICATE KEY UPDATE amount = amount + ?");
    $stmt->execute([$type, $today, $amount, $amount]);
    
    // Update monthly income
    $stmt = $pdo->prepare("INSERT INTO income_monthly (income_type, record_year, record_month, amount) VALUES (?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE amount = amount + ?");
    $stmt->execute([$type, $year, $month, $amount, $amount]);
    
    return true;
}

/**
 * Get daily income
 * @param string $type
 * @param string $date
 * @return float
 */
function getDailyIncome($type, $date = null) {
    $pdo = getDBConnection();
    $date = $date ?: date('Y-m-d');
    
    $stmt = $pdo->prepare("SELECT amount FROM income_daily WHERE income_type = ? AND record_date = ?");
    $stmt->execute([$type, $date]);
    $result = $stmt->fetch();
    return $result ? $result['amount'] : 0;
}

/**
 * Get monthly income
 * @param string $type
 * @param int $year
 * @param int $month
 * @return float
 */
function getMonthlyIncome($type, $year = null, $month = null) {
    $pdo = getDBConnection();
    $year = $year ?: date('Y');
    $month = $month ?: (int)date('m');
    
    $stmt = $pdo->prepare("SELECT amount FROM income_monthly WHERE income_type = ? AND record_year = ? AND record_month = ?");
    $stmt->execute([$type, $year, $month]);
    $result = $stmt->fetch();
    return $result ? $result['amount'] : 0;
}

/**
 * Get all debts
 * @return array
 */
function getDebts() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT * FROM debts ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

/**
 * Add or update debt
 * @param string $name
 * @param float $amount
 * @return bool
 */
function addDebt($name, $amount) {
    $pdo = getDBConnection();
    $normalizedName = strtolower($name);
    
    $stmt = $pdo->prepare("INSERT INTO debts (debtor_name, debtor_name_normalized, amount) VALUES (?, ?, ?) 
                           ON DUPLICATE KEY UPDATE amount = amount + ?, updated_at = NOW()");
    return $stmt->execute([$name, $normalizedName, $amount, $amount]);
}

/**
 * Settle debt
 * @param string $normalizedName
 * @return bool
 */
function settleDebt($normalizedName) {
    $pdo = getDBConnection();
    
    // Get debt amount
    $stmt = $pdo->prepare("SELECT amount FROM debts WHERE debtor_name_normalized = ?");
    $stmt->execute([$normalizedName]);
    $debt = $stmt->fetch();
    
    if (!$debt) return false;
    
    // Add income and delete debt
    addIncome('cafe', $debt['amount']);
    
    $stmt = $pdo->prepare("DELETE FROM debts WHERE debtor_name_normalized = ?");
    return $stmt->execute([$normalizedName]);
}

/**
 * Delete debt without payment
 * @param string $normalizedName
 * @return bool
 */
function deleteDebt($normalizedName) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("DELETE FROM debts WHERE debtor_name_normalized = ?");
    return $stmt->execute([$normalizedName]);
}

/**
 * Add cafe sale
 * @param string $type (cash or debt)
 * @param float $total
 * @param array $items
 * @param string $debtorName (optional)
 * @return bool
 */
function addCafeSale($type, $total, $items, $debtorName = null) {
    $pdo = getDBConnection();
    
    try {
        $pdo->beginTransaction();
        
        // Insert sale
        $stmt = $pdo->prepare("INSERT INTO cafe_sales (sale_type, debtor_name, total_amount, sale_date, sale_time) VALUES (?, ?, ?, CURDATE(), CURTIME())");
        $stmt->execute([$type, $debtorName, $total]);
        
        $saleId = $pdo->lastInsertId();
        
        // Insert sale items
        foreach ($items as $item) {
            $stmt = $pdo->prepare("INSERT INTO cafe_sale_items (sale_id, food_name, food_price) VALUES (?, ?, ?)");
            $stmt->execute([$saleId, $item['name'], $item['price']]);
        }
        
        // If cash, add to income
        if ($type === 'cash') {
            addIncome('cafe', $total);
        } else {
            // If debt, add to debts table
            addDebt($debtorName, $total);
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Cafe Sale Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get cafe sales history
 * @param string $month (optional)
 * @param string $day (optional)
 * @return array
 */
function getCafeSalesHistory($month = null, $day = null) {
    $pdo = getDBConnection();
    
    $sql = "SELECT * FROM cafe_sales WHERE 1=1";
    $params = [];
    
    if ($month && $month !== 'all') {
        $sql .= " AND DATE_FORMAT(sale_date, '%Y/%m') = ?";
        $params[] = $month;
    }
    
    if ($day && $day !== 'all') {
        $sql .= " AND DAY(sale_date) = ?";
        $params[] = (int)$day;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sales = $stmt->fetchAll();
    
    // Get items for each sale
    foreach ($sales as &$sale) {
        $stmt = $pdo->prepare("SELECT * FROM cafe_sale_items WHERE sale_id = ?");
        $stmt->execute([$sale['id']]);
        $sale['items'] = $stmt->fetchAll();
    }
    
    return $sales;
}

/**
 * Delete cafe sale
 * @param int $saleId
 * @return bool
 */
function deleteCafeSale($saleId) {
    $pdo = getDBConnection();
    
    // Get sale first
    $stmt = $pdo->prepare("SELECT * FROM cafe_sales WHERE id = ?");
    $stmt->execute([$saleId]);
    $sale = $stmt->fetch();
    
    if (!$sale) return false;
    
    // Cannot delete debt sales directly
    if ($sale['sale_type'] === 'debt') {
        return false;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Delete items first
        $stmt = $pdo->prepare("DELETE FROM cafe_sale_items WHERE sale_id = ?");
        $stmt->execute([$saleId]);
        
        // Delete sale
        $stmt = $pdo->prepare("DELETE FROM cafe_sales WHERE id = ?");
        $stmt->execute([$saleId]);
        
        // Subtract from income
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("UPDATE income_daily SET amount = amount - ? WHERE income_type = 'cafe' AND record_date = ?");
        $stmt->execute([$sale['total_amount'], $today]);
        
        $year = date('Y');
        $month = (int)date('m');
        $stmt = $pdo->prepare("UPDATE income_monthly SET amount = amount - ? WHERE income_type = 'cafe' AND record_year = ? AND record_month = ?");
        $stmt->execute([$sale['total_amount'], $year, $month]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Delete Cafe Sale Error: " . $e->getMessage());
        return false;
    }
}

// Initialize database (Run this once when setting up)
function initializeDatabase() {
    $pdo = getDBConnection();
    
    // Check if we need to initialize game sessions
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM game_sessions");
    $count = $stmt->fetch()['count'];
    
    if ($count == 0) {
        // Insert PC stations (20 total, 8 VIP)
        for ($i = 1; $i <= NUM_PC; $i++) {
            $isVIP = ($i <= NUM_PC_VIP);
            $price = $isVIP ? PC_PRICE_VIP : PC_PRICE_REGULAR;
            $stmt = $pdo->prepare("INSERT INTO game_sessions (station_type, station_number, is_vip, price_per_hour) VALUES ('PC', ?, ?, ?)");
            $stmt->execute([$i, $isVIP, $price]);
        }
        
        // Insert PS stations (11 total, 1 VIP)
        for ($i = 1; $i <= NUM_PS; $i++) {
            $isVIP = ($i <= NUM_PS_VIP);
            $price = $isVIP ? PS_PRICE_VIP : PS_PRICE_REGULAR;
            $stmt = $pdo->prepare("INSERT INTO game_sessions (station_type, station_number, is_vip, price_per_hour) VALUES ('PS', ?, ?, ?)");
            $stmt->execute([$i, $isVIP, $price]);
        }
    }
}
?>