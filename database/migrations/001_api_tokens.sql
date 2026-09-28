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
