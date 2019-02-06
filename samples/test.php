<?php

$readfds = array();
$writefds = array();
$sock = socket_create_listen(2000);
//socket_set_nonblock($sock); // 非阻塞
//echo "sleep 10 second...\n";
//sleep(10);
socket_getsockname($sock, $addr, $port);
print "Server Listening on $addr:$port\n";
$readfds[(int)$sock] = $sock;
$conn = socket_accept($sock);
$readfds[] = $conn;
$conn = socket_accept($sock);
$readfds[] = $conn;
$e = null;
$t = 100;
$i = 1;
while (true) {
    echo "No.$i\n";
    //当select处于等待时,两个客户端中甲先发数据来,则socket_select会在readfds中保留甲的socket并往下运行,
    //另一个客户端的socket就被丢弃了,所以再次循环时,变成只监听甲了,
    //这个可以在新循环中把所有链接的客户端socket再次加进readfds中,则可以避免本程序的这个逻辑错误
    echo @socket_select($readfds, $writefds, $e, $t) . "\n";
    var_dump($readfds);
    if (in_array($sock, $readfds)) {
        echo "2000 port is activity";
        $readfds[] = socket_accept($sock);
    }
    //将读取到的资源输出
    foreach ($readfds as $s) {
        if ($s != $sock) {
            //新连接到来时,被监听的端口是活跃的,如果是新数据到来或者客户端关闭链接时,活跃的是对应的客户端socket而不是服务器上被监听的端口
            //如果客户端发来数据没有被读走,则socket_select将会始终显示客户端是活跃状态并将其保存在readfds数组中
            //如果客户端先关闭了,则必须手动关闭服务器上相对应的客户端socket,
            //否则socket_select也始终显示该客户端活跃(这个道理跟"有新连接到来然后没有用socket_access把它读出来,导致监听的端口一直活跃"是一样的)
            $result = @socket_read($s, 1024, PHP_NORMAL_READ);
            if ($result === false) {
                $err_code = socket_last_error();
                $err_test = socket_strerror($err_code);
                echo "client " . (int)$s . " has closed[$err_code:$err_test]\n";
                //手动关闭客户端,最好清除一下$readfds数组中对应的元素
                socket_shutdown($s);
                socket_close($s);
            } else {
                echo $result;
            }
        }
    }
    usleep(3000000);
    $readfds[(int)$sock] = $sock;
    $i++;
}
