<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TransactionsController
{
    //fortux
    public function getTransaction(Request $request, Response $response, $args){
      // apro connessione al database
      $mysqli = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');

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
    public function makeDeposit(Request $request, Response $response, $args){
      return $this->makeTransaction($request, $response, $args, "deposit");
    }

    //ALBE0X
    public function makeWithdrawal(Request $request, Response $response, $args){
      return  $this->makeTransaction($request, $response, $args, "withdrawal");
    }

    //ALBE0X
public function makeTransaction(Request $request, Response $response, $args, $type){
    $mysqli = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');

    if ($mysqli->connect_error) {
        $response->getBody()->write(json_encode(['error' => 'DB error']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    // CHATGPT DICE CHE SNEZA $request->getParsedBody() RITORNA NULL. ADesso parlo io
    // NEW (manually parse JSON)
    $body = $request->getBody()->getContents();
    $data = json_decode($body, true);
    if ($data === null) {
        $data = $request->getParsedBody() ?? [];
    }

    $account_id  = (int)($args['id'] ?? 0);
    $amount      = (float)($data['amount'] ?? 0);
    $description = $data['description'] ?? '';
    $created_at  = date('Y-m-d');

    if ($account_id <= 0) {
        $response->getBody()->write(json_encode(['error' => 'Invalid account id']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    // Insert transaction
    $sql = "INSERT INTO transactions (account_id, type, amount, description, created_at) VALUES (?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("isdss", $account_id, $type, $amount, $description, $created_at);
    $insertOk = $stmt->execute();
    $insertError = $stmt->error;
    $insertRows = $stmt->affected_rows;
    $stmt->close();

    // Update balance_after
    $amountWithSign = ($type == "withdrawal") ? -$amount : $amount;
    $sql = "UPDATE accounts SET balance_after = balance_after + ? WHERE id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("di", $amountWithSign, $account_id);
    $updateOk = $stmt->execute();
    $updateError = $stmt->error;
    $updateRows = $stmt->affected_rows;
    $stmt->close();
    $mysqli->close();

    // DEBUG RESPONSE
    $response->getBody()->write(json_encode([
        'success' => true,
        'debug' => [
            'account_id' => $account_id,
            'amount' => $amount,
            'amountWithSign' => $amountWithSign,
            'type' => $type,
            'insert_ok' => $insertOk,
            'insert_error' => $insertError,
            'insert_rows' => $insertRows,
            'update_ok' => $updateOk,
            'update_error' => $updateError,
            'update_rows' => $updateRows
        ]
    ]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
}

    //ALBE0X
    public function makeTransactionNoDebug(Request $request, Response $response, $args, $type){
        $mysqli = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');

        if ($mysqli->connect_error) {
            $response->getBody()->write(json_encode(['error' => 'DB error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $data = $request->getParsedBody();
        $account_id  = (int)($args['id'] ?? 0);      // FIXED: cast to int
        $amount      = (float)($data['amount'] ?? 0); // FIXED: cast to float
        $description = $data['description'] ?? '';
        $created_at  = date('Y-m-d');

        // Validate
        if ($account_id <= 0) {
            $response->getBody()->write(json_encode(['error' => 'Invalid account id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Insert transaction
        $sql = "INSERT INTO transactions (account_id, type, amount, description, created_at) VALUES (?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("isdss", $account_id, $type, $amount, $description, $created_at);

        if (!$stmt->execute()) {
            $response->getBody()->write(json_encode(['error' => 'Insert failed']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->close();

        // Update balance_after - FIXED: proper integer casting
        $amountWithSign = ($type == "withdrawal") ? -$amount : $amount;
        $sql = "UPDATE accounts SET balance_after = balance_after + ? WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("di", $amountWithSign, $account_id);
        $stmt->execute();
        $stmt->close();
        $mysqli->close();

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }


    //tobe implemented
    public function editTransactionNumber(Request $request, Response $response, $args){
        $response->getBody()->write(json_encode(['error' => 'Not implemented']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(501);
    }

    public function deleteTransactionNumber(Request $request, Response $response, $args){
        $response->getBody()->write(json_encode(['error' => 'Not implemented']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(501);
    }

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
      $stmt = $mysqli->prepare('SELECT id, currency, balance_after AS balance FROM accounts WHERE id = ?');
      $stmt->bind_param('i', $accountId);
      $stmt->execute();
      $res = $stmt->get_result();
      $account = $res->fetch_assoc();
      $stmt->close();
      if (!$account) {
        $response->getBody()->write(json_encode(['error'=>'Account not found']));
        return $response->withHeader('Content-Type','application/json')->withStatus(404);
      }



      // ritorno solo le info richieste (studente)
      $payload = [
        'account_id' => $accountId,
        'currency' => $account['currency'] ?? null,
        'balance' => $account['balance']
      ];

      $response->getBody()->write(json_encode($payload));
      return $response->withHeader('Content-Type','application/json')->withStatus(200);
    }

}
