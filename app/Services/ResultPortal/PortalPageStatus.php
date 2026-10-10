<?php

namespace App\Services\ResultPortal;

enum PortalPageStatus: string
{
    case Found = 'found';
    case NotVerified = 'not_verified';
}
