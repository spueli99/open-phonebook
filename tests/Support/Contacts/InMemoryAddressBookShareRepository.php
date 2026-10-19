<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Contacts;

use OpenPhonebook\Contacts\AddressBookAccessLevel;
use OpenPhonebook\Contacts\AddressBookShare;
use OpenPhonebook\Contacts\AddressBookShareRepositoryInterface;

final class InMemoryAddressBookShareRepository implements AddressBookShareRepositoryInterface
{
    /**
     * @var array<string, int>
     */
    private array $activeUsersByEmail = [];

    /**
     * @var array<int, array{email: string, display_name: string}>
     */
    private array $profiles = [];

    /**
     * @var array<int, array<int, AddressBookShare>>
     */
    private array $shares = [];

    public function addActiveUser(
        int $userId,
        string $email,
        string $displayName
    ): void {
        $email = strtolower(trim($email));
        $this->activeUsersByEmail[$email] = $userId;
        $this->profiles[$userId] = [
            'email' => $email,
            'display_name' => $displayName,
        ];

        foreach ($this->shares as $addressBookId => $shares) {
            if (!isset($shares[$userId])) {
                continue;
            }

            $share = $shares[$userId];
            $this->shares[$addressBookId][$userId] = new AddressBookShare(
                $userId,
                $email,
                $displayName,
                $share->accessLevel,
            );
        }
    }

    public function allForAddressBook(int $addressBookId): array
    {
        return array_values($this->shares[$addressBookId] ?? []);
    }

    public function findActiveUserIdByEmail(string $email): ?int
    {
        return $this->activeUsersByEmail[strtolower(trim($email))] ?? null;
    }

    public function upsert(
        int $addressBookId,
        int $userId,
        AddressBookAccessLevel $accessLevel
    ): void {
        $profile = $this->profiles[$userId] ?? null;

        if ($profile === null) {
            return;
        }

        $this->shares[$addressBookId][$userId] = new AddressBookShare(
            $userId,
            $profile['email'],
            $profile['display_name'],
            $accessLevel,
        );
    }

    public function revoke(int $addressBookId, int $userId): bool
    {
        if (!isset($this->shares[$addressBookId][$userId])) {
            return false;
        }

        unset($this->shares[$addressBookId][$userId]);

        return true;
    }
}
