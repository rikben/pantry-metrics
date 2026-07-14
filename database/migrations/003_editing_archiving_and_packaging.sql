-- /database/migrations/003_editing_archiving_and_packaging.sql
ALTER TABLE products
    ADD COLUMN package_amount DECIMAL(10,3) NULL AFTER source_url,
    ADD COLUMN package_unit VARCHAR(20) NULL AFTER package_amount,
    ADD COLUMN package_description VARCHAR(100) NULL AFTER package_unit,
    ADD COLUMN is_archived BOOLEAN NOT NULL DEFAULT FALSE AFTER salt_g,
    ADD KEY idx_products_owner_archived_name (owner_user_id, is_archived, name);
