<?php


namespace Happy\Clock\Service;


class BaseService
{
    const STATUS_SUCCESS = 1;
    const STATUS_FAIL = 0;

    protected $status = self::STATUS_SUCCESS;

    protected $message = '业务操作成功';

    protected $data = [];

    protected function pipeline()
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'data' => $this->data
        ];
    }
}
