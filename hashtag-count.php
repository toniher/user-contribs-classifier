<?php

require_once( __DIR__ . '/vendor/autoload.php' );

use \Mediawiki\Api as MwApi;
use \Mediawiki\Api\ApiUser;
use \Wikibase\Api as WbApi;
use \Mediawiki\DataModel as MwDM;
use \Wikibase\DataModel as WbDM;


// Detect commandline args
$conffile = 'config.json';
$taskname = null; // If no task given, exit

if ( count( $argv ) > 1 ) {
	$conffile = $argv[1];
}

if ( count( $argv ) > 2 ) {
	$taskname = $argv[2];
}
// Detect if files
if ( ! file_exists( $conffile ) ) {
	die( "Config file needed" );
}

$confjson = json_decode( file_get_contents( $conffile ), 1 );

$wikiconfig = null;

if ( array_key_exists( "wikipedia", $confjson ) ) {
	$wikiconfig = $confjson["wikipedia"];
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

$rclimit = 5000;


// Login
if ( array_key_exists( "user", $wikiconfig ) && array_key_exists( "password", $wikiconfig ) ) {
	
	$wpapi->login( new ApiUser( $wikiconfig["user"], $wikiconfig["password"] ) );

}

// Get all pages with a certain tag
if ( array_key_exists( "tag", $props )  &&  array_key_exists( "startdate", $props ) ) {
	
	#https://ca.wikipedia.org/w/api.php?action=query&list=recentchanges&rcprop=comment|user|title|timestamp|sizes|redirect
	
	$params = array( "list" => "recentchanges", "rcprop" => "comment|title", "rclimit" => $rclimit, "rcdir" => "newer", "rcnamespace" => 0 );
	
	if ( array_key_exists( "startdate", $props ) ) {
		$params["rcstart"] = $props["startdate"];
	}
	if ( array_key_exists( "enddate", $props ) ) {
		$params["rcend"] = $props["enddate"];
	}

	if ( array_key_exists( "namespace", $props ) ) {
		$params["rcnamespace"] = $props["namespace"];
	}
	
	$pages = retrieveWpQuery( $pages, $wpapi, $params, null, $props );
	print_r( array_keys( $pages ) );

}

function retrieveWpQuery( $pages, $wpapi, $params, $uccontinue, $props ) {
	
	if ( $uccontinue ) {
		$params["rccontinue"] = $uccontinue;
	}
	
	$userContribRequest = new Mwapi\SimpleRequest( 'query', $params  );

	$outcome = $wpapi->postRequest( $userContribRequest );

	$uccontinue = null;
	
	if ( array_key_exists( "continue", $outcome ) ) {
		
		if ( array_key_exists( "rccontinue", $outcome["continue"] ) ) {
			
			$uccontinue = $outcome["continue"]["rccontinue"];
		} 
	} 
	
	if ( array_key_exists( "query", $outcome ) ) {
	
		if ( array_key_exists( "recentchanges", $outcome["query"] ) ) {

			$pages = processPages( $pages, $outcome["query"]["recentchanges"], $props );
	
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
		$comment = strval( $contrib["comment"] );

		if ( detectTag( $comment, $props["tag"] ) ) {
	
			$pages[$title] = true;
		}
	}
	return $pages;
}

function detectTag( $comment, $tags ){
	
	foreach ( $tags as $tag ) {
		
		if ( strpos( strtolower( $comment ), strtolower( $tag ) ) !== false ) {
			return true;
		}
		
	}
	
	return false;
	
}
