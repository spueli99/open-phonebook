<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Google;

use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\AddressBookService;
use OpenPhonebook\Contacts\ContactPhoneNumberInput;
use OpenPhonebook\Google\GoogleContactsConnectionService;
use OpenPhonebook\Google\GoogleContactsSyncService;
use OpenPhonebook\Google\GoogleOAuthClient;
use OpenPhonebook\Google\GooglePeopleClient;
use OpenPhonebook\Http\HttpResponse;
use OpenPhonebook\Security\SecretCipher;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookAccessRepository;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookRepository;
use OpenPhonebook\Tests\Support\Contacts\InMemoryContactRepository;
use OpenPhonebook\Tests\Support\Contacts\TestPhoneNumberNormalizer;
use OpenPhonebook\Tests\Support\Database\InMemoryTransactionManager;
use OpenPhonebook\Tests\Support\Http\FakeHttpClient;
use OpenPhonebook\Tests\Support\Sources\InMemoryContactSourceRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GoogleContactsSyncServiceTest extends TestCase
{
    public function testSyncCreatesUpdatesDeletesAndSkipsAtomically(): void
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $addressBookId = (new AddressBookService($addressBooks))->create(
            10,
            'Google'
        );
        $access = new AddressBookAccessService(
            new InMemoryAddressBookAccessRepository($addressBooks)
        );
        $sources = new InMemoryContactSourceRepository();
        $contacts = new InMemoryContactRepository();
        $cipher = new SecretCipher(str_repeat('a', 64));
        $normalizer = new TestPhoneNumberNormalizer();

        (new GoogleContactsConnectionService(
            $access,
            $sources,
            $cipher
        ))->connect(10, $addressBookId, 'refresh-secret');

        $source = $sources->findGoogleForAddressBook(
            10,
            $addressBookId
        );
        self::assertNotNull($source);
        self::assertNotSame(
            'refresh-secret',
            $source->encryptedRefreshToken
        );

        $existingId = $contacts->create(
            $addressBookId,
            'Old Alice',
            null,
            null,
            null,
            null,
            [
                new ContactPhoneNumberInput(
                    'Mobile',
                    '+49 170 1111111',
                    '+491701111111'
                ),
            ]
        );
        $deletedId = $contacts->create(
            $addressBookId,
            'Removed',
            null,
            null,
            null,
            null,
            []
        );
        $sources->upsertRecord(1, 'people/1', $existingId);
        $sources->upsertRecord(1, 'people/old', $deletedId);

        $http = new FakeHttpClient();
        $http->queue(new HttpResponse(
            200,
            json_encode([
                'access_token' => 'access-token',
                'expires_in' => 3600,
            ], JSON_THROW_ON_ERROR)
        ));
        $http->queue(new HttpResponse(
            200,
            json_encode([
                'connections' => [
                    [
                        'resourceName' => 'people/1',
                        'names' => [[
                            'displayName' => 'Alice Example',
                        ]],
                        'phoneNumbers' => [[
                            'value' => '+49 170 1234567',
                            'formattedType' => 'Mobile',
                        ]],
                    ],
                    [
                        'resourceName' => 'people/2',
                        'names' => [[
                            'displayName' => 'Bob Example',
                        ]],
                    ],
                    [
                        'resourceName' => 'people/unmappable',
                    ],
                ],
            ], JSON_THROW_ON_ERROR)
        ));
        $transactions = new InMemoryTransactionManager();

        $result = (new GoogleContactsSyncService(
            $access,
            $sources,
            $contacts,
            $normalizer,
            $cipher,
            new GoogleOAuthClient(
                $http,
                'client-id',
                'client-secret'
            ),
            new GooglePeopleClient($http),
            $transactions
        ))->sync(10, $addressBookId);

        self::assertSame(1, $result->created);
        self::assertSame(1, $result->updated);
        self::assertSame(1, $result->deleted);
        self::assertSame(1, $result->skipped);
        self::assertSame(1, $transactions->calls);
        self::assertSame(1, $sources->markSyncedCalls);

        $stored = $contacts->allInAddressBook($addressBookId);
        self::assertCount(2, $stored);
        self::assertSame('Alice Example', $stored[0]->displayName);
        self::assertSame('Bob Example', $stored[1]->displayName);

        self::assertSame(
            'refresh-secret',
            $http->requests[0]['form']['refresh_token']
        );
    }

    public function testNonOwnerCannotSyncSharedAddressBook(): void
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $addressBookId = (new AddressBookService($addressBooks))->create(
            10,
            'Google'
        );
        $access = new AddressBookAccessService(
            new InMemoryAddressBookAccessRepository($addressBooks)
        );
        $sources = new InMemoryContactSourceRepository();
        $cipher = new SecretCipher(str_repeat('a', 64));

        (new GoogleContactsConnectionService(
            $access,
            $sources,
            $cipher
        ))->connect(10, $addressBookId, 'refresh-secret');

        $this->expectException(RuntimeException::class);

        (new GoogleContactsSyncService(
            $access,
            $sources,
            new InMemoryContactRepository(),
            new TestPhoneNumberNormalizer(),
            $cipher,
            new GoogleOAuthClient(
                new FakeHttpClient(),
                'client-id',
                'client-secret'
            ),
            new GooglePeopleClient(new FakeHttpClient()),
            new InMemoryTransactionManager()
        ))->sync(11, $addressBookId);
    }
}
