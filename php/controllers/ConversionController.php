<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ConversionController extends BaseController
{

    //FRED
    public function convertFiat(Request $request, Response $response, array $args): Response
    {
        $mysqli = $this->getDbConnection();
        $accountId = (int)($args['id'] ?? 0);
        $params = $request->getQueryParams();
        $to = strtoupper($params['to'] ?? '');

        // 400 - Valuta target mancante
        if (!$to) {
            return $this->jsonResponse($response, ['error' => 'valuta target mancante'], 400);
        }

        // Recupero l'account e il saldo
        $account = $this->findAccount($mysqli, $accountId);

        // 404 - Conto non trovato
        if (!$account) {
            return $this->jsonResponse($response, ['error' => 'conto non trovato'], 404);
        }

        $from = strtoupper($account['currency']);
        $balance = (float)$account['balance'];

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
            'rate' => $rate,
            'date' => $data['date'] ?? null
        ]);
    }

    //FRED
    public function convertCrypto(Request $request, Response $response, array $args): Response
    {
        $mysqli = $this->getDbConnection();
        $accountId = (int)($args['id'] ?? 0);
        $params = $request->getQueryParams();
        $to = strtoupper($params['to'] ?? '');

        if (!$to) {
            return $this->jsonResponse($response, ['error' => 'valuta target mancante'], 400);
        }

        // Recupero l'account e il saldo
        $account = $this->findAccount($mysqli, $accountId);

        if (!$account) {
            return $this->jsonResponse($response, ['error' => 'conto non trovato'], 404);
        }

        $from = strtoupper($account['currency']);
        $balance = (float)$account['balance'];
        
        // Es: BTC + EUR (Crypto Base + Currency Quote per Binance)
        $symbol = $to . $from; 

        // Chiamata API Binance
        $url = "https://api.binance.com/api/v3/ticker/price?symbol={$symbol}";
        
        // Sopprimo il warning di file_get_contents per catturare l'errore HTTP
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

        $rate = (float)$data['price'];
        $converted = round($balance / $rate, 8); // Quantità crypto = saldo / prezzo

        return $this->jsonResponse($response, [
            'account_id' => $accountId,
            'provider' => 'Binance',
            'conversion_type' => 'crypto',
            'from_currency' => $from,
            'to_crypto' => $to,
            'market_symbol' => $symbol,
            'original_balance' => $balance,
            'price' => $rate,
            'converted_amount' => $converted
        ]);
    }
}