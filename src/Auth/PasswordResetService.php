<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

use DateInterval;
use OpenPhonebook\Clock\ClockInterface;
use OpenPhonebook\Database\TransactionManagerInterface;
use Throwable;

final class PasswordResetService
{
    private const TOKEN_TTL = 'PT30M';
    private const REQUEST_COOLDOWN = 'PT5M';

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly PasswordResetRepositoryInterface $resets,
        private readonly ?PasswordResetMailer $mailer,
        private readonly TransactionManagerInterface $transactions,
        private readonly ClockInterface $clock,
        private readonly string $applicationKey,
    ) {
    }

    public function request(string $email): void
    {
        $email = strtolower(trim($email));

        if (
            $this->mailer === null
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            return;
        }

        $user = $this->users->findByEmail($email);

        if (
            $user === null
            || !$user->isActive
            || $user->isDeleted
        ) {
            return;
        }

        $now = $this->clock->now();
        $cooldownStart = $now->sub(
            new DateInterval(self::REQUEST_COOLDOWN)
        );

        if (
            $this->resets->hasRecentRequest(
                $user->id,
                $cooldownStart
            )
        ) {
            return;
        }

        $token = PasswordResetToken::generate(
            $this->applicationKey
        );
        $expiresAt = $now->add(
            new DateInterval(self::TOKEN_TTL)
        );

        $requestId = $this->transactions->transactional(
            function () use (
                $user,
                $token,
                $expiresAt,
                $now
            ): int {
                $this->resets->invalidateUsableForUser(
                    $user->id,
                    $now
                );

                return $this->resets->create(
                    $user->id,
                    $token->hash,
                    $expiresAt,
                    $now
                );
            }
        );

        try {
            $this->mailer->send(
                $user,
                $token->plainText,
                $expiresAt
            );
        } catch (Throwable) {
            try {
                $this->resets->markUsed($requestId);
            } catch (Throwable) {
                // Keep the external response generic even on cleanup failure.
            }
        }
    }

    public function resolve(
        string $plainToken
    ): ?PasswordResetRequest {
        if (preg_match('/^[a-f0-9]{64}$/', $plainToken) !== 1) {
            return null;
        }

        return $this->resets->findUsableByTokenHash(
            PasswordResetToken::hash(
                $plainToken,
                $this->applicationKey
            ),
            $this->clock->now()
        );
    }

    public function complete(
        PasswordResetRequest $request,
        string $password
    ): void {
        if (mb_strlen($password) < 12) {
            throw new \RuntimeException(
                'Password must contain at least 12 characters.'
            );
        }

        $now = $this->clock->now();

        if (!$request->isUsable($now)) {
            throw new \RuntimeException(
                'Password reset is no longer usable.'
            );
        }

        $this->transactions->transactional(
            function () use ($request, $password, $now): void {
                $this->resets->markUsed($request->id);
                $this->users->resetPasswordAndIncrementRevision(
                    $request->userId,
                    password_hash($password, PASSWORD_DEFAULT)
                );
                $this->resets->invalidateUsableForUser(
                    $request->userId,
                    $now
                );
            }
        );
    }
}
