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
	$sizebios = array();
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
		
		$gender[$columns[0]] = $columns[1];
		$bot[$columns[0]] = $columns[2];

	}
	
}


