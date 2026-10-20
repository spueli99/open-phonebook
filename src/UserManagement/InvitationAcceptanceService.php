<?php

declare(strict_types=1);

namespace OpenPhonebook\UserManagement;

use OpenPhonebook\Audit\AuditEventType;
use OpenPhonebook\Audit\AuditEventWriterInterface;
use OpenPhonebook\Audit\AuditTargetType;
use PDO;
use RuntimeException;
use Throwable;

final class InvitationAcceptanceService
{
    public function __construct(
        private readonly PDO $database,
        private readonly InvitationRepositoryInterface $invitations,
        private readonly UserAdministrationRepositoryInterface $users,
        private readonly ?AuditEventWriterInterface $audit = null,
    ) {
    }

    public function accept(
        Invitation $invitation,
        string $displayName,
        string $password,
        string $locale,
        string $timezone
    ): int {
        $displayName = trim($displayName);

        if ($displayName === '') {
            throw new RuntimeException('Display name is required.');
        }

        if (mb_strlen($password) < 12) {
            throw new RuntimeException(
                'Password must contain at least 12 characters.'
            );
        }

        if (!in_array($locale, ['en', 'de'], true)) {
            throw new RuntimeException('Unsupported locale.');
        }

        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            throw new RuntimeException('Invalid timezone.');
        }

        if ($this->users->emailExists($invitation->email)) {
            throw new RuntimeException(
                'A user with this email address already exists.'
            );
        }

        try {
            $this->database->beginTransaction();

            $statement = $this->database->prepare(
                'INSERT INTO users (
                    email,
                    display_name,
                    password_hash,
                    role,
                    locale,
                    timezone,
                    is_active,
                    created_at,
                    updated_at
                ) VALUES (
                    :email,
                    :display_name,
                    :password_hash,
                    :role,
                    :locale,
                    :timezone,
                    1,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )'
            );
            $statement->execute([
                'email' => $invitation->email,
                'display_name' => $displayName,
                'password_hash' => password_hash(
                    $password,
                    PASSWORD_DEFAULT
                ),
                'role' => $invitation->role,
                'locale' => $locale,
                'timezone' => $timezone,
            ]);

            $userId = (int) $this->database->lastInsertId();
            $this->invitations->markAccepted($invitation->id);
            $this->audit?->record(
                AuditEventType::InvitationAccepted,
                $userId,
                AuditTargetType::Invitation,
                $invitation->id,
                ['role' => $invitation->role]
            );

            $this->database->commit();

            return $userId;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }
}
