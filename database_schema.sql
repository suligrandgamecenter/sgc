-- =====================================================
-- SULYGRAND GAME CENTER - SQL DATABASE SCHEMA
-- =====================================================

-- Drop tables if exists (in correct order)
DROP TABLE IF EXISTS cafe_sale_items;
DROP TABLE IF EXISTS cafe_sales;
DROP TABLE IF EXISTS debts;
DROP TABLE IF EXISTS game_food_orders;
DROP TABLE IF EXISTS game_sessions;
DROP TABLE IF EXISTS food_menu;
DROP TABLE IF EXISTS income_daily;
DROP TABLE IF EXISTS income_monthly;

-- =====================================================
-- FOOD MENU TABLE
-- =====================================================
CREATE TABLE food_menu (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- GAME SESSIONS TABLE (PC & PS Stations)
-- =====================================================
CREATE TABLE game_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    station_type ENUM('PC', 'PS') NOT NULL,
    station_number INT NOT NULL,
    is_vip BOOLEAN DEFAULT FALSE,
    price_per_hour DECIMAL(10, 2) NOT NULL,
    start_time TIMESTAMP NULL,
    elapsed_time BIGINT DEFAULT 0,
    is_running BOOLEAN DEFAULT FALSE,
    total_cost DECIMAL(10, 2) DEFAULT 0,
    food_cost DECIMAL(10, 2) DEFAULT 0,
    paid_amount DECIMAL(10, 2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_station (station_type, station_number)
);

-- =====================================================
-- GAME FOOD ORDERS TABLE
-- =====================================================
CREATE TABLE game_food_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    food_name VARCHAR(255) NOT NULL,
    food_price DECIMAL(10, 2) NOT NULL,
    ordered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES game_sessions(id) ON DELETE CASCADE
);

-- =====================================================
-- DEBTS TABLE
-- =====================================================
CREATE TABLE debts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    debtor_name VARCHAR(255) NOT NULL,
    debtor_name_normalized VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_debtor (debtor_name_normalized)
);

-- =====================================================
-- CAFE SALES HISTORY TABLE
-- =====================================================
CREATE TABLE cafe_sales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sale_type ENUM('cash', 'debt') NOT NULL,
    debtor_name VARCHAR(255),
    total_amount DECIMAL(10, 2) NOT NULL,
    is_settled BOOLEAN DEFAULT FALSE,
    sale_date DATE NOT NULL,
    sale_time TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- CAFE SALE ITEMS TABLE
-- =====================================================
CREATE TABLE cafe_sale_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL,
    food_name VARCHAR(255) NOT NULL,
    food_price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES cafe_sales(id) ON DELETE CASCADE
);

-- =====================================================
-- INCOME TABLES
-- =====================================================
CREATE TABLE income_daily (
    id INT PRIMARY KEY AUTO_INCREMENT,
    income_type ENUM('game', 'cafe') NOT NULL,
    record_date DATE NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_daily_income (income_type, record_date)
);

CREATE TABLE income_monthly (
    id INT PRIMARY KEY AUTO_INCREMENT,
    income_type ENUM('game', 'cafe') NOT NULL,
    record_year INT NOT NULL,
    record_month INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_monthly_income (income_type, record_year, record_month)
);

-- =====================================================
-- INSERT SAMPLE DATA
-- =====================================================

-- Insert Food Menu Items
INSERT INTO food_menu (name, price) VALUES 
('چاوەڕێمەوە', 2000),
('سوودا', 2500),
('کۆکاکۆلا', 1500),
('ئاو', 500),
('قاوە', 2000),
('چای', 1500);

-- PC Stations: 20 total, 8 VIP (PC 1-8 are VIP)
-- Regular PC: 3000 IQD/hour, VIP PC: 5000 IQD/hour
INSERT INTO game_sessions (station_type, station_number, is_vip, price_per_hour) VALUES 
-- VIP PCs (1-8)
('PC', 1, TRUE, 5000),
('PC', 2, TRUE, 5000),
('PC', 3, TRUE, 5000),
('PC', 4, TRUE, 5000),
('PC', 5, TRUE, 5000),
('PC', 6, TRUE, 5000),
('PC', 7, TRUE, 5000),
('PC', 8, TRUE, 5000),
-- Regular PCs (9-20)
('PC', 9, FALSE, 3000),
('PC', 10, FALSE, 3000),
('PC', 11, FALSE, 3000),
('PC', 12, FALSE, 3000),
('PC', 13, FALSE, 3000),
('PC', 14, FALSE, 3000),
('PC', 15, FALSE, 3000),
('PC', 16, FALSE, 3000),
('PC', 17, FALSE, 3000),
('PC', 18, FALSE, 3000),
('PC', 19, FALSE, 3000),
('PC', 20, FALSE, 3000);

-- PS Stations: 11 total, 1 VIP (PS 1 is VIP)
-- Regular PS: 4500 IQD/hour, VIP PS: 7000 IQD/hour
INSERT INTO game_sessions (station_type, station_number, is_vip, price_per_hour) VALUES 
-- VIP PS (1)
('PS', 1, TRUE, 7000),
-- Regular PS (2-11)
('PS', 2, FALSE, 4500),
('PS', 3, FALSE, 4500),
('PS', 4, FALSE, 4500),
('PS', 5, FALSE, 4500),
('PS', 6, FALSE, 4500),
('PS', 7, FALSE, 4500),
('PS', 8, FALSE, 4500),
('PS', 9, FALSE, 4500),
('PS', 10, FALSE, 4500),
('PS', 11, FALSE, 4500);

-- =====================================================
-- STORED PROCEDURES
-- =====================================================

DELIMITER //

-- Update daily income
CREATE PROCEDURE update_daily_income(IN p_type VARCHAR(10), IN p_date DATE, IN p_amount DECIMAL(10,2))
BEGIN
    INSERT INTO income_daily (income_type, record_date, amount)
    VALUES (p_type, p_date, p_amount)
    ON DUPLICATE KEY UPDATE amount = amount + p_amount;
END //

-- Update monthly income
CREATE PROCEDURE update_monthly_income(IN p_type VARCHAR(10), IN p_year INT, IN p_month INT, IN p_amount DECIMAL(10,2))
BEGIN
    INSERT INTO income_monthly (income_type, record_year, record_month, amount)
    VALUES (p_type, p_year, p_month, p_amount)
    ON DUPLICATE KEY UPDATE amount = amount + p_amount;
END //

-- Start game session
CREATE PROCEDURE start_game_session(IN p_type VARCHAR(5), IN p_station INT)
BEGIN
    UPDATE game_sessions 
    SET start_time = NOW(), is_running = TRUE
    WHERE station_type = p_type AND station_number = p_station;
END //

-- Pause game session
CREATE PROCEDURE pause_game_session(IN p_type VARCHAR(5), IN p_station INT)
BEGIN
    UPDATE game_sessions 
    SET elapsed_time = elapsed_time + TIMESTAMPDIFF(SECOND, start_time, NOW()),
        is_running = FALSE,
        start_time = NULL
    WHERE station_type = p_type AND station_number = p_station;
END //

-- Reset game session
CREATE PROCEDURE reset_game_session(IN p_type VARCHAR(5), IN p_station INT)
BEGIN
    DELETE FROM game_food_orders WHERE session_id = (
        SELECT id FROM game_sessions WHERE station_type = p_type AND station_number = p_station
    );
    UPDATE game_sessions 
    SET start_time = NULL, elapsed_time = 0, is_running = FALSE, 
        total_cost = 0, food_cost = 0, paid_amount = 0
    WHERE station_type = p_type AND station_number = p_station;
END //

-- Get game session cost
CREATE PROCEDURE get_game_cost(IN p_type VARCHAR(5), IN p_station INT)
BEGIN
    SELECT 
        station_number,
        is_vip,
        price_per_hour,
        elapsed_time,
        is_running,
        start_time,
        food_cost,
        CASE 
            WHEN is_running = TRUE AND start_time IS NOT NULL THEN
                FLOOR(((elapsed_time + TIMESTAMPDIFF(SECOND, start_time, NOW())) / 3600.0) * price_per_hour)
            ELSE
                FLOOR((elapsed_time / 3600.0) * price_per_hour)
        END as current_game_cost
    FROM game_sessions 
    WHERE station_type = p_type AND station_number = p_station;
END //

DELIMITER ;

-- =====================================================
-- VIEWS FOR REPORTS
-- =====================================================

-- Daily Game Income View
CREATE OR REPLACE VIEW view_daily_game_income AS
SELECT record_date, amount FROM income_daily 
WHERE income_type = 'game' ORDER BY record_date DESC;

-- Monthly Game Income View
CREATE OR REPLACE VIEW view_monthly_game_income AS
SELECT record_year, record_month, amount FROM income_monthly 
WHERE income_type = 'game' ORDER BY record_year DESC, record_month DESC;

-- Daily Cafe Income View
CREATE OR REPLACE VIEW view_daily_cafe_income AS
SELECT record_date, amount FROM income_daily 
WHERE income_type = 'cafe' ORDER BY record_date DESC;

-- Monthly Cafe Income View
CREATE OR REPLACE VIEW view_monthly_cafe_income AS
SELECT record_year, record_month, amount FROM income_monthly 
WHERE income_type = 'cafe' ORDER BY record_year DESC, record_month DESC;

-- Active Game Sessions View
CREATE OR REPLACE VIEW view_active_game_sessions AS
SELECT station_type, station_number, is_vip, price_per_hour, start_time, elapsed_time, 
       total_cost, food_cost FROM game_sessions 
WHERE is_running = TRUE OR elapsed_time > 0;

-- VIP Game Sessions View
CREATE OR REPLACE VIEW view_vip_sessions AS
SELECT station_type, station_number, price_per_hour, start_time, elapsed_time, is_running
FROM game_sessions 
WHERE is_vip = TRUE;

-- Outstanding Debts View
CREATE OR REPLACE VIEW view_outstanding_debts AS
SELECT debtor_name, amount, created_at FROM debts 
ORDER BY created_at DESC;

-- Cafe Sales History View
CREATE OR REPLACE VIEW view_cafe_sales_history AS
SELECT cs.id, cs.sale_type, cs.debtor_name, cs.total_amount, 
       cs.sale_date, cs.sale_time, cs.is_settled,
       GROUP_CONCAT(CONCAT(csi.food_name, ' (', csi.food_price, ')') SEPARATOR ', ') as items
FROM cafe_sales cs
LEFT JOIN cafe_sale_items csi ON cs.id = csi.sale_id
GROUP BY cs.id
ORDER BY cs.created_at DESC;

-- Station Summary (Shows all stations with their status)
CREATE OR REPLACE VIEW view_station_summary AS
SELECT 
    station_type,
    station_number,
    is_vip,
    price_per_hour,
    is_running,
    elapsed_time,
    start_time,
    food_cost,
    CASE 
        WHEN is_running = TRUE AND start_time IS NOT NULL THEN
            FLOOR(((elapsed_time + TIMESTAMPDIFF(SECOND, start_time, NOW())) / 3600.0) * price_per_hour)
        ELSE
            FLOOR((elapsed_time / 3600.0) * price_per_hour)
    END as current_cost
FROM game_sessions
ORDER BY station_type, station_number;