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

$app->get('/accounts/1/transactions',       "ConversionController:getTransaction");
$app->get('/accounts/1/transactions/5',     "ConversionController:getTransactionNumber");
$app->post('/accounts/1/deposits ',         "ConversionController:makeDepositit");
$app->post('/accounts/1/withdrawals',       "ConversionController:makeWithdrawal");
$app->put('/accounts/1/transactions/5 ',    "ConversionController:editTransactionNumber");
$app->delete('/accounts/1/transactions/5',  "ConversionController:deleteTransactionNumber");

$app->get('/accounts/1/balance',            "ConversionController:getBalance");

$app->get('/accounts/1/balance/convert/crypto?to=BTC', "TransactionsController:fiat");
$app->get('/accounts/1/balance/convert/crypto?to=BTC', "TransactionsController:crypto");


$app->run();