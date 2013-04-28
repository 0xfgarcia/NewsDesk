<?php
/**
 * Twitterfeed+ (twitterfeedplus) V1.1
 *
 * Based on twitterfeed.php by Russell Beattie (2012-10-16)
 * (http://www.russellbeattie.com/blog/twitterfeedphp-get-your-authenticated-twitter-stream-as-an-atom-feed)
 * and
 * URL un-shortening by Marty (2012-11-12)
 * (http://www.internoetics.com/2012/11/12/resolve-short-urls-to-their-destination-url-php-api/)
 *
 * Purpose / History
 * Twitter will switch off its RSS feed API in March 2013. In order to read a Twitter timeline through a
 * feed reader, the JSON API that Twitter still offers needs to be converted to a feed format (Atom in this case).
 * Russell had already published a perfectly working PHP script that does this job, I just had to put it on my server
 * and follow the Usage instructions.
 * After I got it running I thought: if I have to pre-process the data from Twitter on my server anyway, before
 * it goes into my feed reader, why not get rid of the top Twitter annoyance? That is, http://t.co/ URLs,
 * which make it impossible to know where a link goes.
 * So after a little googling, I found some readily available code from Marty on internoetics.com, which "un-shortens"
 * URLs. I integrated bits and pieces of it into Russell's script.
 * Now you and I can enjoy our Twitter timelines through any feed reader again, and as a plus we see where links in
 * the tweets lead to.
 *
 * Usage instructions (also taken over from Russell's original script)
 *   1) Go to https://dev.twitter.com/apps and create a new App
 * 	2) Use the Authentication button to create the tokens/secrets needed
 * 	3) Copy the results into the appropriate spots below
 * 	4) Add your screen name and full name.
 * 	5) Serve chilled with a peanut butter and jelly sandwich
 *
 * License
 * Since I haven't really written any code, just copy & pasted and done a little integration work, I'd like to cite
 * Russell's license bit: "100% FREE TO COPY/USE FOR ANY PURPOSE, BUT DON'T BUG ME ABOUT IT, EVER."
 * I couldn't find any license statement for the code from http://www.internoetics.com - so I assume it's in the
 * public domain.
 *
 * Patrick Nagel, 2013-04-06
 */

        date_default_timezone_set('Asia/Shanghai');
        ini_set('display_errors', 0);

        $user_screen_name = '';
        $user_full_name = '';


// Twitter App Settings (https://dev.twitter.com/apps):

        $settings = array(
          'consumer_key' => '',
          'consumer_secret' => '',
          'access_token' => '',
          'access_token_secret' => ''
        );

// API: https://dev.twitter.com/docs/api/1.1/get/statuses/home_timeline

	$api_url = 'https://api.twitter.com/1.1/statuses/home_timeline.json';

	$api_params = array(
		'count' => 40,
		'contributor_details' => 'false',
		'include_entities' => 'false'
	);

// OAuth: 

	function oauth_encode($data){
		if(is_array($data)){
			return array_map('oauth_encode', $data);
		} else if(is_scalar($data)) {
			return str_ireplace(array('+', '%7E'), array(' ', '~'), rawurlencode($data));
		} else {
			return '';
		}
	}

// OAuth base settings

	$oauth_params = array(
		'oauth_consumer_key' => $settings['consumer_key'],
		'oauth_nonce' => md5(microtime() . mt_rand()),
		'oauth_signature_method' => 'HMAC-SHA1',
		'oauth_timestamp' => time(),
		'oauth_token' => $settings['access_token'],
		'oauth_version' => '1.0',
	);

// Sign OAuth params

	$sign_params = array_merge($oauth_params, $api_params);

	uksort($sign_params, 'strcmp');

	foreach ($sign_params as $k => $v) {
		$sparam[] = oauth_encode($k) . '=' . oauth_encode($v);
	}

	$sparams = implode('&', $sparam);

	$base_string = 'GET&' . oauth_encode($api_url) . '&' . oauth_encode($sparams);

	$signing_key = oauth_encode($settings['consumer_secret']) . '&' . oauth_encode($settings['access_token_secret']);

	$oauth_params['oauth_signature'] = oauth_encode(base64_encode(hash_hmac('sha1', $base_string, $signing_key, TRUE)));


// Set Authorization header:

	uksort($oauth_params, 'strcmp');

	foreach ($oauth_params as $k => $v) {
	  $hparam[] = $k . '="' . $v . '"';
	}

	$hparams = implode(', ', $hparam);

	$headers = array();
	$headers['Expect'] = '';
	$headers['Authorization'] = 'OAuth ' . $hparams;

	foreach ($headers as $k => $v) {
		$curlheaders[] = trim($k . ': ' . $v);
	}

// Format params:

	foreach ($api_params as $k => $v) {
		$rparam[] = $k . '=' . $v;
	}

	$rparams = implode('&', $rparam);


// Un-shorten URL
	function resolveShortURL($url) {
		$ch = curl_init("$url");
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$yy = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if($httpCode == 404) {
			return "";
		}

		$w = explode("\n",$yy);

		$TheShortURL = in_array_wildcard('location', $w);
		if(count($TheShortURL) > 0) {
			$url = $TheShortURL[0];
			$url = str_ireplace("Location:", "", "$url");
			$url = trim("$url");
		}
		return $url;
	}

	function in_array_wildcard ( $needle, $arr ) {
		return array_values( preg_grep( '/' . str_ireplace( '*', '.*', $needle ) . '/', $arr ) );
	}

// Find domain from URL
	function stripit($url) {
		$url = trim($url);
		$url = preg_replace("/^(http(s)?:\/\/)*(www.)*/is", "", $url);
		$url = preg_replace("/\/.*$/is" , "" ,$url);
		return $url;
	}

// Array of top URL shorteners
	$urlArray = array(	"tiny.cc", "is.gd", "own.ly", "rubyurl.com", "bit.ly", "tinyurl.com",
		"moourl.com", "cli.gs", "ka.lm", "u.nu", "yep.it", "shrten.com", "miniurl.com", "snipurl.com",
		"short.ie", "idek.net", "w3t.org", "shiturl.com", "dwarfurl.com", "doiop.com", "smallurl.in",
		"notlong.com", "fyad.org", "safe.mn", "hex.io", "own.ly", "lnkd.in", "fb.me", "amzn.to",
		"goo.gl", "j.mp", "mcaf.ee", "lnk.ms", "youtu.be", "wp.me", "fwd4.me", "su.pr", "t.co",
		"snurl.com", "tr.im", "twurl.cc", "fat.ly", "ow.ly");

// GET:
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $api_url . '?' . $rparams);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $curlheaders);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLINFO_HEADER_OUT, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10 );

	$response = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$info = curl_getinfo($ch);
	$error = curl_error($ch);
	$errno = curl_errno($ch);

	curl_close($ch);

	if($code != 200){

		echo 'Error' . PHP_EOL;
		echo $code . PHP_EOL;
		print_r($response);
		print_r($info);

	} else {

		$all = json_decode($response, true);

		$updated = date(DATE_ATOM, strtotime($all[0]['created_at']));

		header('Content-type: application/atom+xml; charset=UTF-8', true);

		echo '<?xml version="1.0" encoding="utf-8"?' . '>' . PHP_EOL;

?>
<feed xml:lang="en-US" xmlns="http://www.w3.org/2005/Atom">
<title>Twitter / <?php echo $user_screen_name ?></title>
<id>tag:twitter.com,2007:Status</id>
<link type="text/html" rel="alternate" href="http://twitter.com/<?php echo $user_screen_name ?>"/>
<link type="application/atom+xml" rel="self" href="http://<?php echo $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"] ?>"></link>
<updated><?php echo $updated ?></updated>
<subtitle>Twitter updates from <?php echo $user_full_name ?>.</subtitle>
<?php

		foreach($all as $row){

			$id = $row['id_str'];
			$text = $row['text'];
			$name = htmlspecialchars($row['user']['name']);
			$screen_name = $row['user']['screen_name'];
			$url = $row['user']['url'];
			$profile_image_url = $row['user']['profile_image_url'];
			$source = htmlspecialchars($row['source']);

			$created = date(DATE_ATOM, strtotime($row['created_at']));

			$entry = $row['user']['screen_name'] . ': ' . $row['text'];

			// Resolve links in this entry (http://t.co/... and one more shortener service after that)
			preg_match_all("/(http|https):\/\/t\.co\/[^<>[:space:]]+[[:alnum:]#?\/&=+%_]/", $entry, $match);
			$list = $match[0];
			if(count($list) > 0) {
				foreach($list as $url) {
					$resolvedURL = resolveShortURL($url);
					if(in_array(stripit($resolvedURL), $urlArray)) {
						$resolvedURL2 = resolveShortURL($resolvedURL);
					} else {
						$resolvedURL2 = $resolvedURL;
					}
					$entry = str_replace($url, $resolvedURL2, $entry);
				}
			}

			$entry = htmlspecialchars($entry);

?>	<entry>
	<title><?php echo $entry ?></title>
	<content type="html"><?php echo $entry ?></content>
	<id>tag:twitter.com,2007:http://twitter.com/<?php echo $screen_name ?>/status/<?php echo $id ?></id>
	<published><?php echo $created ?></published>
	<updated><?php echo $created ?></updated>
	<link type="text/html" rel="alternate" href="http://twitter.com/<?php echo $screen_name ?>/status/<?php echo $id ?>"/>
	<link type="image/png" rel="image" href="<?php echo $profile_image_url ?>"/>
	<author>
	<name><?php echo $name ?></name>
	<uri><?php echo $url ?></uri>
	</author>
	</entry>
<?php
		}
?>
</feed>
<?php
		exit();
	}
