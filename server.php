<?php

include "./lib/Socket.php";
include "./lib/Server.php";
include "./lib/Connection.php";

header('Content-Type: text/plain');

echo "\033[2J";
echo "\033[0;0f";

/**
 * Application class establishing connection with the socket server
 */
class Application {
	
	private $connections = array();
	private $file_to_track = '/var/log/syslog';
	public $server;
	
	/**
	 * Constructor initializing the socket server
	 */
	public function __construct(){
		$this->server = new Server('0.0.0.0', 8000, false);
		$this->server->setMaxClients(100);
		$this->server->setCheckOrigin(false);
		$this->server->setAllowedOrigin('192.168.1.153');
		$this->server->setMaxConnectionsPerIp(100);
		$this->server->setMaxRequestsPerMinute(2000);
		$this->server->setHook($this);
		$this->server->run();
	}
	

	/**
	 * onConnect - Fired when a socket trying to connect
	 */
	public function onConnect($connection_id){
		echo "\nOn connect called : $connection_id\n";
        return true;
    }
    

    /**
     * onDisconnect - Fired when a socket disconnected
     */
    public function onDisconnect($connection_id){
		
		echo "\nOn disconnect called : $connection_id\n";
		
		if(isset($this->connections[$connection_id])){
			unset($this->connections[$connection_id]);
		}
    }
    

    /**
     * onDataReceive - Fired when data received
     */
    public function onDataReceive($connection_id,$data){
		
		$data = json_decode($data,true);
		
		if(isset($data['action'])){
			$action = 'action_'.$data['action'];
			if(method_exists($this,$action)){
				unset($data['action']);
				$this->$action($connection_id,$data);
			}else{
				echo "\n Caution : Action handler '$action' not found!";
			}
		}
		
    }
	

	/**
	 * tail - get last $lines number of lines from the file
	 * @param  string  $filename file to be read
	 * @param  integer $lines    number of lines to be retrieved
	 * @param  integer $buffer   maximum allowed buffer memory size
	 * @return array 	       	 array containing log output as well as cursor position of last character
	 */
	public function tail($filename, $lines = 10, $buffer = 4096){

		$output_array = array();

		echo "opening file: ".$filename."\n";

		// Open the file
		$f = fopen($filename, "rb");

		// Jump to last character
		fseek($f, -1, SEEK_END);

		// save the cursor position of the last character
		$output_array['cursor_pos'] = ftell($f);

		// Read it and adjust line number if necessary
		// (Otherwise the result would be wrong if file doesn't end with a blank line)
		if(fread($f, 1) != "\n")
			$lines -= 1;

		// Start reading
		$output = '';
		$chunk = '';

		// While we would like more
		while(ftell($f) > 0 && $lines >= 0){
			// Figure out how far back we should jump
			$seek = min(ftell($f), $buffer);

			// Do the jump (backwards, relative to where we are)
			fseek($f, -$seek, SEEK_CUR);

			// Read a chunk and prepend it to our output
			$output = ($chunk = fread($f, $seek)).$output;

			// Jump back to where we started reading
			fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);

			// Decrease our line counter
			$lines -= substr_count($chunk, "\n");
		}

		// While we have too many lines
		// (Because of buffer size we might have read too many)
		while($lines++ < 0){
			// Find first newline and remove all text before that
			$output = substr($output, strpos($output, "\n") + 1);
		}

		// Close file and return
		fclose($f); 

		$output_array['output'] = explode("\n", $output);
		return $output_array;
	}
	

	/**
	 * get_new_lines - get all new lines after a given cursor position from the file
	 * @param  [type] $filename file to be read
	 * @param  [type] $last_cur cursor position from where lines are to be retrieved
	 * @return array 	       	array containing log output as well as cursor position of last character
	 */
	public function get_new_lines($filename, $last_cur){

		$output_array = array();

		echo "opening file: ".$filename."\n";

		// Open the file
		$f = fopen($filename, "rb");

		// Jump to last character
		fseek($f, -1, SEEK_END);

		// save the cursor position of the last character
		$output_array['cursor_pos'] = ftell($f);

		$diff = $output_array['cursor_pos'] - $last_cur;

		// take cursor back to where we have to start reading
		fseek($f, -$diff, SEEK_CUR);

		$output = fread($f, $diff);

		// Close file and return
		fclose($f); 

		$output_array['output'] = explode("\n", $output);
		return $output_array;
	}
	
	
	/**
	 * action_register - regsters a new client
	 */
	public function action_register($connection_id,$data){
		$this->connections[$connection_id] = max($this->connections) + 1;
	}

	/**
	 * action_file_changed - looking for any changes in the file and returning new lines if changed
	 * @param  [type] $connection_id identifier for clients
	 */
	public function action_file_changed($connection_id, $data){
		$user_id = $this->connections[$connection_id];

		$new_data = array();
		
		clearstatcache();

		$curr_mtime = filemtime($this->file_to_track);
		$old_mtime = $data['mtime'];

		if($old_mtime == -1){
			// fetch last 10 lines from log file
			$output_array = $this->tail($this->file_to_track, 20, 4096);
			
			$new_data['output'] = $output_array['output'];
			$new_data['mtime'] = $curr_mtime;
			$new_data['cursor_pos'] = $output_array['cursor_pos'];
			$this->server->sendData($connection_id, 'file_changed', $new_data);
		}
		else if($curr_mtime != $old_mtime){
			// file changed, fetch lines after last line number
			$last_cursor_pos = $data['cursor_pos'];
			$output_array = $this->get_new_lines($this->file_to_track, $last_cursor_pos);

			$new_data['output'] = $output_array['output'];
			$new_data['mtime'] = $curr_mtime;
			$new_data['cursor_pos'] = $output_array['cursor_pos'];
			$this->server->sendData($connection_id, 'file_changed', $new_data);
		}
	}
	
}

$app = new Application();
