<?php

namespace OliviaRouter;

class RouterDispatcher
{
    private array $routes;
    private RouterConfig $config;
    private Trie $trie;
    private array $clauses;
    private bool $matched = false;

    public function __construct(array $routes, $configOrClauses = [], ?Trie $trie = null, array $clauses = [])
    {
        $this->routes = $routes;
        $this->trie = $trie ?? new Trie();

        if ($configOrClauses instanceof RouterConfig) {
            $this->config = $configOrClauses;
            $this->clauses = $clauses;
            return;
        }

        $this->config = RouterConfig::fromGlobals();
        $this->clauses = is_array($configOrClauses) ? $configOrClauses : [];
    }

    public function dispatch($request_data)
    {
        $request = $request_data instanceof Request
            ? $request_data
            : Request::fromArray((array) $request_data);

        $this->matched = false;
        $this->publishNotFoundState(true);

        foreach ($this->routes as $route) {
            $route = $this->normalizeRoute($route);

            if (!$route->matches($request, $this->trie)) {
                continue;
            }

            // CSRF antes de qualquer middleware: uma requisição forjada não pode disparar efeitos colaterais
            // de middlewares (log, sessão, auditoria, chamadas externas) antes de ser rejeitada.
            $this->validateCsrf($route, $request);
            if ($this->executeMiddlewares($route->getMiddleware()) === false) {
                $this->matched = true;
                $this->publishNotFoundState(false);
                return;
            }
            $this->executeHandler($route->getControllerMethod(), $route->getParams(), $request);

            $this->matched = true;
            $this->publishNotFoundState(false);
            return;
        }
    }

    private function validateCsrf(Route $route, Request $request): void
    {
        if (!$this->config->isCsrfEnabled() || !in_array($route->getHttpMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $expected = $this->config->getCsrfToken();
        // Presença da chave e valor são coisas distintas: Request::post() devolve null tanto para chave
        // ausente quanto para valor null, então a decisão usa o array bruto.
        $post = $request->getPostData();
        $presente = array_key_exists('_token', $post);
        $token = $presente ? $post['_token'] : null;
        if ($presente && !is_string($token)) {
            // _token presente com tipo inválido (null, array, número...): rejeita, sem cair no header.
            throw new \RuntimeException('CSRF token inválido ou ausente.');
        }
        if (!$presente || $token === '') {
            // Requisições AJAX/JSON enviam o token no header X-CSRF-Token.
            $server = $request->getServerData();
            $token = $server['HTTP_X_CSRF_TOKEN'] ?? null;
        }

        if ($expected === '' || !is_string($token) || !hash_equals($expected, $token)) {
            throw new \RuntimeException('CSRF token inválido ou ausente.');
        }
    }

    public function hasMatched(): bool
    {
        return $this->matched;
    }

    private function executeMiddlewares($middlewares): bool
    {
        if ($middlewares === null) {
            return true;
        }

        $middlewares = is_array($middlewares) ? $middlewares : [$middlewares];

        foreach ($middlewares as $middleware) {
            $middleware = strpos($middleware, '#') === false ? $middleware . '#handle' : $middleware;
            $callMiddleware = $this->callMiddlewareClass($middleware);
            $middlewareInstance = MiddlewareFactory::createMiddleware($callMiddleware['middleware']);

            if (!method_exists($middlewareInstance, $callMiddleware['action'])) {
                throw new \RuntimeException(
                    "Método {$callMiddleware['action']} não encontrado em {$callMiddleware['middleware']}."
                );
            }

            try {
                $result = $middlewareInstance->{$callMiddleware['action']}();
            } catch (MiddlewareHaltException $exception) {
                return false;
            }

            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    private function executeHandler(string $handler, array $params, Request $request): void
    {
        $callHandler = $this->callHandlerClass($handler);
        $handlerInstance = ControllerFactory::createController($callHandler['controller']);

        if (!method_exists($handlerInstance, $callHandler['action'])) {
            throw new \RuntimeException(
                "Método {$callHandler['action']} não encontrado em {$callHandler['controller']}."
            );
        }

        $format = $request->getContentType() ?? 'text/html';
        $mimeType = explode(';', $format)[0];
        $params['format'] = explode('/', $mimeType)[1] ?? 'html';
        $params['method'] = $request->getMethod();

        $handlerInstance->{$callHandler['action']}($params);
    }

    private function callMiddlewareClass(string $str): array
    {
        return $this->resolveCallable($str, $this->config->getMiddlewareFolder());
    }

    private function callHandlerClass(string $str): array
    {
        return $this->resolveCallable($str, $this->config->getControllerFolder());
    }

    private function resolveCallable(string $str, string $defaultFolder): array
    {
        $callables = explode('#', $str);
        $class = trim($callables[0], '\\');
        $action = $callables[1] ?? 'handle';

        if (strpos($class, $this->config->getAppNamespace() . '\\') === 0) {
            $resolvedClass = str_replace('/', '\\', $class);
            return ['controller' => $resolvedClass, 'middleware' => $resolvedClass, 'action' => $action];
        }

        $parts = array_map('ucfirst', array_filter(explode('/', str_replace('\\', '/', $class)), 'strlen'));
        $resolvedClass = $this->config->getAppNamespace()
            . '\\' . $defaultFolder
            . '\\' . implode('\\', $parts);

        return ['controller' => $resolvedClass, 'middleware' => $resolvedClass, 'action' => $action];
    }

    private function normalizeRoute($route): Route
    {
        if ($route instanceof Route) {
            return $route;
        }

        if (is_array($route)) {
            return Route::fromLegacyArray($route);
        }

        throw new \InvalidArgumentException('Formato de rota inválido.');
    }

    private function publishNotFoundState(bool $notFound): void
    {
        $GLOBALS['OLIVIA_ROUTER_404'] = $notFound;

        if (isset($_SESSION) && is_array($_SESSION)) {
            $_SESSION['e404'] = $notFound;
        }
    }
}
