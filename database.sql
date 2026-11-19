-- ============================================================
-- BOBO BAKER'S DATABASE SCHEMA + SAMPLE DATA
-- ============================================================
-- IMPORTANT: these three lines make emoji and Urdu text import
-- correctly. Without them MySQL falls back to latin1 and every
-- emoji becomes garbage (e.g. the candle emoji stored as "Ã°Å¸â€¢Â¯").
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET collation_connection = 'utf8mb4_unicode_ci';

CREATE DATABASE IF NOT EXISTS bobo_bakers CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bobo_bakers;

-- ---------------------------------------
-- TABLE: categories
-- ---------------------------------------
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------
-- TABLE: products (cakes)
-- ---------------------------------------
CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  price DECIMAL(10,2) NOT NULL,
  image_url VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- ---------------------------------------
-- TABLE: toppings (add-ons)
-- ---------------------------------------
CREATE TABLE toppings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  emoji VARCHAR(10) DEFAULT NULL,
  price DECIMAL(10,2) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
);

-- ---------------------------------------
-- TABLE: customers
-- ---------------------------------------
CREATE TABLE customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  address VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_phone (phone)
);

-- ---------------------------------------
-- TABLE: orders
-- ---------------------------------------
CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  product_id INT NOT NULL,
  base_price DECIMAL(10,2) NOT NULL,
  addons_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_price DECIMAL(10,2) NOT NULL,
  delivery_datetime DATETIME NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  status ENUM('pending','confirmed','preparing','out_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ---------------------------------------
-- TABLE: order_toppings (many-to-many: order <-> toppings)
-- ---------------------------------------
CREATE TABLE order_toppings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  topping_id INT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (topping_id) REFERENCES toppings(id) ON DELETE CASCADE
);

-- ---------------------------------------
-- TABLE: admins (for admin panel login)
-- ---------------------------------------
CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin login:
--   username: admin
--   password: admin123   (CHANGE THIS after first login!)
INSERT INTO admins (username, password_hash, full_name) VALUES
('admin', '$2b$12$ggmRfNDORRinY46gLq/k/uhGjtfA7HJLJqlUdwPQcQIM7p7p3fU2G', 'Bobo Baker Admin');

-- ---------------------------------------
-- TABLE: leads (custom-cake enquiries from the "Place Your Order" contact form)
-- These are NOT catalog checkouts — they're free-text custom order requests
-- that also go out over WhatsApp; this table just keeps a permanent record.
-- ---------------------------------------
CREATE TABLE leads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_ref VARCHAR(20) DEFAULT NULL,
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  area VARCHAR(150) NOT NULL,
  occasion VARCHAR(100) DEFAULT NULL,
  required_by DATE DEFAULT NULL,
  flavour VARCHAR(100) DEFAULT NULL,
  size VARCHAR(100) DEFAULT NULL,
  budget VARCHAR(100) DEFAULT NULL,
  cake_message VARCHAR(255) DEFAULT NULL,
  design_details TEXT NOT NULL,
  referral VARCHAR(100) DEFAULT NULL,
  status ENUM('new','contacted','converted','closed') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- ---------------------------------------
-- INDEXES (dashboard filtering + lookups)
-- ---------------------------------------
CREATE INDEX idx_orders_status     ON orders (status);
CREATE INDEX idx_orders_created    ON orders (created_at);
CREATE INDEX idx_orders_delivery   ON orders (delivery_datetime);
CREATE INDEX idx_leads_status      ON leads (status);
CREATE INDEX idx_leads_created     ON leads (created_at);
CREATE INDEX idx_leads_phone       ON leads (phone);
CREATE INDEX idx_products_active   ON products (is_active, category_id);

-- ---------------------------------------
-- SAMPLE DATA: categories
-- ---------------------------------------
INSERT INTO categories (slug, name, description) VALUES
('wedding', 'Wedding & Anniversary', 'Custom wedding, nikkah and anniversary cakes'),
('birthday-boys', 'Birthday Cakes - Boys', 'Birthday cakes themed for boys'),
('birthday-girls', 'Birthday Cakes - Girls', 'Birthday cakes themed for girls'),
('eid', 'Eid Special Cakes', 'Eid Mubarak and Chand Raat themed cakes'),
('gender', 'Gender Reveal Cakes', 'Surprise gender reveal cakes'),
('baby', 'Baby Shower Cakes', 'Baby shower and half birthday cakes'),
('cupcakes', 'Cupcakes', 'Assorted premium cupcakes');

-- ---------------------------------------
-- SAMPLE DATA: toppings
-- ---------------------------------------
INSERT INTO toppings (name, emoji, price) VALUES
('Candles', '🕯️', 300),
('Balloons', '🎊', 500),
('Gift Wrap', '🎀', 400),
('Fresh Flowers', '🌸', 700),
('Sparklers', '✨', 350),
('Photo Topper', '🖼️', 600);

-- ---------------------------------------
-- SAMPLE DATA: products (cakes from website)
-- ---------------------------------------
INSERT INTO products (category_id, name, price) VALUES
(1, 'Royal Red Nikkah Cake', 3500),
(1, 'White Pearl Wedding Tier', 4500),
(1, 'Golden Anniversary Cake', 3800),
(1, 'Happy Anniversary Cake', 2800),
(1, 'Royal Blue Sangeet Dhol', 4100),
(1, 'Wedding Drape Elegance', 5000),
(1, 'Spring Blossom Swirl Cake', 2900),
(1, 'The Royal Bride-to-Be Cake', 3500),
(1, 'Elegant White Pearl Cake', 3800),
(1, 'All of Me Anniversary Cake', 3000),
(1, 'Crimson Love Birthday Heart', 2700),
(1, 'Golden Brushstroke Elegance', 4200),
(1, 'Golden Bride Mayoon Cake', 4000),
(2, 'Ultimate Chocolate Fantasy', 2800),
(2, 'Black Forest Supreme', 2500),
(2, 'PUBG Battleground Cake', 3000),
(2, 'Classic Vanilla Birthday', 2200),
(2, 'CoComelon Fun Cake', 3200),
(2, 'Spidey Action Web Cake', 3000),
(2, 'Vanilla Confetti Celebration', 2500),
(2, 'Oreo Cream Delight', 2800),
(2, 'Mango Citrus Swirl', 2600),
(2, 'Midnight Dark Chocolate', 2900),
(2, 'Chocolate Loaded Bar Cake', 3200),
(2, 'Simple Vanilla Joy', 2000),
(2, 'Rich Dark Fudge Cake', 2700),
(2, 'Black Forest Pastry Cake', 2400),
(2, 'Blue Sapphire Memories Cake', 3500),
(2, 'The Snooker Champion Cake', 3800),
(2, 'Blue Velvet Love Cake', 3000),
(2, 'Purple Princess Gown', 3500),
(3, 'Chocolate Floral Wreath', 2800),
(3, 'Strawberry Rose Dream', 3000),
(3, 'Golden Rose Tier Cake', 4000),
(3, 'Elegant White Rose Cake', 3200),
(3, 'Magical Unicorn Fantasy', 3500),
(3, 'Sparkling Unicorn Tier', 4200),
(3, 'Vanilla Rose Swirl', 2500),
(3, 'Pastel Garden Cake', 2800),
(3, 'Choco-Berry Delight', 3000),
(3, 'Vanilla Gold Elegance', 3100),
(3, 'Enchanted Fairy Doll', 3400),
(3, 'Red Rose Vanilla Classic', 2600),
(3, 'Dark Brownie Fudge', 2900),
(3, 'Pink Star Fairy Cake', 3200),
(3, 'Floral Vanilla Bloom', 2700),
(3, 'Blue Rose Harmony', 2800),
(3, 'Vanilla Strawberry Duo', 2900),
(3, 'Little Fairy Princess', 3300),
(3, 'Royal Barbie Ballgown', 3800),
(3, 'Purple & White Bento Combo', 2500),
(3, 'Hearts in Motion Cake', 3100),
(3, 'Pink & Gold Ball Theme Cake', 3400),
(3, 'Candy Land Drip Cake', 3600),
(3, 'Pink & Gold Butterfly Cake', 3300),
(3, 'Green Eid Mubarak Drip', 3000),
(4, 'Chand Raat Special', 3200),
(4, 'Eid Mubarak Gold Cake', 3500),
(5, 'Pink Surprise Reveal', 3200),
(5, 'Blue Boy Reveal Cake', 3000),
(5, 'Oh Baby! Reveal Cake', 3400),
(5, 'He or She Reveal Cake', 2800),
(6, 'Baby Boss Blue Cake', 3100),
(6, 'Cloud White Baby Shower', 2900),
(6, 'Blue Bow Half Birthday', 2200),
(6, 'Sky High Adventures Cake', 3600),
(7, 'Emerald Elegance Box', 1800),
(7, 'Assorted Floral Cupcakes', 1500),
(7, 'Strawberry Swirl Cupcakes', 1400),
(7, 'Gourmet Choco Cupcakes', 1600),
(7, 'Vanilla Sprinkles Cupcakes', 1200),
(7, 'Strawberry Cupcakes', 1500),
(7, 'Blueberry Burst Cupcakes', 1600),
(7, 'Lemon Zest Cupcakes', 1400),
(7, 'Choco-Fudge Cupcakes', 1700),
(7, 'Party Confetti Cupcakes', 1300);

-- ---------------------------------------
-- IMAGE URLS for all 76 original products (matched from live site)
-- ---------------------------------------
UPDATE products SET image_url = '33.png' WHERE id = 1;
UPDATE products SET image_url = '35.png' WHERE id = 2;
UPDATE products SET image_url = '36.png' WHERE id = 3;
UPDATE products SET image_url = '60.png' WHERE id = 4;
UPDATE products SET image_url = '79.png' WHERE id = 5;
UPDATE products SET image_url = '32.png' WHERE id = 6;
UPDATE products SET image_url = '8.png' WHERE id = 7;
UPDATE products SET image_url = '80.png' WHERE id = 8;
UPDATE products SET image_url = '82.png' WHERE id = 9;
UPDATE products SET image_url = '96.png' WHERE id = 10;
UPDATE products SET image_url = '98.png' WHERE id = 11;
UPDATE products SET image_url = '99.png' WHERE id = 12;
UPDATE products SET image_url = '101.png' WHERE id = 13;
UPDATE products SET image_url = '3.png' WHERE id = 14;
UPDATE products SET image_url = '13.png' WHERE id = 15;
UPDATE products SET image_url = '14.png' WHERE id = 16;
UPDATE products SET image_url = '18.png' WHERE id = 17;
UPDATE products SET image_url = '20.png' WHERE id = 18;
UPDATE products SET image_url = '21.png' WHERE id = 19;
UPDATE products SET image_url = '23.png' WHERE id = 20;
UPDATE products SET image_url = '26.png' WHERE id = 21;
UPDATE products SET image_url = '28.png' WHERE id = 22;
UPDATE products SET image_url = '29.png' WHERE id = 23;
UPDATE products SET image_url = '30.png' WHERE id = 24;
UPDATE products SET image_url = '34.png' WHERE id = 25;
UPDATE products SET image_url = '38.png' WHERE id = 26;
UPDATE products SET image_url = '66.png' WHERE id = 27;
UPDATE products SET image_url = '83.png' WHERE id = 28;
UPDATE products SET image_url = '84.png' WHERE id = 29;
UPDATE products SET image_url = '91.png' WHERE id = 30;
UPDATE products SET image_url = '5.png' WHERE id = 31;
UPDATE products SET image_url = '6.png' WHERE id = 32;
UPDATE products SET image_url = '7.png' WHERE id = 33;
UPDATE products SET image_url = '9.png' WHERE id = 34;
UPDATE products SET image_url = '10.png' WHERE id = 35;
UPDATE products SET image_url = '11.png' WHERE id = 36;
UPDATE products SET image_url = '12.png' WHERE id = 37;
UPDATE products SET image_url = '17.png' WHERE id = 38;
UPDATE products SET image_url = '19.png' WHERE id = 39;
UPDATE products SET image_url = '22.png' WHERE id = 40;
UPDATE products SET image_url = '27.png' WHERE id = 41;
UPDATE products SET image_url = '31.png' WHERE id = 42;
UPDATE products SET image_url = '37.png' WHERE id = 43;
UPDATE products SET image_url = '39.png' WHERE id = 44;
UPDATE products SET image_url = '40.png' WHERE id = 45;
UPDATE products SET image_url = '41.png' WHERE id = 46;
UPDATE products SET image_url = '42.png' WHERE id = 47;
UPDATE products SET image_url = '44.png' WHERE id = 48;
UPDATE products SET image_url = '59.png' WHERE id = 49;
UPDATE products SET image_url = '63.png' WHERE id = 50;
UPDATE products SET image_url = '81.png' WHERE id = 51;
UPDATE products SET image_url = '86.png' WHERE id = 52;
UPDATE products SET image_url = '88.png' WHERE id = 53;
UPDATE products SET image_url = '89.png' WHERE id = 54;
UPDATE products SET image_url = '92.png' WHERE id = 55;
UPDATE products SET image_url = '77.png' WHERE id = 56;
UPDATE products SET image_url = '78.png' WHERE id = 57;
UPDATE products SET image_url = '94.png' WHERE id = 58;
UPDATE products SET image_url = '55.png' WHERE id = 59;
UPDATE products SET image_url = '56.png' WHERE id = 60;
UPDATE products SET image_url = '57.png' WHERE id = 61;
UPDATE products SET image_url = '58.png' WHERE id = 62;
UPDATE products SET image_url = '43.png' WHERE id = 63;
UPDATE products SET image_url = '87.png' WHERE id = 64;
UPDATE products SET image_url = '90.png' WHERE id = 65;
UPDATE products SET image_url = '100.png' WHERE id = 66;
UPDATE products SET image_url = '95.png' WHERE id = 67;
UPDATE products SET image_url = '46.png' WHERE id = 68;
UPDATE products SET image_url = '47.png' WHERE id = 69;
UPDATE products SET image_url = '48.png' WHERE id = 70;
UPDATE products SET image_url = '49.png' WHERE id = 71;
UPDATE products SET image_url = '50.png' WHERE id = 72;
UPDATE products SET image_url = '51.png' WHERE id = 73;
UPDATE products SET image_url = '52.png' WHERE id = 74;
UPDATE products SET image_url = '53.png' WHERE id = 75;
UPDATE products SET image_url = '54.png' WHERE id = 76;

-- ---------------------------------------
-- 3 PRODUCTS THAT WERE ON THE SITE BUT MISSING FROM THE DATABASE
-- (Influencer's / Teacher's Day / Mother's Day cakes — ids 77-79)
-- ---------------------------------------
INSERT INTO products (id, category_id, name, price, image_url) VALUES
(77, 2, 'Influencer\'s Birthday Delight', 3600, '93.png'),
(78, 3, 'Teacher\'s Day Special Cake', 2600, '85.png'),
(79, 3, 'Mother\'s Day Special Cake', 2800, '97.png');
-- ---------------------------------------
-- Deactivated: source images (36.png, 60.png) were missing from the
-- uploaded package, so these 2 cards were removed from the live gallery.
-- Kept in DB as inactive (not deleted) so IDs of other products stay stable.
-- ---------------------------------------
UPDATE products SET is_active = 0 WHERE id IN (3, 4);

-- ============================================================
-- CATEGORY REBUILD
-- ------------------------------------------------------------
-- Every cake was checked against its own photograph. 33 of the 77 were
-- filed under a category the picture contradicted. The worst cases:
--
--   * All four "Gender Reveal" cakes were mehndi / mayoon dholki cakes.
--     Two of them have "NIMRA MEHNDI" and "ASMA KI MAIYOON" piped onto
--     the board. Not one real gender reveal cake existed, so that
--     category has been removed.
--   * "White Pearl Wedding Tier" is a Minion cake, filed under Wedding.
--   * "Black Forest Supreme" is an iPhone cake.
--   * "Lemon Zest Cupcakes" is a tray of donuts.
--   * "Party Confetti Cupcakes" is a platter of gulab jamun.
--   * Plain chocolate and vanilla cakes were split between "Boys" and
--     "Girls" at random, so a plain fudge cake was labelled a girls' cake.
--
-- New categories: Mehndi & Mayoon, and Celebration & Classic.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM categories;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO categories (id, slug, name, description) VALUES (1, 'wedding', 'Wedding & Nikkah', 'Nikkah, wedding, mayoon-adjacent and anniversary cakes, hand-finished in tiers.');
INSERT INTO categories (id, slug, name, description) VALUES (2, 'mehndi', 'Mehndi & Mayoon', 'Mehndi, mayoon and dholki cakes in traditional colours with fondant gota detail.');
INSERT INTO categories (id, slug, name, description) VALUES (3, 'birthday-girls', 'Birthday - Girls', 'Themed birthday cakes for girls - unicorns, princesses, dolls and florals.');
INSERT INTO categories (id, slug, name, description) VALUES (4, 'birthday-boys', 'Birthday - Boys', 'Themed birthday cakes for boys - gaming, superheroes, sports and characters.');
INSERT INTO categories (id, slug, name, description) VALUES (5, 'celebration', 'Celebration & Classic', 'Flavour-led cakes for any celebration, with your own message and colours.');
INSERT INTO categories (id, slug, name, description) VALUES (6, 'eid', 'Eid Special', 'Eid and Chand Raat cakes for family gatherings.');
INSERT INTO categories (id, slug, name, description) VALUES (7, 'baby', 'Baby Shower & 1st Birthday', 'Baby showers, first birthdays and half-birthdays in soft pastel finishes.');
INSERT INTO categories (id, slug, name, description) VALUES (8, 'cupcakes', 'Cupcakes & Desserts', 'Cupcake boxes, donuts and dessert platters for parties and gifting.');

-- Names corrected where the photo did not match the old name
UPDATE products SET name = 'Orange & Green Dholki Cake' WHERE name = 'Pink Surprise Reveal';
UPDATE products SET name = 'Mayoon Colour Block Cake' WHERE name = 'Blue Boy Reveal Cake';
UPDATE products SET name = 'Pink Draped Dholki Cake' WHERE name = 'Oh Baby! Reveal Cake';
UPDATE products SET name = 'Traditional Mayoon Dholki' WHERE name = 'He or She Reveal Cake';
UPDATE products SET name = 'Teal & Gold Shadi Drape Cake' WHERE name = 'Wedding Drape Elegance';
UPDATE products SET name = 'Minion Party Cake' WHERE name = 'White Pearl Wedding Tier';
UPDATE products SET name = 'Smartphone Birthday Cake' WHERE name = 'Black Forest Supreme';
UPDATE products SET name = 'Welcome Baby Cupcake Ring' WHERE name = 'Assorted Floral Cupcakes';
UPDATE products SET name = 'Assorted Glazed Donuts' WHERE name = 'Lemon Zest Cupcakes';
UPDATE products SET name = 'Gulab Jamun Party Platter' WHERE name = 'Party Confetti Cupcakes';

-- Categories corrected
UPDATE products SET category_id = 1 WHERE name = 'Royal Red Nikkah Cake';
UPDATE products SET category_id = 1 WHERE name = 'Spring Blossom Swirl Cake';
UPDATE products SET category_id = 1 WHERE name = 'The Royal Bride-to-Be Cake';
UPDATE products SET category_id = 1 WHERE name = 'Elegant White Pearl Cake';
UPDATE products SET category_id = 1 WHERE name = '"All of Me" Anniversary Cake';
UPDATE products SET category_id = 1 WHERE name = 'Golden Brushstroke Elegance';
UPDATE products SET category_id = 1 WHERE name = 'Golden Rose Tier Cake';
UPDATE products SET category_id = 1 WHERE name = 'Elegant White Rose Cake';
UPDATE products SET category_id = 1 WHERE name = 'Vanilla Gold Elegance';
UPDATE products SET category_id = 2 WHERE name = 'Orange & Green Dholki Cake';
UPDATE products SET category_id = 2 WHERE name = 'Mayoon Colour Block Cake';
UPDATE products SET category_id = 2 WHERE name = 'Pink Draped Dholki Cake';
UPDATE products SET category_id = 2 WHERE name = 'Traditional Mayoon Dholki';
UPDATE products SET category_id = 2 WHERE name = 'Royal Blue Sangeet Dhol';
UPDATE products SET category_id = 2 WHERE name = 'Golden Bride Mayoon Cake';
UPDATE products SET category_id = 2 WHERE name = 'Teal & Gold Shadi Drape Cake';
UPDATE products SET category_id = 4 WHERE name = 'PUBG Battleground Cake';
UPDATE products SET category_id = 4 WHERE name = 'Spidey Action Web Cake';
UPDATE products SET category_id = 4 WHERE name = 'The Snooker Champion Cake';
UPDATE products SET category_id = 4 WHERE name = 'Blue Sapphire Memories Cake';
UPDATE products SET category_id = 4 WHERE name = 'Vanilla Confetti Celebration';
UPDATE products SET category_id = 4 WHERE name = 'CoComelon Fun Cake';
UPDATE products SET category_id = 4 WHERE name = 'Minion Party Cake';
UPDATE products SET category_id = 4 WHERE name = 'Smartphone Birthday Cake';
UPDATE products SET category_id = 3 WHERE name = 'Purple Princess Gown';
UPDATE products SET category_id = 3 WHERE name = 'Strawberry Rose Dream';
UPDATE products SET category_id = 3 WHERE name = 'Magical Unicorn Fantasy';
UPDATE products SET category_id = 3 WHERE name = 'Sparkling Unicorn Tier';
UPDATE products SET category_id = 3 WHERE name = 'Vanilla Rose Swirl';
UPDATE products SET category_id = 3 WHERE name = 'Enchanted Fairy Doll';
UPDATE products SET category_id = 3 WHERE name = 'Red Rose Vanilla Classic';
UPDATE products SET category_id = 3 WHERE name = 'Pink Star Fairy Cake';
UPDATE products SET category_id = 3 WHERE name = 'Little Fairy Princess';
UPDATE products SET category_id = 3 WHERE name = 'Royal Barbie Ballgown';
UPDATE products SET category_id = 3 WHERE name = 'Pink & Gold Butterfly Cake';
UPDATE products SET category_id = 3 WHERE name = 'Candy Land Drip Cake';
UPDATE products SET category_id = 3 WHERE name = 'Pastel Garden Cake';
UPDATE products SET category_id = 3 WHERE name = 'Floral Vanilla Bloom';
UPDATE products SET category_id = 3 WHERE name = 'Vanilla Strawberry Duo';
UPDATE products SET category_id = 5 WHERE name = 'Ultimate Chocolate Fantasy';
UPDATE products SET category_id = 5 WHERE name = 'Classic Vanilla Birthday';
UPDATE products SET category_id = 5 WHERE name = 'Oreo Cream Delight';
UPDATE products SET category_id = 5 WHERE name = 'Mango Citrus Swirl';
UPDATE products SET category_id = 5 WHERE name = 'Midnight Dark Chocolate';
UPDATE products SET category_id = 5 WHERE name = 'Chocolate Loaded Bar Cake';
UPDATE products SET category_id = 5 WHERE name = 'Simple Vanilla Joy';
UPDATE products SET category_id = 5 WHERE name = 'Rich Dark Fudge Cake';
UPDATE products SET category_id = 5 WHERE name = 'Black Forest Pastry Cake';
UPDATE products SET category_id = 5 WHERE name = 'Chocolate Floral Wreath';
UPDATE products SET category_id = 5 WHERE name = 'Choco-Berry Delight';
UPDATE products SET category_id = 5 WHERE name = 'Dark Brownie Fudge';
UPDATE products SET category_id = 5 WHERE name = 'Blue Rose Harmony';
UPDATE products SET category_id = 5 WHERE name = 'Influencer\'s Birthday Delight';
UPDATE products SET category_id = 5 WHERE name = 'Purple & White Bento Combo';
UPDATE products SET category_id = 5 WHERE name = 'Crimson Love Birthday Heart';
UPDATE products SET category_id = 5 WHERE name = 'Blue Velvet Love Cake';
UPDATE products SET category_id = 5 WHERE name = 'Hearts in Motion Cake';
UPDATE products SET category_id = 5 WHERE name = 'Teacher\'s Day Special Cake';
UPDATE products SET category_id = 5 WHERE name = 'Mother\'s Day Special Cake';
UPDATE products SET category_id = 6 WHERE name = 'Green Eid Mubarak Drip';
UPDATE products SET category_id = 6 WHERE name = 'Chand Raat Special';
UPDATE products SET category_id = 6 WHERE name = 'Eid Mubarak Gold Cake';
UPDATE products SET category_id = 7 WHERE name = 'Baby Boss Blue Cake';
UPDATE products SET category_id = 7 WHERE name = 'Cloud White Baby Shower';
UPDATE products SET category_id = 7 WHERE name = 'Blue Bow Half Birthday';
UPDATE products SET category_id = 7 WHERE name = 'Sky High Adventures Cake';
UPDATE products SET category_id = 7 WHERE name = 'Welcome Baby Cupcake Ring';
UPDATE products SET category_id = 7 WHERE name = 'Pink & Gold Ball Theme Cake';
UPDATE products SET category_id = 8 WHERE name = 'Emerald Elegance Box';
UPDATE products SET category_id = 8 WHERE name = 'Strawberry Swirl Cupcakes';
UPDATE products SET category_id = 8 WHERE name = 'Gourmet Choco Cupcakes';
UPDATE products SET category_id = 8 WHERE name = 'Vanilla Sprinkles Cupcakes';
UPDATE products SET category_id = 8 WHERE name = 'Strawberry Cupcakes';
UPDATE products SET category_id = 8 WHERE name = 'Blueberry Burst Cupcakes';
UPDATE products SET category_id = 8 WHERE name = 'Choco-Fudge Cupcakes';
UPDATE products SET category_id = 8 WHERE name = 'Assorted Glazed Donuts';
UPDATE products SET category_id = 8 WHERE name = 'Gulab Jamun Party Platter';

-- The site writes this name with quotation marks; keep the DB identical so
-- name-based lookups and the admin panel show the same thing.
UPDATE products SET name = '\"All of Me\" Anniversary Cake', category_id = 1 WHERE id = 10;


