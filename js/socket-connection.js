
$(document).ready(function() {

	var curr_mtime = -1;
	var cursor_pos = 0;

	var connected = false;

	serverUrl = 'ws://0.0.0.0:8000/demo';

	// Cross browser support
	if (window.MozWebSocket) {
		socket = new MozWebSocket(serverUrl);
	} else if (window.WebSocket) {
		socket = new WebSocket(serverUrl);
	}

	socket.binaryType = 'blob';

	socket.onopen = function(msg) {
		connected = true;
		register_user();
		return true;
	};

	socket.onmessage = function(msg) {
		var response = JSON.parse(msg.data);
		checkJson(response);
		return true;
	};

	socket.onclose = function(msg) {
		connected = false;
		return true;
	};

	function checkJson(res) {

		if(res.action == 'file_changed'){

			var len = res.output.length;

			for(var i=0;i<len;i++){
				$("#logs").append("<p>" + res.output[i] + "</p>");
			}

			// scroll to the bottom of the page
			window.scrollTo(0,document.body.scrollHeight);

			cursor_pos = res.cursor_pos;
			curr_mtime = res.mtime;
		}
	}

	function register_user(){
		payload = new Object();
		payload.action = 'register';
		socket.send(JSON.stringify(payload));
	}

	var action = function(){
		if(connected){
			payload = new Object();
			payload.action = 'file_changed';
			payload.mtime = curr_mtime;
			payload.cursor_pos = cursor_pos;
			socket.send(JSON.stringify(payload));
		}
		else{
			setTimeout(function(){
				location.reload();
			}, 5000);
		}
	}

	setInterval(action, 2000);

});