<?php

require __DIR__ . '/ProxyProtocol.php';

class ProxyServer
{
    use ProxyProtocol;

    private $serverSock;

    /**
     * @var resource 到内网的 socket 连接，只有一个
     */
    private $localSock;

    private $toLocals = [];

    private $toExternals = [];

    private $externalSocks = [];

    public function __construct()
    {
        $this->boot();
    }

    /**
     * 初始化代理 socket 连接
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
                $data = socket_read($read, $this->bytesLength + $this->identityLength);

                if ($data !== '') {
                    echo "receive data from: $ip:$port" . PHP_EOL;
                    echo $data;

                    if ($read == $this->localSock) {
                        $id = $this->getResourceId($data);
                        // 内网返回
                        $this->toExternals[$id] .= $data;
                    } else {
                        $id = $this->sockResourceToIntString($read);
                        // 外网请求
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
        $conn_sock = socket_accept($read); // 响应客户端连接, 此时不会造成阻塞
        if ($conn_sock) {
            socket_getpeername($conn_sock, $ip, $port);
            echo "client connect server: ip=$ip, port=$port" . PHP_EOL;

            // 把新的连接 socket 加入监听
            $read_socks[(int) $conn_sock] = $conn_sock;
            $write_socks[(int) $conn_sock] = $conn_sock;

            if (!$this->localSock) {
                echo 'local connected!' . PHP_EOL;
                $this->localSock = $conn_sock;
            } else {
                echo 'external connected!' . PHP_EOL;
                $this->externalSocks[(int) $conn_sock] = $conn_sock;
            }
        } else {
            echo "client connect failed!" . PHP_EOL;
        }
    }

    protected function handleWrite($writes)
    {
        foreach ($writes as $write) {
            if (isset($this->toLocals[(int) $write]) && !empty($this->toLocals[(int) $write])) {
                // 外网请求需要转发到内网
                $res = socket_write($this->localSock, $this->toLocals[(int) $write]);
                $this->onResult($res);
                unset($this->toLocals[(int) $write]);
            }

            if (isset($this->toExternals[(int) $write]) && !empty($this->toExternals[(int) $write])) {
                // 内网返回需要返回给外网
                $res = socket_write($this->externalSocks[(int) $write], $this->toExternals[(int) $write]);
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

    public function __destruct()
    {
        socket_close($this->serverSock);

        foreach ($this->writeSocks as $writeSock) {
            socket_close($writeSock);
        }
        foreach ($this->readSocks as $readSock) {
            socket_close($readSock);
        }
    }
}

(new ProxyServer())->handle();
