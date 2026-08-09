-- /database/migrations/007_shared_catalog.sql

ALTER TABLE products
    DROP INDEX uq_products_owner_source,
    ADD UNIQUE KEY uq_products_source (
        source_type,
        source_identifier
    );

ALTER TABLE recipes
    DROP INDEX uq_recipes_owner_source,
    ADD UNIQUE KEY uq_recipes_source (
        source_identifier
    );

CREATE INDEX idx_products_archived_name
    ON products (is_archived, name);

CREATE INDEX idx_recipes_archived_updated
    ON recipes (is_archived, updated_at);
