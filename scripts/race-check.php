<?php

/**
 * Measures the stock lock: N processes try to buy the last piece at the same moment.
 *
 *   php scripts/race-check.php [processes]      (run inside the Sail container: composer race)
 *   php scripts/race-check.php --same-token [processes]    (composer race:token)
 *
 * --same-token: every process places the SAME cart token (stock 5, one piece each), like parallel
 * API requests replaying one order. Passes only with exactly one order, stock 4 and the rest
 * refused as already ordered.
 *
 * Uses a separate database (shop_race), migrated fresh on every run.
 * Passes only with exactly one order, stock 0 and every other buyer told that the cart changed.
 */

use App\Actions\PlaceOrder;
use App\Cart\Cart;
use App\Data\CheckoutData;
use App\Exceptions\CartChangedException;
use App\Exceptions\OrderAlreadyPlacedException;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

const RACE_DATABASE = 'shop_race';

$root = dirname(__DIR__);
$env = [
    'DB_DATABASE' => RACE_DATABASE,
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'APP_ENV' => 'local',
];

foreach ($env as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $_SERVER[$key] = $value;
}

if (($argv[1] ?? null) === 'worker') {
    exit(worker($root, (int) $argv[2], (float) $argv[3], $argv[4] ?? null));
}

$args = array_slice($argv, 1);
$sameToken = in_array('--same-token', $args, true);
$args = array_values(array_diff($args, ['--same-token']));

exit(orchestrate($root, (int) ($args[0] ?? 10), $env, $sameToken));

function boot(string $root): void
{
    require_once $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    // A cached config ignores the environment set above, and migrate:fresh would then
    // wipe the development database.
    $database = config('database.connections.'.config('database.default').'.database');

    if ($database !== RACE_DATABASE) {
        fwrite(STDERR, "Refusing to run against database '{$database}'. Run `php artisan config:clear` first.\n");
        exit(2);
    }
}

function orchestrate(string $root, int $processes, array $env, bool $sameToken): int
{
    require_once $root.'/vendor/autoload.php';
    createDatabase($root);
    boot($root);

    Artisan::call('migrate:fresh', ['--force' => true]);
    $product = Product::factory()
        ->for(Category::factory())
        ->create(['stock' => $sameToken ? 5 : 1, 'price' => 10000]);
    $token = $sameToken ? (string) Str::uuid() : null;

    // Booting a worker takes a while; everyone must be waiting before the shared start.
    $startAt = microtime(true) + 2 + $processes * 0.5;
    $workers = [];

    for ($i = 0; $i < $processes; $i++) {
        $workers[] = proc_open(
            array_filter(
                [PHP_BINARY, __FILE__, 'worker', (string) $product->id, sprintf('%.6F', $startAt), $token],
                fn ($argument) => $argument !== null,
            ),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes[$i],
            $root,
            array_merge(getenv(), $env),
        );
    }

    $results = ['ok' => 0, 'changed' => 0, 'already' => 0, 'error' => 0];

    foreach ($workers as $i => $process) {
        $output = trim(stream_get_contents($pipes[$i][1]));
        $errors = trim(stream_get_contents($pipes[$i][2]));
        proc_close($process);

        $outcome = array_key_exists($output, $results) ? $output : 'error';
        $results[$outcome]++;

        if ($outcome === 'error') {
            fwrite(STDERR, "worker {$i}: ".($errors !== '' ? $errors : $output).PHP_EOL);
        }
    }

    $orders = Order::count();
    $stock = $product->fresh()->stock;

    if ($sameToken) {
        echo "processes={$processes} orders={$orders} stock={$stock} ok={$results['ok']} already={$results['already']} errors={$results['error']}".PHP_EOL;

        return $orders === 1 && $stock === 4 && $results['ok'] === 1 && $results['already'] === $processes - 1 && $results['error'] === 0 ? 0 : 1;
    }

    echo "processes={$processes} orders={$orders} stock={$stock} changed={$results['changed']} errors={$results['error']}".PHP_EOL;

    return $orders === 1 && $stock === 0 && $results['changed'] === $processes - 1 && $results['error'] === 0 ? 0 : 1;
}

function worker(string $root, int $productId, float $startAt, ?string $token): int
{
    boot($root);

    $session = new Store('race', new ArraySessionHandler(10));
    $product = Product::findOrFail($productId);

    if ($token !== null) {
        $cart = Cart::fromItems($session, [$product->id => ['quantity' => 1, 'price' => $product->price]], $token);
    } else {
        $cart = new Cart($session);
        $cart->add($product, 1);
    }

    $data = new CheckoutData('Race', 'race@example.com', '777000000', 'Ulice 1', 'Praha', '11000', null);

    if (microtime(true) >= $startAt) {
        echo 'late: booted after the shared start, the race would be weaker';

        return 0;
    }

    time_sleep_until($startAt);

    try {
        app(PlaceOrder::class)->handle($cart, $data);
        echo 'ok';
    } catch (CartChangedException) {
        echo 'changed';
    } catch (OrderAlreadyPlacedException) {
        echo 'already';
    } catch (Throwable $e) {
        echo get_class($e).': '.$e->getMessage();
    }

    return 0;
}

function createDatabase(string $root): void
{
    $dotenv = Dotenv\Dotenv::createArrayBacked($root)->safeLoad();
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s', $dotenv['DB_HOST'] ?? 'mysql', $dotenv['DB_PORT'] ?? '3306'),
        'root',
        $dotenv['DB_PASSWORD'] ?? '',
    );
    $user = $dotenv['DB_USERNAME'] ?? 'sail';
    $pdo->exec('CREATE DATABASE IF NOT EXISTS '.RACE_DATABASE);
    $pdo->exec('GRANT ALL PRIVILEGES ON '.RACE_DATABASE.".* TO '{$user}'@'%'");
}
