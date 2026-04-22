<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

class BaseController
{
    //
    protected function errorResponse(Response $response, string $message, int $status = 400): Response
    {
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    //
    protected function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    //
    protected function getDbConnection(): mysqli
    {
        return new MySQLi('my_mariadb', 'root', 'ciccio', 'banking');
    }

    //
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