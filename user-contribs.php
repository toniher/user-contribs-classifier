<?php

// ini_set('memory_limit', '32384M');

require_once( __DIR__ . '/vendor/autoload.php' );

use \Mediawiki\Api as MwApi;
use \Mediawiki\Api\ApiUser;
use \Wikibase\Api as WbApi;
use \Mediawiki\DataModel as MwDM;
use \Wikibase\DataModel as WbDM;

// Detect commandline args
$conffile = 'config.json';
$userhandle = null;
$taskname = null; // If no task given, exit

if ( count( $argv ) > 1 ) {
	$conffile = $argv[1];
}

if ( count( $argv ) > 2 ) {
	$userhandle = $argv[2];
}

if ( count( $argv ) > 3 ) {
	$taskname = $argv[3];
}

// Detect if files
if ( ! file_exists( $conffile ) || ! $userhandle ) {
	die( "Config file or username needed" );
}

$confjson = json_decode( file_get_contents( $conffile ), 1 );

$wikiconfig = null;
$wikidataconfig = null;

if ( array_key_exists( "wikipedia", $confjson ) ) {
	$wikiconfig = $confjson["wikipedia"];
}

if ( array_key_exists( "wikidata", $confjson ) ) {
	$wikidataconfig = $confjson["wikidata"];
}

if ( array_key_exists( "tasks", $confjson ) ) {
	$tasksConf = $confjson["tasks"];
}


$tasks = array_keys( $tasksConf );
$props = null;

if ( count( $tasks ) < 1 ) {
	// No task, exit
	exit;
}

if ( ! $taskname ) {
	echo "No task specified!";
	exit;
} else {
	if ( in_array( $taskname, $tasks ) ) {
		$props = $tasksConf[ $taskname ];
	} else {
		// Some error here. Stop it
		exit;
	}
}

$wpapi = Mwapi\MediawikiApi::newFromApiEndpoint( $wikiconfig["url"] );

$uclimit = 500;

// Login
if ( array_key_exists( "user", $wikiconfig ) && array_key_exists( "password", $wikiconfig ) ) {
	
	$wpapi->login( new ApiUser( $wikiconfig["user"], $wikiconfig["password"] ) );
	// This below assumes you're a bot
	$uclimit = 500;

}

# Base print
if ( array_key_exists( "retrieve", $props ) ) {

	if ( $props["wikiformat"] ) {
		echo "{| class='wikitable sortable' \n";
		echo "! Usuari !! Articles !! Octets !! ".implode( "\t", $props["retrieve"] )."\n";
	} else {
		echo "Usuari\tArticles\tOctets\t".implode( "\t", $props["retrieve"] )."\n";
	}
} else {
	if ( $props["wikiformat"] ) {
		echo "{|  class='wikitable sortable'\n";
		echo "! Usuari !! Articles !! Octets\n";
	} else {
		echo "Usuari\tArticles\tOctets\n";
	}
}

$userlist = array( );

if ( file_exists( $userhandle ) ) {
	
	$userstr = file_get_contents( $userhandle );
	$userlist = explode( "\n", $userstr );
	
} else {
	
	$userlist = explode( ",", $userhandle );
}

foreach( $userlist as $username ) {
	
	$username = trim( $username );

	$pages = array( );
	
	if ( empty( $username ) ) {
		continue;
	}
	
	$params = array( 'list' => 'usercontribs', 'ucuser' => $username, 'uclimit' => $uclimit, 'ucprop' =>'ids|title|timestamp|comment|sizediff|flags' );
	
	if ( array_key_exists( "namespace", $props ) ) {
		$params["ucnamespace"] = $props["namespace"];
	}
	
	if ( array_key_exists( "newonly", $props ) ) {
		
		if ( $props["newonly"] ) {
			$params["ucshow"] = "new";
		}
		
	}
	
	if ( array_key_exists( "startdate", $props ) ) {
		$params["ucstart"] = $props["startdate"];
		$params["ucdir"] = "newer";
	}
	
	if ( array_key_exists( "enddate", $props ) ) {
		$params["ucend"]= $props["enddate"];
		$params["ucdir"] = "newer";
	}
	
	
	// Get pages of user
	$pages = retrieveWpQuery( $pages, $wpapi, $params, null, $props );
	
	if ( array_key_exists( "redirectmerge", $props ) ) {
	
		if ( $props["redirectmerge"] ) {
			$pages = redirectMerge( $pages, $wpapi );
		} 
	
	} 
	
	$retrieve = null;
	$result = null;
	
	# Only if we handle Wikidata Props
	if ( array_key_exists( "retrieve", $props ) ) {
		// Get Qs of pages
		$retrieve = retrieveQsFromWp( $pages, $wpapi );
		
		$wdapi = MwApi\MediawikiApi::newFromApiEndpoint( $wikidataconfig['url'] );
		
		// Login
		if ( array_key_exists( "user", $wikidataconfig ) && array_key_exists( "password", $wikidataconfig ) ) {
			
			$wdapi->login( new ApiUser( $wikidataconfig["user"], $wikidataconfig["password"] ) );
		
		}
		
		$result = retrievePropsFromWd( $retrieve, $props, $wdapi );
	
	}
	
	# TODO: This changed and now token is needed
	#$wpapi->logout();
	#$wdapi->logout();
	
	
	printAll( $pages, $retrieve, $result, $props, $username );

}

if ( $props["wikiformat"] ) {
	echo "|}";
}

function redirectMerge( $pages, $wpapi ) {
	
	$listPages = array_keys( $pages );
	
	$num = 10;
	
	$contPages = array();
	
	$i = 0;
	$c = 0;
	
	foreach ( $listPages as $page ) {
	
		if ( $i == 0 ) {
			$contPages[] = array();
		}
		
		array_push( $contPages[$c], $page );
		
		$i++;
		if ( $i == $num ) {
			$i = 0;
			$c++;
		}
		
	}
	
	$mapping = array();
	
	foreach ( $contPages as $cont ) {
		
		$titles = implode( "|", $cont );
		
		$params = array( 'titles' => $titles, 'redirects' => true );
		
		$redRequest = new Mwapi\SimpleRequest( 'query', $params  );

		$outcome = $wpapi->postRequest( $redRequest );
		
		if ( array_key_exists( "query", $outcome ) ) {
		
			if ( array_key_exists( "redirects", $outcome["query"] ) ) {
	
				foreach( $outcome["query"]["redirects"] as $redirect ) {
					
					if ( array_key_exists( "from", $redirect ) ) {
						$mapping[$redirect["from"]]= $redirect["to"];
					}
					
				}
		
			}
		
		}
		
	}
	
	foreach( $pages as $page => $struct ) {
		
		if ( array_key_exists( $page, $mapping ) ) {
			$redirect = $mapping[$page];
			
			if ( ! array_key_exists( $redirect, $pages ) ) {
				$pages[$redirect] = array();
				$pages[$redirect]["size"] = 0;
			}
			
			$pages[$redirect]["size"]+= $pages[$page]["size"];
			unset( $pages[$page] );
				
			
		}
		
	}

	return $pages;

}


function retrieveWpQuery( $pages, $wpapi, $params, $uccontinue, $props ) {
	
	if ( $uccontinue ) {
		$params["uccontinue"] = $uccontinue;
	}
	
	$userContribRequest = new Mwapi\SimpleRequest( 'query', $params  );

	$outcome = $wpapi->postRequest( $userContribRequest );

	$uccontinue = null;
	
	if ( array_key_exists( "continue", $outcome ) ) {
		
		if ( array_key_exists( "uccontinue", $outcome["continue"] ) ) {
			
			$uccontinue = $outcome["continue"]["uccontinue"];
		} 
	} 
	
	if ( array_key_exists( "query", $outcome ) ) {
	
		if ( array_key_exists( "usercontribs", $outcome["query"] ) ) {

			$pages = processPages( $pages, $outcome["query"]["usercontribs"], $props );
	
		}
	
	}
	
	if ( $uccontinue ) {
		$pages = retrieveWpQuery( $pages, $wpapi, $params, $uccontinue, $props );
	}
	
	return $pages;
	
}

function processPages( $pages, $contribs, $props ) {
	
	foreach ( $contribs as $contrib ) {
		
		$title = strval( $contrib["title"] );
		#$timestamp = $contrib["timestamp"];
		$size = intval( $contrib["sizediff"] );
	
		$struct = array( "size" => $size );
		
		# Regex skip comment
		if ( array_key_exists( "skip_startswith", $props ) && array_key_exists( "comment", $contrib ) ) {
			
			if ( startsWith( $contrib["comment"], $props["skip_startswith"] ) ) {
				continue;

			}

		}
		
		#$timeCompare = compareTime( $timestamp, $props );
		
		#if ( $timeCompare ) {
		
			if ( array_key_exists( $title, $pages ) ) {
				
				#$prets = $pages[$title]["timestamp"];
				$presize = $pages[$title]["size"];
				
				#if ( strtotime( $timestamp ) < strtotime( $prets ) ) {
					
				#	$pages[$title]["timestamp"] = $prets;
				#}
				
				$pages[$title]["size"] = $presize + $size;
				
				
				
			} else {
				
				$pages[ $title ] = $struct;
			}
		
		#}
		
	}
	return $pages;
}



function startsWith($haystack, $needle)
{
     $length = strlen($needle);
     return (substr($haystack, 0, $length) === $needle);
}

function compareTime( $timestamp, $props ) {
	
	$inInterval = true;
	
	$timestampTime = strtotime( $timestamp );
	
	$startdateTime = null;
	$enddateTime = null;

	if ( array_key_exists( "startdate", $props ) ) {
		$startdateTime = strtotime( $props["startdate"] );
	}
	
	if ( array_key_exists( "enddate", $props ) ) {
		$enddateTime = strtotime( $props["enddate"] );
	}
	
	if ( $startdateTime && ( $timestampTime < $startdateTime ) ) {
		$inInterval = false;
	}
	
	if ( $enddateTime && ( $timestampTime > $enddateTime ) ) {
		$inInterval = false;
	}
	
	return $inInterval;
	
}

function retrieveQsFromWp( $pages, $wpapi ){
	
	$batch = 50;
	$count = 0;
	$titles = array();
	$retrieve = array();
	$retrieve["pagesQ"] = array();
	$retrieve["redirects"] = array();
	
	foreach( $pages as $page => $struct ) {
		
		$count++;
		
		if ( $count < $batch ) {
			array_push( $titles, $page );
		} else {
			
			$retrieve = retrieveWikidataId( $retrieve, $titles, $wpapi );
			
			$count = 0;
			$titles = array( );
			array_push( $titles, $page );

		}
		
	}
			
	if ( count( $titles ) > 0 ) {
		
		$retrieve = retrieveWikidataId( $retrieve, $titles, $wpapi );

	}
	
	return $retrieve;
}

function retrieveWikidataId( $retrieve, $titles, $wpapi ){

	$titlesStr = implode( "|", $titles );
	#$titlesStr = str_replace( " ", "_", $titlesStr );
	
	// Below for main WikiData ID
	$params = array( "titles" => $titlesStr, "prop" => "pageprops", "ppprop" => "wikibase_item", "redirects" => "true" );
	
	$listQsRequest = new Mwapi\SimpleRequest( 'query', $params  );

	$outcome = $wpapi->postRequest( $listQsRequest );
	
	if ( array_key_exists( "query", $outcome ) ) {
		
		if ( array_key_exists( "redirects", $outcome["query"] ) ) {
			
			foreach ( $outcome["query"]["redirects"] as $redirect ) {
				
				$retrieve["redirects"][ $redirect["from"] ] = $redirect["to"];
				
			}
			
		}
		
		if ( array_key_exists( "pages", $outcome["query"] ) ) {
						
			foreach ( $outcome["query"]["pages"] as $id => $qentry ) {
				
				if ( array_key_exists( "pageprops", $qentry ) ) {
					
					if ( array_key_exists( "wikibase_item", $qentry["pageprops"] ) ) {

						if ( count( $qentry["pageprops"]["wikibase_item"] ) > 0 ) {
							
							$retrieve["pagesQ"][$qentry["title"]] = $qentry["pageprops"]["wikibase_item"];
							
						}
					}
				}
			}
			
		}
	}
	
	return $retrieve;
	
}

function retrievePropsFromWd( $retrieve, $props, $wdapi ) {
	
	$batch = 50;
	$count = 0;
	$qentries = array();
	$queryResult = array();
	
	foreach( $retrieve["pagesQ"] as $page => $qentry ) {
		
		$count++;
		
		if ( $count < $batch ) {
			array_push( $qentries, $qentry );
		} else {
			
			$queryResult = retrievePropsWd( $queryResult, $qentries, $props, $wdapi );
			
			$count = 0;
			$qentries = array( );
			array_push( $qentries, $qentry );

		}
		
	}
			
	if ( count( $qentries ) > 0 ) {
		
		$queryResult = retrievePropsWd( $queryResult, $qentries, $props, $wdapi );

	}
	
	return $queryResult;
	
}

function retrievePropsWd( $queryResult, $qentries, $props, $wdapi ) {
	
	$qentriesStr = implode( "|", $qentries );

	// Below for main WikiData ID
	$params = array( "ids" => $qentriesStr, "props" => "claims" );
	
	$listEntities = new Mwapi\SimpleRequest( 'wbgetentities', $params  );

	$outcome = $wdapi->postRequest( $listEntities );
	
	$filter = null;
	$filterProps = array( );
	$retrieve = null;
	
	if ( array_key_exists( "filter", $props ) ) {
		$filter = $props["filter"];

		foreach( $filter as $filterEntry ) {
			
			if ( array_key_exists( "prop", $filterEntry ) ) {
				$filterProps[ $filterEntry["prop"] ] = null;
				
				if ( array_key_exists( "propValue", $filterEntry ) ) {
					$filterProps[ $filterEntry["prop"] ] = $filterEntry["propValue"];
				}
			}
		}
	}
	
	if ( array_key_exists( "retrieve", $props ) ) {
		$retrieve = $props["retrieve"];
	}
	
	if ( array_key_exists( "entities", $outcome	) ) {
		
		
		foreach ( $outcome["entities"] as $entity => $struct ) {
			
			$in = 0;
			$retrieved = array( );
			
			if ( array_key_exists( "claims", $struct ) ) {
								
				foreach ( $struct["claims"] as $claimProp => $claimStruct ) {
					
					
					if ( in_array( $claimProp, $retrieve ) ) {
						$retrieved[ $claimProp ] = getValueSnaks( $claimStruct );
						
					}
					
					if ( array_key_exists( $claimProp, $filterProps ) ) {
						
						if ( ! $filterProps[ $claimProp ] ) {
							$in = 1;
						} else {
							if ( compareValueSnaks( $filterProps[ $claimProp ], $claimStruct ) ) {
								
								$in = 1;
							}
						}
						
					}
					
				}
				
			}
			
			
			if ( $in > 0 ) {
				

				if ( count( array_keys( $retrieved ) ) >  0 ) {

					$queryResult[ $entity ] = $retrieved;
					
				}
				
			}
			
		}
		
	}

	return $queryResult;
	
}

function getValueSnaks( $claimStruct ) {
	
	$values = array();
	
	foreach ( $claimStruct as $struct ) {
		
		if ( array_key_exists( "mainsnak", $struct ) ) {
			
			$mainsnak = $struct["mainsnak"];
			
			if ( array_key_exists( "datavalue", $mainsnak ) ) {
				
				array_push( $values, processDataValue( $mainsnak["datavalue"] ) );
				
			}
			
		}
	}
	
	return $values;
}

function compareValueSnaks(  $value, $claimStruct ) {
	
	$values = array();
	
	foreach ( $claimStruct as $struct ) {
		
		if ( array_key_exists( "mainsnak", $struct ) ) {
			
			$mainsnak = $struct["mainsnak"];
			
			if ( array_key_exists( "datavalue", $mainsnak ) ) {
				
				array_push( $values, processDataValue( $mainsnak["datavalue"] ) );
				
			}
			
		}
	}
	
	if ( in_array( $value, $values ) ) {
		return true;
	} else {
		return false;
	}
	
}

function processDataValue( $datavalue ) {
	
	$type = $datavalue["type"];
	$value = null;
	
	switch( $type ) {
		
		// TODO: More to include
		case "wikibase-entityid" :
			$value = $datavalue["value"]["id"];
			break;
		case "time":
			$value = $datavalue["value"]["time"];
			break;
		default:
			if ( array_key_exists( "value", $datavalue ) ) {
				$value = $datavalue["value"];
			}
	}
	
	return $value;
	
}

function resolveQValue( $qval, $type="label", $lang="ca" ) {
	
	$output = null;
	
	// TODO To be fixed. URLencode and so..
	if ( $type === "label" ) {
	
		$url = "https://query.wikidata.org/sparql?query=PREFIX%20rdfs%3A%20%3C";
		$url .= "http%3A%2F%2Fwww.w3.org%2F2000%2F01%2Frdf-schema%23%3E%20%0APREFIX%20wd%3A%20%3Chttp%3A%2F%2Fwww.wikidata.org%2Fentity%2F%3E%20%0Aselect%20%20*%0A";
		$url .= "where%20%7B%0A%20%20%20%20%20%20%20%20wd%3A".$qval."%20rdfs%3Alabel%20%3Flabel%20.%0A%20%20FILTER%20";
		$url .= "langMatches(%20lang(%3Flabel)%2C%20%22".$lang."%22%20)%20)%0A%20%20%20%20%20%20%7D%20%0ALIMIT%201";
		
		$ch = curl_init();
		$headers = [
			'Accept: application/json'
		];
		
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_URL, $url );
		$result = curl_exec($ch);
		curl_close($ch);
		
		
		$obj = json_decode($result, true);
		
		if ( array_key_exists( "results", $obj ) ) {
			
			if ( array_key_exists( "bindings", $obj["results"] ) ) {
				
				if ( array_key_exists( "label", $obj["results"]["bindings"] ) ) {
					
					if ( array_key_exists( "value", $obj["results"]["bindings"]["label"] ) ) {
					
						$output = $obj["results"]["bindings"]["label"]["value"];
					
					}
					
				}
			}
		}		
		
	}
	
	return $output;
}

function printAll( $pages, $retrieve, $result, $props, $username, $sum=true ) {

	if ( array_key_exists( "wikiformat", $props ) ) {
		if ( $props["wikiformat"] ) {
			$username = "{{u|".$username."}}";
		}
	}
	
	foreach ( $pages as $page => $struct ) {
	
		$sumVal = 0;
		
		$pagelabel = $page;
		if ( array_key_exists( "resolvepage", $props ) ) {
			// To be improved;
			$qval = resolveQValue( $page );
			if ( $qval ) {
				$pagelabel = $qval;
			}
			sleep( 1 );
		}
		
		$print = 0;
		if ( array_key_exists( "size", $props ) ) {
			
			if ( $struct["size"] < $props["size"] ) {
				
				# If smaller size, avoid
				continue;
			}
			
			if ( $sum ) {
				$sumVal =+ $struct["size"];
			
			}
		}
		
		$size = $struct["size"];
			
		if ( $sum ) {
			$size = $sumVal;
		}
		
		$wikiprefix = "";
		if ( array_key_exists( "wikiprefix", $props ) ) {
			$wikiprefix = ":".$props["wikiprefix"].":";
		}
		
		if ( $retrieve && array_key_exists( $page, $retrieve["pagesQ"] ) ) {
			
			$qid = $retrieve["pagesQ"][$page];

			
			$vals = array( );
			if ( array_key_exists( $qid, $result ) ) {

				$print = 1;
				
				$Qprops = $result[ $qid ];
				
				foreach ( $props["retrieve"] as $prop ) {
					
					$toadd = "";
					
					if ( array_key_exists( $prop, $Qprops ) ) {
						$toadd = implode( ",", $Qprops[ $prop ] );
					}
					
					array_push( $vals, $toadd );
					
				}
				
			}
			
			if ( $print > 0 ) {
				
				if ( array_key_exists( "wikiformat", $props ) ) {

					
					if ( $props["wikiformat"] ) {
						$page = "[[".$wikiprefix.$page."|".$pagelabel."]]";
					}
				}
				
				if ( $props["wikiformat"] ) {
					echo "|-\n";
					echo "| ".$username." || ".$page." || ".$size." || ".implode( "\t", $vals ), "\n";
				} else {
					echo $username."\t".$page."\t".$size."\t".implode( "\t", $vals ), "\n";
				}
			}
		} else {

			if ( array_key_exists( "wikiformat", $props ) ) {
				if ( $props["wikiformat"] ) {
					$page = "[[".$wikiprefix.$page."|".$pagelabel."]]";
				}
			}
			
			if ( $props["wikiformat"] ) {
				echo "|-\n";
				echo "| ".$username." || ".$page." || ".$size."\n";
			} else {
				echo $username."\t".$page."\t".$size."\n";
			}
		}
		
		
	}
	
}

