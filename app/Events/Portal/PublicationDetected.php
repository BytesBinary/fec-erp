<?php

namespace App\Events\Portal;

use App\Models\PortalPublication;

/**
 * A new exam id appeared: a publication candidate.
 */
class PublicationDetected
{
    public function __construct(public PortalPublication $publication) {}
}
