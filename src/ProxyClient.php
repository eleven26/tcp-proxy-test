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
     * @var string
     */
    private $toExternals = '';

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
            usleep(500);

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
            if ($ip != '127.0.0.1') {
                // 从代理服务器获取的数据有标志位，内网 http 返回的数据没有
                $data = socket_read($read, $this->bytesLength + $this->identityLength);
            } else {
                $data = socket_read($read, $this->bytesLength);
            }

            if ($data !== '') {
//                echo "receive data from: $ip:$port" . PHP_EOL;
                echo $data;

                if ($ip != '127.0.0.1') {
                    $id = $this->getResourceId($data);

                    // 创建到内网 http 服务的 socket 连接
                    $proxySock = $this->createClientSocket('127.0.0.1', 8005);
                    $this->readSocks[] = $proxySock;
                    $this->writeSocks[] = $proxySock;

                    // 等下从 proxySocket 返回的时候，需要拼接上 id 通过与代理服务器的 socket 连接返回
                    $this->requestTunnels[(int) $proxySock] = $proxySock;

                    // 外网请求 => 内网请求
                    $localId = $this->sockResourceToIntString($proxySock);
                    $this->proxyTunnels[$localId] = $id;
                    if (!isset($this->toLocals[$localId])) {
                        $this->toLocals[$localId] = '';
                    }
                    $this->toLocals[$localId] .= $data;
                } else {
                    // 内网返回
                    // e.g. 00000000 00000000 00000011
                    $localId = $this->sockResourceToIntString($read);
                    $id = $this->sockResourceToIntString($this->proxyTunnels[$localId]);
                    // 所有内网返回的数据需要找回 id，把 id 加到头部然后返回给代理服务器
                    $this->toExternals .= $id . $data;
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
            $id = $this->sockResourceToIntString($write);

            if (isset($this->requestTunnels[(int) $write]) && $this->requestTunnels[(int) $write] == $write) {
                if (isset($this->toLocals[$id]) && !empty($this->toLocals[$id])) {
                    // 外网请求需要转发到内网
                    echo "write to local\n";
                    echo $this->toLocals[$id];
                    $res = socket_write($this->requestTunnels[(int)$write], $this->toLocals[$id]);
                    $this->onResult($res);
                    unset($this->toLocals[$id]);
                }
            }

            if ($this->clientSocket == $write) {
                if ($this->toExternals) {
                    echo "write to external\n";
                    // 内网返回需要返回给外网
                    $data = substr($this->toExternals, 0, $this->bytesLength + $this->identityLength);
                    echo $data;
                    $res = socket_write($this->clientSocket, $data);
                    $this->toExternals = substr($this->toExternals, strlen($data));
                    $this->onResult($res);
                    var_dump($res);
                }
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
