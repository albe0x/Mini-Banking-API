<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/*
  Controller per le operazioni sulle transazioni.
  I commenti sono in italiano e spiegano i passaggi principali.
*/
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
    public function getTransaction(Request $request, Response $response, $args){
      // apro connessione al database
      $mysqli = new MySQLi('my_mariadb', 'root', 'ciccio', 'scuola');

      // controllo eventuali errori di connessione
      if ($mysqli->connect_error) {
          $response->getBody()->write(json_encode(['error' => 'Database connection failed']));
          return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
      }

      // estraggo l'id transazione dai parametri della route
      $id = isset($args['id']) ? (int)$args['id'] : 0;
      if ($id <= 0) {
          // id non valido: rispondo con 400 Bad Request
          $response->getBody()->write(json_encode(['error' => 'Invalid transaction id']));
          return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
      }

      // preparo la query per recuperare la transazione
      $stmt = $mysqli->prepare('SELECT id, account_id, type, amount, balance_after, created_at FROM transactions WHERE id = ?');
      if (!$stmt) {
          // errore nella preparazione della statement
          $response->getBody()->write(json_encode(['error' => 'Failed to prepare statement']));
          return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
      }

      // eseguo la query e prendo il risultato
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $result = $stmt->get_result();
      $transaction = $result->fetch_assoc();

      // chiudo statement e connessione
      $stmt->close();
      $mysqli->close();

      if (!$transaction) {
          // transazione non trovata: 404 Not Found
          $response->getBody()->write(json_encode(['error' => 'Transaction not found']));
          return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
      }

      // ritorno la transazione in formato JSON con 200 OK
      $response->getBody()->write(json_encode($transaction));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
    //fortux

    public function getTransactionNumber(Request $request, Response $response, $args){
      // apro connessione al db
      $mysqli = new MySQLi('my_mariadb', 'root', 'ciccio', 'scuola');
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

      // controllo che l'account esista
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

      // parametri per paginazione semplici
      $params = $request->getQueryParams();
      $limit = isset($params['limit']) ? (int)$params['limit'] : 10;
      $offset = isset($params['offset']) ? (int)$params['offset'] : 0;
      if ($limit <= 0) $limit = 10;
      if ($limit > 100) $limit = 100;
      if ($offset < 0) $offset = 0;

      // prendo il totale
      $stmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM transactions WHERE account_id = ?');
      $stmt->bind_param('i', $accountId);
      $stmt->execute();
      $cnt = $stmt->get_result()->fetch_assoc();
      $total = (int)($cnt['total'] ?? 0);
      $stmt->close();

      // prendo le transazioni (solo campi minimi)
      $stmt = $mysqli->prepare('SELECT id, type, amount FROM transactions WHERE account_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?');
      $stmt->bind_param('iii', $accountId, $limit, $offset);
      $stmt->execute();
      $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
      $stmt->close();
      $mysqli->close();

      // risposta semplice come richiesta
      $payload = [
        'account_id' => $accountId,
        'total_transactions' => $total,
        'transactions' => array_map(function($t){
          return [
            'id' => (int)$t['id'],
            'type' => $t['type'],
            'amount' => (float)$t['amount']
          ];
        }, $rows)
      ];

      $response->getBody()->write(json_encode($payload));
      return $response->withHeader('Content-Type','application/json')->withStatus(200);
    }

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
    public function getBalance(Request $request, Response $response, $args){
      // apro connessione al db
      $mysqli = new MySQLi('my_mariadb', 'root', 'ciccio', 'scuola');
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
