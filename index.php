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

    Sources are tam.mobitrans.fr and tam.agilisprod.com
*/

/* Utilities                                                                  */
/******************************************************************************/

require_once 'libs/utilities.php';
require_once 'libs/phoneticize.php';

function operatorCache() {
	// create a cache of all infos about the curent operator
	global $operator,$lines,$agilis;
    file_put_contents($operator.'_cache.php',"<?php
// Auto generated file, do not edit !!!
// To renew this file, delete it !
\$lines = ".var_export($lines,true).";
\$agilis = ".var_export($agilis,true).";
?>");

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

function getStopFromAgilis($stop) {
	// get the «real» stop name from MOBITrans® one via Agilis service.

	// lets make some pre treatment on $stop
	$stop = preg_replace('/ st /i', ' saint-',
			str_ireplace('rte ', 'route ',
			str_ireplace('bd ', 'boulevard ',
			str_ireplace('av. ', 'avenue ',
			str_ireplace('pl. ', 'place ',
			str_ireplace('cim. ', 'cimetière ',
			// those stops does not exists or need to be corrected to be found
			str_ireplace('ravas', 'ravaz',
//			str_ireplace('arceaux', 'Sainte Thérèse', TODO verify !!!
			str_ireplace('Gare Routiere', 'Gare Saint Roch',
			str_ireplace('Les Lacs', 'La Pompignane (Les Lacs)',
			str_ireplace('Francoise', 'Françoise',
			str_ireplace('Ecossais', 'Écossais',
			str_ireplace('Saint Jean', 'Saint-Jean',
			preg_replace('/europ$/i', 'Europe',
			str_ireplace(' gallois', ' galois',
			str_ireplace('etienne', 'Étienne',
			str_ireplace('etoile', 'Étoile',
			str_ireplace('erables', 'Érables',
			str_ireplace('agriculture', 'l\'Agriculture',

			preg_replace('/[^ ]d\'/i',' d\'',
			preg_replace('/ [tl][1-9]/i','',
			preg_replace('/ Juss$/i',' (Jussieu)',
			' '.$stop)))))))))))))))))))));

	$page = httpRequest(array(
		'host' => 'tam.agilisprod.com',
		'path' => '/stop_area_searches',
		'datas' => array('stop_area_search[user_input]' => $stop),
		'type' => 'post',
		'referer' => 'http://tam.agilisprod.com/stop_area_searches',
		'viaXHR' => true
	));

	$ret = false;
	$dom = new DomDocument();
	$dom->loadXML(trim($page['body']).'>');
	$lis = $dom->getElementsByTagName('li');
	if ($lis->length > 1) {
		// more than one response, lets take the equal or most phonetically near one
		foreach ($lis as $li) {
			if (preg_match('/(.*) ?\([0-9]{5} (.*)\)/',trim($li->nodeValue),$matches)) {
				$name = $matches[1];
				$town = $matches[2];
			} else {
				$name = trim($li->nodeValue);
				$town = '';
			}
			if (trim($name) == trim($stop) || phoneticize($name) == phoneticize($stop)) {
				$ret = array(
					'id' => str_replace('stop_','',$li->getAttribute('id')),
					'name' => $name,
					'town' => $town,
				);
				break;
			}
		}
	} elseif ($lis->length == 1) {
		// only one response, should be the good one
		$li = $lis->item(0);
		if (preg_match('/(.*) ?\([0-9]{5} (.*)\)/',trim($li->nodeValue),$matches)) {
			$name = $matches[1];
			$town = $matches[2];
		} else {
			$name = trim($li->nodeValue);
			$town = '';
		}
		$ret = array(
			'id' => str_replace('stop_','',$li->getAttribute('id')),
			'name' => $name,
			'town' => $town,
		);
	}
	if (!$ret && $lis->length > 1) {
		// names differs too much (no response) : lets compare the last word which is the same generally
		$_stop = explode(' ',$stop);
		$_stop = $_stop[count($_stop)-1];
		foreach ($lis as $li) {
			if (preg_match('/(.*) ?\([0-9]{5} (.*)\)/',trim($li->nodeValue),$matches)) {
				$name = $matches[1];
				$town = $matches[2];
			} else {
				$name = trim($li->nodeValue);
				$town = '';
			}
			$_name = explode(' ',$name);
			$_name = $_name[count($_name)-1];
			if (phoneticize($_name) == phoneticize($_stop)) {
				$ret = array(
					'id' => str_replace('stop_','',$li->getAttribute('id')),
					'name' => $name,
					'town' => $town,
				);
				break;
			}
		}
	}
	if (!$ret && $lis->length) {
		// still no response… lets try another thing : do the request with only the last word on $stop
		$page = httpRequest(array(
			'host' => 'tam.agilisprod.com',
			'path' => '/stop_area_searches',
			'datas' => array('stop_area_search[user_input]' => $_stop),
			'type' => 'post',
			'referer' => 'http://tam.agilisprod.com/stop_area_searches',
			'viaXHR' => true
		));

		$ret = false;
		$dom = new DomDocument();
		$dom->loadXML(trim($page['body']).'>');
		$lis = $dom->getElementsByTagName('li');
		foreach ($lis as $li) {
			if (preg_match('/(.*) ?\([0-9]{5} (.*)\)/',trim($li->nodeValue),$matches)) {
				$name = $matches[1];
				$town = $matches[2];
			} else {
				$name = trim($li->nodeValue);
				$town = '';
			}
			$_name = explode(' ',$name);
			$_name = $_name[count($_name)-1];
			if (phoneticize($_name) == phoneticize($_stop)) {
				$ret = array(
					'id' => str_replace('stop_','',$li->getAttribute('id')),
					'name' => $name,
					'town' => $town,
				);
				break;
			}
		}

	}
	return $ret;
}

function createDataCache() {
	// retrieve all available bus/tram lines
    global $lines,$agilis,$MOBITrans_session_id,$operator,$MOBITrans_cookie;

    if ($MOBITrans_session_id == '') startMobitransSession();

    $lines = $agilis = array();
    $bad_lines = array(); // lines that are not available

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
                    // clean up the value
                    $val = explode("\r\n",$pp[1]);
                    $val = $val[count($val)-1];
                    $params[$pp[0]] = trim($val);
                }
                $lines[$line][$stop] = $params;
                $agilis_stop = getStopFromAgilis($stop);
                if ($agilis_stop !== false)
                	$agilis[$stop] = $agilis_stop;
//                else
//                	echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! $stop non trouvé via Agilis\n"; flush();
//                echo "{$line} - {$stop} - {$agilis_stop['name']}\n"; flush();
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
	operatorCache();
}

/******************************************************************************/

function getStopsList($full=false) {
	// get bus/tram stop names for all available lines
	global $lines,$agilis;
	$ret = array();
	foreach($lines as $k=>$stops) {
		$ret[$k] = array();
		foreach($stops as $name=>$args) {
			if ($full) {
				if (isset($agilis[$name]))
					$infos = $agilis[$name];
				else
					$infos = array('id'=>0,'name'=>$name, 'town'=>'');
				$ret[$k][] = array(
					$name => $infos,
				);
			} else {
				$ret[$k][] = $name;
			}
		}
	}
	return $ret;
}

function getStopInfos($stop,$save_to_cache=false) {
	// get all available infos about a stop
	global $lines,$agilis;
	if (isset($agilis[$stop])) {
		$infos = $agilis[$stop];
		// get connected lines if not cached
		if (!isset($infos['lines'])) {
			$page = httpRequest(array(
				'host' => 'tam.agilisprod.com',
				'path' => '/stop_areas/'.$infos['id'].'/stop_area_line_searches',
				'type' => 'get',
				'referer' => 'http://tam.agilisprod.com/stop_area_searches',
				'viaXHR' => true
			));

			preg_match_all(
				'#<a href="/timetable_at_stop_searches/([0-9]*)_'.$infos['id'].'_'.date('Ymd').'"><p class="line_number"><span class="panel" >([^<]*)</span></p><span class="line_name">([^<]*)</span></a>#m',
				html_entity_decode(preg_replace('/\\\\[uU]([0-9a-fA-F]{4})/', '&#x\1;',str_replace('\"','"',$page['body'])), ENT_NOQUOTES, 'UTF-8'),
				$matches
			);

			$infos['lines'] = array();
			foreach ($matches[1] as $k=>$id) {
				$infos['lines'][] = array (
					'id'		=> $id,
					'number'	=> $matches[2][$k],
					'name'		=> $matches[3][$k],
				);
			}
			if ($save_to_cache) {
				$agilis[$stop] = $infos;
				operatorCache();
			}
		}
		return $infos;
	} else {
		return false;
	}
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
        	if (array_key_exists($direction,$times)) {
            	return $times[$direction];
            } else {
            	return false;
            }
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
// if the cache is empty, retrieve all lines
if (!isset($lines) || !isset($agilis)) createDataCache();

/* Galvanize : Google Analytics tracker for PHP */
error_reporting(0);
include('Galvanize.php');

// handle API calls
switch($_REQUEST['request']) {
	case 'getStopsList':
		$GA = new Galvanize('UA-8358756-1');
		$GA->trackPageView();
		$full = isset($_REQUEST['fullInfos']);
		$response = array(
			'status' => 'ok',
			'response' => getStopsList($full),
		);
		header('Content-type: application/json',true);
		exit(json_encode($response));
		break;
	case 'getTransit':
		$GA = new Galvanize('UA-8358756-1');
		$GA->trackPageView();
		$_REQUEST['stop'] = stripslashes($_REQUEST['stop']);
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
