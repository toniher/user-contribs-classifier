<?php

require_once( __DIR__ . '/vendor/autoload.php' );

use \Mediawiki\Api as MwApi;
use \Wikibase\Api as WbApi;
use \Mediawiki\DataModel as MwDM;
use \Wikibase\DataModel as WbDM;

// Detect commandline args
$conffile = 'config.json';
$username = null;
$taskname = null; // If no task given, exit

if ( count( $argv ) > 1 ) {
	$conffile = $argv[1];
}

if ( count( $argv ) > 2 ) {
	$username = $argv[2];
}

if ( count( $argv ) > 3 ) {
	$taskname = $argv[3];
}

// Detect if files
if ( ! file_exists( $conffile ) || ! $username ) {
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

$pages = array( );

$wpapi = Mwapi\MediawikiApi::newFromApiEndpoint( $wikiconfig["url"] );

$params = array( 'list' => 'usercontribs', 'ucuser' => $username, 'uclimit' => 500 );

if ( array_key_exists( "namespace", $props ) ) {
	$params["ucnamespace"] = $props["namespace"];
}

if ( array_key_exists( "newonly", $props ) ) {
	
	if ( $props["newonly"] ) {
		$params["ucshow"] = "new";
	}
	
}

// Get pages of user
$pages = retrieveWpQuery( $pages, $wpapi, $params, null, $props );

// Get Qs of pages
$retrieve = retrieveQsFromWp( $pages, $wpapi );

$wpapi->logout();

$wdapi = MwApi\MediawikiApi::newFromApiEndpoint( $wikidataconfig['url'] );

$result = retrievePropsFromWd( $retrieve, $props, $wdapi );

$wdapi->logout();

printAll( $pages, $retrieve, $result, $props );


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
		$timestamp = $contrib["timestamp"];
		$size = intval( $contrib["size"] );
	
		$struct = array( "timestamp" => $timestamp, "size" => $size );
		
		$timeCompare = compareTime( $timestamp, $props );
		
		if ( $timeCompare ) {
		
			if ( array_key_exists( $title, $pages ) ) {
				
				$prets = $pages[$title]["timestamp"];
				$presize = $pages[$title]["size"];
				
				if ( strtotime( $timestamp ) < strtotime( $prets ) ) {
					
					$pages[$title]["timestamp"] = $prets;
				}
				
				$pages[$title]["size"] = $presize + $size;
				
				
				
			} else {
				
				$pages[ $title ] = $struct;
			}
		
		}
		
	}
	return $pages;
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

function printAll( $pages, $retrieve, $result, $props ) {
	
	echo "Page\t".implode( "\t", $props["retrieve"] )."\n";

	foreach ( $pages as $page => $struct ) {
		
		$print = 0;
		if ( array_key_exists( "size", $props ) ) {
			
			if ( $struct["size"] < $props["size"] ) {
				
				# If smaller size, avoid
				continue;
			}
			
		}
		
		if ( array_key_exists( $page, $retrieve["pagesQ"] ) ) {
			
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
			
				echo $page."\t".implode( "\t", $vals ), "\n";

			}
		}
		
		
	}
	
}

