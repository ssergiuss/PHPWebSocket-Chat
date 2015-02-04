<?php
// prevent the server from timing out
set_time_limit(0);

// include the web sockets server script (the server is started at the far bottom of this file)
require 'class.PHPWebSocket.php';

$Authentication = array();

function isAuthenticated($clientID) {
	global $Authentication;

	if (!isset($Authentication[$clientID])) {
		return false;
	}

	return $Authentication[$clientID]['status'] == 2;
}

function authenticate($clientID, $message) {
	global $Server;
	global $Authentication;

	if (!isset($Authentication[$clientID])) {
		$Authentication[$clientID] = array(
			'status' => 0
		);
	}

	if (0 === $Authentication[$clientID]['status']) {
		$obj = json_decode($message);

		$Authentication[$clientID]['username'] = $obj->username;
		$Authentication[$clientID]['x'] = $obj->x;

		$publicKeys = getPublicKeys($Authentication[$clientID]['username']);
		$Authentication[$clientID]['n'] = $publicKeys['n'];
		$Authentication[$clientID]['publicKeys'] = $publicKeys['keys'];

		$Authentication[$clientID]['b'] = array();
		for ($i = 0; $i < count($Authentication[$clientID]['publicKeys']); $i++) {
			$Authentication[$clientID]['b'][] = mt_rand(0, 1);
		}

		$Server->wsSend($clientID, json_encode($Authentication[$clientID]['b']));

		$Authentication[$clientID]['status'] = 1;
	} else if (1 === $Authentication[$clientID]['status']) {
		$obj = json_decode($message);

		$Authentication[$clientID]['y'] = $obj->y;

		$n = $Authentication[$clientID]['n'];
		$y = $Authentication[$clientID]['y'];
		$v = $Authentication[$clientID]['publicKeys'];
		$b = $Authentication[$clientID]['b'];
		$x = $Authentication[$clientID]['x'];

		$y2 = ($y * $y) % $n;

		$y22 = $x;

		for ($i = 0; $i < count($v); ++$i) {
			$y22 = ($y22 * pow($v[$i], $b[$i])) % $n;
		}

		if ($y2 === $y22) {
			$Server->wsSend($clientID, json_encode(array(
				'authenticated' => true
			)));

			$Authentication[$clientID]['status'] = 2;
		} else {
			$Server->wsClose($clientID);
		}
	}
}

function getPublicKeys($username) {
	$mysqli = new mysqli('localhost', 'root', '1234', 'websocket_chat');

	if (mysqli_connect_errno()) {
	    return null;
	}

	$query = "SELECT * FROM public_keys WHERE username='$username';";

	if (!$query_result = $mysqli->query($query)) {
	    return null;
	}

	$publicKeys = array();

	if (!$row = $query_result->fetch_array()) {
    	return null;
    } else {
    	$publicKeys = array(
    		'n' => $row['n'],
    		'keys' => explode(' ', $row['keys'])
		);
    }

    return $publicKeys;
}

// when a client sends data to the server
function wsOnMessage($clientID, $message, $messageLength, $binary) {
	global $Server;
	global $Authentication;

	$ip = long2ip($Server->wsClients[$clientID][6]);

	// check if message length is 0
	if ($messageLength == 0) {
		$Server->wsClose($clientID);
		return;
	}

	if (!isAuthenticated($clientID)) {
		authenticate($clientID, $message);
	} else {
		//The speaker is the only person in the room. Don't let them feel lonely.
		if ( sizeof($Server->wsClients) == 1 )
			$Server->wsSend($clientID, "There isn't anyone else in the room, but I'll still listen to you. --Your Trusty Server");
		else
			//Send the message to everyone but the person who said it
			foreach ( $Server->wsClients as $id => $client )
				if ( $id != $clientID )
					$Server->wsSend($id, "Visitor $clientID ($ip) said \"$message\"");
	}
}

// when a client connects
function wsOnOpen($clientID)
{
	global $Server;
	$ip = long2ip( $Server->wsClients[$clientID][6] );

	$Server->log( "$ip ($clientID) has connected." );

	//Send a join notice to everyone but the person who joined
	foreach ( $Server->wsClients as $id => $client )
		if ( $id != $clientID )
			$Server->wsSend($id, "Visitor $clientID ($ip) has joined the room.");
}

// when a client closes or lost connection
function wsOnClose($clientID, $status) {
	global $Server;
	global $Authentication;

	unset($Authentication[$clientID]);

	$ip = long2ip( $Server->wsClients[$clientID][6] );

	$Server->log( "$ip ($clientID) has disconnected." );

	//Send a user left notice to everyone in the room
	foreach ( $Server->wsClients as $id => $client )
		$Server->wsSend($id, "Visitor $clientID ($ip) has left the room.");
}

// start the server
$Server = new PHPWebSocket();
$Server->bind('message', 'wsOnMessage');
$Server->bind('open', 'wsOnOpen');
$Server->bind('close', 'wsOnClose');
// for other computers to connect, you will probably need to change this to your LAN IP or external IP,
// alternatively use: gethostbyaddr(gethostbyname($_SERVER['SERVER_NAME']))
$Server->wsStartServer('127.0.0.1', 9300);

?>
