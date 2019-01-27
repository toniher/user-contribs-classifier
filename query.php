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

// Get pages of user
$pages = retrieveWpQuery( $pages, $wpapi, $params, null );

// Get Qs of pages
$retrieve = retrieveQsFromWp( $pages, $wpapi );

var_dump( $pages );
var_dump( $retrieve );

$wpapi->logout();

$wdapi = MwApi\MediawikiApi::newFromApiEndpoint( $wikidataconfig['url'] );

$result = retrievePropsFromWd( $retrieve, $wdapi );

$wdapi->logout();


function retrieveWpQuery( $pages, $wpapi, $params, $uccontinue ) {
	
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

			$pages = processPages( $pages, $outcome["query"]["usercontribs"] );
	
		}
	
	}
	
	if ( $uccontinue ) {
		$pages = retrieveWpQuery( $pages, $wpapi, $params, $uccontinue );
	}
	
	return $pages;
	
}

function processPages( $pages, $contribs ) {
	
	foreach ( $contribs as $contrib ) {
		
		$title = $contrib["title"];
		$timestamp = $contrib["timestamp"];
	
		$struct = array( "timestamp" => $timestamp );
		
		if ( array_key_exists( $title, $pages ) ) {
			
			$prets = $pages[$title]["timestamp"];
			
			if ( strtotime( $timestamp ) < strtotime( $prets ) ) {
				
				$pages[$title]["timestamp"] = $prets;
			}
			
			
		} else {
			
			$pages[ $title ] = $struct;
		}
		
	}
	return $pages;
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

function retrievePropsFromWd( $retrieve, $wdapi ) {
	
	$batch = 50;
	$count = 0;
	$qentries = array();
	$queryResult = array();
	
	foreach( $retrieve["pagesQ"] as $page => $qentry ) {
		
		$count++;
		
		if ( $count < $batch ) {
			array_push( $qentries, $qentry );
		} else {
			
			$queryResult = retrievePropsWd( $queryResult, $qentries, $wdapi );
			
			$count = 0;
			$qentries = array( );
			array_push( $qentries, $qentry );

		}
		
	}
			
	if ( count( $qentries ) > 0 ) {
		
		$queryResult = retrievePropsWd( $queryResult, $qentries, $wdapi );

	}
	
	return $queryResult;
	
}

function retrievePropsWd( $queryResult, $qentries, $wdapi ) {
	
	$qentriesStr = implode( "|", $qentries );

		
	// Below for main WikiData ID
	$params = array( "ids" => $qentriesStr, "props" => "claims" );
	
	$listEntities = new Mwapi\SimpleRequest( 'wbgetentities', $params  );

	$outcome = $wdapi->postRequest( $listEntities );
	
	var_dump( $outcome );

	return $queryResult;
	
}

