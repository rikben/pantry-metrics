ALTER TABLE recipe_source_ingredients
    ADD COLUMN converted_amount DECIMAL(10,3) NULL AFTER parsed_unit,
    ADD COLUMN converted_unit VARCHAR(20) NULL AFTER converted_amount;
