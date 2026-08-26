<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->useStoragePath(sys_get_temp_dir().DIRECTORY_SEPARATOR.'armory-aa-tests');
        $app->make(Kernel::class)->bootstrap();

        $searchPath = (string) $app['config']->get('database.connections.pgsql.search_path');
        if (!str_ends_with($searchPath, '_testing')) {
            throw new \LogicException("Refusing to run tests outside a testing schema [{$searchPath}].");
        }

        return $app;
    }
}
