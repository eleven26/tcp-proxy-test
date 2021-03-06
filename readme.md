### 使用 php 实现内网穿透，支持 http 协议

### 概念
* 内网穿透: 通俗地讲，就是让外网访问内网

### 整体架构
![architecture.png](https://raw.githubusercontent.com/eleven26/tcp-proxy-test/master/imgs/architecture.png)

### 前提条件
* 有一个公网服务器，作为中转使用

### 目的
* 只是为了测试，了解一下内网穿透原理，了解一下网络编程。
* 网上已有成熟的实现，现成的有，[frp](https://github.com/fatedier/frp.git)，go 语言实现，一直在使用

### 使用
* `git clone https://github.com/eleven26/tcp-proxy-test.git`
* `cd tcp-proxy-test`
* 服务端: `php src/ProxyServer.php`
* 内网: `php src/ProxyClient.php`
* 注意: 目前代理服务器的地址和端口是写死的，需要修改代码进行修改

### todo
* 处理多个请求
* 还有其他大大小小的 bug，先搁置一会了
* 标识位加一个字节记录实际读取的数据长度

### 依赖
* `socket` 扩展

### 原理
* `http` 是应用层协议，底层使用的还是 `tcp`连接，所以可以使用 `socket` 扩展对 `http` 再进行处理，在传输的报文前面加上标志位，代表是属于哪个 `socket` 连接的
* 代理服务器接收浏览器发送过来的请求，把标识位加到报文开头（3个字节的二进制字符串），传输到内网
* 内网接收到请求之后，取出标识位，创建一个到内网 `http` 服务的连接，并把该连接和标识位建立关联，以便结果返回的时候可以返回给对应的浏览器 `socket` 连接
* 接收到内网返回后，加上标识位到开头，通过内网到代理服务器的连接，返回给代理服务器
* 代理服务器接收到内网返回后，取出标识位，返回给该标识对应的浏览器连接，over

### 关键技术
* 使用 `socket_select` 实现 I/O 复用

### 遇到的问题
* 代理服务器一个连接处理多个外网 http 请求，解决办法： I/O 复用
* 代理服务器到内网的一个连接处理多个代理服务器发送的 http 请求，解决方法： 使用 `select` 网络模型， 所有 http 报文加上标识位，在代理服务器接收到返回的时候再根据标识位进行分开处理
