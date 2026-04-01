<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Frankfurter
 * Binance
 */
class ConversionController
{
  public function index(Request $request, Response $response, $args){
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'scuola');
    $result = $mysqli_connection->query("SELECT * FROM alunni");
    $results = $result->fetch_all(MYSQLI_ASSOC);

    $response->getBody()->write(json_encode($results));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }

public function getTransaction(Request $request, Response $response, $args){}

public function getTransactionNumber(Request $request, Response $response, $args){}

public function makeDepositit(Request $request, Response $response, $args){}

public function makeWithdrawal(Request $request, Response $response, $args){}

public function editTransactionNumber(Request $request, Response $response, $args){}

public function deleteTransactionNumber(Request $request, Response $response, $args){}

public function getBalance(Request $request, Response $response, $args){}


}

