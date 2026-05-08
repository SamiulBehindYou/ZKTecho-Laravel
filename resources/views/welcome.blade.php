<?php

use Jmrashed\Zkteco\Lib\ZKTeco;
$zk = new ZKTeco('192.168.1.201');
$connected = $zk->connect();
if ($connected) {
    echo "Sockets extension is enabled.";
	
	$version = $zk->version();
	echo $version;
	$users = $zk->getUser();
	$attendanceLog = $zk->getAttendance();
	dd($attendanceLog);
	
	
} else {
    echo "Sockets extension is not enabled.";
}