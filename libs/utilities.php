<?php
/*
	Part of APITam https://github.com/Modulaweb/API-Tam
	Licence : GPLv3 or higher
	Author : Jean-François VIAL <http://about.me/Jeff_>

*/

function httpRequest($params = array()) {
	// perform an HTTP Request

	$parameters = array( // default parameters
			'host' 		=> '', 													//the host to reach
			'port'		=> 80,
			'path' 		=> '/', 												// the path to ask for
			'datas' 	=> array(), 											// the datas to send
			'type' 		=> 'get', 												// the request type (get, post…)
			'referer' 	=> null, 												// the referer to define
			'viaXHR' 	=> false, 												// simulare an XMLHTTPRequest ?
			'userAgent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:9.0.1) Gecko/20100101 Firefox/9.0.1',
			'headers' 	=> array(),												// some custom additional headers to send
			'cookies'	=> array(),
	);
	$parameters = array_merge($parameters,$params);
	$fp = fsockopen($parameters['host'], $parameters['port'], $errno, $errstr, 30);
	if (!$fp) {
		return array(
			'success' => false,
			'message' => "$errstr ($errno)"
		);
	} else {
		$type = strtoupper($parameters['type']);
		switch($type) {
			case 'POST_MULTIPART':
				$method = 'POST';
				break;
			default:
				$method = $type;
		}

		$encodedDatas = '';
		foreach ($parameters['datas'] as $k=>$v) {
			$encodedDatas .= ($encodedDatas ? '&' : '');
	        $encodedDatas .= rawurlencode(trim($k)).'='.rawurlencode($v);
	    }

		$out = "{$method} {$parameters['path']}";
		if ($method == 'GET' && $encodedDatas != '')
			$out .= '?'.$encodedDatas;
		$out .= " HTTP/1.1\r\n";
		$out .= "Host: {$parameters['host']}\r\n";
		$out .= "User-Agent: {$parameters['userAgent']}\r\n";
		if ($parameters['viaXHR']) {
			$out .= "X-Requested-With: XMLHttpRequest\r\n";
			$out .= "Accept: text/javascript, text/html, application/xml, text/xml, */*\r\n";
		} else {
			$out .= "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n";
		}
		if ($type == 'POST')
			$out .= "Content-Type: application/x-www-form-urlencoded; charset=UTF-8\r\n";
		if ($type == 'POST_MULTIPART')
			$out .= "Content-Type: multipart/form-data; charset=UTF-8\r\n";
		if (!is_null($parameters['referer']))
			$out .= "Referer: {$parameters['referer']}\r\n";
		foreach($parameters['headers'] as $k=>$v) {
			$out .= "{$k}: {$v}\r\n";
		}
		$cookies = 0;
		if (count($parameters['cookies']) > 0) {
			$out .= 'Cookie: ';
			foreach($parameters['cookies'] as $k=>$v) {
				if ($cookies > 0) $out .= '; ';
				$out .= "{$k}={$v}";
				$cookies++;
			}
			$out .= "\r\n";
		}
		$out .= "Connection: keep-alive\r\n";
		if ($method == 'POST') {
			$out .= "Content-Length: ".strlen($encodedDatas)."\r\n\r\n";
			$out .= $encodedDatas;
		}
		if ($method == 'GET') {
			$out .= "Content-Length: 0\r\n\r\n";
		}

		fwrite($fp, $out);
//		echo $out."\n\n";
		// getting response
		$in_headers = true;	// true if we are still in headers
		$headers = $body = '';
		while (!feof($fp)) {
			$line = fgets($fp);
			if ($in_headers && ($line == "\n" || $line == "\r\n")) {
				// empty line : end of headers
				$in_headers = false;
			}
			if ($in_headers)
				$headers .= $line;
			else
				$body .= $line;
		}
		fclose($fp);
		$ret = array(
			'success' => true,
			'headers' => $headers,
			'body'    => $body
		);
		return $ret;
	}
}
?>
