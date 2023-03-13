## socket.io客户端示例  
```
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<title>Title</title>
		<script src="https://cdn.jsdelivr.net/npm/socket.io-client@4.6.0/dist/socket.io.min.js"></script>
		<script src="https://code.jquery.com/jquery-1.11.1.js"></script>
	</head>
	<body>

		<div>
			<div>
				返回内容:
			</div>
			<div style="width: 600px;height: 100px" id="content">

			</div>
		</div>
		<div>
			在控制台中执行 socket.emit("test",{"asd":"我是内容"})
		</div>
		<div>
			在控制台中执行 socket.emit("join",{"room":"roomtest"}) 加入房间
		</div>
		<div>
			在控制台中执行 socket.emit("leave",{"room":["roomtest"]}) 离开房间
		</div>
		<script>
			var socket = io('ws://localhost:9501/', {
				transports: ['websocket']
			});
			//xxx.com 这个自己替换成自己的环境thinkphp-swoole 的端口或者是nginx的代理端口
			//transports: ['websocket'] 一定要这个,改为websocket链接
			//polling 这个不支持,轮询会导致请求变成http请求,post请求全部拒接掉
			socket.emit('test', {
				"asd": "asd"
			});

			//自定义msg事件，发送‘你好服务器’字符串向服务器
			socket.on('testcallback', (data) => {
				//监听浏览器通过msg事件发送的信息
				console.log('testcallback服务器返回的数据：', data); //你好浏览器
			});
			//socket.emit('join',{"asd":"asd"});
			socket.on('export_finish', (data) => {
				console.log('roomJoin服务器返回的数据：', data); //你好浏览器
			})
		</script>
	</body>
</html>
```
> 代码来自think-swoole qq群
