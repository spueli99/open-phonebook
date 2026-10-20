<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

enum WebPushTransportResult
{
    case Sent;
    case Expired;
}
