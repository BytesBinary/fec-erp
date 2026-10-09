<?php

namespace App\Support\Authorization;

/**
 * Implemented by models whose access depends on the actor's scope
 * (department, hall, course or ownership).
 */
interface HasAuthorizationScope
{
    public function authorizationScope(): ResourceScope;
}
