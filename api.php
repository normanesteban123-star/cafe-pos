<?php
// ============================================
// API HANDLER
// ============================================

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

function ensureUserSchema($db) {
    $db->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(120) NOT NULL,
        username VARCHAR(80) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $countRes = $db->query("SELECT COUNT(*) AS total FROM users");
    $count = $countRes ? (int)$countRes->fetch_assoc()['total'] : 0;
    if ($count === 0) {
        $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
        $staffPass = password_hash('staff123', PASSWORD_DEFAULT);
        $db->query("INSERT INTO users (full_name, username, password_hash, role) VALUES
            ('Administrator', 'admin', '$adminPass', 'admin'),
            ('POS Staff', 'staff', '$staffPass', 'staff')");
    }
}

function getCurrentUserFromSession() {
    if (empty($_SESSION['user_id'])) return null;
    return [
        'id' => (int)$_SESSION['user_id'],
        'full_name' => $_SESSION['full_name'] ?? '',
        'username' => $_SESSION['username'] ?? '',
        'role' => $_SESSION['role'] ?? 'staff'
    ];
}

function requireAuth() {
    $user = getCurrentUserFromSession();
    if (!$user) jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    return $user;
}

function requireRole($roles) {
    $user = requireAuth();
    if (!in_array($user['role'], $roles, true)) {
        jsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
    }
    return $user;
}

function ensureIngredientSchema($db) {
    $db->query("CREATE TABLE IF NOT EXISTS ingredients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL UNIQUE,
        unit VARCHAR(30) NOT NULL DEFAULT 'g',
        stock DECIMAL(12,2) NOT NULL DEFAULT 0,
        low_stock_threshold DECIMAL(12,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    try {
        $db->query("CREATE TABLE IF NOT EXISTS product_ingredients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            ingredient_id INT NOT NULL,
            quantity_required DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_product_ingredient (product_id, ingredient_id),
            KEY idx_pi_product (product_id),
            KEY idx_pi_ingredient (ingredient_id),
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
        )");
    } catch (Throwable $e) {
        $db->query("CREATE TABLE IF NOT EXISTS product_ingredients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            ingredient_id INT NOT NULL,
            quantity_required DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_product_ingredient (product_id, ingredient_id),
            KEY idx_pi_product (product_id),
            KEY idx_pi_ingredient (ingredient_id)
        )");
    }

    $countResult = $db->query("SELECT COUNT(*) AS total FROM ingredients");
    $ingredientCount = $countResult ? (int)$countResult->fetch_assoc()['total'] : 0;
    if ($ingredientCount === 0) {
        $db->query("INSERT INTO ingredients (name, unit, stock, low_stock_threshold) VALUES
            ('Coffee Beans', 'g', 10000, 1500),
            ('Milk', 'ml', 20000, 3000),
            ('Water', 'ml', 100000, 15000),
            ('Ice', 'g', 20000, 3000)
        ");
    }

    $recipeSql = [
        "INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
         SELECT p.id, i.id, 18 FROM products p
         JOIN categories c ON c.id = p.category_id
         JOIN ingredients i ON i.name = 'Coffee Beans'
         WHERE c.name = 'Coffee' AND p.name = 'Espresso'
         ON DUPLICATE KEY UPDATE quantity_required = VALUES(quantity_required)",
        "INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
         SELECT p.id, i.id, 40 FROM products p
         JOIN categories c ON c.id = p.category_id
         JOIN ingredients i ON i.name = 'Water'
         WHERE c.name = 'Coffee' AND p.name = 'Espresso'
         ON DUPLICATE KEY UPDATE quantity_required = VALUES(quantity_required)",

        "INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
         SELECT p.id, i.id, 18 FROM products p
         JOIN categories c ON c.id = p.category_id
         JOIN ingredients i ON i.name = 'Coffee Beans'
         WHERE c.name = 'Coffee' AND p.name = 'Americano'
         ON DUPLICATE KEY UPDATE quantity_required = VALUES(quantity_required)",
        "INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
         SELECT p.id, i.id, 180 FROM products p
         JOIN categories c ON c.id = p.category_id
         JOIN ingredients i ON i.name = 'Water'
         WHERE c.name = 'Coffee' AND p.name = 'Americano'
         ON DUPLICATE KEY UPDATE quantity_required = VALUES(quantity_required)",

        "INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
         SELECT p.id, i.id, 18 FROM products p
         JOIN categories c ON c.id = p.category_id
         JOIN ingredients i ON i.name = 'Coffee Beans'
         WHERE c.name = 'Coffee' AND p.name = 'Cappuccino'
         ON DUPLICATE KEY UPDATE quantity_required = VALUES(quantity_required)",
        "INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
         SELECT p.id, i.id, 150 FROM products p
         JOIN categories c ON c.id = p.category_id
         JOIN ingredients i ON i.name = 'Milk'
         WHERE c.name = 'Coffee' AND p.name = 'Cappuccino'
         ON DUPLICATE KEY UPDATE quantity_required = VALUES(quantity_required)",

        "INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
         SELECT p.id, i.id, 18 FROM products p
         JOIN categories c ON c.id = p.category_id
         JOIN ingredients i ON i.name = 'Coffee Beans'
         WHERE c.name = 'Coffee' AND p.name = 'Latte'
         ON DUPLICATE KEY UPDATE quantity_required = VALUES(quantity_required)",
        "INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
         SELECT p.id, i.id, 220 FROM products p
         JOIN categories c ON c.id = p.category_id
         JOIN ingredients i ON i.name = 'Milk'
         WHERE c.name = 'Coffee' AND p.name = 'Latte'
         ON DUPLICATE KEY UPDATE quantity_required = VALUES(quantity_required)",

        "INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
         SELECT p.id, i.id, 20 FROM products p
         JOIN categories c ON c.id = p.category_id
         JOIN ingredients i ON i.name = 'Coffee Beans'
         WHERE c.name = 'Coffee' AND p.name = 'Flat White'
         ON DUPLICATE KEY UPDATE quantity_required = VALUES(quantity_required)",
        "INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
         SELECT p.id, i.id, 180 FROM products p
         JOIN categories c ON c.id = p.category_id
         JOIN ingredients i ON i.name = 'Milk'
         WHERE c.name = 'Coffee' AND p.name = 'Flat White'
         ON DUPLICATE KEY UPDATE quantity_required = VALUES(quantity_required)",

        "INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
         SELECT p.id, i.id, 30 FROM products p
         JOIN categories c ON c.id = p.category_id
         JOIN ingredients i ON i.name = 'Coffee Beans'
         WHERE c.name = 'Coffee' AND p.name = 'Cold Brew'
         ON DUPLICATE KEY UPDATE quantity_required = VALUES(quantity_required)",
        "INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
         SELECT p.id, i.id, 250 FROM products p
         JOIN categories c ON c.id = p.category_id
         JOIN ingredients i ON i.name = 'Water'
         WHERE c.name = 'Coffee' AND p.name = 'Cold Brew'
         ON DUPLICATE KEY UPDATE quantity_required = VALUES(quantity_required)",
        "INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
         SELECT p.id, i.id, 120 FROM products p
         JOIN categories c ON c.id = p.category_id
         JOIN ingredients i ON i.name = 'Ice'
         WHERE c.name = 'Coffee' AND p.name = 'Cold Brew'
         ON DUPLICATE KEY UPDATE quantity_required = VALUES(quantity_required)",

        "INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
         SELECT p.id, i.id, 18 FROM products p
         JOIN ingredients i ON i.name = 'Coffee Beans'
         WHERE p.name = 'Iced Latte'
         ON DUPLICATE KEY UPDATE quantity_required = VALUES(quantity_required)",
        "INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
         SELECT p.id, i.id, 200 FROM products p
         JOIN ingredients i ON i.name = 'Milk'
         WHERE p.name = 'Iced Latte'
         ON DUPLICATE KEY UPDATE quantity_required = VALUES(quantity_required)",
        "INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
         SELECT p.id, i.id, 120 FROM products p
         JOIN ingredients i ON i.name = 'Ice'
         WHERE p.name = 'Iced Latte'
         ON DUPLICATE KEY UPDATE quantity_required = VALUES(quantity_required)"
    ];

    foreach ($recipeSql as $sql) {
        $db->query($sql);
    }
}

function ensureOrderSchema($db) {
    $schema = $db->real_escape_string(DB_NAME);
    $colRes = $db->query("SELECT 1 FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA = '$schema'
                            AND TABLE_NAME = 'orders'
                            AND COLUMN_NAME = 'order_type'
                          LIMIT 1");
    $hasOrderType = $colRes && $colRes->num_rows > 0;
    if (!$hasOrderType) {
        $db->query("ALTER TABLE orders
                    ADD COLUMN order_type ENUM('dine_in','take_out')
                    NOT NULL DEFAULT 'dine_in'
                    AFTER customer_name");
    }
    // Add cashier column if not exists - use try/catch for safety
    try {
        $db->query("ALTER TABLE orders ADD COLUMN cashier_id INT DEFAULT NULL AFTER customer_id");
    } catch (Throwable $e) {
        // Column might already exist, ignore
    }
}

function ensureProductSizeColumn($db) {
    $res = $db->query("SHOW COLUMNS FROM products LIKE 'size'");
    if (!$res || $res->num_rows === 0) {
        $db->query("ALTER TABLE products ADD COLUMN size VARCHAR(20) NOT NULL DEFAULT 'Regular' AFTER unit");
    }
}

function ensureVariantsSchema($db) {
    try {
        $db->query("CREATE TABLE IF NOT EXISTS product_variants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            size_label VARCHAR(50) NOT NULL DEFAULT 'Regular',
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            stock INT NOT NULL DEFAULT 0,
            low_stock_threshold INT DEFAULT 5,
            is_active TINYINT(1) DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )");
    } catch (Throwable $e) {
        // Fallback without foreign key constraint
        $db->query("CREATE TABLE IF NOT EXISTS product_variants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            size_label VARCHAR(50) NOT NULL DEFAULT 'Regular',
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            stock INT NOT NULL DEFAULT 0,
            low_stock_threshold INT DEFAULT 5,
            is_active TINYINT(1) DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    }
    // Ensure order_items has variant columns (silently ignore errors)
    try {
        $colRes = $db->query("SHOW COLUMNS FROM order_items LIKE 'variant_id'");
        if (!$colRes || $colRes->num_rows === 0) {
            $db->query("ALTER TABLE order_items ADD COLUMN variant_id INT DEFAULT NULL");
        }
        $colRes2 = $db->query("SHOW COLUMNS FROM order_items LIKE 'variant_name'");
        if (!$colRes2 || $colRes2->num_rows === 0) {
            $db->query("ALTER TABLE order_items ADD COLUMN variant_name VARCHAR(50) DEFAULT NULL");
        }
    } catch (Throwable $e) { /* ignore */ }
}

function ensureVariantIngredientSchema($db) {
    try {
        $db->query("CREATE TABLE IF NOT EXISTS product_variant_ingredients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            variant_id INT NOT NULL,
            ingredient_id INT NOT NULL,
            quantity_required DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_variant_ingredient (variant_id, ingredient_id),
            KEY idx_pvi_variant (variant_id),
            KEY idx_pvi_ingredient (ingredient_id),
            FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
            FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
        )");
    } catch (Throwable $e) {
        $db->query("CREATE TABLE IF NOT EXISTS product_variant_ingredients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            variant_id INT NOT NULL,
            ingredient_id INT NOT NULL,
            quantity_required DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_variant_ingredient (variant_id, ingredient_id),
            KEY idx_pvi_variant (variant_id),
            KEY idx_pvi_ingredient (ingredient_id)
        )");
    }
}

function fetchProductRecipeMap($db, $productId, $forUpdate = false) {
    $lock = $forUpdate ? " FOR UPDATE" : "";
    $result = $db->query("SELECT i.id, i.name, i.stock, i.unit, pi.quantity_required
                          FROM product_ingredients pi
                          JOIN ingredients i ON i.id = pi.ingredient_id
                          WHERE pi.product_id = $productId$lock");
    $map = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $iid = (int)$row['id'];
            $map[$iid] = [
                'quantity_required' => (float)$row['quantity_required'],
                'name' => $row['name'],
                'unit' => $row['unit'],
                'stock' => (float)$row['stock']
            ];
        }
    }
    return $map;
}

function fetchVariantRecipeMap($db, $variantId, $forUpdate = false) {
    $lock = $forUpdate ? " FOR UPDATE" : "";
    $result = $db->query("SELECT i.id, i.name, i.stock, i.unit, pvi.quantity_required
                          FROM product_variant_ingredients pvi
                          JOIN ingredients i ON i.id = pvi.ingredient_id
                          WHERE pvi.variant_id = $variantId$lock");
    $map = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $iid = (int)$row['id'];
            $map[$iid] = [
                'quantity_required' => (float)$row['quantity_required'],
                'name' => $row['name'],
                'unit' => $row['unit'],
                'stock' => (float)$row['stock']
            ];
        }
    }
    return $map;
}

function ensureAuditSchema($db) {
    $db->query("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        user_name VARCHAR(120) DEFAULT NULL,
        action_text VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

function logAudit($db, $user, $actionText) {
    if (!$actionText) return;
    ensureAuditSchema($db);
    $uid = $user && isset($user['id']) ? (int)$user['id'] : 'NULL';
    $uname = $user && isset($user['full_name']) ? sanitize($user['full_name']) : '';
    $action = sanitize($actionText);
    $namePart = $uname ? "'$uname'" : 'NULL';
    $db->query("INSERT INTO audit_logs (user_id, user_name, action_text) VALUES ($uid, $namePart, '$action')");
}

switch ($action) {
    case 'login':
        $db = getDB();
        ensureUserSchema($db);
        $username = sanitize($input['username'] ?? '');
        $password = $input['password'] ?? '';
        if (!$username || !$password) jsonResponse(['success' => false, 'message' => 'Username and password are required'], 400);
        $res = $db->query("SELECT * FROM users WHERE username='$username' AND is_active=1 LIMIT 1");
        $user = $res ? $res->fetch_assoc() : null;
        if (!$user || !password_verify($password, $user['password_hash'])) {
            jsonResponse(['success' => false, 'message' => 'Invalid credentials'], 401);
        }
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        logAudit($db, getCurrentUserFromSession(), "Logged in");
        jsonResponse(['success' => true, 'user' => [
            'id' => (int)$user['id'],
            'full_name' => $user['full_name'],
            'username' => $user['username'],
            'role' => $user['role']
        ]]);
        break;

    case 'logout':
        $db = getDB();
        $user = requireAuth();
        logAudit($db, $user, "Logged out");
        session_unset();
        session_destroy();
        jsonResponse(['success' => true]);
        break;

    case 'me':
        $db = getDB();
        ensureUserSchema($db);
        $user = getCurrentUserFromSession();
        if (!$user) jsonResponse(['success' => true, 'authenticated' => false, 'user' => null]);
        jsonResponse(['success' => true, 'authenticated' => true, 'user' => $user]);
        break;

    case 'create_staff':
        $db = getDB();
        ensureUserSchema($db);
        requireRole(['admin']);
        $user = getCurrentUserFromSession();
        $fullName = sanitize($input['full_name'] ?? 'Staff');
        $username = sanitize($input['username'] ?? '');
        $password = $input['password'] ?? '';
        if (!$username || !$password) jsonResponse(['success' => false, 'message' => 'Username and password are required'], 400);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!$db->query("INSERT INTO users (full_name, username, password_hash, role) VALUES ('$fullName','$username','$hash','staff')")) {
            jsonResponse(['success' => false, 'message' => 'Failed to create staff', 'error' => $db->error], 500);
        }
        logAudit($db, $user, "Created staff account: $fullName ($username)");
        jsonResponse(['success' => true, 'message' => 'Staff account created']);
        break;

    case 'get_users':
        $db = getDB();
        ensureUserSchema($db);
        requireRole(['admin']);
        $result = $db->query("SELECT id, full_name, username, role, is_active, created_at FROM users ORDER BY role DESC, full_name ASC");
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        jsonResponse(['success' => true, 'data' => $rows]);
        break;

    case 'update_user':
        $db = getDB();
        ensureUserSchema($db);
        $current = requireRole(['admin']);
        $user = getCurrentUserFromSession();
        $id = (int)($input['id'] ?? 0);
        $fullName = sanitize($input['full_name'] ?? '');
        $username = sanitize($input['username'] ?? '');
        $role = sanitize($input['role'] ?? 'staff');
        $isActive = (int)($input['is_active'] ?? 1) ? 1 : 0;
        $password = $input['password'] ?? '';
        if ($id <= 0) jsonResponse(['success' => false, 'message' => 'Invalid user'], 400);
        if (!$fullName || !$username) jsonResponse(['success' => false, 'message' => 'Name and username are required'], 400);
        if (!in_array($role, ['admin', 'staff'], true)) $role = 'staff';
        if ($id === (int)$current['id'] && $isActive === 0) {
            jsonResponse(['success' => false, 'message' => 'You cannot deactivate your own account'], 400);
        }
        $dup = $db->query("SELECT id FROM users WHERE username='$username' AND id <> $id LIMIT 1");
        if ($dup && $dup->num_rows) jsonResponse(['success' => false, 'message' => 'Username already exists'], 400);
        if (!$db->query("UPDATE users SET full_name='$fullName', username='$username', role='$role', is_active=$isActive WHERE id=$id")) {
            jsonResponse(['success' => false, 'message' => 'Failed to update user', 'error' => $db->error], 500);
        }
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->query("UPDATE users SET password_hash='$hash' WHERE id=$id");
        }
        logAudit($db, $user, "Updated account: $fullName ($username)");
        jsonResponse(['success' => true, 'message' => 'Account updated']);
        break;

    // ── CATEGORIES ──────────────────────────────────
    case 'get_categories':
        requireRole(['admin', 'staff']);
        $db = getDB();
        $result = $db->query("SELECT * FROM categories ORDER BY name");
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        jsonResponse(['success' => true, 'data' => $rows]);
        break;

    case 'get_categories_manage':
        requireRole(['admin']);
        $db = getDB();
        $result = $db->query("SELECT c.*, COUNT(p.id) AS product_count
                              FROM categories c
                              LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
                              GROUP BY c.id
                              ORDER BY c.name");
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        jsonResponse(['success' => true, 'data' => $rows]);
        break;

    case 'save_category':
        requireRole(['admin']);
        $db = getDB();
        $user = getCurrentUserFromSession();
        $id = (int)($input['id'] ?? 0);
        $name = sanitize($input['name'] ?? '');
        $icon = sanitize($input['icon'] ?? 'coffee');
        if (!$name) jsonResponse(['success' => false, 'message' => 'Category name is required'], 400);
        if ($id > 0) {
            if (!$db->query("UPDATE categories SET name='$name', icon='$icon' WHERE id=$id")) {
                jsonResponse(['success' => false, 'message' => 'Failed to update category', 'error' => $db->error], 500);
            }
            logAudit($db, $user, "Updated category: $name");
            jsonResponse(['success' => true, 'message' => 'Category updated']);
        } else {
            if (!$db->query("INSERT INTO categories (name, icon) VALUES ('$name', '$icon')")) {
                jsonResponse(['success' => false, 'message' => 'Failed to add category', 'error' => $db->error], 500);
            }
            logAudit($db, $user, "Created category: $name");
            jsonResponse(['success' => true, 'message' => 'Category added', 'id' => $db->insert_id]);
        }
        break;

    case 'delete_category':
        requireRole(['admin']);
        $db = getDB();
        $user = getCurrentUserFromSession();
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) jsonResponse(['success' => false, 'message' => 'Invalid category'], 400);
        $nameRes = $db->query("SELECT name FROM categories WHERE id = $id");
        $catName = $nameRes && $nameRes->num_rows ? $nameRes->fetch_assoc()['name'] : 'Category';
        $db->query("UPDATE products SET category_id = NULL WHERE category_id = $id");
        if (!$db->query("DELETE FROM categories WHERE id = $id")) {
            jsonResponse(['success' => false, 'message' => 'Failed to delete category', 'error' => $db->error], 500);
        }
        logAudit($db, $user, "Deleted category: $catName");
        jsonResponse(['success' => true, 'message' => 'Category deleted']);
        break;

    case 'get_ingredients':
        requireRole(['admin']);
        $db = getDB();
        ensureIngredientSchema($db);
        $result = $db->query("SELECT i.*,
                                     COUNT(DISTINCT pi.id) AS products_using,
                                     COALESCE(MAX(CASE WHEN c.name = 'Coffee' AND p.is_active = 1 THEN pi.quantity_required ELSE NULL END), 0) AS coffee_qty
                              FROM ingredients i
                              LEFT JOIN product_ingredients pi ON pi.ingredient_id = i.id
                              LEFT JOIN products p ON p.id = pi.product_id
                              LEFT JOIN categories c ON c.id = p.category_id
                              GROUP BY i.id
                              ORDER BY i.name");
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        jsonResponse(['success' => true, 'data' => $rows]);
        break;

    case 'save_ingredient':
        requireRole(['admin']);
        $db = getDB();
        ensureIngredientSchema($db);
        $user = getCurrentUserFromSession();
        $id = (int)($input['id'] ?? 0);
        $name = sanitize($input['name'] ?? '');
        $unit = sanitize($input['unit'] ?? 'g');
        $stock = (float)($input['stock'] ?? 0);
        $threshold = (float)($input['low_stock_threshold'] ?? 0);
        $coffeeQty = (float)($input['coffee_qty'] ?? 0);
        if (!$name) jsonResponse(['success' => false, 'message' => 'Ingredient name is required'], 400);

        if ($id > 0) {
            if (!$db->query("UPDATE ingredients SET name='$name', unit='$unit', stock=$stock, low_stock_threshold=$threshold WHERE id=$id")) {
                jsonResponse(['success' => false, 'message' => 'Failed to update ingredient', 'error' => $db->error], 500);
            }
            $ingredientId = $id;
        } else {
            if (!$db->query("INSERT INTO ingredients (name, unit, stock, low_stock_threshold) VALUES ('$name','$unit',$stock,$threshold)")) {
                jsonResponse(['success' => false, 'message' => 'Failed to add ingredient', 'error' => $db->error], 500);
            }
            $ingredientId = (int)$db->insert_id;
        }

        if ($coffeeQty > 0) {
            $db->query("INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
                        SELECT p.id, $ingredientId, $coffeeQty
                        FROM products p
                        JOIN categories c ON c.id = p.category_id
                        WHERE c.name = 'Coffee' AND p.is_active = 1
                        ON DUPLICATE KEY UPDATE quantity_required = VALUES(quantity_required)");
        } else {
            // If coffee usage is cleared, remove this ingredient from active coffee recipes.
            $db->query("DELETE pi FROM product_ingredients pi
                        JOIN products p ON p.id = pi.product_id
                        JOIN categories c ON c.id = p.category_id
                        WHERE pi.ingredient_id = $ingredientId
                          AND c.name = 'Coffee'
                          AND p.is_active = 1");
        }

        logAudit($db, $user, ($id > 0 ? "Updated ingredient: $name" : "Created ingredient: $name"));
        jsonResponse(['success' => true, 'message' => $id > 0 ? 'Ingredient updated' : 'Ingredient added']);
        break;

    case 'delete_ingredient':
        requireRole(['admin']);
        $db = getDB();
        ensureIngredientSchema($db);
        ensureVariantIngredientSchema($db);
        $user = getCurrentUserFromSession();
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) jsonResponse(['success' => false, 'message' => 'Invalid ingredient'], 400);
        $nameRes = $db->query("SELECT name FROM ingredients WHERE id = $id");
        $ingName = $nameRes && $nameRes->num_rows ? $nameRes->fetch_assoc()['name'] : 'Ingredient';
        $db->query("DELETE FROM product_ingredients WHERE ingredient_id = $id");
        $db->query("DELETE FROM product_variant_ingredients WHERE ingredient_id = $id");
        if (!$db->query("DELETE FROM ingredients WHERE id = $id")) {
            jsonResponse(['success' => false, 'message' => 'Failed to delete ingredient', 'error' => $db->error], 500);
        }
        logAudit($db, $user, "Deleted ingredient: $ingName");
        jsonResponse(['success' => true, 'message' => 'Ingredient deleted']);
        break;

    // ── PRODUCTS ────────────────────────────────────
    case 'upload_product_image':
        requireRole(['admin']);
        $db = getDB();
        $user = getCurrentUserFromSession();
        $productId = (int)($_POST['product_id'] ?? 0);
        if ($productId <= 0) jsonResponse(['success' => false, 'message' => 'Invalid product'], 400);
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['success' => false, 'message' => 'No image uploaded'], 400);
        }
        $file = $_FILES['image'];
        if ($file['size'] > 5 * 1024 * 1024) jsonResponse(['success' => false, 'message' => 'Image too large (max 5MB)'], 400);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif'
        ];
        if (!isset($allowed[$mime])) jsonResponse(['success' => false, 'message' => 'Invalid image format'], 400);
        $ext = $allowed[$mime];
        $dir = __DIR__ . '/uploads/products';
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                jsonResponse(['success' => false, 'message' => 'Failed to create upload directory'], 500);
            }
        }
        $filename = 'product_' . $productId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            jsonResponse(['success' => false, 'message' => 'Failed to save image'], 500);
        }
        $imageUrl = 'uploads/products/' . $filename;
        if (!$db->query("UPDATE products SET image_url='" . sanitize($imageUrl) . "' WHERE id=$productId")) {
            jsonResponse(['success' => false, 'message' => 'Failed to link image to product', 'error' => $db->error], 500);
        }
        logAudit($db, $user, "Updated product image (product_id $productId)");
        jsonResponse(['success' => true, 'message' => 'Image uploaded', 'image_url' => $imageUrl]);
        break;

    case 'get_variants':
        requireRole(['admin', 'staff']);
        $db = getDB();
        ensureVariantsSchema($db);
        $pid = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
        if (!$pid) jsonResponse(['success' => false, 'message' => 'product_id required'], 400);
        $result = $db->query("SELECT * FROM product_variants WHERE product_id=$pid AND is_active=1 ORDER BY sort_order, id");
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        jsonResponse(['success' => true, 'data' => $rows]);
        break;

    case 'save_variant':
        requireRole(['admin']);
        $db = getDB();
        ensureVariantsSchema($db);
        $user = getCurrentUserFromSession();
        $id = (int)($input['id'] ?? 0);
        $product_id = (int)($input['product_id'] ?? 0);
        $size_label = sanitize($input['size_label'] ?? '');
        $price = (float)($input['price'] ?? 0);
        $stock = (int)($input['stock'] ?? 0);
        $threshold = (int)($input['low_stock_threshold'] ?? 5);
        $sort_order = (int)($input['sort_order'] ?? 0);
        if (!$product_id || !$size_label || $price < 0) jsonResponse(['success' => false, 'message' => 'product_id, size_label and price are required'], 400);
        if ($id > 0) {
            $db->query("UPDATE product_variants SET size_label='$size_label', price=$price, stock=$stock, low_stock_threshold=$threshold, sort_order=$sort_order, updated_at=NOW() WHERE id=$id AND product_id=$product_id");
            logAudit($db, $user, "Updated variant: $size_label (product_id $product_id)");
            jsonResponse(['success' => true, 'message' => 'Variant updated']);
        } else {
            $db->query("INSERT INTO product_variants (product_id, size_label, price, stock, low_stock_threshold, sort_order) VALUES ($product_id, '$size_label', $price, $stock, $threshold, $sort_order)");
            logAudit($db, $user, "Created variant: $size_label (product_id $product_id)");
            jsonResponse(['success' => true, 'message' => 'Variant added', 'id' => $db->insert_id]);
        }
        break;

    case 'delete_variant':
        requireRole(['admin']);
        $db = getDB();
        ensureVariantsSchema($db);
        ensureVariantIngredientSchema($db);
        $user = getCurrentUserFromSession();
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonResponse(['success' => false, 'message' => 'id required'], 400);
        $nameRes = $db->query("SELECT size_label, product_id FROM product_variants WHERE id=$id");
        $vLabel = $nameRes && $nameRes->num_rows ? $nameRes->fetch_assoc()['size_label'] : 'Variant';
        $db->query("DELETE FROM product_variant_ingredients WHERE variant_id=$id");
        $db->query("DELETE FROM product_variants WHERE id=$id");
        logAudit($db, $user, "Deleted variant: $vLabel");
        jsonResponse(['success' => true, 'message' => 'Variant deleted']);
        break;

    case 'get_products':
        requireRole(['admin', 'staff']);
        $db = getDB();
        ensureProductSizeColumn($db);
        ensureIngredientSchema($db);
        ensureVariantsSchema($db);
        ensureVariantIngredientSchema($db);
        $cat = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
        $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
        $where = "WHERE p.is_active = 1";
        if ($cat > 0) $where .= " AND p.category_id = $cat";
        if ($search) $where .= " AND p.name LIKE '%$search%'";
        $sql = "SELECT p.*, c.name AS category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                $where ORDER BY c.name, p.name";
        $result = $db->query($sql);
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;

        // Enforce ingredient-linked stock availability:
        // if recipe exists, effective stock is computed from ingredients only.
        $baseRecipeByProduct = [];
        $ingredientStock = [];
        if (!empty($rows)) {
            $productIds = array_map(fn($r) => (int)$r['id'], $rows);
            $idList = implode(',', $productIds);
            $recipeRes = $db->query("SELECT pi.product_id, i.id AS ingredient_id, pi.quantity_required, i.stock
                                     FROM product_ingredients pi
                                     JOIN ingredients i ON i.id = pi.ingredient_id
                                     WHERE pi.product_id IN ($idList)");
            $limits = []; // product_id => max units possible from ingredients
            while ($rec = $recipeRes->fetch_assoc()) {
                $pid = (int)$rec['product_id'];
                $iid = (int)$rec['ingredient_id'];
                $qtyReq = (float)$rec['quantity_required'];
                $ingStock = (float)$rec['stock'];
                $ingredientStock[$iid] = $ingStock;
                if (!isset($baseRecipeByProduct[$pid])) $baseRecipeByProduct[$pid] = [];
                $baseRecipeByProduct[$pid][$iid] = $qtyReq;
                if ($qtyReq <= 0) continue;
                $possibleUnits = (int)floor($ingStock / $qtyReq);
                if (!isset($limits[$pid])) {
                    $limits[$pid] = $possibleUnits;
                } else {
                    $limits[$pid] = min($limits[$pid], $possibleUnits);
                }
            }
            foreach ($rows as &$row) {
                $pid = (int)$row['id'];
                if (isset($limits[$pid])) {
                    $effective = max(0, $limits[$pid]);
                    $row['stock'] = (string)$effective;
                    $row['ingredient_limited'] = 1;
                } else {
                    $row['ingredient_limited'] = 0;
                }
            }
            unset($row);
        }

        // Attach variants to each product
        if (!empty($rows)) {
            $allIds = implode(',', array_map(fn($r) => (int)$r['id'], $rows));
            $varRes = $db->query("SELECT * FROM product_variants WHERE product_id IN ($allIds) AND is_active=1 ORDER BY sort_order, id");
            $varMap = [];
            if ($varRes) {
                while ($v = $varRes->fetch_assoc()) {
                    $varMap[(int)$v['product_id']][] = $v;
                }
            }
            $variantRecipeMap = [];
            if (!empty($varMap)) {
                $variantIds = [];
                foreach ($varMap as $variants) {
                    foreach ($variants as $v) {
                        $variantIds[] = (int)$v['id'];
                    }
                }
                if (!empty($variantIds)) {
                    $vIdList = implode(',', $variantIds);
                    $vRes = $db->query("SELECT pvi.variant_id, i.id AS ingredient_id, pvi.quantity_required, i.stock
                                        FROM product_variant_ingredients pvi
                                        JOIN ingredients i ON i.id = pvi.ingredient_id
                                        WHERE pvi.variant_id IN ($vIdList)");
                    if ($vRes) {
                        while ($vr = $vRes->fetch_assoc()) {
                            $vid = (int)$vr['variant_id'];
                            $iid = (int)$vr['ingredient_id'];
                            $ingredientStock[$iid] = (float)$vr['stock'];
                            if (!isset($variantRecipeMap[$vid])) $variantRecipeMap[$vid] = [];
                            $variantRecipeMap[$vid][$iid] = (float)$vr['quantity_required'];
                        }
                    }
                }
            }
            foreach ($rows as &$row) {
                $row['variants'] = $varMap[(int)$row['id']] ?? [];
                $row['has_variants'] = !empty($row['variants']);
                if ($row['has_variants']) {
                    $pid = (int)$row['id'];
                    $baseRecipe = $baseRecipeByProduct[$pid] ?? [];
                    foreach ($row['variants'] as &$variant) {
                        $vid = (int)$variant['id'];
                        $combined = $baseRecipe;
                        if (isset($variantRecipeMap[$vid])) {
                            foreach ($variantRecipeMap[$vid] as $iid => $qty) {
                                $combined[$iid] = $qty;
                            }
                        }
                        $limit = null;
                        foreach ($combined as $iid => $qtyReq) {
                            if ($qtyReq <= 0) continue;
                            $stock = $ingredientStock[$iid] ?? null;
                            if ($stock === null) continue;
                            $possibleUnits = (int)floor($stock / $qtyReq);
                            $limit = $limit === null ? $possibleUnits : min($limit, $possibleUnits);
                        }
                        if ($limit !== null) {
                            $variant['stock'] = (string)min((int)$variant['stock'], $limit);
                        }
                    }
                    unset($variant);
                }
            }
            unset($row);
        }

        jsonResponse(['success' => true, 'data' => $rows]);
        break;

    case 'get_product':
        requireRole(['admin']);
        $db = getDB();
        ensureProductSizeColumn($db);
        $id = (int)($_GET['id'] ?? 0);
        $result = $db->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = $id");
        $row = $result->fetch_assoc();
        jsonResponse(['success' => true, 'data' => $row]);
        break;

    case 'get_product_ingredients':
        requireRole(['admin']);
        $db = getDB();
        ensureIngredientSchema($db);
        $pid = (int)($_GET['product_id'] ?? 0);
        $result = $db->query("SELECT pi.ingredient_id, pi.quantity_required, i.name, i.unit
                              FROM product_ingredients pi
                              JOIN ingredients i ON i.id = pi.ingredient_id
                              WHERE pi.product_id = $pid
                              ORDER BY i.name");
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        jsonResponse(['success' => true, 'data' => $rows]);
        break;

    case 'get_variant_ingredients':
        requireRole(['admin']);
        $db = getDB();
        ensureIngredientSchema($db);
        ensureVariantsSchema($db);
        ensureVariantIngredientSchema($db);
        $pid = (int)($_GET['product_id'] ?? 0);
        if ($pid <= 0) jsonResponse(['success' => false, 'message' => 'product_id required'], 400);
        $result = $db->query("SELECT pvi.variant_id, pvi.ingredient_id, pvi.quantity_required
                              FROM product_variant_ingredients pvi
                              JOIN product_variants pv ON pv.id = pvi.variant_id
                              WHERE pv.product_id = $pid");
        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) $rows[] = $row;
        }
        jsonResponse(['success' => true, 'data' => $rows]);
        break;

    case 'save_product':
        requireRole(['admin']);
        $db = getDB();
        ensureProductSizeColumn($db);
        $user = getCurrentUserFromSession();
        $id = (int)($input['id'] ?? 0);
        $cat_id = (int)($input['category_id'] ?? 0);
        if ($cat_id <= 0) $cat_id = 'NULL';
        $recipe = is_array($input['ingredients'] ?? null) ? $input['ingredients'] : [];
        $name = sanitize($input['name'] ?? '');
        $desc = sanitize($input['description'] ?? '');
        $price = (float)($input['price'] ?? 0);
        $cost = (float)($input['cost'] ?? 0);
        $hasStock = array_key_exists('stock', $input);
        $stock = (int)($input['stock'] ?? 0);
        $threshold = (int)($input['low_stock_threshold'] ?? 10);
        $unit = sanitize($input['unit'] ?? 'pcs');
        $size = sanitize($input['size'] ?? 'Regular');
        if (!$size) $size = 'Regular';
        if (!$name) jsonResponse(['success' => false, 'message' => 'Name is required'], 400);
        ensureIngredientSchema($db);
        if ($id > 0) {
            $stockPart = $hasStock ? ", stock=$stock" : "";
            $sql = "UPDATE products SET category_id=$cat_id, name='$name', description='$desc', price=$price, cost=$cost{$stockPart}, low_stock_threshold=$threshold, unit='$unit', size='$size' WHERE id=$id";
            if (!$db->query($sql)) {
                jsonResponse(['success' => false, 'message' => 'Failed to update product', 'error' => $db->error], 500);
            }
            $productId = $id;
            $message = 'Product updated';
        } else {
            $sql = "INSERT INTO products (category_id, name, description, price, cost, stock, low_stock_threshold, unit, size) VALUES ($cat_id,'$name','$desc',$price,$cost,$stock,$threshold,'$unit','$size')";
            if (!$db->query($sql)) {
                jsonResponse(['success' => false, 'message' => 'Failed to add product', 'error' => $db->error], 500);
            }
            $productId = (int)$db->insert_id;
            $message = 'Product added';
        }
        $db->query("DELETE FROM product_ingredients WHERE product_id=$productId");
        foreach ($recipe as $r) {
            $ingredientId = (int)($r['ingredient_id'] ?? 0);
            $qtyRequired = (float)($r['quantity_required'] ?? 0);
            if ($ingredientId <= 0 || $qtyRequired <= 0) continue;
            $db->query("INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
                        VALUES ($productId, $ingredientId, $qtyRequired)
                        ON DUPLICATE KEY UPDATE quantity_required=VALUES(quantity_required)");
        }
        logAudit($db, $user, ($id > 0 ? "Updated product: $name" : "Created product: $name"));
        jsonResponse(['success' => true, 'message' => $message, 'id' => $productId]);
        break;

    case 'save_variant_ingredients':
        requireRole(['admin']);
        $db = getDB();
        ensureIngredientSchema($db);
        ensureVariantsSchema($db);
        ensureVariantIngredientSchema($db);
        $user = getCurrentUserFromSession();
        $productId = (int)($input['product_id'] ?? 0);
        $items = is_array($input['items'] ?? null) ? $input['items'] : [];
        if ($productId <= 0) jsonResponse(['success' => false, 'message' => 'product_id required'], 400);
        $variantIds = [];
        $varRes = $db->query("SELECT id FROM product_variants WHERE product_id=$productId");
        while ($varRes && ($row = $varRes->fetch_assoc())) {
            $variantIds[(int)$row['id']] = true;
        }
        $db->query("DELETE pvi FROM product_variant_ingredients pvi
                    JOIN product_variants pv ON pv.id = pvi.variant_id
                    WHERE pv.product_id = $productId");
        foreach ($items as $item) {
            $vid = (int)($item['variant_id'] ?? 0);
            $iid = (int)($item['ingredient_id'] ?? 0);
            $qty = (float)($item['quantity_required'] ?? 0);
            if ($vid <= 0 || $iid <= 0 || $qty <= 0) continue;
            if (!isset($variantIds[$vid])) continue;
            $db->query("INSERT INTO product_variant_ingredients (variant_id, ingredient_id, quantity_required)
                        VALUES ($vid, $iid, $qty)
                        ON DUPLICATE KEY UPDATE quantity_required=VALUES(quantity_required)");
        }
        logAudit($db, $user, "Updated variant ingredients (product_id $productId)");
        jsonResponse(['success' => true, 'message' => 'Variant ingredients saved']);
        break;

    case 'delete_product':
        requireRole(['admin']);
        $db = getDB();
        $user = getCurrentUserFromSession();
        $id = (int)($input['id'] ?? 0);
        $nameRes = $db->query("SELECT name FROM products WHERE id=$id");
        $prodName = $nameRes && $nameRes->num_rows ? $nameRes->fetch_assoc()['name'] : 'Product';
        $db->query("UPDATE products SET is_active=0 WHERE id=$id");
        logAudit($db, $user, "Deleted product: $prodName");
        jsonResponse(['success' => true, 'message' => 'Product removed']);
        break;

    case 'adjust_stock':
        requireRole(['admin']);
        $db = getDB();
        $user = getCurrentUserFromSession();
        $id = (int)($input['product_id'] ?? 0);
        $qty = (int)($input['quantity'] ?? 0);
        $type = sanitize($input['type'] ?? 'adjustment');
        $notes = sanitize($input['notes'] ?? '');
        $res = $db->query("SELECT stock FROM products WHERE id=$id");
        $product = $res->fetch_assoc();
        if (!$product) jsonResponse(['success' => false, 'message' => 'Product not found'], 404);
        $before = $product['stock'];
        $after = $before + $qty;
        if ($after < 0) jsonResponse(['success' => false, 'message' => 'Insufficient stock'], 400);
        $db->query("UPDATE products SET stock=$after WHERE id=$id");
        $db->query("INSERT INTO inventory_logs (product_id, type, quantity_change, quantity_before, quantity_after, notes) VALUES ($id,'$type',$qty,$before,$after,'$notes')");
        logAudit($db, $user, "Adjusted stock (product_id $id): $qty ($type)");
        jsonResponse(['success' => true, 'message' => 'Stock adjusted', 'new_stock' => $after]);
        break;

    // ── ORDERS ──────────────────────────────────────
    case 'create_order':
        requireRole(['admin', 'staff']);
        $db = getDB();
        $user = getCurrentUserFromSession();
        $items = $input['items'] ?? [];
        if (empty($items)) jsonResponse(['success' => false, 'message' => 'No items in order'], 400);
        $customer_name = sanitize($input['customer_name'] ?? 'Walk-in');
        $order_type = sanitize($input['order_type'] ?? 'dine_in');
        $payment = sanitize($input['payment_method'] ?? 'cash');
        $discount = (float)($input['discount'] ?? 0);
        $amount_paid = (float)($input['amount_paid'] ?? 0);
        $notes = sanitize($input['notes'] ?? '');
        $currentUser = getCurrentUserFromSession();
        $cashier_id = $currentUser ? $currentUser['id'] : 'NULL';
        if (!in_array($order_type, ['dine_in', 'take_out'], true)) $order_type = 'dine_in';
        if ($discount < 0) jsonResponse(['success' => false, 'message' => 'Discount cannot be negative'], 400);
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += (float)$item['unit_price'] * (int)$item['quantity'];
        }
        $after_discount = max(0, $subtotal - $discount);
        $tax = 0;
        $total = round($after_discount, 2);
        if ($payment !== 'cash') {
            $amount_paid = $total;
        }
        if ($payment === 'cash' && $amount_paid + 0.00001 < $total) {
            jsonResponse(['success' => false, 'message' => 'Amount paid is less than order total'], 400);
        }
        $change = max(0, $amount_paid - $total);
        $order_number = generateOrderNumber();
        try {
            $db->begin_transaction();
            ensureIngredientSchema($db);
            ensureVariantsSchema($db);
            ensureVariantIngredientSchema($db);
            ensureOrderSchema($db);

            $ingredientNeeds = [];
            $productStocks = [];
            $recipeProduct = [];
            $baseRecipeCache = [];
            $variantRecipeCache = [];

            foreach ($items as $item) {
                $pid = (int)$item['product_id'];
                $qty = max(1, (int)$item['quantity']);
                $vid = isset($item['variant_id']) && $item['variant_id'] ? (int)$item['variant_id'] : 0;
                $res = $db->query("SELECT id, name, stock FROM products WHERE id=$pid FOR UPDATE");
                if (!$res || !$res->num_rows) {
                    throw new Exception('One of the ordered products no longer exists');
                }
                $prod = $res->fetch_assoc();

                if (!isset($baseRecipeCache[$pid])) {
                    $baseRecipeCache[$pid] = fetchProductRecipeMap($db, $pid, true);
                }
                $baseRecipe = $baseRecipeCache[$pid];
                $variantRecipe = [];
                if ($vid > 0) {
                    if (!isset($variantRecipeCache[$vid])) {
                        $variantRecipeCache[$vid] = fetchVariantRecipeMap($db, $vid, true);
                    }
                    $variantRecipe = $variantRecipeCache[$vid];
                }
                $combined = $baseRecipe;
                if (!empty($variantRecipe)) {
                    foreach ($variantRecipe as $iid => $rec) {
                        $combined[$iid] = $rec;
                    }
                }
                $hasRecipe = !empty($combined);
                if ($hasRecipe) {
                    foreach ($combined as $iid => $recipe) {
                        if ((float)$recipe['quantity_required'] <= 0) continue;
                        if (!isset($ingredientNeeds[$iid])) {
                            $ingredientNeeds[$iid] = [
                                'name' => $recipe['name'],
                                'unit' => $recipe['unit'],
                                'stock' => (float)$recipe['stock'],
                                'required' => 0.0
                            ];
                        }
                        $ingredientNeeds[$iid]['required'] += (float)$recipe['quantity_required'] * $qty;
                    }
                    $recipeProduct[$pid] = true;
                } else {
                    $before = (int)$prod['stock'];
                    if ($before < $qty) {
                        throw new Exception("Insufficient stock for {$prod['name']}");
                    }
                    $productStocks[$pid] = ['before' => $before, 'name' => $prod['name']];
                }
            }

            foreach ($ingredientNeeds as $ingredient) {
                if ($ingredient['stock'] < $ingredient['required']) {
                    throw new Exception("Not enough {$ingredient['name']} ({$ingredient['unit']})");
                }
            }

            // Try with cashier_id, fallback to without if column doesn't exist
            $insertSql = "INSERT INTO orders (order_number, cashier_id, customer_name, order_type, subtotal, discount, tax, total, payment_method, amount_paid, change_given, status, notes) VALUES ('$order_number',$cashier_id,'$customer_name','$order_type',$subtotal,$discount,$tax,$total,'$payment',$amount_paid,$change,'completed','$notes')";
            if (!$db->query($insertSql)) {
                // Fallback: try without cashier_id column
                $insertSql = "INSERT INTO orders (order_number, customer_name, order_type, subtotal, discount, tax, total, payment_method, amount_paid, change_given, status, notes) VALUES ('$order_number','$customer_name','$order_type',$subtotal,$discount,$tax,$total,'$payment',$amount_paid,$change,'completed','$notes')";
                if (!$db->query($insertSql)) {
                    throw new Exception('Failed to create order');
                }
            }
            $order_id = $db->insert_id;

            foreach ($items as $item) {
                $pid = (int)$item['product_id'];
                $pname = sanitize($item['product_name']);
                $qty = max(1, (int)$item['quantity']);
                $uprice = (float)$item['unit_price'];
                $sub = $uprice * $qty;
                $vid = isset($item['variant_id']) && $item['variant_id'] ? (int)$item['variant_id'] : 'NULL';
                $vname = isset($item['variant_name']) && $item['variant_name'] ? "'" . sanitize($item['variant_name']) . "'" : 'NULL';
                if (!$db->query("INSERT INTO order_items (order_id, product_id, variant_id, product_name, variant_name, quantity, unit_price, subtotal) VALUES ($order_id,$pid,$vid,'$pname',$vname,$qty,$uprice,$sub)")) {
                    throw new Exception('Failed to save order items');
                }
                // Deduct variant stock if applicable
                if ($vid !== 'NULL') {
                    $db->query("UPDATE product_variants SET stock=GREATEST(0, stock-$qty) WHERE id=$vid");
                } elseif (!isset($recipeProduct[$pid])) {
                    $before = $productStocks[$pid]['before'];
                    $after = $before - $qty;
                    if (!$db->query("UPDATE products SET stock=$after WHERE id=$pid")) {
                        throw new Exception('Failed to update product stock');
                    }
                    $db->query("INSERT INTO inventory_logs (product_id, type, quantity_change, quantity_before, quantity_after, notes) VALUES ($pid,'sale',-$qty,$before,$after,'Order $order_number')");
                    $productStocks[$pid]['before'] = $after;
                }
            }

            foreach ($ingredientNeeds as $iid => $ingredient) {
                $after = $ingredient['stock'] - $ingredient['required'];
                if (!$db->query("UPDATE ingredients SET stock=$after WHERE id=$iid")) {
                    throw new Exception('Failed to update ingredient stock');
                }
            }

            $db->commit();
            logAudit($db, $user, "Created order: $order_number");
            jsonResponse(['success' => true, 'order_id' => $order_id, 'order_number' => $order_number, 'total' => $total, 'change' => $change]);
        } catch (Throwable $e) {
            $db->rollback();
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
        break;

    case 'get_orders':
        requireRole(['admin']);
        $db = getDB();
        $date = sanitize($_GET['date'] ?? date('Y-m-d'));
        $status = sanitize($_GET['status'] ?? '');
        $where = "WHERE DATE(o.created_at) = '$date'";
        if ($status) $where .= " AND o.status = '$status'";
        $sql = "SELECT o.*, COUNT(oi.id) as item_count FROM orders o LEFT JOIN order_items oi ON o.id = oi.order_id $where GROUP BY o.id ORDER BY o.created_at DESC";
        $result = $db->query($sql);
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        jsonResponse(['success' => true, 'data' => $rows]);
        break;

    case 'get_audit_logs':
        requireRole(['admin']);
        $db = getDB();
        ensureAuditSchema($db);
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
        if ($limit <= 0 || $limit > 1000) $limit = 200;
        $result = $db->query("SELECT user_name, action_text, created_at FROM audit_logs ORDER BY created_at DESC LIMIT $limit");
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        jsonResponse(['success' => true, 'data' => $rows]);
        break;

    case 'get_order_detail':
        requireRole(['admin']);
        $db = getDB();
        $id = (int)($_GET['id'] ?? 0);
        // Try with cashier_name, fallback to without if column doesn't exist
        $orderRes = $db->query("SELECT o.*, a.full_name as cashier_name FROM orders o LEFT JOIN accounts a ON o.cashier_id = a.id WHERE o.id=$id");
        if (!$orderRes) {
            $order = $db->query("SELECT * FROM orders WHERE id=$id")->fetch_assoc();
            $order['cashier_name'] = null;
        } else {
            $order = $orderRes->fetch_assoc();
        }
        $items_result = $db->query("SELECT * FROM order_items WHERE order_id=$id");
        $items = [];
        while ($row = $items_result->fetch_assoc()) $items[] = $row;
        jsonResponse(['success' => true, 'order' => $order, 'items' => $items]);
        break;

    case 'get_sales_report':
        requireRole(['admin']);
        $db = getDB();
        $from = sanitize($_GET['from'] ?? date('Y-m-01'));
        $to = sanitize($_GET['to'] ?? date('Y-m-d'));
        $group = sanitize($_GET['group'] ?? 'day');
        
        $data = [];
        $summary = ['revenue' => 0, 'orders' => 0, 'items_sold' => 0];
        
        if ($group === 'day') {
            $sql = "SELECT DATE(created_at) as label,
                    COUNT(*) as orders,
                    SUM(total) as revenue
                    FROM orders
                    WHERE status = 'completed' AND DATE(created_at) BETWEEN '$from' AND '$to'
                    GROUP BY DATE(created_at)
                    ORDER BY label";
            $result = $db->query($sql);
            while ($row = $result->fetch_assoc()) {
                $row['items_sold'] = 0;
                $itemsRes = $db->query("SELECT SUM(quantity) as cnt FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE DATE(created_at) = '{$row['label']}' AND status = 'completed')");
                if ($itemsRow = $itemsRes->fetch_assoc()) $row['items_sold'] = (int)($itemsRow['cnt'] ?? 0);
                $data[] = $row;
                $summary['revenue'] += $row['revenue'];
                $summary['orders'] += $row['orders'];
                $summary['items_sold'] += $row['items_sold'];
            }
        } elseif ($group === 'product') {
            $sql = "SELECT p.name as label, p.category,
                    SUM(oi.quantity) as items_sold,
                    SUM(oi.quantity * oi.price) as revenue
                    FROM order_items oi
                    JOIN orders o ON oi.order_id = o.id
                    JOIN products p ON oi.product_id = p.id
                    WHERE o.status = 'completed' AND DATE(o.created_at) BETWEEN '$from' AND '$to'
                    GROUP BY oi.product_id
                    ORDER BY revenue DESC";
            $result = $db->query($sql);
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
                $summary['revenue'] += $row['revenue'];
                $summary['items_sold'] += $row['items_sold'];
                $summary['orders'] = $db->query("SELECT COUNT(DISTINCT order_id) as cnt FROM order_items WHERE product_id IN (SELECT id FROM products WHERE name = '{$row['label']}')")->fetch_assoc()['cnt'] ?? 0;
            }
        } else { // category
            $sql = "SELECT COALESCE(p.category, 'Uncategorized') as label,
                    COUNT(DISTINCT o.id) as orders,
                    SUM(oi.quantity) as items_sold,
                    SUM(oi.quantity * oi.price) as revenue
                    FROM order_items oi
                    JOIN orders o ON oi.order_id = o.id
                    JOIN products p ON oi.product_id = p.id
                    WHERE o.status = 'completed' AND DATE(o.created_at) BETWEEN '$from' AND '$to'
                    GROUP BY p.category
                    ORDER BY revenue DESC";
            $result = $db->query($sql);
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
                $summary['revenue'] += $row['revenue'];
                $summary['orders'] += $row['orders'];
                $summary['items_sold'] += $row['items_sold'];
            }
        }
        jsonResponse(['success' => true, 'data' => $data, 'summary' => $summary]);
        break;

    case 'void_order':
        requireRole(['admin']);
        $db = getDB();
        $id = (int)($input['id'] ?? 0);
        $order = $db->query("SELECT * FROM orders WHERE id=$id")->fetch_assoc();
        if ($order && $order['status'] === 'completed') {
            try {
                $db->begin_transaction();
                ensureIngredientSchema($db);
                ensureVariantsSchema($db);
                ensureVariantIngredientSchema($db);
                $user = getCurrentUserFromSession();
                $ingredientRestore = [];
                $baseRecipeCache = [];
                $variantRecipeCache = [];
                $items_result = $db->query("SELECT * FROM order_items WHERE order_id=$id");
                while ($item = $items_result->fetch_assoc()) {
                    $pid = (int)$item['product_id'];
                    $qty = (int)$item['quantity'];
                    $vid = isset($item['variant_id']) && $item['variant_id'] ? (int)$item['variant_id'] : 0;

                    if (!isset($baseRecipeCache[$pid])) {
                        $baseRecipeCache[$pid] = fetchProductRecipeMap($db, $pid, true);
                    }
                    $baseRecipe = $baseRecipeCache[$pid];
                    $variantRecipe = [];
                    if ($vid > 0) {
                        if (!isset($variantRecipeCache[$vid])) {
                            $variantRecipeCache[$vid] = fetchVariantRecipeMap($db, $vid, true);
                        }
                        $variantRecipe = $variantRecipeCache[$vid];
                    }
                    $combined = $baseRecipe;
                    if (!empty($variantRecipe)) {
                        foreach ($variantRecipe as $iid => $rec) {
                            $combined[$iid] = $rec;
                        }
                    }
                    $hasRecipe = !empty($combined);
                    if ($hasRecipe) {
                        foreach ($combined as $iid => $recipe) {
                            if ((float)$recipe['quantity_required'] <= 0) continue;
                            if (!isset($ingredientRestore[$iid])) {
                                $ingredientRestore[$iid] = [
                                    'stock' => (float)$recipe['stock'],
                                    'quantity' => 0.0
                                ];
                            }
                            $ingredientRestore[$iid]['quantity'] += (float)$recipe['quantity_required'] * $qty;
                        }
                    } else {
                        $res = $db->query("SELECT stock FROM products WHERE id=$pid FOR UPDATE");
                        $prod = $res->fetch_assoc();
                        $before = (int)$prod['stock'];
                        $after = $before + $qty;
                        $db->query("UPDATE products SET stock=$after WHERE id=$pid");
                        $db->query("INSERT INTO inventory_logs (product_id, type, quantity_change, quantity_before, quantity_after, notes) VALUES ($pid,'adjustment',$qty,$before,$after,'Void Order #{$order['order_number']}')");
                    }
                }
                foreach ($ingredientRestore as $iid => $ing) {
                    $after = $ing['stock'] + $ing['quantity'];
                    $db->query("UPDATE ingredients SET stock=$after WHERE id=$iid");
                }
                $db->query("UPDATE orders SET status='cancelled' WHERE id=$id");
                $db->commit();
                logAudit($db, $user, "Deleted order: {$order['order_number']}");
                jsonResponse(['success' => true, 'message' => 'Order voided']);
            } catch (Throwable $e) {
                $db->rollback();
                jsonResponse(['success' => false, 'message' => 'Failed to void order'], 500);
            }
        } else {
            jsonResponse(['success' => false, 'message' => 'Cannot void this order'], 400);
        }
        break;

    // ── DASHBOARD / REPORTS ─────────────────────────
    case 'get_dashboard':
        requireRole(['admin']);
        $db = getDB();
        $period = sanitize($_GET['period'] ?? 'day');
        if (!in_array($period, ['day', 'week', 'month', 'year'], true)) $period = 'day';

        $now = new DateTime();
        $startDt = new DateTime();
        $endDt = new DateTime();
        $chartData = [];
        $periodLabel = ucfirst($period);
        $chartSubtitle = 'Selected period';

        if ($period === 'day') {
            $startDt = new DateTime($now->format('Y-m-d 00:00:00'));
            $endDt = new DateTime($now->format('Y-m-d 00:00:00'));
            $endDt->modify('+1 day');
            $chartSubtitle = 'Hourly';
        } elseif ($period === 'week') {
            $startDt = new DateTime('monday this week');
            $startDt->setTime(0, 0, 0);
            $endDt = clone $startDt;
            $endDt->modify('+7 days');
            $chartSubtitle = 'Daily (this week)';
        } elseif ($period === 'month') {
            $startDt = new DateTime($now->format('Y-m-01 00:00:00'));
            $endDt = clone $startDt;
            $endDt->modify('+1 month');
            $chartSubtitle = 'Daily (this month)';
        } else {
            $startDt = new DateTime($now->format('Y-01-01 00:00:00'));
            $endDt = clone $startDt;
            $endDt->modify('+1 year');
            $chartSubtitle = 'Monthly (this year)';
        }

        $start = $startDt->format('Y-m-d H:i:s');
        $end = $endDt->format('Y-m-d H:i:s');

        $sales = $db->query("SELECT COUNT(*) as count, COALESCE(SUM(total),0) as revenue
                             FROM orders
                             WHERE created_at >= '$start' AND created_at < '$end' AND status='completed'")->fetch_assoc();
        $low_stock = $db->query("SELECT COUNT(*) as count FROM products WHERE stock <= low_stock_threshold AND is_active=1")->fetch_assoc();
        $total_products = $db->query("SELECT COUNT(*) as count FROM products WHERE is_active=1")->fetch_assoc();
        $recent = $db->query("SELECT order_number, customer_name, total, status, created_at FROM orders ORDER BY created_at DESC LIMIT 6");
        $recent_orders = [];
        while ($row = $recent->fetch_assoc()) $recent_orders[] = $row;

        if ($period === 'day') {
            $result = $db->query("SELECT HOUR(created_at) AS bucket, COALESCE(SUM(total),0) AS revenue, COUNT(*) AS orders
                                  FROM orders
                                  WHERE created_at >= '$start' AND created_at < '$end' AND status='completed'
                                  GROUP BY HOUR(created_at)
                                  ORDER BY bucket");
            $map = [];
            while ($row = $result->fetch_assoc()) {
                $map[(int)$row['bucket']] = $row;
            }
            for ($h = 0; $h < 24; $h++) {
                $r = $map[$h] ?? ['revenue' => 0, 'orders' => 0];
                $chartData[] = ['label' => str_pad((string)$h, 2, '0', STR_PAD_LEFT), 'revenue' => (float)$r['revenue'], 'orders' => (int)$r['orders']];
            }
        } elseif ($period === 'week' || $period === 'month') {
            $result = $db->query("SELECT DATE(created_at) AS bucket, COALESCE(SUM(total),0) AS revenue, COUNT(*) AS orders
                                  FROM orders
                                  WHERE created_at >= '$start' AND created_at < '$end' AND status='completed'
                                  GROUP BY DATE(created_at)
                                  ORDER BY bucket");
            $map = [];
            while ($row = $result->fetch_assoc()) {
                $map[$row['bucket']] = $row;
            }
            $cursor = clone $startDt;
            while ($cursor < $endDt) {
                $key = $cursor->format('Y-m-d');
                $r = $map[$key] ?? ['revenue' => 0, 'orders' => 0];
                $label = ($period === 'week') ? $cursor->format('D') : $cursor->format('j');
                $chartData[] = ['label' => $label, 'revenue' => (float)$r['revenue'], 'orders' => (int)$r['orders']];
                $cursor->modify('+1 day');
            }
        } else {
            $result = $db->query("SELECT MONTH(created_at) AS bucket, COALESCE(SUM(total),0) AS revenue, COUNT(*) AS orders
                                  FROM orders
                                  WHERE created_at >= '$start' AND created_at < '$end' AND status='completed'
                                  GROUP BY MONTH(created_at)
                                  ORDER BY bucket");
            $map = [];
            while ($row = $result->fetch_assoc()) {
                $map[(int)$row['bucket']] = $row;
            }
            for ($m = 1; $m <= 12; $m++) {
                $r = $map[$m] ?? ['revenue' => 0, 'orders' => 0];
                $label = DateTime::createFromFormat('!m', (string)$m)->format('M');
                $chartData[] = ['label' => $label, 'revenue' => (float)$r['revenue'], 'orders' => (int)$r['orders']];
            }
        }

        $top_products = $db->query("SELECT p.name, SUM(oi.quantity) as sold, SUM(oi.subtotal) as revenue
                                    FROM order_items oi
                                    JOIN products p ON oi.product_id = p.id
                                    JOIN orders o ON oi.order_id = o.id
                                    WHERE o.created_at >= '$start' AND o.created_at < '$end' AND o.status='completed'
                                    GROUP BY p.id ORDER BY sold DESC LIMIT 5");
        $top = [];
        while ($row = $top_products->fetch_assoc()) $top[] = $row;
        jsonResponse(['success' => true, 'data' => [
            'period' => $period,
            'period_label' => $periodLabel,
            'period_sales' => $sales['count'],
            'period_revenue' => $sales['revenue'],
            'chart_subtitle' => $chartSubtitle,
            'low_stock_count' => $low_stock['count'],
            'total_products' => $total_products['count'],
            'recent_orders' => $recent_orders,
            'chart_data' => $chartData,
            'top_products' => $top
        ]]);
        break;

    case 'get_inventory_logs':
        requireRole(['admin']);
        $db = getDB();
        $pid = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
        $where = $pid > 0 ? "WHERE il.product_id = $pid" : "";
        $sql = "SELECT il.*, p.name as product_name FROM inventory_logs il JOIN products p ON il.product_id = p.id $where ORDER BY il.created_at DESC LIMIT 100";
        $result = $db->query($sql);
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        jsonResponse(['success' => true, 'data' => $rows]);
        break;

    case 'get_popular_products':
        requireRole(['admin', 'staff']);
        $db = getDB();
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 8;
        $result = $db->query("SELECT p.id, p.name, p.price, p.stock, p.image_url, p.size, p.low_stock_threshold,
                                     SUM(oi.quantity) as sold
                              FROM order_items oi
                              JOIN products p ON oi.product_id = p.id
                              JOIN orders o ON oi.order_id = o.id
                              WHERE o.status='completed' AND p.is_active=1
                              GROUP BY p.id
                              ORDER BY sold DESC
                              LIMIT $limit");
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        jsonResponse(['success' => true, 'data' => $rows]);
        break;

    case 'ensure_schema':
        requireRole(['admin']);
        $db = getDB();
        ensureOrderSchema($db);
        ensureVariantsSchema($db);
        ensureVariantIngredientSchema($db);
        ensureAuditSchema($db);
        jsonResponse(['success' => true, 'message' => 'Schema ensured']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action'], 404);
}
