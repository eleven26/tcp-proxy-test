<?php

require __DIR__ . '/helpers.php';

$ip = '127.0.0.1';
$port = 15000;

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

//$file = "/tmp/test_server.sock";
//@unlink($file);
//
//$socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
//
//if (socket_bind($socket, $file) === false) {
//    echo "bind failed";
//}
//
//if (socket_recvfrom($socket, $buf, 64 * 1024, 0, $source) === false) {
//    echo "recv_from failed";
//} else {
//    echo $buf . PHP_EOL;
//}
//
//var_dump($source);
//
//if (socket_recvfrom($socket, $buf, 64 * 1024, 0, $source) === false) {
//    echo "recv_from failed";
//} else {
//    echo $buf . PHP_EOL;
//}
//
//var_dump($source);
