<?php

// prevent the server from timing out
set_time_limit(0);

require 'class.PHPWebSocket.php';

$Authentication = array();

function isAuthenticated($clientID)
{
	global $Authentication;

	if (!isset($Authentication[$clientID])) {
		return false;
	}

	return $Authentication[$clientID]['status'] == 2;
}

function isAuthenticatedByUsername($username, $clientID)
{
	global $Server;
	global $Authentication;

	foreach ($Server->wsClients as $id => $client) {
		if ($id !== $clientID
			&& isset($Authentication[$id])
			&& $username === $Authentication[$id]['username']
			&& isAuthenticated($id)
		) {
			return true;
		}
	}

	return false;
}

function authenticate($clientID, $message)
{
	global $Server;
	global $Authentication;

	$ip = long2ip($Server->wsClients[$clientID][6]);

	if (!isset($Authentication[$clientID])) {
		$Authentication[$clientID] = array(
			'status' => 0
		);
	}

	if (0 === $Authentication[$clientID]['status']) {
		$obj = json_decode($message);

		$Authentication[$clientID]['username'] = $obj->username;
		$Authentication[$clientID]['x'] = $obj->x;

		if (isAuthenticatedByUsername($Authentication[$clientID]['username'], $clientID)) {
			$username = $Authentication[$clientID]['username'];
			$Server->log("Client \"$username\" already connected.");
			$Server->wsClose($clientID);
		}

		$publicKeys = getPublicKeys($Authentication[$clientID]['username']);

		if (null === $publicKeys) {
			$username = $Authentication[$clientID]['username'];
			$Server->log("No public key found for client \"$username\".");
			$Server->wsClose($clientID);
		}

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

		if (isAuthenticatedByUsername($Authentication[$clientID]['username'], $clientID)) {
			$username = $Authentication[$clientID]['username'];
			$Server->log("Client \"$username\" already connected.");
			$Server->wsClose($clientID);
		}

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

			$username = $Authentication[$clientID]['username'];

			$Server->log("Authentication for client \"$username\" succeeded.");

			foreach ($Server->wsClients as $id => $client) {
				if ($id != $clientID) {
					$Server->wsSend($id, "$username ($ip) has joined the room.");
				}
			}
		} else {
			$username = $Authentication[$clientID]['username'];
			$Server->log("Authentication for client \"$username\" failed.");
			$Server->wsClose($clientID);
		}
	}
}

function getPublicKeys($username)
{
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
    		'n'    => $row['n'],
    		'keys' => explode(' ', $row['keys'])
		);
    }

    return $publicKeys;
}

function wsOnMessage($clientID, $message, $messageLength, $binary)
{
	global $Server;
	global $Authentication;

	$ip = long2ip($Server->wsClients[$clientID][6]);

	if (0 == $messageLength) {
		$Server->wsClose($clientID);
		return;
	}

	if (!isAuthenticated($clientID)) {
		authenticate($clientID, $message);
	} else {
		$username = $Authentication[$clientID]['username'];

		if (sizeof($Server->wsClients) == 1) {
			$Server->wsSend($clientID, "There isn't anyone else in the room, but I'll still listen to you. --Your Trusty Server");
		} else {
			foreach ($Server->wsClients as $id => $client) {
				if ($id != $clientID) {
					$Server->wsSend($id, "$username ($ip): $message");
				}
			}
		}
	}
}

function wsOnOpen($clientID)
{
	global $Server;
	$ip = long2ip($Server->wsClients[$clientID][6]);

	$Server->log("$ip ($clientID) has connected.");
}

function wsOnClose($clientID, $status)
{
	global $Server;
	global $Authentication;

	$ip = long2ip($Server->wsClients[$clientID][6]);

	$Server->log("$ip ($clientID) has disconnected.");

	if (isAuthenticated($clientID)) {
		$username = $Authentication[$clientID]['username'];

		foreach ($Server->wsClients as $id => $client) {
			$Server->wsSend($id, "$username ($ip) has left the room.");
		}
	}

	unset($Authentication[$clientID]);
}

// start the server
$Server = new PHPWebSocket();
$Server->bind('message', 'wsOnMessage');
$Server->bind('open', 'wsOnOpen');
$Server->bind('close', 'wsOnClose');
$Server->wsStartServer('127.0.0.1', 9300);

?>
