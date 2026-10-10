<?php

namespace App\Events\Portal;

/**
 * The portal could not be read or answered with an unknown page.
 */
class PortalHealthFailed
{
    public function __construct(public string $reason) {}
}
