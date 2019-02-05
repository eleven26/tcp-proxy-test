<?php

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

//socket_set_nonblock($server_sock);

// 要监听的三个 sockets 数组
$read_socks = [];
$write_socks = [];
$except_socks = null;

$local_sock = null;
$external_sock = null;

$to_local = '';
$to_external = '';

$read_socks[] = $server_sock;
$data = null;

while (true) {
    // 这两个数组会被改变, 所以用两个临时变量
    $tmp_reads = $read_socks;
    $tmp_writes = $write_socks;

    $count = socket_select($tmp_reads, $tmp_writes, $except_socks, null);

    foreach ($tmp_reads as $read) {
        if ($read == $server_sock) {
            // 有新的客户端连接请求
            $conn_sock = socket_accept($server_sock); // 响应客户端连接, 此时不会造成阻塞
            if ($conn_sock) {
//                socket_set_nonblock($conn_sock);
                socket_getpeername($conn_sock, $ip, $port);
                echo "client connect server: ip = $ip, port=$port" . PHP_EOL;

                // 把新的连接 socket 加入监听
                $read_socks[] = $conn_sock;
                $write_socks[] = $conn_sock;

                if (!$local_sock) {
                    $local_sock = $conn_sock;
                } else {
                    $external_sock = $conn_sock;
                }
            } else {
                echo "client connect failed!" . PHP_EOL;
            }
        } else {
            socket_getpeername($read, $ip, $port);
            $data = socket_read($read, 8192);

            if ($data !== '') {
                echo "receive data from: $ip:$port" . PHP_EOL;
                echo $data;

                if ($read == $local_sock) {
                    $to_external = $data;
                }
                if ($read == $external_sock) {
                    $to_local = $data;
                }
            } else if ($data === false) {
                // 移除对该 socket 监听
                foreach ($read_socks as $key => $val) {
                    if ($val == $read) unset($read_socks[$key]);
                }

                foreach ($write_socks as $key => $val) {
                    if ($val == $read) unset($write_socks[$key]);
                }

                socket_close($read);
                echo "client close" . PHP_EOL;
            }
        }
    }

    foreach ($tmp_writes as $write) {
        if ($write == $local_sock && $to_local != '') {
            echo "write to local -----------\n";
            socket_write($write, $to_local);
//            $to_local = '';
            echo "write to local ----------- end\n";
        }
        if ($write == $external_sock && $to_external != '') {
            echo "write to external -----------\n";
            socket_write($write, $to_external);
            echo "write to external ----------- end\n";
            $to_external = '';
        }
    }
}

socket_close($server_sock);
