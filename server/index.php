<?php
require_once("config.php");
if(@$dedug){
	error_reporting(~0); ini_set('display_errors', 1);
}
require_once("config.php");
$r = [];

function call($con, $fun, $params){
	$letters = array('a', 'b', 'c', 'd', 'e', 'f','g', 'h', 'i', 'j', 'l', 'n');
	$sql = "CALL `$fun`(";
	$sql2 = "SELECT";
	for ($i = 0; $i < count($params); $i ++){
		$s = "SET @$letters[$i]='$params[$i]';";
		mysqli_query($con, $s);
		$sql .= " @$letters[$i]";
		$sql2 .= " @$letters[$i] as $letters[$i]";
		if ($i != count($params) - 1){
			$sql2 .= ",";
			$sql .= ",";
		}
	}
	$sql .= ");";
	$sql2 .= ";";
	mysqli_query($con, $sql);
	$row = mysqli_fetch_array(mysqli_query($con, $sql2));
	$rString = array();
	for ($i = 0; $i < count($params); $i++){
		$rString[$i] = $row[$letters[$i]];
	}
	return $rString;
}

$kng_con = mysqli_connect($mysql_host, $mysql_user, $mysql_password, $mysql_database);
if (mysqli_connect_errno($kng_con)) {
	http_response_code(500);
	die("Failed to connect to MySQL: ".mysqli_connect_error());
}

$row = mysqli_fetch_array(mysqli_query($kng_con, "SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema = '$mysql_database' and TABLE_TYPE='BASE TABLE'"));
if($row['c'] == 0){
	//no tables
	require_once("init.php");
	$r['log'][] = "Initing Database...";
	initDatabase();
	$r['log'][] = "Done";
}

require_once("api.php");

if(isset($_POST['last'])){
	//get messages
	if(isset($_POST['game'])){
		$r['messages'] = array();
		$mess = call($kng_con, 'getMessage', array($_POST['game'], $_POST['last'], null, null, null, null, null));
		while ($mess[2] != null){
			$last = $mess[2];
			$r['messages'][] = array(
				'tiles' => $mess[3],
				'location' => intval($mess[4]),
				'down' => $mess[5],
				'score' => intval($mess[6]),
				'last' => intval($last)
			);
			$mess = call($kng_con, 'getMessage', array($_POST['game'], $last, null, null, null, null, null));
		}
	} else {
		http_response_code(400);
		echo "Missing parameter 'game'";
		die();
	}
}
if(isset($_POST['lastGame'])){
	$r['games'] = array();
	$ga = call($kng_con, 'getGame', array($_POST['lastGame'], null, null));
	while ($ga[1] != null){
		$lg = $ga[1];
		$r['games'][] = array(
			'id' => intval($ga[1]),
			'key' => $ga[2]
		);
		$ga = call($kng_con, 'getGame', array($lg, null, null));
	}
}
if(isset($_POST['type'])){
	if(isset($fun[$_POST['type']])){
		$f = $fun[$_POST['type']];
		$a = array_pad(array(),isset($f['c']) ? $f['c'] : 2, null);
		if(is_array($f['p'])){
			foreach ($f['p'] as $k => $v) {
				//if required
				if($v['r']){
					if(isset($_POST[$k])){
						$a[$v['p']] = $_POST[$k];
					} else {
						http_response_code(400);
						die("Missing parameter '$k'");
					}
				}
			}
		} else {
			if(isset($_POST[$f['p']])){
				$a[0] = $_POST[$f['p']];
			} else {
				http_response_code(400);
				die("Missing parameter '$f[p]'");
			}
		}
		$a = call($kng_con, $f['n'], $a, 0);
		if(is_array($f['r'])){
			foreach($f['r'] as $k => $v){
				if(is_numeric($a[$v])){
					$r[$k] = intval($a[$v]);
				} else {
					$r[$k] = $a[$v];
				}
			}
		} else {
			if(is_numeric($a[1])){
				$r[$f['r']] = intval($a[1]);
			} else {
				$r[$f['r']] = $a[1];
			}
		}
	} else {
		http_response_code(400);
		die("Unknown type '$_POST[type]'");
	}
}
echo json_encode($r);
