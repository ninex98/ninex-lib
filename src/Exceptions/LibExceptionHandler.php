<?php

namespace Ninex\Lib\Exceptions;

use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Exceptions\HttpResponseException;
use Throwable;

/** Keeps the 1.x protected customization points while using the framework's exception lifecycle. */
class LibExceptionHandler extends Handler
{
    public function register(): void
    {
        $this->renderable(function (Throwable $e, $request) {
            if ($e instanceof HttpResponseException || (!$request->expectsJson() && !$request->is('api/*'))) {
                return null;
            }
            return $this->handleApiException($request, $e);
        });
        $this->ignore(\Ninex\Lib\Core\ServiceException::class);
    }

    protected function handleApiException($request, Throwable $e)
    {
        $response = (new ApiExceptionRenderer())($e, $request);
        if ($response === null) {
            return parent::render($request, $e);
        }
        return $response->setData(array_merge($response->getData(true), $this->convertExceptionToArray($e)));
    }

    protected function convertExceptionToArray(Throwable $e): array
    {
        $error = (new ApiExceptionRenderer())->describe($e);
        return ['code' => $this->getExceptionCode($e), 'message' => $this->getExceptionMessage($e), 'data' => $error['data']];
    }

    protected function getExceptionCode(Throwable $e): int
    {
        return (new ApiExceptionRenderer())->describe($e)['code'];
    }
    protected function getExceptionMessage(Throwable $e): string
    {
        return (new ApiExceptionRenderer())->describe($e)['message'];
    }
}
