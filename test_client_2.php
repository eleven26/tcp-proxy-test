<?php

require __DIR__ . '/helpers.php';

$ip = '127.0.0.1';
$port = 15000;

/*
 +-------------------------------
 *    socket通信整个过程
 +-------------------------------
 *    @socket_create
 *    @socket_bind
 *    @socket_listen
 *    @socket_accept
 *    @socket_read
 *    @socket_write
 *    @socket_close
 +--------------------------------
 */

$sock = createClientSocket($ip, $port);

socket_write($sock, 1);

socket_write($sock, __FILE__ . PHP_EOL);

// 接收到外网请求
while ($buf = socket_read($sock, 8192)) {
    echo $buf;
}

socket_close($sock);

//$msg = "Hello World! 123";
//
//$socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
//socket_sendto($socket, $msg, strlen($msg), 0, "/tmp/test_server.sock", 0);
//echo "sent\n";