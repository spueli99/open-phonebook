<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

use OpenPhonebook\Contacts\AddressBookAccessService;
use RuntimeException;

final class ContactImportPreviewService
{
    public function __construct(
        private readonly AddressBookAccessService $addressBookAccess,
        private readonly ContactImportParserInterface $parser,
    ) {
    }

    public function preview(
        int $addressBookId,
        int $userId,
        string $contents
    ): ImportPreviewResult {
        $this->addressBookAccess->requireWritable(
            $addressBookId,
            $userId
        );

        $preview = $this->parser->parse($contents);

        if ($preview->contacts === []) {
            throw new RuntimeException(
                'Import preview contains no importable contacts.'
            );
        }

        return $preview;
    }
}
