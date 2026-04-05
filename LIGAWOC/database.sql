-- =============================================
-- Liga WOC - Mobile Legends Tournament Platform
-- Database Schema
-- =============================================

CREATE DATABASE IF NOT EXISTS ligawoc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ligawoc;

-- =============================================
-- USERS & AUTHENTICATION
-- =============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('player','designer','admin','superadmin') DEFAULT 'player',
    cover VARCHAR(255) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    ml_id VARCHAR(50) DEFAULT NULL,
    ml_server VARCHAR(20) DEFAULT NULL,
    ml_nickname VARCHAR(50) DEFAULT NULL,
    main_hero VARCHAR(50) DEFAULT NULL,
    current_rank VARCHAR(30) DEFAULT NULL,
    whatsapp VARCHAR(30) DEFAULT NULL,
    phone_brand VARCHAR(100) DEFAULT NULL,
    discord VARCHAR(100) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    is_verified TINYINT(1) DEFAULT 0,
    verification_token VARCHAR(100) DEFAULT NULL,
    reset_token VARCHAR(100) DEFAULT NULL,
    reset_expires DATETIME DEFAULT NULL,
    is_banned TINYINT(1) DEFAULT 0,
    ban_reason VARCHAR(255) DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- EMAIL CHANGE CODES
-- =============================================
CREATE TABLE email_change_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    new_email VARCHAR(100) NOT NULL,
    code VARCHAR(6) NOT NULL,
    used TINYINT(1) DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_email (user_id, new_email, used)
) ENGINE=InnoDB;

-- =============================================
-- NEWS
-- =============================================
CREATE TABLE news_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#7C3AED',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    content TEXT NOT NULL,
    excerpt VARCHAR(500) DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    category_id INT DEFAULT NULL,
    author_id INT NOT NULL,
    is_published TINYINT(1) DEFAULT 0,
    is_featured TINYINT(1) DEFAULT 0,
    publish_date DATETIME DEFAULT NULL,
    views INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES news_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- TEAMS
-- =============================================
CREATE TABLE teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    tag VARCHAR(10) DEFAULT NULL,
    logo VARCHAR(255) DEFAULT NULL,
    banner VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    region VARCHAR(50) DEFAULT NULL,
    captain_id INT NOT NULL,
    max_members INT DEFAULT 7,
    is_active TINYINT(1) DEFAULT 1,
    is_verified TINYINT(1) DEFAULT 0,
    wins INT DEFAULT 0,
    losses INT DEFAULT 0,
    points INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (captain_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    ml_id VARCHAR(50) NOT NULL,
    ml_server VARCHAR(20) NOT NULL,
    ml_nickname VARCHAR(50) NOT NULL,
    nickname VARCHAR(50) DEFAULT NULL,
    role ENUM('adc','mage','tank','assassin','fighter','support') NOT NULL,
    lane_1 ENUM('gold','mid','exp','roam','jungle') NOT NULL,
    lane_2 ENUM('gold','mid','exp','roam','jungle') DEFAULT NULL,
    main_hero VARCHAR(50) NOT NULL,
    current_rank VARCHAR(30) DEFAULT NULL,
    is_captain TINYINT(1) DEFAULT 0,
    is_starter TINYINT(1) DEFAULT 1,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_team (user_id),
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE team_join_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    ml_id VARCHAR(50) NOT NULL,
    ml_server VARCHAR(20) NOT NULL,
    ml_nickname VARCHAR(50) NOT NULL,
    nickname VARCHAR(50) DEFAULT NULL,
    role ENUM('adc','mage','tank','assassin','fighter','support') NOT NULL,
    lane_1 ENUM('gold','mid','exp','roam','jungle') NOT NULL,
    lane_2 ENUM('gold','mid','exp','roam','jungle') DEFAULT NULL,
    main_hero VARCHAR(50) NOT NULL,
    current_rank VARCHAR(30) DEFAULT NULL,
    message TEXT DEFAULT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    reviewed_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- SEASONS
-- =============================================
CREATE TABLE seasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 0,
    description TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- TOURNAMENTS
-- =============================================
CREATE TABLE tournaments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    rules TEXT DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    season_id INT DEFAULT NULL,
    format ENUM('single_elimination','double_elimination','group_stage','round_robin') NOT NULL,
    team_size INT DEFAULT 5,
    max_teams INT DEFAULT 16,
    min_rank VARCHAR(30) DEFAULT NULL,
    entry_fee DECIMAL(10,2) DEFAULT 0.00,
    prize_pool VARCHAR(500) DEFAULT NULL,
    status ENUM('draft','registration','ready','in_progress','completed','cancelled') DEFAULT 'draft',
    registration_start DATETIME DEFAULT NULL,
    registration_end DATETIME DEFAULT NULL,
    start_date DATETIME DEFAULT NULL,
    end_date DATETIME DEFAULT NULL,
    stream_url VARCHAR(500) DEFAULT NULL,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tournament_teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    team_id INT NOT NULL,
    seed INT DEFAULT NULL,
    group_name VARCHAR(10) DEFAULT NULL,
    status ENUM('registered','confirmed','eliminated','winner','disqualified') DEFAULT 'registered',
    payment_status ENUM('pending','paid','waived') DEFAULT 'pending',
    registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_tournament_team (tournament_id, team_id),
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tournament_matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    round INT NOT NULL DEFAULT 1,
    match_number INT NOT NULL,
    bracket_type ENUM('winners','losers','finals','group','round_robin') DEFAULT 'winners',
    group_name VARCHAR(10) DEFAULT NULL,
    team1_id INT DEFAULT NULL,
    team2_id INT DEFAULT NULL,
    winner_id INT DEFAULT NULL,
    loser_id INT DEFAULT NULL,
    team1_score INT DEFAULT 0,
    team2_score INT DEFAULT 0,
    best_of INT DEFAULT 1,
    room_id VARCHAR(50) DEFAULT NULL,
    room_password VARCHAR(50) DEFAULT NULL,
    scheduled_at DATETIME DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    status ENUM('pending','scheduled','live','completed','disputed','cancelled') DEFAULT 'pending',
    next_match_id INT DEFAULT NULL,
    referee_id INT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (team1_id) REFERENCES teams(id) ON DELETE SET NULL,
    FOREIGN KEY (team2_id) REFERENCES teams(id) ON DELETE SET NULL,
    FOREIGN KEY (winner_id) REFERENCES teams(id) ON DELETE SET NULL,
    FOREIGN KEY (referee_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE match_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    game_number INT DEFAULT 1,
    team1_score INT DEFAULT 0,
    team2_score INT DEFAULT 0,
    team1_kills INT DEFAULT 0,
    team2_kills INT DEFAULT 0,
    team1_towers INT DEFAULT 0,
    team2_towers INT DEFAULT 0,
    mvp_user_id INT DEFAULT NULL,
    screenshot VARCHAR(255) DEFAULT NULL,
    submitted_by INT DEFAULT NULL,
    verified_by INT DEFAULT NULL,
    verification_status ENUM('pending','verified','disputed') DEFAULT 'pending',
    notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES tournament_matches(id) ON DELETE CASCADE,
    FOREIGN KEY (mvp_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE match_player_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    user_id INT NOT NULL,
    team_id INT NOT NULL,
    hero_used VARCHAR(100) DEFAULT NULL,
    kills INT DEFAULT 0,
    deaths INT DEFAULT 0,
    assists INT DEFAULT 0,
    gold_earned INT DEFAULT 0,
    mvp_score DECIMAL(4,2) DEFAULT 0.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES tournament_matches(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- RANKINGS
-- =============================================
CREATE TABLE team_rankings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    season_id INT DEFAULT NULL,
    wins INT DEFAULT 0,
    losses INT DEFAULT 0,
    draws INT DEFAULT 0,
    points INT DEFAULT 0,
    kills_diff INT DEFAULT 0,
    towers_diff INT DEFAULT 0,
    tournaments_played INT DEFAULT 0,
    tournaments_won INT DEFAULT 0,
    matches_played INT DEFAULT 0,
    position INT DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_team_season (team_id, season_id),
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE player_rankings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    season_id INT DEFAULT NULL,
    matches_played INT DEFAULT 0,
    matches_won INT DEFAULT 0,
    mvp_count INT DEFAULT 0,
    kills INT DEFAULT 0,
    deaths INT DEFAULT 0,
    assists INT DEFAULT 0,
    points INT DEFAULT 0,
    position INT DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_player_season (user_id, season_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- NOTIFICATIONS
-- =============================================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('match','team','tournament','news','system','prize') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(500) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- STREAMS
-- =============================================
CREATE TABLE streams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    url VARCHAR(500) NOT NULL,
    platform ENUM('youtube','twitch','facebook','other') DEFAULT 'youtube',
    tournament_id INT DEFAULT NULL,
    is_live TINYINT(1) DEFAULT 0,
    scheduled_at DATETIME DEFAULT NULL,
    thumbnail VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- PRIZES
-- =============================================
CREATE TABLE prizes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    position INT NOT NULL,
    prize_type ENUM('money','diamonds','skin','other') NOT NULL,
    description VARCHAR(200) NOT NULL,
    value VARCHAR(100) DEFAULT NULL,
    awarded_to_team_id INT DEFAULT NULL,
    is_delivered TINYINT(1) DEFAULT 0,
    delivered_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (awarded_to_team_id) REFERENCES teams(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- PLATFORM CONFIG & AUDIT
-- =============================================
CREATE TABLE platform_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id INT DEFAULT NULL,
    old_value TEXT DEFAULT NULL,
    new_value TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- DEFAULT DATA
-- =============================================

-- Default news categories
INSERT INTO news_categories (name, slug, color) VALUES
('General', 'general', '#7C3AED'),
('Torneos', 'torneos', '#9D4EDD'),
('Actualizaciones', 'actualizaciones', '#6B2DB5'),
('Comunidad', 'comunidad', '#B565F0'),
('Parches ML', 'parches-ml', '#C77DFF');

-- Default platform config
INSERT INTO platform_config (config_key, config_value, description) VALUES
('site_name', 'Liga WOC', 'Nombre del sitio'),
('site_description', 'Plataforma de torneos de Mobile Legends', 'Descripción del sitio'),
('points_win', '3', 'Puntos por victoria'),
('points_loss', '0', 'Puntos por derrota'),
('points_draw', '1', 'Puntos por empate'),
('max_team_members', '7', 'Máximo de miembros por equipo'),
('current_season', '1', 'Temporada actual'),
('registration_open', '1', 'Registro abierto');

-- Default season
INSERT INTO seasons (name, start_date, is_active, description) VALUES
('Temporada 1', CURDATE(), 1, 'Primera temporada de Liga WOC');

-- Create default superadmin (password: Admin123!)
INSERT INTO users (username, email, password, role, is_verified) VALUES
('superadmin', 'admin@ligawocdominicana.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', 1);

-- =============================================
-- SHOP & COINS SYSTEM
-- =============================================

-- User coins balance
CREATE TABLE user_coins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    coins INT DEFAULT 0,
    lifetime_coins INT DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- Coin packages for purchase
CREATE TABLE coin_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    coins INT NOT NULL,
    price_usd DECIMAL(10,2) NOT NULL,
    bonus_coins INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default coin packages
INSERT INTO coin_packages (name, coins, price_usd, bonus_coins, sort_order) VALUES
('Paquete Básico', 100, 1.00, 0, 1),
('Paquete Popular', 550, 5.00, 50, 2),
('Paquete Grande', 1200, 10.00, 200, 3),
('Paquete Premium', 2500, 20.00, 500, 4),
('Paquete Legendario', 6500, 50.00, 1500, 5);

-- Coin purchase history
CREATE TABLE coin_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    package_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    coins_purchased INT NOT NULL,
    bonus_coins INT DEFAULT 0,
    payment_method VARCHAR(50) DEFAULT 'paypal',
    transaction_id VARCHAR(100) DEFAULT NULL,
    status ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Item categories (marcos, portadas)
CREATE TABLE item_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    type ENUM('marco','portada','avatar','badge') NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    icon VARCHAR(50) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0
) ENGINE=InnoDB;

-- Default categories
INSERT INTO item_categories (name, slug, type, description, sort_order) VALUES
('Marcos de Perfil', 'marcos', 'marco', 'Marcos decorativos para tu foto de perfil', 1),
('Portadas', 'portadas', 'portada', 'Fondos para tu banner de perfil', 2),
('Avatares', 'avatares', 'avatar', 'Avatares exclusivos', 3),
('Insignias', 'insignias', 'badge', 'Insignias y logros', 4);

-- Shop items
CREATE TABLE shop_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    designer_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    image VARCHAR(255) NOT NULL,
    price_coins INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_featured TINYINT(1) DEFAULT 0,
    total_sales INT DEFAULT 0,
    approved_by INT DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category_id),
    INDEX idx_designer (designer_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- User inventory (purchased items)
CREATE TABLE user_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    purchased_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    equipped_at DATETIME DEFAULT NULL,
    is_equipped TINYINT(1) DEFAULT 0,
    UNIQUE KEY unique_user_item (user_id, item_id),
    INDEX idx_user (user_id),
    INDEX idx_equipped (is_equipped)
) ENGINE=InnoDB;

-- Designer applications
CREATE TABLE designer_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    portfolio TEXT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- PayPal Orders
CREATE TABLE paypal_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    package_id INT NOT NULL,
    paypal_order_id VARCHAR(50) NOT NULL,
    paypal_capture_id VARCHAR(50) DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('created','approved','completed','failed','denied') DEFAULT 'created',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    INDEX idx_user (user_id),
    INDEX idx_paypal_order (paypal_order_id)
) ENGINE=InnoDB;

-- Designer payouts
CREATE TABLE designer_payouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    designer_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','processing','completed','rejected') DEFAULT 'pending',
    payment_method VARCHAR(50) DEFAULT NULL,
    transaction_id VARCHAR(100) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT NULL,
    INDEX idx_designer (designer_id)
) ENGINE=InnoDB;
