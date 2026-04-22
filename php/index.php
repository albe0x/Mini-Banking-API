<?php
use Slim\Factory\AppFactory;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/controllers/BaseController.php';
require_once __DIR__ . '/controllers/TransactionsController.php';
require_once __DIR__ . '/controllers/ConversionController.php';

$app = AppFactory::create();

$app->get('/', function (Request $request, Response $response, array $args) {
    $response->getBody()->write("Test page");
    return $response;
});


//FORTUX
$app->get('/accounts/{id}/transactions',                    "TransactionsController:getTransaction");
$app->get('/accounts/{id}/transactions/{transaction_id}',   "TransactionsController:getTransactionNumber");
$app->get('/accounts/{id}/balance',                         "TransactionsController:getBalance");

//ALBE0X
$app->post('/accounts/{id}/deposits',                       "TransactionsController:makeDeposit");
$app->post('/accounts/{id}/withdrawals',                    "TransactionsController:makeWithdrawal");
$app->put('/accounts/{id}/transactions/{transaction_id}',   "TransactionsController:editTransactionNumber");
$app->delete('/accounts/{id}/transactions/{transaction_id}',"TransactionsController:deleteTransactionNumber");

//FRED
$app->get('/accounts/{id}/balance/convert/fiat',            "ConversionController:convertFiat");
$app->get('/accounts/{id}/balance/convert/crypto',          "ConversionController:convertCrypto");


$app->run();