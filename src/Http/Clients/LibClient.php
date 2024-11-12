<?php

namespace Ninex\Lib\Http\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use Ninex\Lib\Exceptions\ServiceException;

abstract class LibClient
{
    /**
     * HTTP 客户端实例
     */
    protected Client $client;

    /**
     * 基础配置
     */
    protected array $config = [
        'base_uri' => '',
        'timeout' => 30,
        'connect_timeout' => 10,
        'http_errors' => false,
        'verify' => false,
    ];

    /**
     * 请求头
     */
    protected array $headers = [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];

    /**
     * 构造函数
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
        $this->initClient();
    }

    /**
     * 初始化客户端
     */
    protected function initClient(): void
    {
        $this->client = new Client($this->config);
    }

    /**
     * 发送请求
     *
     * @throws ServiceException
     */
    protected function request(string $method, string $uri, array $options = []): array
    {
        try {
            // 合并默认头部
            $options['headers'] = array_merge(
                $this->headers,
                $options['headers'] ?? []
            );

            // 发送请求
            $response = $this->client->request($method, $uri, $options);

            // 获取响应内容
            $contents = $response->getBody()->getContents();
            $result = json_decode($contents, true);

            // 检查响应
            $this->checkResponse($result, $response->getStatusCode());

            return $result;
        } catch (GuzzleException $e) {
            throw $this->handleRequestException($e);
        }
    }

    /**
     * GET 请求
     *
     * @throws ServiceException
     */
    protected function get(string $uri, array $query = [], array $options = []): array
    {
        return $this->request('GET', $uri, array_merge(
            $options,
            ['query' => $query]
        ));
    }

    /**
     * POST 请求
     *
     * @throws ServiceException
     */
    protected function post(string $uri, array $data = [], array $options = []): array
    {
        return $this->request('POST', $uri, array_merge(
            $options,
            ['json' => $data]
        ));
    }

    /**
     * PUT 请求
     *
     * @throws ServiceException
     */
    protected function put(string $uri, array $data = [], array $options = []): array
    {
        return $this->request('PUT', $uri, array_merge(
            $options,
            ['json' => $data]
        ));
    }

    /**
     * DELETE 请求
     *
     * @throws ServiceException
     */
    protected function delete(string $uri, array $options = []): array
    {
        return $this->request('DELETE', $uri, $options);
    }

    /**
     * 检查响应
     *
     * @throws ServiceException
     */
    protected function checkResponse(array $result, int $statusCode): void
    {
        // 检查 HTTP 状态码
        if ($statusCode >= 400) {
            throw new ServiceException(
                '请求失败：' . Arr::get($result, 'message', '未知错误'),
                $statusCode
            );
        }

        // 检查业务状态码（根据实际 API 响应格式调整）
        $code = Arr::get($result, 'code', 0);
        if ($code !== 0) {
            throw new ServiceException(
                Arr::get($result, 'message', '业务处理失败'),
                $code
            );
        }
    }

    /**
     * 处理请求异常
     */
    protected function handleRequestException(GuzzleException $e): ServiceException
    {
        return new ServiceException(
            '请求异常：' . $e->getMessage(),
            $e->getCode() ?: 500
        );
    }

    /**
     * 设置请求头
     */
    protected function setHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * 设置配置
     */
    protected function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        $this->initClient();
        return $this;
    }
}
