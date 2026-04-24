<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

//Classe usata come base per i controller.
class BaseController
{
    private $mysqli;

    //Connessione al database mariadb
    protected function getDbConnection(): mysqli
    {
        if(!$this->mysqli) {
            $this->mysqli = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');
        }
        return $this->mysqli;
    }

    //Metodo per trovare account
    protected function findAccount($mysqli, $accountId)
    {
        $stmt = $mysqli->prepare("SELECT id, currency, balance FROM accounts WHERE id = ?");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    //Metodo per aggiornare saldo
    protected function updateBalance($mysqli, $accountId, $amount)
    {
        $stmt = $mysqli->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $accountId);
        return $stmt->execute();
    }

    //Metodo per trovare transazione
    protected function findTransaction($mysqli, $transactionId, $accountId)
    {
        $stmt = $mysqli->prepare("SELECT * FROM transactions WHERE id = ? AND account_id = ?");
        $stmt->bind_param("ii", $transactionId, $accountId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    //Risposta di errore standard
    protected function errorResponse(Response $response, string $message, int $status = 400): Response
    {
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    //Risposta in formato JSON
    protected function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    //Lettura dati dal body
    protected function getJsonBody(Request $request): array
    {
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);
        if ($data === null) {
            $data = $request->getParsedBody() ?? [];
        }
        return $data;
    }
}