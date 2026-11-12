-- E-Commerce Website with PayPal Payment Gateway
-- Import this file in phpMyAdmin (Import tab) or run it in the MySQL console.

CREATE DATABASE IF NOT EXISTS ecommerce_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ecommerce_db;

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
    id    INT AUTO_INCREMENT PRIMARY KEY,
    name  VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Uploaded product photos live in the database so they survive redeploys on
-- hosts without a persistent disk (like Render's free plan)
CREATE TABLE product_images (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    mime        VARCHAR(30) NOT NULL,
    data        MEDIUMBLOB  NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE products (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    category_id  INT NULL,
    name         VARCHAR(150) NOT NULL,
    description  TEXT,
    price        DECIMAL(10,2) NOT NULL,  -- INR, inclusive of GST
    stock        INT NOT NULL DEFAULT 0,
    image        VARCHAR(255) NULL,       -- 'db:<product_images.id>' for uploads, or a bundled file in assets/products/
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE orders (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    user_id            INT NOT NULL,
    total              DECIMAL(10,2) NOT NULL,              -- in INR
    amount_usd         DECIMAL(10,2) NOT NULL,              -- what PayPal charges (USD)
    status             ENUM('pending','paid','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
    shipping_name      VARCHAR(100) NOT NULL,
    shipping_phone     VARCHAR(20)  NOT NULL,
    shipping_address   VARCHAR(255) NOT NULL,
    shipping_city      VARCHAR(100) NOT NULL,
    shipping_state     VARCHAR(60)  NOT NULL,
    shipping_zip       VARCHAR(6)   NOT NULL,               -- 6-digit PIN code
    paypal_order_id    VARCHAR(50)  NULL,
    paypal_capture_id  VARCHAR(50)  NULL,
    paid_at            DATETIME     NULL,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    order_id      INT NOT NULL,
    product_id    INT NULL,
    product_name  VARCHAR(150) NOT NULL,   -- snapshot, so history survives product edits/deletes
    price         DECIMAL(10,2) NOT NULL,  -- unit price (INR) at time of purchase
    quantity      INT NOT NULL,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Seed data
-- Default passwords (admin123 / customer123) are for local testing only.
-- Set ADMIN_PASSWORD and CUSTOMER_PASSWORD (config/local.php or environment
-- variables) and the site replaces them automatically.
-- ---------------------------------------------------------------
INSERT INTO users (name, email, password, role) VALUES
('Store Admin', 'admin@example.com',    '$2y$10$9.hH14wqoD9ny.iI37HLPepOQfRU85yZJmCzETu7YrFa7T7qweVxS', 'admin'),
('Priya Sharma', 'customer@example.com', '$2y$10$6l/h4k4TrIoS8o9QWEuvQefXZwAB8wYx.3x6DiEBGu5OfoXnE5bRW', 'customer');

INSERT INTO categories (name) VALUES ('Notebooks'), ('Pens'), ('Desk');

INSERT INTO products (category_id, name, description, price, stock, image) VALUES
((SELECT id FROM categories WHERE name = 'Notebooks'), 'Sanganeri Handmade Paper Notebook', 'A5 notebook of cotton-rag paper handmade in Sanganer, Jaipur. 160 pages, dot grid, thread-bound so it lies flat.', 349.00, 40, 'product-1.svg'),
((SELECT id FROM categories WHERE name = 'Notebooks'), 'Pocket Diary Set (3)',             'Three slim 48-page pocket diaries with kraft covers. Fits a shirt pocket.', 199.00, 60, 'product-2.svg'),
((SELECT id FROM categories WHERE name = 'Notebooks'), 'Hardcover Sketchbook',             '120 GSM heavyweight paper that takes ink, pencil and light watercolour washes.', 549.00, 25, 'product-3.svg'),
((SELECT id FROM categories WHERE name = 'Pens'), 'Fountain Pen, Fine Nib',           'Steel fine nib, ink converter included, ebonite-look body. Writes smoothly on Indian notebook paper.', 1250.00, 15, 'product-4.svg'),
((SELECT id FROM categories WHERE name = 'Pens'), 'Gel Pen 0.5 (Pack of 5)',          'Quick-drying ink in black, blue, red, green and violet.', 120.00, 120, 'product-5.svg'),
((SELECT id FROM categories WHERE name = 'Desk'), 'Channapatna Wooden Pen Stand',     'Hand-turned and lacquered with natural colours by artisans in Channapatna, Karnataka.', 450.00, 30, 'product-6.svg'),
((SELECT id FROM categories WHERE name = 'Desk'), 'Moradabad Brass Ruler 30 cm',      'Solid brass ruler from Moradabad with etched centimetre and inch scales.', 699.00, 20, 'product-7.svg'),
((SELECT id FROM categories WHERE name = 'Desk'), 'Jute Desk Mat',                    'Woven jute desk mat, 80 x 30 cm, with a cotton backing. Made in West Bengal.', 899.00, 0, 'product-8.svg');
