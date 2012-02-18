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

function curlIt($url,$data=array(),$method='get',$progress=false) {
	// performs all curl calls.
    $c = curl_init($url);
    if ($method=='post') {
        curl_setopt($c, CURLOPT_POST, 1);
        curl_setopt($c, CURLOPT_POSTFIELDS, $data);
    }
    // UA *must* be a classic one, for example Firefox
    curl_setopt($c, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64; rv:9.0.1) Gecko/20100101 Firefox/9.0.1');
    // those options are mandatory
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
    if ($progress) curl_setopt($c, CURLOPT_NOPROGRESS, 0);
    $ret = curl_exec($c);
    curl_close($c);
    return $ret;
}

/* Main functions & global vars                                               */
/******************************************************************************/

$operators = array(
				'montpellier' => 'tam',
				'grenoble'	  => 'tag',
				'orleans'	  => 'tao',
				'reims'		  => 'citura',
			);
$operator = 'tam';
$session_id = ''; // the curent session ID from the Transdev server

function getSessionId() {
	// Get the session Id from the server
    global $session_id,$operator;
    $page = curlIt('http://'.$operator.'.mobitrans.fr/index.php');
    preg_match('#&I=([^&]*)&#',$page,$matches);
    $session_id = $matches[1];
}
function getLines() {
	// retrieve all available bus/tram lines
    global $lines,$session_id,$operator;
    $lines = array();
    $bad_lines = array(3,4); // lines that are not available

    for ($line = 1; $line<17; $line++) {
        if (!in_array($line,$bad_lines)) {
            $page = curlIt(
                'http://'.$operator.'.mobitrans.fr/index.php',
                array(
                    'ligne' => $line,
                    'p' => 41,
                    'I' => $session_id,
                    's_ligne' => 'Valider'
                ),
                'post'
            );

            preg_match_all('#<a href="\?([^"]*)" class="white">([^<]*)</a><br#',$page,$matches);

            $lines[$line] = array();
            foreach ($matches[2] as $k=>$stop) {
                $params = array();
                $p = explode('&',$matches[1][$k]);
                foreach ($p as $row) {
                    $pp = explode('=',$row);
                    $params[$pp[0]] = $pp[1];
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
    file_put_contents($operator.'.php','<?php $lines = '.var_export($lines,true).'; ?>');
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

function getTimes($line=16,$stopName='Alco') {
	// get the nex transit time
	// its diff time from the curent time
    global $lines,$session_id,$operator;
    $params = $lines[$line][$stopName];
    $params['I'] = $session_id;
    $params['m'] = 1; // *must* be 1 !!!!
    $url = '';
    // forge the url
    foreach ($params as $k=>$v) {
        if ($url != '') $url .= '&';
        $url .= $k.'='.$v;
    }

    $page = curlIt('http://'.$operator.'.mobitrans.fr/index.php?'.$url);
    if (!preg_match_all('#<b>Vers ([^<]*)</b>#',$page,$directions)) {
    	// if that mask is not found, there may be a problem…
    	// TODO : improuve that
    	// TODO : sometimes the Mobitrans server don't answer correctly the very first time we call it, we need to ask again until it respond correctly
        if (preg_match("#Pas d'horaire disponible pour l'instant#",$page,$junk)) {
        	// if the page explain explicitely that no times are available at this time, return false
            return false;
        }
    } else {
    	// everything is ok, lets get the directions
        $directions = $directions[1];
        // next transit time at this stop
        preg_match_all('#(!?) Prochain passage : (.*)#',strip_tags($page),$times);
		$ret = array();
		$step = 0;
        foreach($directions as $k=>$dir) {
		    $ret[$dir] = array(
		    				'times' => array(
								            trim(utf8_encode($times[2][$step])),
										    trim(utf8_encode($times[2][$step+1])),
							),
							'theoric' => array(
											($times[1][$step] != ''), // is the first time is theoric ? yes = true
											($times[1][$step+1] != ''),// is the second time is theoric ? yes = true
							),
		                 );
		    $step+=2;
		}
        return $ret;
    }
}

function getTransit($line=2,$stopName='Aiguelongue',$direction='') {
	// get the transit time
    $times = getTimes($line,$stopName);
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
        return array('times'=>array(false,false),'theoric'=>array(false,false));
    }
}

/* Main                                                                       */
/******************************************************************************/

getSessionId();
@include $operator.'.php';
// it the cache is empty, retrieve all lines
if (!isset($lines)) getLines();

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
			$ret = getTransit($_REQUEST['line'],$_REQUEST['stop'],$_REQUEST['direction'],'json');
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
