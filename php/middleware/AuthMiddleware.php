<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware {
    public function __invoke(Request $request, RequestHandler $handler): Response {
        $correctAuth = hash('sha256', "cicciobello");
        $auth = $request->getHeaderLine('Authorization');

        if (!$auth) {
            $response = new SlimResponse();
            $response->getBody()->write('Unauthorized');
            return $response->withStatus(401);
        }

        if ($auth != $correctAuth) {
            $response = new SlimResponse();
            $response->getBody()->write('Forbidden');
            return $response->withStatus(403);
        }

        return $handler->handle($request);
    }
}


