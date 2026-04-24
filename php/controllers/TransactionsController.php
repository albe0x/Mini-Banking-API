<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TransactionsController extends Helper
{
	//fortux
	public function getTransaction(Request $request, Response $response, $args)
	{
		// DB connection
		$mysqli = $this->getDbConnection();

		// qui "id" è l'id dell'account di cui vogliamo tutte le transazioni
		$accountId = isset($args['id']) ? (int)$args['id'] : 0;
		if ($accountId <= 0) {
			return $this->errorResponse($response, 'Invalid account id');
		}

		// DB query
		$stmt = $mysqli->prepare('SELECT * FROM transactions WHERE account_id = ? ORDER BY created_at DESC');
		$stmt->bind_param('i', $accountId);
		$stmt->execute();
		$res = $stmt->get_result();
		$transactions = $res->fetch_all(MYSQLI_ASSOC);

		return $this->jsonResponse($response, $transactions, 200);
	}


	//fortux
	public function getTransactionNumber(Request $request, Response $response, $args)
	{
		// DB connection
		$mysqli = $this->getDbConnection();

		if ($args['transaction_id'] <= 0) {
			$mysqli->close();
			return $this->errorResponse($response, 'Invalid transaction id');
		}

		$stmt = $mysqli->prepare('SELECT t.*, a.currency FROM transactions t LEFT JOIN accounts a ON t.account_id = a.id WHERE t.id = ?');
		if (!$stmt) {
			$err = $mysqli->error;
			$mysqli->close();
			return $this->errorResponse($response, 'Failed to prepare statement: ' . $err, 500);
		}

		$stmt->bind_param('i', $args['transaction_id']);
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
	public function makeDeposit(Request $request, Response $response, $args)
	{
		return $this->makeTransaction($request, $response, $args, "deposit");
	}

	//ALBE0X
	public function makeWithdrawal(Request $request, Response $response, $args)
	{
		return $this->makeTransaction($request, $response, $args, "withdrawal");
	}

	//ALBE0X
	protected function makeTransaction(Request $request, Response $response, $args, $type)
	{
		// DB connection
		$mysqli = $this->getDbConnection();

		// Get data from request
		$data = $this->getJsonBody($request);
		$account_id  = (int)($args['id'] ?? 0);
		$amount      = (float)($data['amount'] ?? 0);
		$description = $data['description'] ?? '';
		$created_at  = date('Y-m-d');

		// Check If account_id is valid
		$stmt = $mysqli->prepare("SELECT 1 FROM accounts WHERE id = ?");
		$stmt->bind_param("i", $id);
		$stmt->execute();
		$res = $stmt->get_result();
		if ($res->num_rows == 0) {
			return $this->errorResponse($response, 'Invalid account id');
		}

		// Check If ammount is negative
		if($amount < 0){
			return $this->errorResponse($response, 'Invalid ammount');
		}

		// DB insert
		$stmt = $mysqli->prepare("INSERT INTO transactions (account_id, type, amount, description, created_at) VALUES (?, ?, ?, ?, ?)");
		$stmt->bind_param("isdss", $account_id, $type, $amount, $description, $created_at);
		$stmt->execute();

		// DB update balance
		$amountWithSign = ($type == "withdrawal") ? -$amount : $amount;
		$sql = "UPDATE accounts SET balance = balance + ? WHERE id = ?";
		$stmt = $mysqli->prepare($sql);
		if (!$stmt) {
			$mysqli->close();
			return $this->errorResponse($response, 'Failed to prepare update', 500);
		}
		$stmt->bind_param("di", $amountWithSign, $account_id);
		$stmt->execute();

		// Confitmation response
		return $this->jsonResponse($response, ['success' => true], 201);
	}

	//tobe implemented
	public function editTransactionNumber(Request $request, Response $response, $args)
	{
		// DB connection
		$mysqli = $this->getDbConnection();

		// Get transaction
		$stmt = $mysqli->prepare("SELECT * FROM transactions WHERE id = ? AND account_id = ?");
		$stmt->bind_param("ii", $args['transaction_id'], $args['id']);
		$stmt->execute();
		$res = $stmt->get_result();
		$transaction = $res->fetch_assoc();

		// Set new data
		$data = $this->getJsonBody($request);
		$amount      = (float)	($data['amount'] 	?? $transaction['amount']);
		$description = $data['description'] 		?? $transaction['description'];
		$created_at  = date('Y-m-d') 				?? $transaction['created_at'];

		// DB update
		$stmt = $mysqli->prepare("UPDATE transactions SET type = ?, amount = ?, description = ?, created_at = ? WHERE id = ? AND account_id = ?");
		$stmt->bind_param("sdssii", $type, $amount, $description, $created_at, $args['transaction_id'], $args['id']);
		$stmt->execute();

		// Update balance
		$amountWithSign = ($type == "withdrawal") ? -$amount : $amount;
		$stmt = $mysqli->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
		$stmt->bind_param("di", $amountWithSign, $args['id']);
		$stmt->execute();

		return $this->jsonResponse($response, ['success' => true], 201);
	}

	public function deleteTransactionNumber(Request $request, Response $response, $args)
	{
		// DB connection
		$mysqli = $this->getDbConnection();

		//Get the transaction to be deleted
		$stmt = $mysqli->prepare("SELECT amount, type FROM transactions WHERE id = ? AND account_id = ?");
		$stmt->bind_param("ii", $args['transaction_id'], $args['id']);
		$stmt->execute();
		$res = $stmt->get_result();
		$transaction = $res->fetch_assoc();

		if (!$transaction) {
			$response->getBody()->write(json_encode(['error' => 'Transaction not found']));
			return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
		}

		$reversalAmount = ($transaction['type'] == "withdrawal") ? $transaction['amount'] : -$transaction['amount'];

		// 3. Update the Account Balance
		$stmt = $mysqli->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
		$stmt->bind_param("di", $reversalAmount, $args['id']);
		$stmt->execute();

		// 4. Delete the Transaction record
		$stmt = $mysqli->prepare("DELETE FROM transactions WHERE id = ? AND account_id = ?");
		$stmt->bind_param("ii", $args['transaction_id'], $args['id']);
		$stmt->execute();

		// Return Success
		return $this->jsonResponse($response, ['success' => true], 201);
	}
	//fortux
	public function getBalance(Request $request, Response $response, $args)
	{
		$mysqli = $this->getDbConnection();

		$accountId = (int)($args['id'] ?? 0);
		if ($accountId <= 0) {
			$mysqli->close();
			return $this->errorResponse($response, 'Invalid account id');
		}

		$stmt = $mysqli->prepare('SELECT id, currency, balance FROM accounts WHERE id = ?');

		$stmt->bind_param('i', $accountId);
		$stmt->execute();
		$res = $stmt->get_result();
		$account = $res->fetch_assoc();
		$stmt->close();
		$mysqli->close();

		if (!$account) {
			return $this->errorResponse($response, 'Account not found', 404);
		}

		return $this->jsonResponse($response, $account, 200);
	}

}
