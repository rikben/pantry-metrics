-- /database/migrations/004_ajax_ingredients_and_images.sql
ALTER TABLE products
    ADD COLUMN image_path VARCHAR(255) NULL AFTER package_description,
    ADD COLUMN image_source_url TEXT NULL AFTER image_path;

ALTER TABLE recipes
    ADD COLUMN image_path VARCHAR(255) NULL AFTER source_url,
    ADD COLUMN image_source_url TEXT NULL AFTER image_path;
