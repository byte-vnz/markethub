<?php
require __DIR__ . "/../../vendor/autoload.php";
$app = require __DIR__ . "/../../bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (
    !app()->environment("testing") ||
    config("database.default") !== "mysql" ||
    config("database.connections.mysql.database") !== "markethub_lgu_test"
) {
    fwrite(STDERR, "Refusing non-test database");
    exit(3);
}
while (microtime(true) < (float) $argv[3]) {
    usleep(10000);
}
try {
    app(App\Services\PaymentService::class)->post(
        App\Models\User::findOrFail($argv[1]),
        App\Models\Payment::findOrFail($argv[2]),
    );
    echo "posted";
    exit(0);
} catch (Illuminate\Validation\ValidationException $e) {
    echo "allocation rejected";
    exit(2);
}
