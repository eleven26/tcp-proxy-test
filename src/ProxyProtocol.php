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

    private $tmpReads;

    private $tmpWrites;

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
     * 内网 socket 客户端到内网 http 服务的 socket 连接
     *
     * @var array
     */
    private $requestTunnels = [];

    /**
     * 代理服务器与外网浏览器 socket 连接
     * 格式：
     * [
     *      '<socket resource id>' => '<socket resource>'
     * ]
     *
     * @var array
     */
    private $externalSocks = [];

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
     * 根据自定义报文获取关联的 resource id 字符串
     *
     * @param string $data 报文字符串
     * @return int
     */
    private function getResourceIdStr(&$data)
    {
        $identify = substr($data, 0, $this->identityLength);
        $data = substr($data, $this->identityLength);

        return $identify;
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
        foreach ($reads as $key => $read) {
            if (!is_resource($read)) {
                unset($reads[$key]);
            }
        }
        foreach ($writes as $key => $write) {
            if (!is_resource($write)) {
                unset($writes[$key]);
            }
        }

        $res = socket_select($reads, $writes, $this->except, null);
        if (false === $res) {
            echo "socket_select() failed, reason: " . socket_strerror(socket_last_error()) . PHP_EOL;
            exit(-1);
        }

        return $res;
    }

    /**
     * 把 socket 资源从 socket_select 列表移除
     *
     * @param resource $sock
     */
    protected function removeSock($sock)
    {
        if (!is_resource($sock)) {
            unset($this->requestTunnels[(int) $sock]);
            unset($this->externalSocks[(int) $sock]);
            return;
        }

        foreach ($this->tmpReads as $key => $readSock) {
            if ($readSock === $sock) {
                if (is_resource($sock)) socket_close($sock);
                unset($this->tmpReads[$key]);
            }
        }

        foreach ($this->tmpWrites as $key => $writeSock) {
            if ($writeSock === $sock) {
                if (is_resource($sock)) socket_close($sock);
                unset($this->tmpWrites[$key]);
            }
        }

        foreach ($this->readSocks as $key => $readSock) {
            if ($readSock === $sock) {
                if (is_resource($sock)) socket_close($sock);
                unset($this->readSocks[$key]);
            }
        }
        foreach ($this->writeSocks as $key => $writeSock) {
            if ($writeSock === $sock) {
                if (is_resource($sock)) socket_close($sock);
                unset($this->writeSocks[$key]);
            }
        }

        unset($this->requestTunnels[(int) $sock]);
        unset($this->externalSocks[(int) $sock]);
    }
}