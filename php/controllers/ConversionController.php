<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


//fiat
//crypto

class ConversionController
{
  public function crypto(Request $request, Response $response, $args){
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'scuola');
        
        $app->get('/accounts/{id}/balance/convert/fiat', function (Request $request, Response $response, array $args) use ($mysqli) {
        $accountId = (int)$args['id'];
        $params = $request->getQueryParams();
        $to = strtoupper($params['to'] ?? '');

        //Divisione errori in base al numero
        //400
        //Missing target currency
        if (!$to) {
            $response->getBody()->write(json_encode([
                'error' => 'Missing target currency'
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        //Missing import
        if ($amount === null || $amount === '') {
            $response->getBody()->write(json_encode([
                'error' => 'Importo mancante'
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        // Not valid import
        if (!is_numeric($amount) || (float)$amount <= 0) {
            $response->getBody()->write(json_encode([
                'error' => 'Importo non valido'
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        $stmt = $mysqli->prepare('SELECT id, currency FROM accounts WHERE id = ?');
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        $account = $result->fetch_assoc();

        if (!$account) {
            $response->getBody()->write(json_encode([
                'error' => 'Account not found'
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        }

    $amount = $params['balance_after'] ?? null;


        $from = strtoupper($account['currency']);

        $stmt = $mysqli->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END), 0) AS balance
            FROM transactions
            WHERE account_id = ?
        ");
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $balance = (float)($row['balance'] ?? 0);

        $url = "https://api.frankfurter.dev/v1/latest?base={$from}&symbols={$to}";
        $json = @file_get_contents($url);

        if ($json === false) {
            $response->getBody()->write(json_encode([
                'error' => 'External exchange API unavailable'
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(502);
        }

        $data = json_decode($json, true);

        if (!isset($data['rates'][$to])) {
            $response->getBody()->write(json_encode([
                'error' => 'Target currency not supported'
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        $rate = (float)$data['rates'][$to];
        $converted = round($balance * $rate, 2);

        $response->getBody()->write(json_encode([
            'account_id' => $accountId,
            'provider' => 'Frankfurter',
            'conversion_type' => 'fiat',
            'from_currency' => $from,
            'to_currency' => $to,
            'original_balance' => $balance,
            'converted_balance' => $converted,
            'rate' => $rate,
            'date' => $data['date'] ?? null
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    });
      
  }



}

