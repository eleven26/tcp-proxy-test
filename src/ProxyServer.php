<?php

require __DIR__ . '/ProxyProtocol.php';

class ProxyServer
{
    use ProxyProtocol;

    /**
     * @var resource 代理服务器 socket
     */
    private $serverSock;

    /**
     * @var resource 到内网的 socket 连接，只有一个
     */
    private $localSock;

    /**
     * 需要转发给内网的数据
     * 格式：
     * [
     *      '<socket resource id string>' => '<socket resource id><data(外网请求报文)>'
     * ]
     *
     * @var array
     */
    private $toLocals = [];

    /**
     * 需要转发给外网的数据
     * 格式：
     * [
     *      '<socket resource id string>' => '<socket resource id><data(内网返回结果)>'
     * ]
     *
     * @var array
     */
    private $toExternals = [];

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
     * ProxyServer constructor.
     */
    public function __construct()
    {
        $this->boot();
    }

    /**
     * 初始化代理 socket 连接
     *
     * @return void
     */
    private function boot()
    {
        // 创建一个 tcp socket
        $server_sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if ($server_sock === false) {
            $error_code = socket_last_error();
            fwrite(STDERR, "socket create fail: " . socket_strerror($error_code));
            exit(-1);
        }

        // 绑定 ip 地址及端口
        if (!socket_bind($server_sock, '0.0.0.0', 8888)) {
            $error_code = socket_last_error();
            fwrite(STDERR, "socket bind fail: " . socket_strerror($error_code));
            exit(-1);
        }

        if (!socket_listen($server_sock, 128)) { // 允许多少客户端来排队连接
            $error_code = socket_last_error();
            fwrite(STDERR, "socket listen fail: " . socket_strerror($error_code));
            exit(-1);
        }

        $this->serverSock = $server_sock;
        $this->readSocks[] = $this->serverSock;
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
        foreach ($reads as $read) {
            if ($read == $this->serverSock) {
                $this->newConnection($read);
            } else {
                $res = socket_getpeername($read, $ip, $port);
                if ($res === false) {
                    echo "getpeername fails." . PHP_EOL;
                    $this->removeExternalSock($read);
                    continue;
                }

                // 读取到数据的几种情况
                // 1. 外网请求，需要转发到内网
                // 2. 内网返回，需要返回给外网
                echo "before read ...\n";
                $data = socket_read($read, $this->bytesLength + $this->identityLength);
                echo "after read ...\n";

                if ($data !== '') {
                    echo "receive data from: $ip:$port" . PHP_EOL;
                    echo $data;

                    if ((int) $read == (int) $this->localSock) {
                        // e.g. 00000000 00000000 00000011
                        $id = $this->getResourceId($data);
                        // 内网返回
                        if (!array_key_exists($id, $this->toExternals)) {
                            $this->toExternals[$id] = '';
                        }
                        $this->toExternals[$id] .= $data;
                        echo "return from local\n";
                        echo $data;
                        echo $id . PHP_EOL;
                    } else {
                        // e.g. 00000000 00000000 00000011
                        $id = $this->sockResourceToIntString($read);
                        // 外网请求
                        if (!array_key_exists($id, $this->toLocals)) {
                            $this->toLocals[$id] = '';
                        }
                        $this->toLocals[$id] .= $id . $data;
                    }
                } else if ($data === false) {
                    echo "socket_read() failed, reason: " .
                        socket_strerror(socket_last_error()) . "\n";
                    $this->removeExternalSock($read);
                }
            }
        }
    }

    private function removeExternalSock($sock)
    {
        echo "client close: "  . ((int) $sock) . PHP_EOL;
        unset($this->externalSocks[(int) $sock]);
        socket_close($sock);
    }

    private function newConnection($read)
    {
        // 有新的客户端连接请求
        $connSock = socket_accept($read); // 响应客户端连接, 此时不会造成阻塞
        if ($connSock) {
            socket_getpeername($connSock, $ip, $port);
            echo "client connect server: ip=$ip, port=$port" . PHP_EOL;

            // 把新的连接 socket 加入监听
            $this->readSocks[(int) $connSock] = $connSock;
            $this->writeSocks[(int) $connSock] = $connSock;

            // 先从内网启动服务
            if (!$this->localSock) {
                echo 'local connected!' . PHP_EOL;
                $this->localSock = $connSock;
            } else {
                echo 'external connected!' . PHP_EOL;
                $this->externalSocks[$this->sockResourceToIntString($connSock)] = $connSock;
            }
        } else {
            echo "client connect failed!" . PHP_EOL;
        }
    }

    protected function handleWrite($writes)
    {
        foreach ($writes as $write) {
            $id = $this->sockResourceToIntString($write);

            if (isset($this->toLocals[$id]) && !empty($this->toLocals[$id])) {
                echo "writing to local...\n";
                // 外网请求需要转发到内网
                $data = substr($this->toLocals[$id], 0, $this->bytesLength + $this->identityLength);
                $res = socket_write($this->localSock, $data, strlen($data));
                $this->toLocals[$id] = substr($this->toLocals[$id], strlen($data));
                $this->onResult($res);
            }

            if (isset($this->externalSocks[$id]) && $this->externalSocks[$id] == $write) {
                if (isset($this->toExternals[$id]) && !empty($this->toExternals[$id])) {
                    echo "writing to external...\n";
                    // 内网返回需要返回给外网
                    $res = socket_write($this->externalSocks[$id], $this->toExternals[$id]);
                    $this->onResult($res);
                    unset($this->toExternals[$id]);
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

    public function __destruct()
    {
        if (is_resource($this->serverSock))
            socket_close($this->serverSock);

        foreach ($this->writeSocks as $writeSock) {
            if (is_resource($writeSock))
                socket_close($writeSock);
        }
        foreach ($this->readSocks as $readSock) {
            if (is_resource($readSock))
                socket_close($readSock);
        }
    }
}

(new ProxyServer())->handle();
