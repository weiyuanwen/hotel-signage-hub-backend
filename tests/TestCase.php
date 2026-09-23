<?php

namespace Tests;

use App\Domains\Content\MediaStore;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    protected function fakeMediaDisk(): string
    {
        $disk = MediaStore::disk();
        Storage::fake($disk);

        return $disk;
    }
}
