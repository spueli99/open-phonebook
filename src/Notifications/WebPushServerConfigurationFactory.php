<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use OpenPhonebook\Config\Config;
use RuntimeException;

final class WebPushServerConfigurationFactory
{
    public static function fromConfig(
        Config $config
    ): ?WebPushServerConfiguration {
        $subject = $config->get('providers.web_push.subject');
        $publicKey = $config->get('providers.web_push.public_key');
        $privateKey = $config->get('providers.web_push.private_key');

        if (
            $subject === null
            && $publicKey === null
            && $privateKey === null
        ) {
            return null;
        }

        if (
            !is_string($subject)
            || !is_string($publicKey)
            || !is_string($privateKey)
        ) {
            throw new RuntimeException(
                'Web Push configuration is incomplete.'
            );
        }

        return new WebPushServerConfiguration(
            $subject,
            $publicKey,
            $privateKey
        );
    }

    private function __construct()
    {
    }
}
