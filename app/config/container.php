<?php
declare(strict_types=1);

use App\Security\DocumentProtector;
use Dice\Dice;
use Medoo\Medoo;

$root = dirname(__DIR__, 2);
$dice = new Dice();

$dice = $dice->addRule(Medoo::class, [
    'shared' => true,
    'constructParams' => [
        'type'      => getenv('DB_CONNECTION') ?: 'sqlite',
        'database'  => getenv('DB_PATH') ?: $root . '/../storage/app.sqlite',
        'error'     => PDO::ERRMODE_EXCEPTION,
        'command'   => ['PRAGMA foreign_keys = ON', 'PRAGMA busy_timeout = 5000'],
    ],
]);

$dice = $dice->addRule(DocumentProtector::class,[
    'shared'            => true,
    'constructParams'   => [
        getenv('APP_KEY') ?: '', getenv('HASH_KEY') ?: ''
    ],

]);

return $dice;