<?php

require_once( __DIR__ . '/vendor/autoload.php' );

use \Mediawiki\Api as MwApi;

// Detect commandline args
$conffile = 'config.json';

if ( count( $argv ) > 1 ) {
	$conffile = $argv[1];
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

$wpapi = Mwapi\MediawikiApi::newFromApiEndpoint( $wikiconfig["url"] );

// Login
if ( array_key_exists( "user", $wikiconfig ) && array_key_exists( "password", $wikiconfig ) ) {
	
	$wpapi->login( new ApiUser( $wikiconfig["user"], $wikiconfig["password"] ) );

}

// Get a page
if ( array_key_exists( "list", $confjson ) && array_key_exists( "regex", $confjson ) ) {
	
	$params = array( "titles" => $confjson["list"], "prop" => "revisions", "rvlimit" => 1, "rvprop" => "content" );
	$listPage = new Mwapi\SimpleRequest( 'query', $params  );
	$outcome = $wpapi->postRequest( $listPage );

	$text = getWikiText( $outcome );
	
	// TODO: This may be adapted to other pages
	$users = getUsers( $text, $confjson["regex"] );

}

function getWikiText( $outcome ) {
	
	$text = null;
	
	if ( array_key_exists( "query", $outcome ) ) {

		if ( array_key_exists( "pages", $outcome["query"] ) ) {

			foreach ( $outcome["query"]["pages"] as $page ) {

				if ( array_key_exists( "revisions", $page ) ) {
					
					if ( count( $page["revisions"] ) > 0 ) {
						
						$rev = $page["revisions"][0];
						
						if ( array_key_exists( "*", $rev ) ) {
							
							$text = $rev["*"];
						}

					}
				}
			}
		
		}
	}
	
	return $text;
}


function getUsers( $text, $regex ) {
	
	$lines = explode( "\n", $text );
		
	foreach ( $lines as $line ) {
		
		preg_match( $regex, $line, $matches );
	
		if ( count( $matches ) > 1 ) {
			
			echo $matches[1]. "\n";
		}
	
	}
}


