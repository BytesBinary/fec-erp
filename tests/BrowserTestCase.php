<?php

namespace Tests;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Tests\Support\ResetRequestState;

abstract class BrowserTestCase extends TestCase
{
    /**
     * Browser tests always use the compiled Vite manifest, even when a local
     * `npm run dev` server has left a `public/hot` file behind.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->useFileSessions();
        $this->app->make(HttpKernel::class)->prependMiddleware(ResetRequestState::class);

        Vite::useHotFile(storage_path('framework/testing/vite.hot'));
    }

    /**
     * Browser contexts must not share session state: the in-memory `array`
     * driver keeps one attribute bag for every cookie, so E2E tests with
     * several logged-in browsers use isolated file sessions instead.
     */
    protected function useFileSessions(): void
    {
        $path = storage_path('framework/testing/sessions');

        File::deleteDirectory($path);
        File::ensureDirectoryExists($path);

        config(['session.driver' => 'file', 'session.files' => $path]);
    }
}
