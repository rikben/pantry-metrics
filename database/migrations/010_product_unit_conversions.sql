-- /database/migrations/010_product_unit_conversions.sql
--
-- Reusable, per-product culinary-unit conversions (e.g. "1 tbsp of tomato
-- puree = 15 g"). Previously this was only ever captured as a one-off
-- value on a single recipe_source_ingredients row and had to be
-- re-entered for every recipe. Setting it once here on the product makes
-- it available automatically the next time that unit is used, for any
-- recipe and any user (conversions live on the shared product record).
--
-- `reference_amount` is expressed in the product's own reference_unit
-- (products.reference_unit): how much of that unit equals 1 of `unit`.

CREATE TABLE IF NOT EXISTS product_unit_conversions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    unit VARCHAR(20) NOT NULL,
    reference_amount DECIMAL(10,3) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_unit_conversions_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE CASCADE,
    UNIQUE KEY uq_product_unit_conversions (product_id, unit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
