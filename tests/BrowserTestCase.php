<?php

namespace Tests;

use Illuminate\Support\Facades\Vite;

abstract class BrowserTestCase extends TestCase
{
    /**
     * Browser tests always use the compiled Vite manifest, even when a local
     * `npm run dev` server has left a `public/hot` file behind.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Vite::useHotFile(storage_path('framework/testing/vite.hot'));
    }
}
