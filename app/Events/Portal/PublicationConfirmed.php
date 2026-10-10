<?php

namespace App\Events\Portal;

use App\Models\PortalPublication;

/**
 * A probe student proved the results are visible.
 */
class PublicationConfirmed
{
    public function __construct(public PortalPublication $publication) {}
}
