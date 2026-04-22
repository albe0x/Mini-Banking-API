<?php
use Slim\Factory\AppFactory;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/controllers/TransactionsController.php';
require __DIR__ . '/controllers/ConversionController.php';

$app = AppFactory::create();

$app->get('/', function (Request $request, Response $response, array $args) {
    $response->getBody()->write("Test page");
    return $response;
});

$app->get('/accounts/{id}/transactions',                    "TransactionsController:getTransaction");
$app->get('/accounts/{id}/transactions/{transaction_id}',   "TransactionsController:getTransactionNumber");
$app->post('/accounts/{id}/deposits ',                      "TransactionsController:makeDepositit");
$app->post('/accounts/{id}/withdrawals',                    "TransactionsController:makeWithdrawal");
$app->put('/accounts/{id}/transactions/{transaction_id}',                 "TransactionsController:editTransactionNumber");
$app->delete('/accounts/{id}/transactions/{transaction_id}',               "TransactionsController:deleteTransactionNumber");

$app->get('/accounts/{id}/balance',                         "TransactionsController:getBalance");


$app->get('/accounts/{id}/balance/convert/fiat?to={to}',    "ConversionController:fiat");
$app->get('/accounts/{id}/balance/convert/crypto?to={to}',  "ConversionController:crypto");


$app->run();