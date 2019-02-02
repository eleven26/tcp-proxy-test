<?php

function createServerSocket($ip, $port)
{
    if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) < 0) {
        echo "socket_create() 失败的原因是:" . socket_last_error() . PHP_EOL;
    }

    if (($ret = socket_bind($sock, $ip, $port)) < 0) {
        echo "socket_bind() 失败的原因是:" . socket_strerror($ret) . PHP_EOL;
    }

    if (($ret = socket_listen($sock, 4)) < 0) {
        echo "socket_listen() 失败的原因是:" . socket_strerror($ret) . PHP_EOL;
    }

    return $sock;
}

function createClientSocket($serverIp, $port)
{
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_connect($socket, $serverIp, $port);

    return $socket;
}

