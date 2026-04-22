<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TransactionsController extends Helper
{
	//fortux
	public function getTransaction(Request $request, Response $response, $args){
		$mysqli = $this->getDbConnection();

		if ($mysqli->connect_error) {
			$mysqli->close();
			return $this->errorResponse($response, 'Database connection failed: ' . $mysqli->connect_error, 500);
		}

	// qui "id" è l'id dell'account di cui vogliamo tutte le transazioni
	$accountId = isset($args['id']) ? (int)$args['id'] : 0;
	if ($accountId <= 0) {
		$mysqli->close();
		return $this->errorResponse($response, 'Invalid account id');
	}

	$stmt = $mysqli->prepare('SELECT * FROM transactions WHERE account_id = ? ORDER BY created_at DESC');
	if (!$stmt) {
		$err = $mysqli->error;
		$mysqli->close();
		return $this->errorResponse($response, 'Failed to prepare statement: ' . $err, 500);
	}

	$stmt->bind_param('i', $accountId);
	$stmt->execute();
	$res = $stmt->get_result();
	$transactions = $res->fetch_all(MYSQLI_ASSOC);

	$stmt->close();
	$mysqli->close();

	// anche se vuoto ritorniamo array ([]) con 200
	$stmt->close();
	$mysqli->close();
	return $this->jsonResponse($response, $transactions, 200);
	}
	//fortux

	public function getTransactionNumber(Request $request, Response $response, $args){
	$mysqli = $this->getDbConnection();
	if ($mysqli->connect_error) {
		$mysqli->close();
		return $this->errorResponse($response, 'DB error: ' . $mysqli->connect_error, 500);
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
		return $this->errorResponse($response, 'Invalid transaction id');
	}

	$stmt = $mysqli->prepare('SELECT t.*, a.currency FROM transactions t LEFT JOIN accounts a ON t.account_id = a.id WHERE t.id = ?');
	if (!$stmt) {
		$err = $mysqli->error;
		$mysqli->close();
		return $this->errorResponse($response, 'Failed to prepare statement: ' . $err, 500);
	}

	$stmt->bind_param('i', $txId);
	$stmt->execute();
	$res = $stmt->get_result();
	$transaction = $res->fetch_assoc();

	$stmt->close();
	$mysqli->close();

	if (!$transaction) {
		return $this->errorResponse($response, 'Transaction not found', 404);
	}

	return $this->jsonResponse($response, $transaction, 200);
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
		$mysqli = $this->getDbConnection();

		if ($mysqli->connect_error) {
			$mysqli->close();
			return $this->errorResponse($response, 'DB error', 500);
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
		return $this->errorResponse($response, 'Invalid account id');
		}

		if($amount < 0){
		return $this->errorResponse($response, 'Invalid ammount');
		}

		// Insert transaction
		$sql = "INSERT INTO transactions (account_id, type, amount, description, created_at) VALUES (?, ?, ?, ?, ?)";
		$stmt = $mysqli->prepare($sql);
		$stmt->bind_param("isdss", $account_id, $type, $amount, $description, $created_at);
		$insertOk = $stmt->execute();
		$insertError = $stmt->error;
		$insertRows = $stmt->affected_rows;
		$stmt->close();

		// Update balance
		$amountWithSign = ($type == "withdrawal") ? -$amount : $amount;
		$sql = "UPDATE accounts SET balance = balance + ? WHERE id = ?";
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
		$mysqli = $this->getDbConnection();

		if ($mysqli->connect_error) {
			$mysqli->close();
			return $this->errorResponse($response, 'DB error', 500);
		}

		$data = $request->getParsedBody();
		$account_id  = (int)($args['id'] ?? 0);      // FIXED: cast to int
		$amount      = (float)($data['amount'] ?? 0); // FIXED: cast to float
		$description = $data['description'] ?? '';
		$created_at  = date('Y-m-d');

		// Validate
		if ($account_id <= 0) {
			$mysqli->close();
			return $this->errorResponse($response, 'Invalid account id');
		}

		// Insert transaction
		$sql = "INSERT INTO transactions (account_id, type, amount, description, created_at) VALUES (?, ?, ?, ?, ?)";
		$stmt = $mysqli->prepare($sql);
		$stmt->bind_param("isdss", $account_id, $type, $amount, $description, $created_at);

		if (!$stmt->execute()) {
			$stmt->close();
			$mysqli->close();
			return $this->errorResponse($response, 'Insert failed', 500);
		}
		$stmt->close();

		// Update balance - FIXED: proper integer casting
		$amountWithSign = ($type == "withdrawal") ? -$amount : $amount;
		$sql = "UPDATE accounts SET balance = balance + ? WHERE id = ?";
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
	$mysqli = $this->getDbConnection();
	if ($mysqli->connect_error) {
		$mysqli->close();
		return $this->errorResponse($response, 'DB error', 500);
	}

	// prendo id account
	$accountId = isset($args['id']) ? (int)$args['id'] : 0;
	if ($accountId <= 0) {
		$mysqli->close();
		return $this->errorResponse($response, 'Invalid account id');
	}

	// verifico account e prendo valuta
	$stmt = $mysqli->prepare('SELECT id, currency, balance AS balance FROM accounts WHERE id = ?');
	$stmt->bind_param('i', $accountId);
	$stmt->execute();
	$res = $stmt->get_result();
	$account = $res->fetch_assoc();
	$stmt->close();
	if (!$account) {
		$mysqli->close();
		return $this->errorResponse($response, 'Account not found', 404);
	}



	// ritorno solo le info richieste (studente)
	$payload = [
		'account_id' => $accountId,
		'currency' => $account['currency'] ?? null,
		'balance' => $account['balance']
	];

	$mysqli->close();
	return $this->jsonResponse($response, $payload, 200);
	}

}
