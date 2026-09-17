<?php

namespace App\Engagement;

use LogicException;

class EngagementDeliveryGateway
{
    /** @return array{provider_reference:string} */
    public function send(array $message): array
    {
        throw new LogicException('No production engagement delivery gateway is configured.');
    }
}
