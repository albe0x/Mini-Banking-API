<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

//Classe usata come base per i controller.
class BaseController
{
    private $mysqli;

    //Connessione al database
    protected function getDbConnection(): mysqli
    {
        if(!$this->mysqli) {
            $this->mysqli = new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');
        }
        return $this->mysqli;
    }

    //Risposta di errore standardizzata
    protected function errorResponse(Response $response, string $message, int $status = 400): Response
    {
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    //Risposta JSON standardizzata
    protected function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    //Metodo per ottenere i dati JSON dal body della richiesta
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