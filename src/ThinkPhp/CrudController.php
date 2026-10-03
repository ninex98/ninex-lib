<?php

namespace Ninex\Lib\ThinkPhp;

use Closure;
use Ninex\Lib\Core\CrudService;
use Ninex\Lib\Core\Result;
use Ninex\Lib\Core\ServiceException;
use think\{App, Container, Request, Response};
use think\exception\Handle;
use Throwable;

abstract class CrudController
{
    protected CrudService $service;
    private ?Closure $serviceFactory = null;

    public function __construct(protected Request $request, CrudService|Closure $service)
    {
        if ($service instanceof Closure) {
            $this->serviceFactory = $service;
        } else {
            $this->service = $service;
        }
    }

    public function index(): Response
    {
        return $this->respond(fn () => $this->service->paginate($this->request->get())->toArray());
    }

    public function read($id): Response
    {
        return $this->respond(fn () => $this->service->show($id));
    }

    public function save(): Response
    {
        return $this->respond(fn () => $this->service->store($this->request->post()), 201);
    }

    public function update($id): Response
    {
        return $this->respond(fn () => $this->service->update($id, $this->request->put()));
    }

    public function delete($id): Response
    {
        return $this->respond(function () use ($id) {
            $this->service->destroy($id);
            return [];
        });
    }

    /** Catch action errors before ThinkPHP's pipeline renders them through the host handler. */
    protected function respond(Closure $operation, int $status = 200): Response
    {
        $app = Container::getInstance();
        $legacy = $app instanceof App && $app->config->get('ninexlib.legacy_http_200', false);
        try {
            if ($this->serviceFactory) {
                $this->service = ($this->serviceFactory)();
            }
            return Response::create(Result::success($operation()), 'json', $legacy ? 200 : $status);
        } catch (Throwable $e) {
            if ($app instanceof App && !$e instanceof ServiceException) {
                try {
                    $app->make(Handle::class)->report($e);
                } catch (Throwable) { /* A logging outage must not replace the original response. */
                }
            }
            return ExceptionHandler::jsonResponse($e, $legacy);
        }
    }
}
