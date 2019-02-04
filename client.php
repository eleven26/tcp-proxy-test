<?php

require __DIR__ . '/helpers.php';

$ip = 'ide.baiguiren.com';
$port = 8888;

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

// 第一个字节作为标志位
socket_write($sock, '1', 1);

// 接收到外网请求
while ($buf = socket_read($sock, 8192)) {
    echo $buf;

    $proxySock = createClientSocket('127.0.0.1', '8005');
    socket_write($proxySock, $buf, strlen($buf));

    while ($out = socket_read($proxySock, 8192)) {
        echo $out;

        socket_write($sock, $out, strlen($out));
        socket_close($proxySock);
        break;
    }
}

socket_close($sock);
