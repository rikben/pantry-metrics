-- /database/migrations/002_scope_product_sources_to_owner.sql
ALTER TABLE products
    DROP INDEX uq_products_source,
    ADD UNIQUE KEY uq_products_owner_source (owner_user_id, source_type, source_identifier);
