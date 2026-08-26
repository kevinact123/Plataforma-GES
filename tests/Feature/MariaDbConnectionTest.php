<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MariaDbConnectionTest extends TestCase
{
    public function test_laravel_can_query_the_existing_mariadb_database(): void
    {
        $connection = config('database.connections.mysql');
        $connection['database'] = 'plataforma ges';

        Config::set('database.connections.ges_mysql_test', $connection);
        DB::purge('ges_mysql_test');

        $tableCount = DB::connection('ges_mysql_test')
            ->table('information_schema.tables')
            ->where('table_schema', 'plataforma ges')
            ->count();

        $this->assertGreaterThan(0, $tableCount);
    }
}
