<?php
/*
Author: Thomas Barthelet
Contact: http://thomas.barthelet.eu/
Code available online as a web-service at http://api.barthelet.eu/backup/twitter.php
*/
define("DEFAULT_POSTS", 100); // Default number of posts to fetch

function url_get_contents($url) {
	if (!function_exists('curl_init')){
		die('CURL is not installed!');
	}
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	$response = curl_exec($ch);
	curl_close($ch);
	return $response;
}
function getPostsCount($user) { // get the total number of posts on Tumblr using the API
	$url = 'http://'.$user.'.tumblr.com/api/read/json?num=0&debug=1';
	$json = url_get_contents($url);
	$json = json_decode($json);
	$count = $json->{'posts-total'};
	return $count;
}
function fetchPosts($user, $start, $num) { // fetch user posts from Tumblr using their API
	$url = 'http://'.$user.'.tumblr.com/api/read?start='.$start.'&num='.$num;
	$xml = url_get_contents($url);
	return $xml;
}
function getPosts($user, $count = DEFAULT_POSTS) {
	$xml = preg_replace('/(.*?)\/><\/tumblr>/s', '$1>', fetchPosts($user,0,0));
	for ($i=$count; $i>0; $i-=50) {
		($i>50 ? $num = 50 : $num = $i);
		$xml .= preg_replace('/(.*?)(<post id=.*?)<\/posts>.*/s', '$2', fetchPosts($user,($count-$i),$num));
	}
	$xml .= "</posts></tumblr>";
	return $xml;
}

function getStatusCodeMessage($status) {
	$codes = Array(
		200 => 'OK',
		400 => 'Bad Request',
		401 => 'Unauthorized',
		500 => 'Internal Server Error'
	);
	return (isset($codes[$status])) ? $codes[$status] : '';
}

function sendResponse($status = 200, $body = '', $content_type = 'text/html') {
	$status_header = 'HTTP/1.1 ' . $status . ' ' . getStatusCodeMessage($status);
	header($status_header); // set the status
	header('Content-type: ' . $content_type); // set the content type

	if($body != '') { // send the body
		echo $body;
		exit;
	}
	else { // we need to create the body if none is passed
		switch($status) {
			case 400: // if the request is not well-formed, display help message
				$message = 	'<strong>Usage:</strong> <code>http://api.barthelet.eu/backup/tumblr/YOUR-TUMBLR-USERNAME</code><br />'."\n";
				$message .= '<strong>Example:</strong> <a href="http://api.barthelet.eu/backup/tumblr/demo">http://api.barthelet.eu/backup/tumblr/demo</a><br />'."\n";
				$message .= 'You can add a <em>count</em> argument to retrieve as many posts as you want (use <code>count=all</code> to retrieve them all):<br />'."\n";
				$message .= '<code>http://api.barthelet.eu/backup/tumblr/YOUR-TUMBLR-USERNAME/?count=NUMBER-OF-POSTS</code>';
				break;
			case 401:
				$message = 'You must be authorized to view this page.';
				break;
			case 500:
				$message = 'The server encountered an error processing your request.';
				break;
		}
		echo $message;
		exit;
	}
}

/*
	API Interface
*/

if(!isset($_GET["user"])) {
	sendResponse(400);
}
else {
	if(!isset($_GET["count"])) {
		$_GET["count"] = DEFAULT_POSTS;
	}
	else {
		if($_GET["count"] == "all") $_GET["count"] = getPostsCount($_GET['user']); // if asked to fetch all posts, get the total number
	}
	$aResponse = getPosts($_GET["user"],$_GET["count"]);
	sendResponse(200, $aResponse, 'text/xml');
}
?>
