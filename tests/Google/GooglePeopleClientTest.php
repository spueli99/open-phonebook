<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Google;

use OpenPhonebook\Google\GooglePeopleClient;
use OpenPhonebook\Http\HttpResponse;
use OpenPhonebook\Tests\Support\Http\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class GooglePeopleClientTest extends TestCase
{
    public function testReadsAllPagesAndMapsContactFields(): void
    {
        $http = new FakeHttpClient();
        $http->queue(new HttpResponse(
            200,
            json_encode([
                'connections' => [[
                    'resourceName' => 'people/1',
                    'names' => [[
                        'displayName' => 'Alice Example',
                        'givenName' => 'Alice',
                        'familyName' => 'Example',
                    ]],
                    'organizations' => [['name' => 'Example GmbH']],
                    'biographies' => [['value' => 'Friend']],
                    'phoneNumbers' => [[
                        'value' => '+49 170 1234567',
                        'formattedType' => 'Mobile',
                    ]],
                ]],
                'nextPageToken' => 'next',
            ], JSON_THROW_ON_ERROR)
        ));
        $http->queue(new HttpResponse(
            200,
            json_encode([
                'connections' => [[
                    'resourceName' => 'people/2',
                    'names' => [[
                        'givenName' => 'Bob',
                        'familyName' => 'Example',
                    ]],
                ]],
            ], JSON_THROW_ON_ERROR)
        ));

        $people = (new GooglePeopleClient($http))
            ->allConnections('access');

        self::assertCount(2, $people);
        self::assertSame('people/1', $people[0]->externalId);

        $alice = $people[0]->contact;
        $bob = $people[1]->contact;

        self::assertNotNull($alice);
        self::assertNotNull($bob);
        self::assertSame('Alice Example', $alice->displayName);
        self::assertSame(
            'Example GmbH',
            $alice->organizationName
        );
        self::assertSame(
            'Mobile',
            $alice->phoneNumbers[0]->label
        );
        self::assertSame('Bob Example', $bob->displayName);
        self::assertCount(2, $http->requests);
        self::assertStringContainsString(
            'pageToken=next',
            $http->requests[1]['url']
        );
        self::assertSame(
            'Bearer access',
            $http->requests[0]['headers']['Authorization']
        );
    }

    public function testKeepsUnmappableResourceAsSeenSnapshot(): void
    {
        $http = new FakeHttpClient();
        $http->queue(new HttpResponse(
            200,
            '{"connections":[{"resourceName":"people/1"}]}'
        ));

        $people = (new GooglePeopleClient($http))
            ->allConnections('access');

        self::assertSame('people/1', $people[0]->externalId);
        self::assertNull($people[0]->contact);
    }
}
