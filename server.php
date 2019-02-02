<?php

require __DIR__ . '/helpers.php';

// 接收外部请求
$ip = '0.0.0.0';
$port = 9007;

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

$sock = createServerSocket($ip, $port);

do {
    if (($socks = socket_accept($sock)) < 0) {
        echo "socket_accept() failed: reason: " . socket_last_error() . PHP_EOL;
        break;
    } else {
        $buf = socket_read($socks, 8192);
        echo $buf;

        $proxySock = createClientSocket('127.0.0.1', '9005');
        socket_write($proxySock, $buf, strlen($buf));

        while ($out = socket_read($proxySock, 8192)) {
            echo $out;

            //发到客户端
            socket_write($socks, $out, strlen($out));
            socket_close($socks);

            break;
        }
    }
    socket_close($proxySock);
} while (true);

socket_close($sock);
