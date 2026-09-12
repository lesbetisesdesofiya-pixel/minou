-- RESTO API - Schema Optimization for Hostinger
-- Note: Remove 'CREATE DATABASE' lines to avoid errors on shared hosting.

SET FOREIGN_KEY_CHECKS = 0;

-- Table des catégories
DROP TABLE IF EXISTS categories;
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Table des plats
DROP TABLE IF EXISTS dishes;
CREATE TABLE dishes (
    id INT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    description TEXT,
    prix DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    category_id INT,
    image VARCHAR(255),
    menu_type ENUM('plats', 'bar') DEFAULT 'plats',
    active BOOLEAN DEFAULT TRUE,
    orders_count INT DEFAULT 0,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Table des variations (Taille, Type...)
DROP TABLE IF EXISTS dish_variations;
CREATE TABLE dish_variations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dish_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    group_name VARCHAR(255) DEFAULT 'Variations',
    stock_kg DECIMAL(10, 2) DEFAULT 0.00,
    FOREIGN KEY (dish_id) REFERENCES dishes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table des options globales (Garnitures, Supplements, Parfums, Sauces)
DROP TABLE IF EXISTS options;
CREATE TABLE options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    type ENUM('garniture', 'supplement', 'parfum', 'custom_radio') NOT NULL,
    prix DECIMAL(10, 2) DEFAULT 0.00
) ENGINE=InnoDB;

-- Table de liaison Catégories <-> Options
DROP TABLE IF EXISTS category_options;
CREATE TABLE category_options (
    category_id INT,
    option_id INT,
    PRIMARY KEY (category_id, option_id),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES options(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table de liaison Plats <-> Options
DROP TABLE IF EXISTS dish_options;
CREATE TABLE dish_options (
    dish_id INT,
    option_id INT,
    PRIMARY KEY (dish_id, option_id),
    FOREIGN KEY (dish_id) REFERENCES dishes(id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES options(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table des commandes
DROP TABLE IF EXISTS orders;
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_type VARCHAR(50) NOT NULL,
    client_name VARCHAR(255),
    client_phone VARCHAR(50),
    neighborhood VARCHAR(255),
    table_number INT,
    notes TEXT,
    total_amount DECIMAL(10, 2) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    assigned_driver_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table des articles de commande
DROP TABLE IF EXISTS order_items;
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    options_text TEXT,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table des livreurs
DROP TABLE IF EXISTS delivery_persons;
CREATE TABLE delivery_persons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    last_name VARCHAR(255) NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    email VARCHAR(255) NOT NULL UNIQUE,
    active BOOLEAN DEFAULT TRUE,
    suspended BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table Admin
DROP TABLE IF EXISTS admins;
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'RESTAURANT'
) ENGINE=InnoDB;

ALTER TABLE orders ADD CONSTRAINT fk_driver FOREIGN KEY (assigned_driver_id) REFERENCES delivery_persons(id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;
