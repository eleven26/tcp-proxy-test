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

    private $readSocks = [];

    private $writeSocks = [];

    private $except = null;

    private function getResourceId(&$data)
    {
        $identify = substr($data, 0, $this->identityLength);
        $data = substr($data, $this->identityLength);

        return $this->intStringToSockResource($identify);
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
     * 标志位转换回十进制整数
     *
     * @param string $binary
     * @return float|int
     */
    private function intStringToSockResource($binary)
    {
        return bindec($binary);
    }

    protected function select(&$read, &$write)
    {
        $res = socket_select($read, $write, $this->except, null);
        if (false === $res) {
            echo "socket_select() failed, reason: " .
                socket_strerror(socket_last_error()) . "\n";
            exit(-1);
        }

        return $res;
    }
}