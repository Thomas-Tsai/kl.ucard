<?php
$token = '851fc9fb3410e174ff156b65689f6922';
$server = 'http://moodle.nchc.org.tw';
$dir = '/moodle'; // May be null if moodle runs in the root directory in the server.

$level=2;
$track=4;
$request = xmlrpc_encode_request('course_level_get_last_course', array($level, $track), array('encoding'=>'UTF-8'));

var_dump($request);  // In case you want to see XML.

$context = stream_context_create(array('http' => array(
				'method' => "POST",
				'header' => "Content-Type: text/xml",
				'content' => $request
				)));

$path = $server.$dir."/webservice/xmlrpc/server.php?wstoken=".$token;
// Send XML to server and get a reply from it.
$file = file_get_contents($path, false, $context); // $file is the reply from server.
// Decode the reply.
$response = xmlrpc_decode($file);

// This is our normal exit (returning an array of user properties).
var_dump($response);
foreach($response as $c){
    echo $c['id'];
}
?>
