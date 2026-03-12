-- ============================================
-- CAFE POS & INVENTORY MANAGEMENT SYSTEM
-- Database: cafe_pos
-- ============================================

CREATE DATABASE IF NOT EXISTS cafe_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cafe_pos;

-- Categories Table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(50) DEFAULT 'coffee',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products / Menu Items Table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock INT NOT NULL DEFAULT 0,
    low_stock_threshold INT DEFAULT 10,
    unit VARCHAR(30) DEFAULT 'pcs',
    size VARCHAR(20) NOT NULL DEFAULT 'Regular',
    image_url VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Customers Table
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150),
    email VARCHAR(150),
    phone VARCHAR(30),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Orders Table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(20) NOT NULL UNIQUE,
    customer_id INT DEFAULT NULL,
    customer_name VARCHAR(150) DEFAULT 'Walk-in',
    order_type ENUM('dine_in','take_out') DEFAULT 'dine_in',
    subtotal DECIMAL(10,2) DEFAULT 0.00,
    discount DECIMAL(10,2) DEFAULT 0.00,
    tax DECIMAL(10,2) DEFAULT 0.00,
    total DECIMAL(10,2) DEFAULT 0.00,
    payment_method ENUM('cash','card','gcash','maya') DEFAULT 'cash',
    amount_paid DECIMAL(10,2) DEFAULT 0.00,
    change_given DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('pending','completed','cancelled','refunded') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

-- Order Items Table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);

-- Inventory Logs Table
CREATE TABLE IF NOT EXISTS inventory_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    type ENUM('restock','sale','adjustment','waste') NOT NULL,
    quantity_change INT NOT NULL,
    quantity_before INT NOT NULL,
    quantity_after INT NOT NULL,
    notes VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Audit Logs Table
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    user_name VARCHAR(120) DEFAULT NULL,
    action_text VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Ingredients Table
CREATE TABLE IF NOT EXISTS ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    unit VARCHAR(30) NOT NULL DEFAULT 'g',
    stock DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    low_stock_threshold DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Product Variants Table (sizes with individual pricing)
CREATE TABLE IF NOT EXISTS product_variants (
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
);

-- Product Ingredients / Recipe Table
CREATE TABLE IF NOT EXISTS product_ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity_required DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_product_ingredient (product_id, ingredient_id),
    KEY idx_pi_product (product_id),
    KEY idx_pi_ingredient (ingredient_id)
);

-- Product Variant Ingredients / Recipe Table
CREATE TABLE IF NOT EXISTS product_variant_ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    variant_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity_required DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_variant_ingredient (variant_id, ingredient_id),
    KEY idx_pvi_variant (variant_id),
    KEY idx_pvi_ingredient (ingredient_id)
);

-- ============================================
-- SAMPLE DATA
-- ============================================

INSERT INTO categories (name, icon) VALUES
('Coffee', 'coffee'),
('Tea', 'leaf'),
('Pastries', 'cake'),
('Sandwiches', 'sandwich'),
('Cold Drinks', 'glass-water'),
('Merchandise', 'shopping-bag');

INSERT INTO products (category_id, name, description, price, cost, stock, low_stock_threshold, unit) VALUES
(1, 'Espresso', 'Single shot of rich espresso', 90.00, 25.00, 100, 20, 'cup'),
(1, 'Americano', 'Espresso with hot water', 110.00, 30.00, 100, 20, 'cup'),
(1, 'Cappuccino', 'Espresso with steamed milk foam', 145.00, 40.00, 80, 15, 'cup'),
(1, 'Latte', 'Espresso with steamed milk', 155.00, 42.00, 80, 15, 'cup'),
(1, 'Flat White', 'Double ristretto with microfoam', 165.00, 45.00, 60, 10, 'cup'),
(1, 'Cold Brew', '12-hour cold brewed coffee', 175.00, 50.00, 40, 8, 'cup'),
(2, 'Matcha Latte', 'Premium matcha with oat milk', 175.00, 55.00, 50, 10, 'cup'),
(2, 'Chamomile Tea', 'Soothing chamomile blend', 95.00, 20.00, 60, 10, 'cup'),
(2, 'Earl Grey', 'Classic bergamot tea', 95.00, 20.00, 60, 10, 'cup'),
(3, 'Croissant', 'Butter croissant, freshly baked', 85.00, 35.00, 30, 5, 'pcs'),
(3, 'Banana Bread', 'House-made banana loaf', 95.00, 38.00, 20, 5, 'slice'),
(3, 'Chocolate Muffin', 'Double chocolate chip muffin', 90.00, 35.00, 25, 5, 'pcs'),
(4, 'Club Sandwich', 'Turkey, bacon, lettuce, tomato', 215.00, 85.00, 20, 5, 'pcs'),
(4, 'Avocado Toast', 'Sourdough with smashed avocado', 195.00, 75.00, 15, 5, 'pcs'),
(5, 'Iced Latte', 'Espresso over ice with milk', 155.00, 42.00, 80, 15, 'cup'),
(5, 'Iced Matcha', 'Matcha over ice with oat milk', 175.00, 55.00, 50, 10, 'cup'),
(5, 'Sparkling Water', 'San Pellegrino 500ml', 75.00, 30.00, 48, 10, 'bottle'),
(6, 'Tote Bag', 'Canvas cafe branded tote', 350.00, 120.00, 15, 3, 'pcs'),
(6, 'Coffee Beans 250g', 'Single origin house blend', 450.00, 200.00, 10, 3, 'bag');

INSERT INTO ingredients (name, unit, stock, low_stock_threshold) VALUES
('Coffee Beans', 'g', 10000, 1500),
('Milk', 'ml', 20000, 3000),
('Water', 'ml', 100000, 15000),
('Ice', 'g', 20000, 3000);

INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
SELECT p.id, i.id, 18
FROM products p
JOIN ingredients i ON i.name = 'Coffee Beans'
WHERE p.name = 'Espresso';
INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
SELECT p.id, i.id, 40
FROM products p
JOIN ingredients i ON i.name = 'Water'
WHERE p.name = 'Espresso';

INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
SELECT p.id, i.id, 18
FROM products p
JOIN ingredients i ON i.name = 'Coffee Beans'
WHERE p.name = 'Americano';
INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
SELECT p.id, i.id, 180
FROM products p
JOIN ingredients i ON i.name = 'Water'
WHERE p.name = 'Americano';

INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
SELECT p.id, i.id, 18
FROM products p
JOIN ingredients i ON i.name = 'Coffee Beans'
WHERE p.name = 'Cappuccino';
INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
SELECT p.id, i.id, 150
FROM products p
JOIN ingredients i ON i.name = 'Milk'
WHERE p.name = 'Cappuccino';

INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
SELECT p.id, i.id, 18
FROM products p
JOIN ingredients i ON i.name = 'Coffee Beans'
WHERE p.name = 'Latte';
INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
SELECT p.id, i.id, 220
FROM products p
JOIN ingredients i ON i.name = 'Milk'
WHERE p.name = 'Latte';

INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
SELECT p.id, i.id, 20
FROM products p
JOIN ingredients i ON i.name = 'Coffee Beans'
WHERE p.name = 'Flat White';
INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
SELECT p.id, i.id, 180
FROM products p
JOIN ingredients i ON i.name = 'Milk'
WHERE p.name = 'Flat White';

INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
SELECT p.id, i.id, 30
FROM products p
JOIN ingredients i ON i.name = 'Coffee Beans'
WHERE p.name = 'Cold Brew';
INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
SELECT p.id, i.id, 250
FROM products p
JOIN ingredients i ON i.name = 'Water'
WHERE p.name = 'Cold Brew';
INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
SELECT p.id, i.id, 120
FROM products p
JOIN ingredients i ON i.name = 'Ice'
WHERE p.name = 'Cold Brew';

INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
SELECT p.id, i.id, 18
FROM products p
JOIN ingredients i ON i.name = 'Coffee Beans'
WHERE p.name = 'Iced Latte';
INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
SELECT p.id, i.id, 200
FROM products p
JOIN ingredients i ON i.name = 'Milk'
WHERE p.name = 'Iced Latte';
INSERT INTO product_ingredients (product_id, ingredient_id, quantity_required)
SELECT p.id, i.id, 120
FROM products p
JOIN ingredients i ON i.name = 'Ice'
WHERE p.name = 'Iced Latte';
