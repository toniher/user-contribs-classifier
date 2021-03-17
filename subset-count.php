<?php

require_once( __DIR__ . '/vendor/autoload.php' );
require_once( __DIR__ . '/lib/functions.php' );
require_once( __DIR__ . '/lib/scores.php' );

use \Mediawiki\Api as MwApi;
use \Mediawiki\Api\ApiUser;
use \Wikibase\Api as WbApi;
use \Mediawiki\DataModel as MwDM;
use \Wikibase\DataModel as WbDM;

use \League\Csv\Reader;

ini_set('memory_limit', '-1'); # Comment if not needed

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
$newpages = array( );
$oldpages = array( );

$wpapi = Mwapi\MediawikiApi::newFromApiEndpoint( $wikiconfig["url"] );

$rclimit = 1000;


// Login
if ( array_key_exists( "user", $wikiconfig ) && array_key_exists( "password", $wikiconfig ) ) {

	$wpapi->login( new ApiUser( $wikiconfig["user"], $wikiconfig["password"] ) );

}

// Get all pages with a certain tag
if ( array_key_exists( "store", $props )  &&  array_key_exists( "query", $props ) ) {

	$startdate = null;
	$enddate = null;

	if ( array_key_exists( "startdate", $props ) ) {
		$startdate = $props["startdate"];
	}

	if ( array_key_exists( "enddate", $props ) ) {
		$enddate = $props["enddate"];
	}

	$pages = array();

	$database = new SQLite3($props['store']);

	$pages = retrievePagesFromDb( $database, $props['query'], $startdate, $enddate );

	if ( count( $pages ) == 0 ) {
		# No pages, exit...
		exit();
	}

	if ( array_key_exists( "notnew", $props ) && $props["notnew"] ) {

		$pages = filterInNew( $pages, $wpapi, $props["startdate"], false );

	}

	if ( array_key_exists( "onlynew", $props ) && $props["onlynew"] ) {

		$pages = filterInNew( $pages, $wpapi, $props["startdate"], true );

	}

	if ( array_key_exists( "checknew", $props ) && $props["checknew"] ) {

		$newpages = filterInNew( $pages, $wpapi, $props["startdate"], true, true );
	}

	$arrpages = array_keys( $pages );
	$arrnewpages = array_keys( $newpages );

	if ( count( $arrpages ) > 0 ) {
		foreach ( $arrpages as $page ) {
			if ( in_array( $page, $arrnewpages ) ) {
				continue;
			} else {
				$oldpages[$page] = 1;
			}
		}
	}

	# echo "NEW\n";
	# var_dump( $newpages );
	# echo "OLD\n";
	# var_dump( $oldpages );
	# exit;

	list( $history, $elements ) = retrieveHistoryPages( $pages, $wpapi, $props, "array" );
	var_dump( $history );
	var_dump( $elements );
	exit();

	$filterin = null;
	if ( array_key_exists( "filterin", $props ) ) {
		$filterin = applyFilterIn( $history, $props["filterin"] );
	}


	if ( array_key_exists( "filterout", $props ) ) {
		$history = applyFilterOut( $history, $props["filterout"] );
	}
	// var_dump( $history );

	// Get users from tags
	$users = retrieveUsersFromElements( $database, $pages );

	// var_dump( $filterin );
	$counts = getCounts( $history, $users, $filterin );
	$edits = getTotalNumEditions( $history, $users );
	echo "COUNTS\n";
	var_dump( $counts );

	// Counts of Biblio, Images and so (aka elements)
	$elements_counts = getElementsCounts( $elements, $users );
	echo "ELCOUNTS\n";
	var_dump( $elements_counts );

	// Assign scores
	if ( array_key_exists( "scores", $props ) ) {
		$scores = assignScores( $counts, $edits, $elements_counts, $wpapi, $props, $newpages, $oldpages );
		echo "SCORES\n";
		var_dump( $scores );

		printScores( $scores, "wiki", $wpapi, $counts, $elements_counts, $edits, $props );

	} else {
		var_dump( $counts );
		printScores( null, "wiki", $wpapi, $counts, $elements_counts, $edits, $props );

	}



}
