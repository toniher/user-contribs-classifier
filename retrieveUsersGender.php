<?php

require_once( __DIR__ . '/vendor/autoload.php' );

use \Mediawiki\Api as MwApi;
use \Mediawiki\Api\ApiUser;

// Detect commandline args
$conffile = 'config.json';
$userfile = null;

if ( count( $argv ) > 1 ) {
	$conffile = $argv[1];
}

if ( count( $argv ) > 2 ) {
	$userfile = $argv[2];
}

// Detect if files
if ( ! file_exists( $conffile ) && ! file_exists( $userfile ) ) {
	die( "Config file needed" );
}

$confjson = json_decode( file_get_contents( $conffile ), 1 );

$wikiconfig = null;

if ( array_key_exists( "wikipedia", $confjson ) ) {
	$wikiconfig = $confjson["wikipedia"];
}

$wpapi = Mwapi\MediawikiApi::newFromApiEndpoint( $wikiconfig["url"] );

// Login
if ( array_key_exists( "user", $wikiconfig ) && array_key_exists( "password", $wikiconfig ) ) {
	
	$wpapi->login( new ApiUser( $wikiconfig["user"], $wikiconfig["password"] ) );

}

processUserFile( $userfile, $wpapi );

function processUserFile( $userfile, $wpapi ){
	
	echo "User\tGender\n";
	
	$userfileText = file_get_contents( $userfile );
	
	$lines = explode( "\n", $userfileText );
		
	$users = array();
	
	$count = 0;
	
	foreach ( $lines as $line ) {

		$count++;
	
		$user = rtrim( $line );
		
		array_push( $users, $user );
		
		if ( $count >= 50 ) {
			
			getUsersGender( $users, $wpapi );
			$count = 0;
			$users = array();
		}
	
	}
	
	getUsersGender( $users, $wpapi );

}

function getUsersGender( $users, $wpapi ) {
	
	$usersStr = implode( "|", $users );
	$params = array();
	
	$params["list"] = "users";
	$params["ususers"] = $usersStr;
	$params["usprop"] = "gender";

	var_dump( $params );

	$listPage = new Mwapi\SimpleRequest( 'query', $params );
	$outcome = $wpapi->postRequest( $listPage );
	
	if ( array_key_exists( "query", $outcome ) ) {
		
		if ( array_key_exists( "users", $outcome["query"] ) ) {
			
			foreach ( $outcome["query"]["users"] as $user ) {
				
				if ( array_key_exists( "gender", $user )  && array_key_exists( "name", $user ) ) {
					
					echo $user["name"]."\t".$user["gender"]."\n";
				}
				
			}
			
		}
	}
	
}


