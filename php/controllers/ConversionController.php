<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ConversionController
{
    private function getDbConnection(): mysqli
    {
        
        return new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    // 1. CONVERSIONE FIAT 

    public function convertFiat(Request $request, Response $response, array $args): Response
    {
        $mysqli = $this->getDbConnection();
        $accountId = (int)$args['id'];
        $params = $request->getQueryParams();
        $to = strtoupper($params['to'] ?? '');

        // 400 - Valuta target mancante
        if (!$to) {
            return $this->jsonResponse($response, ['error' => 'valuta target mancante'], 400);
        }

        // Recupero l'account
        $stmt = $mysqli->prepare('SELECT id, currency FROM accounts WHERE id = ?');
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();

        // 404 - Conto non trovato
        if (!$account) {
            return $this->jsonResponse($response, ['error' => 'conto non trovato'], 404);
        }

        $from = strtoupper($account['currency']);

        // Calcolo Saldo
        $stmt = $mysqli->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END), 0) AS balance
            FROM transactions
            WHERE account_id = ?
        ");
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $balance = (float)($stmt->get_result()->fetch_assoc()['balance'] ?? 0);

        // Chiamata API Frankfurter
        $url = "https://api.frankfurter.dev/v1/latest?base={$from}&symbols={$to}";
        $json = @file_get_contents($url);

        // 502 - Errore API Esterna
        if ($json === false) {
            return $this->jsonResponse($response, ['error' => 'errore nella chiamata a Frankfurter'], 502);
        }

        $data = json_decode($json, true);

        // 400 - Valuta fiat non supportata
        if (!isset($data['rates'][$to])) {
            return $this->jsonResponse($response, ['error' => 'valuta fiat non supportata'], 400);
        }

        $rate = (float)$data['rates'][$to];
        $converted = round($balance * $rate, 2);

        return $this->jsonResponse($response, [
            'account_id' => $accountId,
            'provider' => 'Frankfurter',
            'conversion_type' => 'fiat',
            'from_currency' => $from,
            'to_currency' => $to,
            'original_balance' => $balance,
            'converted_balance' => $converted,
            'rate' => $rate
        ]);
    }

    // 2. CONVERSIONE CRYPTO (Binance API)

    public function convertCrypto(Request $request, Response $response, array $args): Response
    {
        $mysqli = $this->getDbConnection();
        $accountId = (int)$args['id'];
        $params = $request->getQueryParams();
        $to = strtoupper($params['to'] ?? '');

        if (!$to) {
            return $this->jsonResponse($response, ['error' => 'valuta target mancante'], 400);
        }

        $stmt = $mysqli->prepare('SELECT id, currency FROM accounts WHERE id = ?');
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();

        if (!$account) {
            return $this->jsonResponse($response, ['error' => 'conto non trovato'], 404);
        }

        $from = strtoupper($account['currency']);
        
        // Es: EURUSDT (Base + Target per Binance)
        $symbol = $from . $to; 

        // Chiamata API Binance
        $url = "https://api.binance.com/api/v3/ticker/price?symbol={$symbol}";
        
        // Sopprimo il warning di file_get_contents per catturare l'errore HTTP (es. 400 da binance se la coppia non esiste)
        $context = stream_context_create(['http' => ['ignore_errors' => true]]);
        $json = @file_get_contents($url, false, $context);

        // 502 - Errore connettività Binance
        if ($json === false) {
            return $this->jsonResponse($response, ['error' => 'errore nella chiamata a Binance'], 502);
        }

        $data = json_decode($json, true);

        // 400 - Coppia Binance non valida / Crypto non supportata
        if (isset($data['code']) || !isset($data['price'])) {
            return $this->jsonResponse($response, ['error' => 'coppia Binance non valida o crypto target non supportata'], 400);
        }

        // Il resto della logica del saldo...
        $rate = (float)$data['price'];
        return $this->jsonResponse($response, [
            'provider' => 'Binance',
            'symbol' => $symbol,
            'rate' => $rate
        ]);
    }

    // 3. ESEMPIO PRELIEVO (Gestione Importi/Saldo)

    public function withdraw(Request $request, Response $response, array $args): Response
    {
        $accountId = (int)$args['id'];
        $body = json_decode($request->getBody()->getContents(), true);
        $amount = $body['amount'] ?? null;

        // 400 - Importo mancante
        if ($amount === null || $amount === '') {
            return $this->jsonResponse($response, ['error' => 'importo mancante'], 400);
        }

        // 400 - Importo non valido
        if (!is_numeric($amount) || (float)$amount <= 0) {
            return $this->jsonResponse($response, ['error' => 'importo non valido'], 400);
        }

        $amount = (float)$amount;
        $mysqli = $this->getDbConnection();

        // 404 - Controllo Conto
        $stmt = $mysqli->prepare('SELECT id FROM accounts WHERE id = ?');
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            return $this->jsonResponse($response, ['error' => 'conto non trovato'], 404);
        }

        // Calcolo del saldo disponibile per la verifica
        $stmt = $mysqli->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END), 0) AS balance
            FROM transactions
            WHERE account_id = ?
        ");
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $balance = (float)($stmt->get_result()->fetch_assoc()['balance'] ?? 0);

        // 422 - Prelievo superiore al saldo
        if ($amount > $balance) {
            return $this->jsonResponse($response, ['error' => 'prelievo superiore al saldo disponibile'], 422); // o 400
        }

        // Inserimento prelievo (Logica mock)
        return $this->jsonResponse($response, ['status' => 'success', 'message' => 'Prelievo effettuato']);
    }
}