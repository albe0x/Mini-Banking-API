<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TransactionsController extends BaseController
{
	//fortux
	public function getTransaction(Request $request, Response $response, $args)
	{
		// DB connection
		$mysqli = $this->getDbConnection();

		// Get account ID
		$accountId = isset($args['id']) ? (int)$args['id'] : 0;
		if ($accountId <= 0) {
			return $this->errorResponse($response, 'Invalid account id');
		}

		// Fetch all transactions
		$stmt = $mysqli->prepare('SELECT * FROM transactions WHERE account_id = ? ORDER BY created_at DESC');
		$stmt->bind_param('i', $accountId);
		$stmt->execute();
		$res = $stmt->get_result();
		$transactions = $res->fetch_all(MYSQLI_ASSOC);

		// Return JSON response
		return $this->jsonResponse($response, $transactions, 200);
	}


	//fortux
	public function getTransactionNumber(Request $request, Response $response, $args)
	{
		// DB connection
		$mysqli = $this->getDbConnection();

		// Validate transaction ID
		if ($args['transaction_id'] <= 0) {
			return $this->errorResponse($response, 'Invalid transaction id');
		}

		// Fetch single transaction
		$stmt = $mysqli->prepare('SELECT t.*, a.currency FROM transactions t LEFT JOIN accounts a ON t.account_id = a.id WHERE t.id = ?');
		if (!$stmt) {
			$err = $mysqli->error;
			return $this->errorResponse($response, 'Failed to prepare statement: ' . $err, 500);
		}

		$stmt->bind_param('i', $args['transaction_id']);
		$stmt->execute();
		$res = $stmt->get_result();
		$transaction = $res->fetch_assoc();

		// Handle not found
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

		// Get request data
		$data = $this->getJsonBody($request);
		$accountId  = (int)($args['id'] ?? 0);
		$amount     = (float)($data['amount'] ?? 0);
		$description = $data['description'] ?? '';
		$created_at  = date('Y-m-d');

		// Validate input data
		if($amount <= 0){
			return $this->errorResponse($response, 'Invalid amount');
		}

		// Find existing account
		$account = $this->findAccount($mysqli, $accountId);
		if (!$account) {
			return $this->errorResponse($response, 'Invalid account id');
		}

		// Check sufficient balance
		if ($type == "withdrawal" && $account['balance'] < $amount) {
			return $this->errorResponse($response, 'Insufficient balance', 422);
		}

		// Insert new transaction
		$stmt = $mysqli->prepare("INSERT INTO transactions (account_id, type, amount, description, created_at) VALUES (?, ?, ?, ?, ?)");
		$stmt->bind_param("isdss", $accountId, $type, $amount, $description, $created_at);
		$stmt->execute();

		// Update account balance
		$this->updateBalance($mysqli, $accountId, ($type == "withdrawal" ? -$amount : $amount));

		return $this->jsonResponse($response, ['success' => true], 201);
	}

	//ALBE0X
	public function editTransactionNumber(Request $request, Response $response, $args)
	{
		// DB connection
		$mysqli = $this->getDbConnection();
		$transactionId = (int)($args['transaction_id'] ?? 0);
		$accountId = (int)($args['id'] ?? 0);

		// Find existing transaction
		$transaction = $this->findTransaction($mysqli, $transactionId, $accountId);

		if (!$transaction) {
			return $this->errorResponse($response, 'Transaction not found', 404);
		}

		// Get updated description
		$data = $this->getJsonBody($request);
		$description = $data['description'] ?? $transaction['description'];

		// Update transaction record
		$stmt = $mysqli->prepare("UPDATE transactions SET description = ? WHERE id = ? AND account_id = ?");
		$stmt->bind_param("sii", $description, $transactionId, $accountId);
		$stmt->execute();

		return $this->jsonResponse($response, ['success' => true]);
	}

	//ALBE0X
	public function deleteTransactionNumber(Request $request, Response $response, $args)
	{
		// DB connection
		$mysqli = $this->getDbConnection();
		$accountId = (int)($args['id'] ?? 0);
		$transactionId = (int)($args['transaction_id'] ?? 0);

		// Find existing transaction
		$transaction = $this->findTransaction($mysqli, $transactionId, $accountId);

		if (!$transaction) {
			return $this->errorResponse($response, 'Transaction not found', 404);
		}

		// Calculate reversal amount
		$rev = ($transaction['type'] == "withdrawal") ? $transaction['amount'] : -$transaction['amount'];

		// Update account balance
		$this->updateBalance($mysqli, $accountId, $rev);

		// Delete transaction record
		$stmt = $mysqli->prepare("DELETE FROM transactions WHERE id = ? AND account_id = ?");
		$stmt->bind_param("ii", $transactionId, $accountId);
		$stmt->execute();

		return $this->jsonResponse($response, ['success' => true]);
	}

	//fortux
	public function getBalance(Request $request, Response $response, $args)
	{
		// DB connection
		$mysqli = $this->getDbConnection();
		$accountId = (int)($args['id'] ?? 0);

		// Validate account ID
		if ($accountId <= 0) {
			return $this->errorResponse($response, 'Invalid account id');
		}

		// Fetch account data
		$account = $this->findAccount($mysqli, $accountId);

		if (!$account) {
			return $this->errorResponse($response, 'Account not found', 404);
		}

		return $this->jsonResponse($response, $account, 200);
	}

}
