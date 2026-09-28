CREATE DATABASE IF NOT EXISTS `widget`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `widget`;

CREATE TABLE IF NOT EXISTS `tUser` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `lastSearch` DATETIME NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `permission` VARCHAR(50) NOT NULL,
    `state` INT NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tUser_email` (`email`),
    UNIQUE KEY `uq_tUser_token` (`token`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tSettings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `FK_userID` INT UNSIGNED NOT NULL,
    `settings` TEXT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tSettings_user` (`FK_userID`),
    CONSTRAINT `fk_tSettings_user`
        FOREIGN KEY (`FK_userID`)
        REFERENCES `tUser` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Run against the existing application database before deploying version 1.1.
-- Existing tUser.token values are no longer accepted; extension users must log in again.
CREATE TABLE IF NOT EXISTS `tApiToken` (
    `tokenHash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `FK_userID` INT UNSIGNED NOT NULL,
    `issuedAt` BIGINT NOT NULL,
    `expiresAt` BIGINT NOT NULL,
    `revokedAt` BIGINT NULL,
    PRIMARY KEY (`tokenHash`),
    KEY `ix_tApiToken_user` (`FK_userID`),
    KEY `ix_tApiToken_expiry` (`expiresAt`),
    CONSTRAINT `fk_tApiToken_user` FOREIGN KEY (`FK_userID`)
        REFERENCES `tUser` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Run against the existing application database. No new database service is needed.
CREATE TABLE IF NOT EXISTS `tLookupCache` (
    `cacheKey` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `expiresAt` BIGINT NOT NULL,
    PRIMARY KEY (`cacheKey`),
    KEY `ix_tLookupCache_expiry` (`expiresAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
