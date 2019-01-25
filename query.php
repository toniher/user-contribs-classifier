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

$pages = retrieveWpQuery( $pages, $wpapi, $params, null );

$wpapi->logout();

$wdapi = new MwApi\MediawikiApi( $wikidataconfig['url'] );

$dataValueClasses = array(
    'unknown' => 'DataValues\UnknownValue',
    'string' => 'DataValues\StringValue',
    'boolean' => 'DataValues\BooleanValue',
    'number' => 'DataValues\NumberValue',
    'time' => 'DataValues\TimeValue',
    'globecoordinate' => 'DataValues\Geo\Values\GlobeCoordinateValue',
    'wikibase-entityid' => 'Wikibase\DataModel\Entity\EntityIdValue',
);

$wbFactory = new WbApi\WikibaseFactory(
    $wdapi,
    new DataValues\Deserializers\DataValueDeserializer( $dataValueClasses ),
    new DataValues\Serializers\DataValueSerializer()
);

$wdapi->logout();


function retrieveWpQuery( $pages, $wpapi, $params, $uccontinue ) {
	
	if ( $uccontinue ) {
		$params["uccontinue"] = $uccontinue;
	}
	
	$userContribRequest = new Mwapi\SimpleRequest( 'query', $params  );

	$contribs = $wpapi->postRequest( $userContribRequest );

	$uccontinue = null;
	
	if ( array_key_exists( "continue", $contribs ) ) {
		
		if ( array_key_exists( "uccontinue", $contribs["continue"] ) ) {
			
			$uccontinue = $contribs["continue"]["uccontinue"];
		} 
	} 
	
	$pages = processPages( $pages, $contribs );
	

	if ( $uccontinue ) {
		$pages = retrieveWpQuery( $pages, $wpapi, $params, $uccontinue );
	}
	
	return $pages;
	
}

function processPages( $pages, $contribs ) {
	
	return $pages;
}

