<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TransactionsController
{
    //fortux
    public function getTransaction(Request $request, Response $response, $args){
      $mysqli = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');

      if ($mysqli->connect_error) {
          $response->getBody()->write(json_encode(['error' => 'Database connection failed', 'details' => $mysqli->connect_error]));
          return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
      }

      // qui "id" è l'id dell'account di cui vogliamo tutte le transazioni
      $accountId = isset($args['id']) ? (int)$args['id'] : 0;
      if ($accountId <= 0) {
          $mysqli->close();
          $response->getBody()->write(json_encode(['error' => 'Invalid account id']));
          return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
      }

      $stmt = $mysqli->prepare('SELECT * FROM transactions WHERE account_id = ? ORDER BY created_at DESC');
      if (!$stmt) {
          $err = $mysqli->error;
          $mysqli->close();
          $response->getBody()->write(json_encode(['error' => 'Failed to prepare statement', 'details' => $err]));
          return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
      }

      $stmt->bind_param('i', $accountId);
      $stmt->execute();
      $res = $stmt->get_result();
      $transactions = $res->fetch_all(MYSQLI_ASSOC);

      $stmt->close();
      $mysqli->close();

      // anche se vuoto ritorniamo array ([]) con 200
      $response->getBody()->write(json_encode($transactions));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
    //fortux

    public function getTransactionNumber(Request $request, Response $response, $args){
      $mysqli = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');
      if ($mysqli->connect_error) {
        $response->getBody()->write(json_encode(['error'=>'DB error', 'details'=>$mysqli->connect_error]));
        return $response->withHeader('Content-Type','application/json')->withStatus(500);
      }

      // prendo l'id della transazione: route param {transaction_id} ha priorità
      $query = $request->getQueryParams();
      $txId = 0;
      if (isset($args['transaction_id'])) $txId = (int)$args['transaction_id'];
      elseif (isset($args['txId'])) $txId = (int)$args['txId'];
      elseif (isset($query['transaction_id'])) $txId = (int)$query['transaction_id'];
      elseif (isset($query['id'])) $txId = (int)$query['id'];

      if ($txId <= 0) {
        $mysqli->close();
        $response->getBody()->write(json_encode(['error'=>'Invalid transaction id']));
        return $response->withHeader('Content-Type','application/json')->withStatus(400);
      }

      $stmt = $mysqli->prepare('SELECT t.*, a.currency FROM transactions t LEFT JOIN accounts a ON t.account_id = a.id WHERE t.id = ?');
      if (!$stmt) {
        $err = $mysqli->error;
        $mysqli->close();
        $response->getBody()->write(json_encode(['error'=>'Failed to prepare statement', 'details' => $err]));
        return $response->withHeader('Content-Type','application/json')->withStatus(500);
      }

      $stmt->bind_param('i', $txId);
      $stmt->execute();
      $res = $stmt->get_result();
      $transaction = $res->fetch_assoc();

      $stmt->close();
      $mysqli->close();

      if (!$transaction) {
        $response->getBody()->write(json_encode(['error'=>'Transaction not found']));
        return $response->withHeader('Content-Type','application/json')->withStatus(404);
      }

      $response->getBody()->write(json_encode($transaction));
      return $response->withHeader('Content-Type','application/json')->withStatus(200);
    }

    //ALBE0X
    public function makeDeposit(Request $request, Response $response, $args){
      return $this->makeTransaction($request, $response, $args, "depositit");
    }

    //ALBE0X
    public function makeWithdrawal(Request $request, Response $response, $args){
      return  $this->makeTransaction($request, $response, $args, "withdrawal");
    }

    //ALBE0X
    public function makeTransaction(Request $request, Response $response, $args, $type){
      $mysqli = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');

      // create transacion
      $sql = "INSERT INTO transactions (account_id, type, amount, description, created_at) VALUES (?, ?, ?, ?, ?)";
      $stmt = $mysqli->prepare($sql);

      $data = $request->getParsedBody();
    
      $account_id  = $args['id'] ?? null;
      $amount      = $data['amount'] ?? 0;
      $description = $data['description'] ?? '';
      $created_at  = $data['created_at'] ?? date('Y-m-d H:i:s');

      $stmt->bind_param("isdss", $account_id, $type, $amount, $description, $created_at);

      if ($stmt->execute()) {
          echo "Nuovo record inserito con successo! ID: ";
      } else {
          echo "Errore durante l'inserimento: ";

      }

      $stmt->close();
      return $response;
    }

    //public function editTransactionNumber(Request $request, Response $response, $args){}
    //public function deleteTransactionNumber(Request $request, Response $response, $args){}

    //fortux
    public function getBalance(Request $request, Response $response, $args){
      // apro connessione al db
      $mysqli = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');
      if ($mysqli->connect_error) {
        $response->getBody()->write(json_encode(['error'=>'DB error']));
        return $response->withHeader('Content-Type','application/json')->withStatus(500);
      }

      // prendo id account
      $accountId = isset($args['id']) ? (int)$args['id'] : 0;
      if ($accountId <= 0) {
        $response->getBody()->write(json_encode(['error'=>'Invalid account id']));
        return $response->withHeader('Content-Type','application/json')->withStatus(400);
      }

      // verifico account e prendo valuta
      $stmt = $mysqli->prepare('SELECT id, currency FROM accounts WHERE id = ?');
      $stmt->bind_param('i', $accountId);
      $stmt->execute();
      $res = $stmt->get_result();
      $account = $res->fetch_assoc();
      $stmt->close();
      if (!$account) {
        $response->getBody()->write(json_encode(['error'=>'Account not found']));
        return $response->withHeader('Content-Type','application/json')->withStatus(404);
      }

      // calcolo saldo semplice: depositi - prelievi
      $stmt = $mysqli->prepare("
        SELECT
          COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END),0) -
          COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END),0) AS balance
        FROM transactions
        WHERE account_id = ?
      ");
      $stmt->bind_param('i', $accountId);
      $stmt->execute();
      $row = $stmt->get_result()->fetch_assoc();
      $balance = (float)($row['balance'] ?? 0);
      $stmt->close();
      $mysqli->close();

      // ritorno solo le info richieste (studente)
      $payload = [
        'account_id' => $accountId,
        'currency' => $account['currency'] ?? null,
        'balance' => $balance
      ];

      $response->getBody()->write(json_encode($payload));
      return $response->withHeader('Content-Type','application/json')->withStatus(200);
    }

}
