#getRedisInfo.class.php-`sentinel`实现`redis`主从高可用；
>**必要条件**
>安装了`redis`扩展，
>安装了`apcu`

## 1. 连接客sentinel户端获得redis信息；

原理挺简单的，sentinel就是个redis的特殊客户端（指令集有限，所以特殊，想了解这个知识的，毕竟是官网推出的一个东西，可以去官网看）；

所以使用redis的时候，我们往往不知道谁是*主*谁是*从*，我们需要首先和sentinel进行通讯来获得主从的ip，端口号，连接sentinel直接使用了：

`$sentinel = new redis();`

获得扩展的实例，实例里有rawcommand方法（这个可以去找这个扩展的手册昂！）该方法可以直接跑redis的命令行；

就先把实例化的$sentinel连接到sentinel地址端口上，不就是个客户端嘛，咋就不能connect了呐，妥妥的能呀（注意端口号，默认的是26379）；

`$sentinel->connect(ipxxxx, portxxxx);`

在官网把sentinel的命令行拿到，在rawcommand方法里直接执行masterof，like this：

```
$sentinel->rawcommand('SENTINEL', 'get-master-addr-by-name', 'mymaster');   // 获得master的相关信息；
$slaveInfo  = $this->sentinel->rawcommand('SENTINEL', 'slaves', 'mymaster');// 获得slaves的相关信息；

```
其实我也不知道返回的格式，但是基本的端口号在写的时候就知道，看到返回的东西还是有这个尿性都区分出来的，我就要一个端口号和ip而已

## 2. 获得信息后进行推举
What's the Fu\*\*....你上面不是说弄到redis信息了么，肿么还要推举？
sentinel是部署在集群中各个机器上的，也就是说有好多个sentinel，我们完全有能力完全获取所有sentinel的消息。

获取一个万一拿到的信息不准呢，所以可能要全部获取，每个客户端都会获取到master和slave的消息，我们把这些消息汇总统计，去重，检验，这里除了检验麻烦点儿，其余的都还好，检验步骤如下：

 - 最终得到的master就一个，slave可能会是一组；
 - 将所有slave和master都链接下，发送ping命令（实例化的这个东西里面倒是可以直接使用ping方法），如果没问题得到了`pang`的回复，就没问题，有问题就踢出；
 - 将slave进行一次排序，排序方式按照统计量进行排序，也就是说前面的统计在这里用，为了以后slave进行排序，读取的时候会有1个主要读取的的slave，剩下的slave用来备份；

最终我们获得一个master和slaves（一组slave）

最后我弄一个输出出来，输出结构是这样的：

```
$write = false;
$read = false;
if($master)
	$write = true;
if($slave1)
	$read = true;
$ret = array();
$ret['write'] = $write;
$ret['read']  = $read;
$ret['master'] = $master;  // master是一个数组，里面是ip和port
$ret['slave1'] = $slave1;  // 结构同上
$ret['slave2'] = $slave2;  // 结构同上
$ret['slave3'] = $slave3;  // 结构同上
....
$ret['time']   = time();
```
## 3. 存入apcu
你会看到我上面有个time的键位；
其实拿到了消息，我们总不能老是使用这个东西都去拿redis消息吧，找个地方存就行了，开始想生成个配置文件使用opcache？后来想想你说咱又没有锁之类的直接写到apcu里去算了,也好操作，既然都用apcu了,其实有了sentinel咱毕竟是发生问题的时候才弄的这套主从切换及从转移的方案，为了应对发生转移问题,我打算定期过来走一遍上面这个流程，拿一遍配置，咋办，里面写个时间戳再加上20秒构成一个未来的时间戳，一旦读取配置时发现当前时间戳小于这个值了，就去走一遍上面的流程；
