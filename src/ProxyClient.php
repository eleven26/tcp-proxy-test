<?php

require __DIR__ . '/ProxyProtocol.php';

class ProxyClient
{
    use ProxyProtocol;

    /**
     * @var string 代理服务器地址
     */
    private $host = 'ide.baiguiren.com';

    /**
     * @var int 代理服务器端口
     */
    private $port = 8888;

    /**
     * @var resource 代理服务器到内网的连接
     */
    private $clientSocket;

    /**
     * 接收到的外网请求数据
     *
     * @var array
     */
    private $toLocals = [];

    /**
     * 外网请求返回数据
     *
     * @var array
     */
    private $toExternals = [];

    /**
     * @var array
     */
    private $proxyTunnels = [];

    /**
     * 内网 socket 客户端到内网 http 服务的 socket 连接
     *
     * @var array
     */
    private $requestTunnels = [];

    /**
     * ProxyClient constructor.
     */
    public function __construct()
    {
        $this->boot();
    }

    public function handle()
    {
        while (true) {
            // 这两个数组会被改变, 所以用两个临时变量
            $tmpReads = $this->readSocks;
            $tmpWrites = $this->writeSocks;

            $this->select($tmpReads, $tmpWrites);
            if ($tmpReads) {
                $this->handleRead($tmpReads);
            }
            if ($tmpWrites) {
                $this->handleWrite($tmpWrites);
            }
        }
    }

    protected function handleRead($reads)
    {
        // socket 可读的情况
        // 1. 内网 http 服务请求返回
        // 2. 外网请求数据到来 （$this->clientSocket）
        foreach ($reads as $read) {
            $res = socket_getpeername($read, $ip, $port);
            if ($res === false) {
                $this->removeInvalidTunnels($read);
                continue;
            }

            // 读取到数据的两种情况
            // 1. 外网请求，需要转发到内网
            // 2. 内网返回，需要返回给外网
            if ($read == $this->clientSocket) {
                // 从代理服务器获取的数据有标志位，内网 http 返回的数据没有
                $data = socket_read($read, $this->bytesLength + $this->identityLength);
            } else {
                $data = socket_read($read, $this->bytesLength);
            }

            if ($data !== '') {
                echo "receive data from: $ip:$port" . PHP_EOL;
                echo $data;

                if ($read == $this->clientSocket) {
                    $id = $this->getResourceId($data);

                    // 创建到内网 http 服务的 socket 连接
                    $proxySock = $this->createClientSocket('127.0.0.1', 8005);
                    $this->readSocks[(int) $proxySock] = $proxySock;
                    $this->writeSocks[(int) $proxySock] = $proxySock;

                    // 等下从 proxySocket 返回的时候，需要拼接上 id 通过与代理服务器的 socket 连接返回
                    $this->requestTunnels[(int) $proxySock] = $proxySock;
                    $this->proxyTunnels[(int) $proxySock] = $id;
                    // 外网请求
                    $this->toLocals[(int) $proxySock] .= $data;
                } else {
                    $id = $this->sockResourceToIntString($read);
                    // 内网返回
                    // 所有内网返回的数据需要找回 id，把 id 加到头部然后返回给代理服务器
                    $this->toExternals[$id] .= $id . $data;
                }
            } else if ($data === false) {
                echo "socket_read() failed, reason: " .
                    socket_strerror(socket_last_error()) . "\n";
                $this->removeInvalidTunnels($read);
            }
        }
    }

    private function removeInvalidTunnels($sock)
    {
        echo "client close: "  . ((int) $sock) . PHP_EOL;
        unset($this->requestTunnels[(int) $sock]);
        socket_close($sock);
    }

    protected function handleWrite($writes)
    {
        foreach ($writes as $write) {
            if (isset($this->toLocals[(int) $write]) && !empty($this->toLocals[(int) $write])) {
                // 外网请求需要转发到内网
                $res = socket_write($this->requestTunnels[(int) $write], $this->toLocals[(int) $write]);
                $this->onResult($res);
                unset($this->toLocals[(int) $write]);
            }

            if (isset($this->toExternals[(int) $write]) && !empty($this->toExternals[(int) $write])) {
                // 内网返回需要返回给外网
                $data = substr($this->toExternals[(int) $write], 0, $this->bytesLength + $this->identityLength);
                $res = socket_write($this->clientSocket, $data);
                $this->toExternals[(int) $write] = substr($this->toExternals[(int) $write], strlen($data));
                $this->onResult($res);
                unset($this->toExternals[(int) $write]);
            }
        }
    }

    private function onResult($res)
    {
        if ($res === false) {
            echo "error: " . socket_strerror(socket_last_error()) . PHP_EOL;
        }
    }

    private function boot()
    {
        $this->createClientSocket($this->host, $this->port);
    }

    public function createClientSocket($serverIp, $port)
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $socket or die('create socket fails.' . PHP_EOL);
        socket_connect($socket, $serverIp, $port) or die('connect fails.' . PHP_EOL);

        $this->clientSocket = $socket;

        $this->readSocks[] = $this->clientSocket;
        $this->writeSocks[] = $this->clientSocket;

        return $socket;
    }
}

(new ProxyClient())->handle();
