### http中获取客户端列表及推送消息给指定客户端  
1. 修改websocket.handler，在`onOpen`方法中加一行代码`$this->websocket->join('global');`  
2. 获取客户端列表  
```php
public function root(Room $room, $name = 'global')
{
    return json($room->getClients($name));
}
```
3. 推送消息  
```php
/** 推送给指定客户端 */
public function emit(Pusher $pusher, string $id, string $msg)
{
    $pusher->to($id)->emit('test', $msg);
    return 'ok';
}
/** 广播 */
public function broadcast(Room $room, Pusher $pusher, string $msg)
{
    // 广播
    $clients = $room->getClients('global');
    foreach ($clients as $client) {
        $pusher->to($client)->emit('test', $msg);
    }
    return 'ok';
}
```
