<?php
// /public_html/app/Repositories/UserRepository.php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class UserRepository
{
    public function findOrProvisionOidc(
        string $issuer,
        string $subject,
        string $email,
        string $displayName
    ): array {
        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $statement = $connection->prepare(
                'SELECT *
                 FROM users
                 WHERE identity_provider = :provider
                   AND external_subject = :subject
                 LIMIT 1
                 FOR UPDATE'
            );
            $statement->execute([
                'provider' => $issuer,
                'subject' => $subject,
            ]);
            $user = $statement->fetch();

            if ($user) {
                $this->updateProfile(
                    (int) $user['id'],
                    $email,
                    $displayName
                );

                $connection->commit();

                return $this->findById(
                    (int) $user['id']
                );
            }

            $oidc = config('oidc');
            $bootstrapUserId = (int) (
                $oidc['bootstrap_user_id']
                ?? 0
            );

            if ($bootstrapUserId > 0) {
                $bootstrap = $connection->prepare(
                    'SELECT *
                     FROM users
                     WHERE id = :id
                     LIMIT 1
                     FOR UPDATE'
                );
                $bootstrap->execute([
                    'id' => $bootstrapUserId,
                ]);
                $bootstrapUser = $bootstrap->fetch();

                if (
                    $bootstrapUser
                    && empty($bootstrapUser['external_subject'])
                    && empty($bootstrapUser['identity_provider'])
                ) {
                    $this->linkIdentity(
                        $bootstrapUserId,
                        $issuer,
                        $subject,
                        $email,
                        $displayName
                    );

                    $connection->commit();

                    return $this->findById(
                        $bootstrapUserId
                    );
                }
            }

            $emailStatement = $connection->prepare(
                'SELECT *
                 FROM users
                 WHERE email = :email
                 LIMIT 1
                 FOR UPDATE'
            );
            $emailStatement->execute([
                'email' => $email,
            ]);
            $emailUser = $emailStatement->fetch();

            if ($emailUser) {
                if (
                    !empty($oidc['allow_email_link'])
                    && empty($emailUser['external_subject'])
                    && empty($emailUser['identity_provider'])
                ) {
                    $this->linkIdentity(
                        (int) $emailUser['id'],
                        $issuer,
                        $subject,
                        $email,
                        $displayName
                    );

                    $connection->commit();

                    return $this->findById(
                        (int) $emailUser['id']
                    );
                }

                throw new \RuntimeException(
                    'A local user already exists with this email address, '
                    . 'but it is not linked to this Authentik identity.'
                );
            }

            $insert = $connection->prepare(
                'INSERT INTO users (
                    external_subject,
                    identity_provider,
                    email,
                    display_name,
                    role
                 ) VALUES (
                    :subject,
                    :provider,
                    :email,
                    :display_name,
                    :role
                 )'
            );
            $insert->execute([
                'subject' => $subject,
                'provider' => $issuer,
                'email' => $email,
                'display_name' => mb_substr(
                    $displayName,
                    0,
                    191
                ),
                'role' => 'user',
            ]);

            $userId = (int) $connection->lastInsertId();

            $connection->commit();

            return $this->findById($userId);
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public function findById(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                id,
                external_subject,
                identity_provider,
                email,
                display_name,
                role
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();

        if (!$user) {
            throw new \RuntimeException(
                'User not found.'
            );
        }

        return $user;
    }

    private function linkIdentity(
        int $userId,
        string $issuer,
        string $subject,
        string $email,
        string $displayName
    ): void {
        $statement = Database::connection()->prepare(
            'UPDATE users
             SET external_subject = :subject,
                 identity_provider = :provider,
                 email = :email,
                 display_name = :display_name
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $userId,
            'subject' => $subject,
            'provider' => $issuer,
            'email' => $email,
            'display_name' => mb_substr(
                $displayName,
                0,
                191
            ),
        ]);
    }

    private function updateProfile(
        int $userId,
        string $email,
        string $displayName
    ): void {
        $statement = Database::connection()->prepare(
            'UPDATE users
             SET email = :email,
                 display_name = :display_name
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $userId,
            'email' => $email,
            'display_name' => mb_substr(
                $displayName,
                0,
                191
            ),
        ]);
    }
}
