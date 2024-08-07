<?php

namespace Illuminate\Tests\Integration\Database\Sqlite;

use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\Concerns\InteractsWithPublishedFiles;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\RequiresOperatingSystem;

use function Orchestra\Testbench\remote;

class SchemaStateTest extends TestCase
{
    use InteractsWithPublishedFiles;

    protected $files = [
        'database/schema',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        remote('migrate:install')->mustRun();
    }

    protected function tearDown(): void
    {
        remote('db:wipe')->mustRun();

        parent::tearDown();
    }

    protected function defineEnvironment($app)
    {
        $connection = $app['config']->get('database.default');

        if ($app['config']->get("database.connections.$connection.driver") !== 'sqlite') {
            $this->markTestSkipped('Test requires a Sqlite connection.');
        }
    }

    #[RequiresOperatingSystem('Linux|Darwin')]
    public function testSchemaDumpOnSqlite()
    {
        if ($this->usesSqliteInMemoryDatabaseConnection()) {
            $this->markTestSkipped('Test cannot be run using :in-memory: database connection');
        }

        $connection = DB::connection();
        $connection->getSchemaBuilder()->createDatabase($connection->getConfig('database'));

        $connection->statement('CREATE TABLE users(id integer primary key autoincrement not null, email varchar not null, name varchar not null);');
        $connection->statement('INSERT INTO users (email, name) VALUES ("taylor@laravel.com", "Taylor Otwell");');

        $this->assertTrue($connection->table('sqlite_sequence')->exists());

        $this->app['files']->ensureDirectoryExists(database_path('schema'));

        $connection->getSchemaState()->dump($connection, database_path('schema/sqlite-schema.sql'));

        $this->assertFileContains([
            'CREATE TABLE users',
        ], 'database/schema/sqlite-schema.sql');
        $this->assertFileNotContains([
            'sqlite_sequence',
        ], 'database/schema/sqlite-schema.sql');
    }
}
