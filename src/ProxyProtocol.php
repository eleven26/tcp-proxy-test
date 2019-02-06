<?php

trait ProxyProtocol
{
    /**
     * @var int 标志位长度
     */
    private $identityLength = 24;

    /**
     * @var int 每次读取的数据长度
     */
    private $bytesLength = 1000;

    /**
     * @var array socket_select socket to read
     */
    private $readSocks = [];

    /**
     * @var array socket_select socket to write
     */
    private $writeSocks = [];

    /**
     * @var null socket_select exceptions.
     */
    private $except = null;

    /**
     * 根据自定义报文获取关联的 resource id
     *
     * @param string $data 报文字符串
     * @return int
     */
    private function getResourceId(&$data)
    {
        $identify = substr($data, 0, $this->identityLength);
        $data = substr($data, $this->identityLength);

        return bindec($identify);
    }

    /**
     * 标志位（socket resource 转整型后再转二进制，使用4个字节保存，填充前导0）
     *
     * @param resource $sock
     * @return string
     */
    private function sockResourceToIntString($sock)
    {
        $binary = base_convert((int) $sock, 10, 2);

        return str_pad($binary, $this->identityLength, '0', STR_PAD_LEFT);
    }

    /**
     * socket_select wrapper.
     *
     * @param array $reads
     * @param array $writes
     * @return int
     */
    protected function select(&$reads, &$writes)
    {
        $res = socket_select($reads, $writes, $this->except, null);
        if (false === $res) {
            echo "socket_select() failed, reason: " . socket_strerror(socket_last_error()) . PHP_EOL;
            exit(-1);
        }

        return $res;
    }
}