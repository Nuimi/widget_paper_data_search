-- Run against the existing application database. No new database service is needed.
CREATE TABLE IF NOT EXISTS `tLookupCache` (
    `cacheKey` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `expiresAt` BIGINT NOT NULL,
    PRIMARY KEY (`cacheKey`),
    KEY `ix_tLookupCache_expiry` (`expiresAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
