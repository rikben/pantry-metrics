-- /database/migrations/008_shared_catalog_fix.sql
--
-- Migration 007 made AH-imported products and recipes globally unique by
-- source identifier (instead of unique per owner), which was the first
-- step towards a shared catalog. This migration finishes that job:
--
-- - `owner_user_id` becomes nullable on recipes, matching products.
-- - Existing AH-imported rows (anything with a source identifier /
--   non-manual source) are converted into shared catalog entries by
--   clearing their owner. Manually created products/recipes keep their
--   owner and stay private.
--
-- A NULL owner_user_id means "shared catalog entry, visible and editable
-- by every signed-in user" (see ProductRepository/RecipeRepository).

ALTER TABLE recipes
    MODIFY COLUMN owner_user_id BIGINT UNSIGNED NULL;

UPDATE products
    SET owner_user_id = NULL
    WHERE source_type <> 'manual';

UPDATE recipes
    SET owner_user_id = NULL
    WHERE source_identifier IS NOT NULL;
