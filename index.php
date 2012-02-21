<?php
/*
    Author : jean-François VIAL <http://about.me/Jeff_>
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>
*/

/* Utilities                                                                  */
/******************************************************************************/

function httpRequest($params = array()) {
	// perform an HTTP Request

	$parameters = array( // default parameters
			'host' 		=> '', 													//the host to reach
			'port'		=> 80,
			'path' 		=> '/', 												// the path to ask for
			'datas' 	=> array(), 											// the datas to send
			'type' 		=> 'get', 												// the request type (get, post (urlencoded form), post_multipart (multipart form data post)
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

/* Main functions & global vars                                               */
/******************************************************************************/

// operators : 'town' => 'operator name'
$operators = array(
				'montpellier' => 'tam',
				'grenoble'	  => 'tag',
				'orleans'	  => 'tao',
				'reims'		  => 'citura',
			);
$operator = 'tam'; // default operator

$MOBITrans_session_id = ''; // the curent session ID from the Transdev server
$MOBITrans_cookie = ''; // the curent cookie from the Transdev server

function startMobitransSession() {
	// Get the session Id from the server
    global $MOBITrans_session_id,$operator,$MOBITrans_cookie;
    $page = httpRequest(array(
    	'host' => $operator.'.mobitrans.fr',
    	'path' => '/index.php'
    ));
    if ($page['success']) {
		preg_match('#&I=([^&]*)&#',$page['body'],$matches);
		$MOBITrans_session_id = $matches[1];
	}
	preg_match('/MOBITRANS_ID=([^;]*);/',$page['headers'],$matches);
	$MOBITrans_cookie = $matches[1];
}
function getLinesFromMobitrans() {
	// retrieve all available bus/tram lines
    global $lines,$MOBITrans_session_id,$operator,$MOBITrans_cookie;

    if ($MOBITrans_session_id == '') startMobitransSession();

    $lines = array();
    $bad_lines = array(3,4); // lines that are not available

    for ($line = 1; $line<17; $line++) {
        if (!in_array($line,$bad_lines)) {
            $page = httpRequest(array(
                'host' => $operator.'.mobitrans.fr',
                'path' => '/index.php',
                'datas' => array(
                    'ligne' => $line,
                    'p' => 41,
                    'I' => $MOBITrans_session_id,
                    's_ligne' => 'Valider'
                ),
                'type' => 'post',
                'cookies' => array('MOBITRANS_ID'=>$MOBITrans_cookie)
            ));
            preg_match_all('#<a href="\?([^"]*)" class="white">([^<]*)</a><br#',$page['body'],$matches);

            $lines[$line] = array();
            foreach ($matches[2] as $k=>$stop) {
                $params = array();
                $p = explode('&',$matches[1][$k]);
                foreach ($p as $row) {
                    $pp = explode('=',$row);
                    $params[$pp[0]] = trim($pp[1]);
                }
                $lines[$line][$stop] = $params;
            }
            set_time_limit(100);
            // Simulates human clicks
			/*
            $wait = rand(6,35);
            usleep($wait*1000000);
			*/
        }
    }
    // cache the lines informations
    file_put_contents($operator.'_cache.php','<?php $lines = '.var_export($lines,true).'; ?>');
}

function getStopsList() {
	// get bus/tram stop names for all available lines
	global $lines;
	$ret = array();
	foreach($lines as $k=>$stops) {
		$ret[$k] = array();
		foreach($stops as $name=>$args) {
			$ret[$k][] = $name;
		}
	}
	return $ret;
}

function getTimes($line=16,$stopName='Alco',$needTheoric=false) {
	// get the nex transit time
	// its diff time from the curent time
    global $lines,$MOBITrans_session_id,$operator,$MOBITrans_cookie;
    if ($MOBITrans_session_id == '') startMobitransSession();
    $params = $lines[$line][$stopName];
    $params['I'] = $MOBITrans_session_id;
    $params['m'] = 1; // *must* be 1 !!!!
    if ($needTheoric) {
    	$params['o'] = 2; // will serve only theoric times !
    }
    $page = httpRequest(array(
    	'host' => $operator.'.mobitrans.fr',
    	'path' => '/index.php',
    	'datas' => $params,
        'cookies' => array('MOBITRANS_ID'=>$MOBITrans_cookie)
    ));

    if (!preg_match_all('#<b>Vers ([^<]*)</b>#',$page['body'],$directions)) {
    	// if that mask is not found, there may be a problem…
    	// TODO : improuve that
    	// TODO : sometimes the Mobitrans server don't answer correctly the very first time we call it, we need to ask again until it respond correctly
        if (preg_match("#Pas d'horaire disponible pour l'instant#",$page['body'],$junk)) {
        	// if the page explain explicitely that no times are available at this time, return false
            return false;
        }
    } else {
    	// everything is ok, lets get the directions
        $directions = $directions[1];
        // next transit time at this stop
        preg_match_all('#(!?) Prochain passage : (.*)#',strip_tags($page['body']),$times);
		$ret = array();
		$step = 0;
        foreach($directions as $k=>$dir) {
		    $ret[$dir] = array(
		    				'times' => array(
								            trim(utf8_encode($times[2][$step])),
										    trim(utf8_encode($times[2][$step+1])),
							),
							'theoric' => array(
											(($times[1][$step] != '') || $needTheoric), // is the first time is theoric ? yes = true
											(($times[1][$step+1] != '') || $needTheoric),// is the second time is theoric ? yes = true
							),
		                 );
		    $step+=2;
		}
        return $ret;
    }
}

function getTransit($line=2,$stopName='Aiguelongue',$direction='',$needTheoric=false) {
	// get the transit time
    $times = getTimes($line,$stopName,$needTheoric);
    if (is_array($times)) {
    	if (trim($direction)!='') {
    		// verify, if a direction that has been explicitely asked, really exists
        	if (array_key_exists($direction,$times))
            	return $times[$direction];
            else
            	return false;
    	} else {
    		return $times;
        }
    } else {
    	// no transit time or error occured
        return array('times'=>array(false,false),'theoric'=>array($needTheoric,$needTheoric));
    }
}

/* Main                                                                       */
/******************************************************************************/

@include $operator.'_cache.php';
// it the cache is empty, retrieve all lines
if (!isset($lines)) getLinesFromMobitrans();

// handle API calls
switch($_REQUEST['request']) {
	case 'getStopsList':
		$response = array(
			'status' => 'ok',
			'response' => getStopsList(),
		);
		header('Content-type: application/json',true);
		exit(json_encode($response));
		break;
	case 'getTransit':
		if (isset($lines[$_REQUEST['line']][$_REQUEST['stop']])) {
			$ret = getTransit($_REQUEST['line'],$_REQUEST['stop'],$_REQUEST['direction'],$_REQUEST['theoric']);
			if ($ret === false)
				$response = array(
					'status' => 'error',
					'response' => 'Bad direction',
				);
			else
				$response = array(
					'status' => 'ok',
					'response' => $ret,
				);
		} else {
			$response = array(
				'status' => 'error',
				'response' => 'Bad line or stop. Use request=getStopList first, to discover all available lines and stops !',
			);
		}
		header('Content-type: application/json',true);
		exit(json_encode($response));
		break;
	default:
		// display the home page (not included with this code)
		include('index2.html');
		break;
}
?>
