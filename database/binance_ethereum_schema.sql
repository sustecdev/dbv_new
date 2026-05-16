-- Binance Smart Chain Tables
CREATE TABLE IF NOT EXISTS `binance_deposit` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `uid` INT(11) UNSIGNED NOT NULL,
    `txn_hash_bsc` VARCHAR(66) NOT NULL,
    `txn_hash_yemchain` VARCHAR(255) DEFAULT NULL,
    `amount` DECIMAL(20,2) NOT NULL,
    `status` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `txn_hash_bsc` (`txn_hash_bsc`),
    KEY `uid_created` (`uid`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `binance_withdraw` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `uid` INT(11) UNSIGNED NOT NULL,
    `address` VARCHAR(42) NOT NULL,
    `amount` DECIMAL(20,2) NOT NULL,
    `txn_hash_bsc` VARCHAR(66) DEFAULT NULL,
    `txn_hash_yemchain` VARCHAR(255) DEFAULT NULL,
    `status` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `processed_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `uid_created` (`uid`, `created_at`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ethereum Tables
CREATE TABLE IF NOT EXISTS `ethereum_deposit` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `uid` INT(11) UNSIGNED NOT NULL,
    `txn_hash_eth` VARCHAR(66) NOT NULL,
    `txn_hash_yemchain` VARCHAR(255) DEFAULT NULL,
    `amount` DECIMAL(20,2) NOT NULL,
    `status` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `txn_hash_eth` (`txn_hash_eth`),
    KEY `uid_created` (`uid`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ethereum_withdraw` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `uid` INT(11) UNSIGNED NOT NULL,
    `address` VARCHAR(42) NOT NULL,
    `amount` DECIMAL(20,2) NOT NULL,
    `txn_hash_eth` VARCHAR(66) DEFAULT NULL,
    `txn_hash_yemchain` VARCHAR(255) DEFAULT NULL,
    `status` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `processed_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `uid_created` (`uid`, `created_at`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

