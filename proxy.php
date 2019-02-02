<?php

require __DIR__ . '/helpers.php';

$ip = '0.0.0.0';
$port = 9005;

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

        // 传给内网

    }
    socket_close($socks);
} while (true);

socket_close($sock);
