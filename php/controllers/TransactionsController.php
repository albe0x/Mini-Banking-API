<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TransactionsController
{
/*   
  public function index(Request $request, Response $response, $args){
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');
    $result = $mysqli_connection->query("SELECT * FROM accounts");
    $results = $result->fetch_all(MYSQLI_ASSOC);

    $response->getBody()->write(json_encode($results));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }
*/

    //fortux
    public function getTransaction(Request $request, Response $response, $args){}
    //fortux
    public function getTransactionNumber(Request $request, Response $response, $args){}

    //ALBE0X
    public function makeDepositit(Request $request, Response $response, $args){
      return makeTransaction($request, $response, $args, "depositit");
    }

    public function makeWithdrawal(Request $request, Response $response, $args){
      return makeTransaction($request, $response, $args, "withdrawal");
    }

    public function makeTransaction(Request $request, Response $response, $args, $type){
      $mysqli = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');

      $sql = "INSERT INTO transactions (account_id, type, amount, description, created_at) VALUES (?, ?, ?, ?, ?)";
      $stmt = $mysqli->prepare($sql);

      $data = $request->getParsedBody();
    
      $account_id  = $data['account_id'] ?? null;
      $amount      = $data['amount'] ?? 0;
      $description = $data['description'] ?? '';
      $created_at  = $data['created_at'] ?? date('Y-m-d H:i:s');

      $stmt->bind_param("isdds", $account_id, $type, $amount, $description, $created_at);

      // 3. Esegui la query
      if ($stmt->execute()) {
          echo "Nuovo record inserito con successo! ID: " . $mysql_connection->insert_id;
      } else {
          echo "Errore durante l'inserimento: " . $stmt->error;
      }

      // 4. Chiudi lo statement
      $stmt->close();
    }

    //public function editTransactionNumber(Request $request, Response $response, $args){}
    //public function deleteTransactionNumber(Request $request, Response $response, $args){}

    //fortux
    public function getBalance(Request $request, Response $response, $args){}

}
