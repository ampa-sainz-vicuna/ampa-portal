<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Http;

use Ampa\PortalCliente\Security\PortalUser;

final class NoMeExtension implements MeExtension
{
    public function describe(PortalUser $user): array
    {
        return [];
    }
}
