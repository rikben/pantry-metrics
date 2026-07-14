-- /database/migrations/005_recipe_import_workflow.sql
ALTER TABLE recipes
    ADD COLUMN instructions LONGTEXT NULL AFTER description,
    ADD COLUMN source_identifier VARCHAR(80) NULL AFTER source_url,
    ADD UNIQUE KEY uq_recipes_owner_source (
        owner_user_id,
        source_identifier
    );

CREATE TABLE recipe_source_ingredients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipe_id BIGINT UNSIGNED NOT NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    raw_text VARCHAR(500) NOT NULL,
    parsed_amount DECIMAL(10,3) NULL,
    parsed_unit VARCHAR(40) NULL,
    parsed_name VARCHAR(300) NULL,
    linked_product_id BIGINT UNSIGNED NULL,
    recipe_ingredient_id BIGINT UNSIGNED NULL,
    is_ignored BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_recipe_source_ingredient_recipe
        FOREIGN KEY (recipe_id) REFERENCES recipes(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_recipe_source_ingredient_product
        FOREIGN KEY (linked_product_id) REFERENCES products(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_recipe_source_ingredient_recipe_ingredient
        FOREIGN KEY (recipe_ingredient_id)
        REFERENCES recipe_ingredients(id)
        ON DELETE SET NULL,
    KEY idx_recipe_source_recipe_position (
        recipe_id,
        position
    )
);
