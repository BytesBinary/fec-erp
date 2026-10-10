<?php

namespace App\Mcp\Resources;

use App\Models\User;
use App\Services\Users\RoleService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;

class MeResource extends Resource
{
    protected string $uri = 'erp://me';

    protected string $mimeType = 'application/json';

    protected string $name = 'me';

    protected string $title = 'Current user';

    protected string $description = 'The signed-in user: name, email, roles and the scopes (departments, halls, courses) each role is limited to.';

    public function handle(Request $request): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        return Response::json([
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'roles' => app(RoleService::class)->rolesOf($user, $user),
        ]);
    }
}
