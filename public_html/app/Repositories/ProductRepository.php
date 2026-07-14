<?php
// /public_html/app/Repositories/ProductRepository.php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class ProductRepository
{
    public function allForUser(int $userId, bool $archived = false): array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM products
             WHERE (owner_user_id = :user_id OR owner_user_id IS NULL)
               AND is_archived = :is_archived
             ORDER BY name, brand'
        );
        $statement->execute([
            'user_id' => $userId,
            'is_archived' => $archived ? 1 : 0,
        ]);

        return $statement->fetchAll();
    }

    public function findForUser(int $productId, int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM products
             WHERE id = :id
               AND (owner_user_id = :user_id OR owner_user_id IS NULL)
             LIMIT 1'
        );
        $statement->execute([
            'id' => $productId,
            'user_id' => $userId,
        ]);

        $product = $statement->fetch();
        return $product ?: null;
    }

    public function create(int $userId, array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO products (
                owner_user_id, name, brand, source_type, source_url,
                package_amount, package_unit, package_description,
                reference_amount, reference_unit, energy_kj, energy_kcal,
                fat_g, saturated_fat_g, carbohydrates_g, sugars_g,
                fiber_g, protein_g, salt_g
             ) VALUES (
                :owner_user_id, :name, :brand, :source_type, :source_url,
                :package_amount, :package_unit, :package_description,
                :reference_amount, :reference_unit, :energy_kj, :energy_kcal,
                :fat_g, :saturated_fat_g, :carbohydrates_g, :sugars_g,
                :fiber_g, :protein_g, :salt_g
             )'
        );

        $statement->execute($this->writeParameters($userId, $data, 'manual'));

        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $productId, int $userId, array $data): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE products SET
                name = :name,
                brand = :brand,
                source_url = :source_url,
                package_amount = :package_amount,
                package_unit = :package_unit,
                package_description = :package_description,
                reference_amount = :reference_amount,
                reference_unit = :reference_unit,
                energy_kj = :energy_kj,
                energy_kcal = :energy_kcal,
                fat_g = :fat_g,
                saturated_fat_g = :saturated_fat_g,
                carbohydrates_g = :carbohydrates_g,
                sugars_g = :sugars_g,
                fiber_g = :fiber_g,
                protein_g = :protein_g,
                salt_g = :salt_g
             WHERE id = :id AND owner_user_id = :owner_user_id'
        );

        $parameters = $this->writeParameters($userId, $data, 'manual');
        unset($parameters['source_type']);
        $parameters['id'] = $productId;
        $statement->execute($parameters);
    }

    public function setArchived(int $productId, int $userId, bool $archived): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE products
             SET is_archived = :is_archived
             WHERE id = :id AND owner_user_id = :owner_user_id'
        );
        $statement->execute([
            'id' => $productId,
            'owner_user_id' => $userId,
            'is_archived' => $archived ? 1 : 0,
        ]);
    }

    public function findBySource(int $userId, string $sourceType, string $sourceIdentifier): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM products
             WHERE owner_user_id = :user_id
               AND source_type = :source_type
               AND source_identifier = :source_identifier
             LIMIT 1'
        );
        $statement->execute([
            'user_id' => $userId,
            'source_type' => $sourceType,
            'source_identifier' => $sourceIdentifier,
        ]);

        $product = $statement->fetch();
        return $product ?: null;
    }

    public function upsertImported(int $userId, array $data): int
    {
        $existing = $this->findBySource(
            $userId,
            (string) $data['source_type'],
            (string) $data['source_identifier']
        );

        if ($existing) {
            $statement = Database::connection()->prepare(
                'UPDATE products SET
                    name = :name,
                    brand = :brand,
                    source_url = :source_url,
                    package_amount = :package_amount,
                    package_unit = :package_unit,
                    package_description = :package_description,
                    reference_amount = :reference_amount,
                    reference_unit = :reference_unit,
                    energy_kj = :energy_kj,
                    energy_kcal = :energy_kcal,
                    fat_g = :fat_g,
                    saturated_fat_g = :saturated_fat_g,
                    carbohydrates_g = :carbohydrates_g,
                    sugars_g = :sugars_g,
                    fiber_g = :fiber_g,
                    protein_g = :protein_g,
                    salt_g = :salt_g,
                    is_archived = 0,
                    source_checked_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND owner_user_id = :owner_user_id'
            );
            $statement->execute([
                'id' => $existing['id'],
                'owner_user_id' => $userId,
                ...$this->importParameters($data),
            ]);

            return (int) $existing['id'];
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO products (
                owner_user_id, name, brand, source_type, source_identifier, source_url,
                package_amount, package_unit, package_description,
                reference_amount, reference_unit, energy_kj, energy_kcal,
                fat_g, saturated_fat_g, carbohydrates_g, sugars_g,
                fiber_g, protein_g, salt_g, source_checked_at
             ) VALUES (
                :owner_user_id, :name, :brand, :source_type, :source_identifier, :source_url,
                :package_amount, :package_unit, :package_description,
                :reference_amount, :reference_unit, :energy_kj, :energy_kcal,
                :fat_g, :saturated_fat_g, :carbohydrates_g, :sugars_g,
                :fiber_g, :protein_g, :salt_g, CURRENT_TIMESTAMP
             )'
        );
        $statement->execute([
            'owner_user_id' => $userId,
            ...$this->importParameters($data),
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    private function writeParameters(int $userId, array $data, string $sourceType): array
    {
        return [
            'owner_user_id' => $userId,
            'name' => $data['name'],
            'brand' => $data['brand'] ?: null,
            'source_type' => $sourceType,
            'source_url' => $data['source_url'] ?: null,
            'package_amount' => $data['package_amount'] ?: null,
            'package_unit' => $data['package_unit'] ?: null,
            'package_description' => $data['package_description'] ?: null,
            'reference_amount' => $data['reference_amount'],
            'reference_unit' => $data['reference_unit'],
            'energy_kj' => $data['energy_kj'],
            'energy_kcal' => $data['energy_kcal'],
            'fat_g' => $data['fat_g'],
            'saturated_fat_g' => $data['saturated_fat_g'],
            'carbohydrates_g' => $data['carbohydrates_g'],
            'sugars_g' => $data['sugars_g'],
            'fiber_g' => $data['fiber_g'],
            'protein_g' => $data['protein_g'],
            'salt_g' => $data['salt_g'],
        ];
    }

    private function importParameters(array $data): array
    {
        return [
            'name' => $data['name'],
            'brand' => $data['brand'] ?: null,
            'source_type' => $data['source_type'],
            'source_identifier' => $data['source_identifier'],
            'source_url' => $data['source_url'],
            'package_amount' => $data['package_amount'] ?: null,
            'package_unit' => $data['package_unit'] ?: null,
            'package_description' => $data['package_description'] ?: null,
            'reference_amount' => $data['reference_amount'],
            'reference_unit' => $data['reference_unit'],
            'energy_kj' => $data['energy_kj'],
            'energy_kcal' => $data['energy_kcal'],
            'fat_g' => $data['fat_g'],
            'saturated_fat_g' => $data['saturated_fat_g'],
            'carbohydrates_g' => $data['carbohydrates_g'],
            'sugars_g' => $data['sugars_g'],
            'fiber_g' => $data['fiber_g'],
            'protein_g' => $data['protein_g'],
            'salt_g' => $data['salt_g'],
        ];
    }
}
