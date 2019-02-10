### 使用 php 实现内网穿透，支持 http 协议

### 前提条件
* 有一个公网服务器，作为中转使用

### 使用
* `git clone https://github.com/eleven26/tcp-proxy-test.git`
* `cd tcp-proxy-test`
* 服务端: `php src/ProxyServer.php`
* 内网: `php src/ProxyClient.php`
* 注意: 目前代理服务器的地址和端口是写死的，需要修改代码进行修改

### todo
* 处理多个请求