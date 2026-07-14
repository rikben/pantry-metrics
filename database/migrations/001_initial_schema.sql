-- /database/migrations/001_initial_schema.sql
CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(255) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    external_subject VARCHAR(191) NULL,
    identity_provider VARCHAR(191) NULL,
    email VARCHAR(254) NOT NULL,
    display_name VARCHAR(191) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_external_identity (identity_provider, external_subject)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NULL,
    name VARCHAR(191) NOT NULL,
    brand VARCHAR(191) NULL,
    source_type ENUM('manual', 'ah', 'other') NOT NULL DEFAULT 'manual',
    source_identifier VARCHAR(191) NULL,
    source_url TEXT NULL,
    reference_amount DECIMAL(10,3) NOT NULL DEFAULT 100.000,
    reference_unit ENUM('g', 'ml', 'serving') NOT NULL DEFAULT 'g',
    energy_kj DECIMAL(10,3) NOT NULL DEFAULT 0,
    energy_kcal DECIMAL(10,3) NOT NULL DEFAULT 0,
    fat_g DECIMAL(10,3) NOT NULL DEFAULT 0,
    saturated_fat_g DECIMAL(10,3) NOT NULL DEFAULT 0,
    carbohydrates_g DECIMAL(10,3) NOT NULL DEFAULT 0,
    sugars_g DECIMAL(10,3) NOT NULL DEFAULT 0,
    fiber_g DECIMAL(10,3) NOT NULL DEFAULT 0,
    protein_g DECIMAL(10,3) NOT NULL DEFAULT 0,
    salt_g DECIMAL(10,3) NOT NULL DEFAULT 0,
    source_checked_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_owner
        FOREIGN KEY (owner_user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    UNIQUE KEY uq_products_source (source_type, source_identifier),
    KEY idx_products_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(191) NOT NULL,
    description TEXT NULL,
    source_url TEXT NULL,
    servings DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    is_archived BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_recipes_owner
        FOREIGN KEY (owner_user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    KEY idx_recipes_owner_name (owner_user_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_ingredients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipe_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    original_description VARCHAR(255) NULL,
    amount DECIMAL(10,3) NOT NULL,
    unit ENUM('g', 'ml', 'serving') NOT NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_recipe_ingredients_recipe
        FOREIGN KEY (recipe_id) REFERENCES recipes(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_recipe_ingredients_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE RESTRICT,
    KEY idx_recipe_ingredients_recipe_position (recipe_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (email, display_name, role)
VALUES ('developer@example.test', 'Local Developer', 'admin')
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name);

INSERT INTO products (
    owner_user_id, name, brand, reference_amount, reference_unit,
    energy_kj, energy_kcal, fat_g, saturated_fat_g,
    carbohydrates_g, sugars_g, fiber_g, protein_g, salt_g
)
SELECT
    u.id, 'Greek yoghurt 0%', 'Example', 100, 'g',
    245, 58, 0.2, 0.1, 3.6, 3.6, 0, 10.3, 0.10
FROM users u
WHERE u.email = 'developer@example.test'
  AND NOT EXISTS (
      SELECT 1 FROM products WHERE name = 'Greek yoghurt 0%' AND brand = 'Example'
  );

INSERT INTO products (
    owner_user_id, name, brand, reference_amount, reference_unit,
    energy_kj, energy_kcal, fat_g, saturated_fat_g,
    carbohydrates_g, sugars_g, fiber_g, protein_g, salt_g
)
SELECT
    u.id, 'Rolled oats', 'Example', 100, 'g',
    1570, 372, 7.0, 1.3, 59.0, 1.0, 10.0, 13.0, 0.01
FROM users u
WHERE u.email = 'developer@example.test'
  AND NOT EXISTS (
      SELECT 1 FROM products WHERE name = 'Rolled oats' AND brand = 'Example'
  );
