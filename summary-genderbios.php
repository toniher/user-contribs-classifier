<?php

require_once( __DIR__ . '/vendor/autoload.php' );

use \Mediawiki\Api as MwApi;
use \Mediawiki\Api\ApiUser;

// Detect commandline args
$conffile = 'conf.json';
$listfile = 'ca.list';
$genderfile = 'ca.gender';
$contribdir = 'ca';
$format = 'text';

if ( count( $argv ) > 1 ) {
	$conffile = $argv[1];
}

if ( count( $argv ) > 2 ) {
	$listfile = $argv[2];
}

if ( count( $argv ) > 3 ) {
	$genderfile = $argv[3];
}

if ( count( $argv ) > 4 ) {
	$contribdir = $argv[4];
}

if ( count( $argv ) > 5 ) {
	$format = $argv[5];
}

// Detect if files
if ( ! file_exists( $conffile ) && ! file_exists( $listfile ) && ! file_exists( $genderfile ) && ! file_exists( $contribdir ) ) {
	die( "more files needed" );
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

$output = processFiles( $listfile, $genderfile, $contribdir );

var_dump( $output );

if ( $format == 'wiki' ) {
	
	# Send to wiki page
	
} else {
	
	# Print as CSV
}



function processFiles( $listfile, $genderfile, $contribdir ){
	
	$rows = array();
	
	$first = array( "User", "Gender", "Bot", "Bios", "NoMale", "PercNoMale", "Size", "SizeNoMale", "PercSizeNoMale" );

	array_push( $rows, $first );
	
	# Simple array
	$users = array();
	
	# Associative ones
	$gender = array();
	$bot = array();
	$countBios = array();
	$countNoMaleNios = array();
	$sizeBios = array();
	$sizeNoMaleBios = array();
	
	$userfileText = file_get_contents( $listfile );
	
	$lines = explode( "\n", $userfileText );
	
	foreach ( $lines as $line ) {
	
		$user = rtrim( $line );
		
		array_push( $users, $user );

	}
	

	$genderfileText = file_get_contents( $genderfile );

	$lines = explode( "\n", $genderfileText );

	$g = 0;
	foreach ( $lines as $line ) {

		$g++;

		if ( $g <= 1 ) {
			
			continue;
		}
		
		$columns = explode( "\t", $line );

		if ( count( $columns ) > 2 ) {
			$gender[$columns[0]] = $columns[1];
			$bot[$columns[0]] = $columns[2];
		}

	}

	// Read dir
	if ( is_dir( $contribdir ) ) {
		if ( $dh = opendir( $contribdir ) ) {
	
			while (($file = readdir($dh)) !== false) {
				
				if ( endsWith( $file, ".csv" ) ) {
					
					$username = str_replace( ".csv", "", $file );
					processCSV( $contribdir."/".$file, $username, $countBios, $countNoMaleBios, $sizeBios, $sizeNoMaleBios );
				}
				
			}
			
			closedir( $dh );
		}
	}
	
	foreach ( $users as $user ) {
		
		$row = array();

		if ( $user == "" ) {
			continue;
		}

		array_push( $row, $user );
		
		if ( array_key_exists( $user, $gender ) ){
			array_push( $row, $gender[$user] );
		} else {
			array_push( $row, "" );
		}

		if ( array_key_exists( $user, $bot ) ){
			array_push( $row, $bot[$user] );
		} else {
			array_push( $row, "" );
		}
		
		if ( array_key_exists( $user, $countBios ) ){
			array_push( $row, $countBios[$user] );
		} else {
			array_push( $row, 0 );
		}

		if ( array_key_exists( $user, $countNoMaleBios ) ){
			array_push( $row, $countNoMaleBios[$user] );
		} else {
			array_push( $row, 0 );
		}
		
		if ( array_key_exists( $user, $countBios ) && array_key_exists( $user, $countNoMaleBios ) ){
			
			if ( $countBios[$user] === 0 ) {
				array_push( $row, 0 );
			} else {
				array_push( $row, round( ( $countNoMaleBios[$user]/$countBios[$user] )*100, 2 ) );
			}
		} else {
			array_push( $row, 0 );
		}
				
		if ( array_key_exists( $user, $sizeBios ) ){
			array_push( $row, $sizeBios[$user] );
		} else {
			array_push( $row, 0 );
		}

		if ( array_key_exists( $user, $sizeNoMaleBios ) ){
			array_push( $row, $sizeNoMaleBios[$user] );
		} else {
			array_push( $row, 0 );
		}
		
		if ( array_key_exists( $user, $sizeBios ) && array_key_exists( $user, $sizeNoMaleBios ) ){
			
			if ( $sizeBios[$user] === 0 ) {
				array_push( $row, 0 );
			} else {		
				array_push( $row, round( ( $sizeNoMaleBios[$user]/$sizeBios[$user] )*100, 2 ) );
			}
		} else {
			array_push( $row, 0 );
		}
		
		array_push( $rows, $row );
		
	}
	
	return $rows;
	
}

function processCSV( $file, $username, &$countBios, &$countNoMaleBios, &$sizeBios, &$sizeNoMaleBios ) {
	
	$male = "Q6581097";

	$content = file_get_contents( $file );
	
	$rows = explode( "\n", $content );
	
	$c = 0;
	
	$countNoMaleBios[ $username ] = 0;
	$sizeNoMaleBios[ $username ] = 0;
	$countBios[ $username ] = 0;
	$sizeBios[ $username ] = 0;
		
	foreach ( $rows as $row ) {
		
		$c++;

		if ( $c <= 1 ) {
			
			continue;
		}
	
		$columns = explode( "\t", $row );

		if ( count( $columns ) > 2 ) {

			if ( $columns[2] != $male ) {
				$countNoMaleBios[ $username ]++;
				$sizeNoMaleBios[ $username ] += $columns[1];
			}
	
			
			$countBios[ $username ]++;
			$sizeBios[ $username ] += $columns[1];


		}
	}


}


function endsWith($haystack, $needle){
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}
