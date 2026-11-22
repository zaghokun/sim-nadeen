<?php

namespace Tests\Feature;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    public function test_home_page_loads_built_assets_and_health_check_responds(): void
    {
        app(Vite::class)->useHotFile(storage_path('framework/testing/vite.hot'));

        $this->get('/')
            ->assertOk()
            ->assertSee('SIM Furniture Nadeen')
            ->assertSee('/build/assets/app-', escape: false);

        $this->getJson('/up')->assertOk()->assertExactJson(['status' => 'up']);
    }

    public function test_mysql_test_schema_has_the_foundation_tables(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('sim_nadeen_test', config('database.connections.mysql.database'));

        $database = DB::selectOne('SELECT DATABASE() AS name, @@session.time_zone AS time_zone');

        $this->assertSame('sim_nadeen_test', $database->name);
        $this->assertSame('+00:00', $database->time_zone);
        $this->assertTrue(Schema::hasTable('migrations'));
        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertTrue(Schema::hasTable('cache'));
        $this->assertTrue(Schema::hasTable('cache_locks'));
        $this->assertSame('0.30', bcadd('0.10', '0.20', 2));
    }
}
