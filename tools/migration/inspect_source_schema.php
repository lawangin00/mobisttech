<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

// Run only pinned, disposable source exports against an in-memory database.
$root = dirname(__DIR__, 2);
$source = $argv[1] ?? '';
if (! in_array($source, ['pos', 'website'], true)) {
    throw new RuntimeException('Expected pos or website.');
}
$copy = $root.'/.local/mt11/sources/'.$source;
if (is_dir($copy.'/.git')) {
    throw new RuntimeException('Source export must not contain Git metadata.');
}
chdir($copy);
require $copy.'/vendor/autoload.php';
$app = require $copy.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, 'FAIL: '.$error->getMessage().PHP_EOL);
    exit(1);
});
if (! $app->environment('testing') || config('database.default') !== 'sqlite'
    || config('database.connections.sqlite.database') !== ':memory:') {
    throw new RuntimeException('In-memory source isolation required.');
}
Http::preventStrayRequests();
$declarations = [];
$app->bind(Blueprint::class, function ($app, $parameters) use (&$declarations) {
    return new class($parameters['connection'], $parameters['table'], $parameters['callback'], $declarations) extends Blueprint
    {
        private $captured;

        public function __construct($connection, $table, $callback, &$captured)
        {
            $this->captured = &$captured;
            parent::__construct($connection, $table, $callback);
        }

        public function build()
        {
            foreach ($this->getColumns() as $column) {
                $this->captured[$this->getTable()][$column->name] = $column->toArray();
            }
            parent::build();
        }
    };
});
if ($kernel->call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException($kernel->output());
}
$schema = Schema::getFacadeRoot();
$tables = [];
foreach ($schema->getTables() as $table) {
    $name = $table['name'];
    if ($name === 'migrations') {
        continue;
    }
    $tables[$name] = [
        'columns' => $schema->getColumns($name),
        'indexes' => $schema->getIndexes($name),
        'foreign_keys' => $schema->getForeignKeys($name),
        'declarations' => $declarations[$name] ?? [],
    ];
}
$out = $root.'/docs/schema';
if (! is_dir($out)) {
    mkdir($out, 0777, true);
}
file_put_contents($out.'/source_'.$source.'.json', json_encode([
    'source' => $source,
    'method' => 'Pinned source migrations applied to empty SQLite memory; schema only, no source business data.',
    'tables' => $tables,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
echo $source.': '.count($tables)." final tables inspected; no rows exported.\n";
