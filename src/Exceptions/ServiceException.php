<?php

namespace Ninex\Lib\Exceptions;

use Exception;

class ServiceException extends Exception
{
    /**
     * 额外的错误数据
     *
     * @var mixed
     */
    protected $data;

    /**
     * 创建异常实例
     *
     * @param string $message 错误消息
     * @param int $code 错误代码
     * @param mixed $data 额外数据
     */
    public function __construct(string $message = '', int $code = 400, $data = null)
    {
        parent::__construct($message, $code);
        $this->data = $data;
    }

    /**
     * 获取额外数据
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }
}
