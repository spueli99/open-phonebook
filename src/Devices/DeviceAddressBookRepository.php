<?php

declare(strict_types=1);

namespace OpenPhonebook\Devices;

use OpenPhonebook\Database\TransactionManagerInterface;
use PDO;

final class DeviceAddressBookRepository implements DeviceAddressBookRepositoryInterface
{
    public function __construct(
        private readonly PDO $database,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    public function addressBookIdsForDevice(int $deviceId): array
    {
        $statement = $this->database->prepare(
            'SELECT address_book_id
             FROM device_address_books
             WHERE device_id = :device_id
             ORDER BY address_book_id ASC'
        );
        $statement->execute(['device_id' => $deviceId]);

        $result = [];

        while (($value = $statement->fetchColumn()) !== false) {
            $result[] = (int) $value;
        }

        return $result;
    }

    public function replaceAssignments(
        int $deviceId,
        array $addressBookIds
    ): void {
        $this->transactions->transactional(
            function () use ($deviceId, $addressBookIds): void {
                $delete = $this->database->prepare(
                    'DELETE FROM device_address_books
                     WHERE device_id = :device_id'
                );
                $delete->execute(['device_id' => $deviceId]);

                if ($addressBookIds === []) {
                    return;
                }

                $insert = $this->database->prepare(
                    'INSERT INTO device_address_books (
                        device_id,
                        address_book_id,
                        created_at
                     ) VALUES (
                        :device_id,
                        :address_book_id,
                        UTC_TIMESTAMP()
                     )'
                );

                foreach ($addressBookIds as $addressBookId) {
                    $insert->execute([
                        'device_id' => $deviceId,
                        'address_book_id' => $addressBookId,
                    ]);
                }
            }
        );
    }
}
