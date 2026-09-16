-- /database/migrations/009_recipe_categories.sql
--
-- Recipe categories (breakfast/lunch/dinner/...). A recipe can belong to
-- more than one category. The category list itself lives in a table
-- rather than an ENUM so new categories can be added without a schema
-- change (see CategoryRepository).

CREATE TABLE IF NOT EXISTS categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL,
    name VARCHAR(50) NOT NULL,
    position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_categories (
    recipe_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (recipe_id, category_id),
    CONSTRAINT fk_recipe_categories_recipe
        FOREIGN KEY (recipe_id) REFERENCES recipes(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_recipe_categories_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE CASCADE,
    KEY idx_recipe_categories_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (slug, name, position) VALUES
    ('breakfast', 'Breakfast', 1),
    ('lunch', 'Lunch', 2),
    ('dinner', 'Dinner', 3),
    ('snack', 'Snack', 4),
    ('dessert', 'Dessert', 5)
ON DUPLICATE KEY UPDATE name = VALUES(name);
