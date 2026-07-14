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
