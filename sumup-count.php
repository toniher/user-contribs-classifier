<?php

require_once( __DIR__ . '/vendor/autoload.php' );
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

$wpapi = Mwapi\MediawikiApi::newFromApiEndpoint( $wikiconfig["url"] );

$rclimit = 1000;


// Login
if ( array_key_exists( "user", $wikiconfig ) && array_key_exists( "password", $wikiconfig ) ) {
	
	$wpapi->login( new ApiUser( $wikiconfig["user"], $wikiconfig["password"] ) );

}

// Get all pages with a certain tag
if ( array_key_exists( "pages", $props ) ) {
	
	$users = array();
	
	foreach( $props["pages"] as $page ) {

		$params = array( "prop" => "revisions", "redirects" => true, "rvslots" => "*", "rvprop" => "content" );
		$params["titles"] = $page;
		
		$userContribRequest = new Mwapi\SimpleRequest( 'query', $params  );

		$outcome = $wpapi->postRequest( $userContribRequest );
		$text = getWikiText( $outcome );
		$users = retrieveUsers( $wpapi, $text, $users );
		
		// var_dump( $users );
	}
	
	printTotal( $users, $mode="wiki", $wpapi, $props );

	
}

function retrieveUsers( $page, $text, $users ) {
	
	$lines = explode( "\n", $text );
	
	foreach ( $lines as $line ) {
	
		if ( preg_match( "/^\s*\|/", $line ) ) {
						
			$parts = explode( "||", $line );
			
			if ( count( $parts) > 2 ) {
				preg_match( "/\s*(\d+)\s*$/", trim( strip_tags( $parts[2] ) ), $match );
				
				// echo strip_tags( $parts[2] )."\n";
				// var_dump( $match );
				$num = $match[0];
				
				// var_dump( $parts[0]);
				preg_match( "/\{\{Utot\|(\S.*?)\|/", trim( $parts[0] ), $matchu );
	
				$user = trim( $matchu[1] );
				
				preg_match_all( "/\[\[/" , trim( $parts[1] ), $matchc );
				
				$pages = 0;
				
				if ( count( $matchc ) > 0 ) {
					
					$pages = count( $matchc[0] );
				}
				
				// echo $user."\t".$num."\n";
				
				if ( array_key_exists( $user, $users  ) ) {
					
					$users[$user] = array( "score" => $users[$user]["score"] + $num, "pages" => $users[$user]["pages"] + $pages );
				} else {
					
					$users[$user] = array( "score" => $num, "pages" => $pages );
				}
			
			}
			
		}
	}
	
	
	return $users;
}

function getWikiText( $outcome ) {
	
	$text = "";
	
	if ( array_key_exists( "query", $outcome ) ) {
				
		if ( array_key_exists( "pages", $outcome["query"] ) ) {
		
			foreach ( $outcome["query"]["pages"] as $pageid => $struct ) {
				
				if ( array_key_exists( "revisions", $outcome["query"]["pages"][$pageid] ) ) {
										
					$revisions = $outcome["query"]["pages"][$pageid]["revisions"];
					
					if ( count( $revisions) > 0 ) {

						if ( array_key_exists( "slots", $revisions[0] ) ) {
							
							
							if ( array_key_exists( "main", $revisions[0]["slots"] ) ) {
		
								if ( array_key_exists( "*", $revisions[0]["slots"]["main"] ) ) {
									
									$text = $revisions[0]["slots"]["main"]["*"];
									
								}
		
							}
						}
					}
				}
				
			}
		
		}									
	}
	
	return $text;
	
}

function printTotal( $users, $mode="wiki", $wpapi, $props ) {
	
	$scores = array();
	
	foreach ( $users as $user => $struct ) {
		
		$scores[$user] = $struct["score"];
		
	}
	
	// Sort by size
	arsort( $scores );
	
	$target = null;
	
	if ( array_key_exists( "target", $props ) ) {
		$target = $props["target"];
	}
	
	if ( $mode === "wiki" ) {

		$string = "";
	
		$string.= "{| class='sortable mw-collapsible wikitable'
! Participant !! Núm pàgines !! Puntuació\n";
		
		foreach ( $scores as $user => $score ) {
			
			$string.= "|-\n";
			$string.= "| {{Utot|". $user."|".$user."}} || ". $users[$user]["pages"] . " || " .$score."\n";
		}
		$string.= "|}";
		
		if ( $target && $wpapi ) {
			
			https://en.wikipedia.org/w/api.php?action=query&format=json&meta=tokens
			$params = array( "meta" => "tokens" );
			$getToken = new Mwapi\SimpleRequest( 'query', $params  );
			$outcome = $wpapi->postRequest( $getToken );
		
			if ( array_key_exists( "query", $outcome ) ) {
				if ( array_key_exists( "tokens", $outcome["query"] ) ) {
					if ( array_key_exists( "csrftoken", $outcome["query"]["tokens"] ) ) {
						
						$token = $outcome["query"]["tokens"]["csrftoken"];
						$params = array( "title" => $target, "summary" => "Viquiestirada", "text" => $string, "token" => $token );
						$sendText = new Mwapi\SimpleRequest( 'edit', $params  );
						$outcome = $wpapi->postRequest( $sendText );			
					
					}				
				}
			}

		} else {
			echo $string;
		}
		
	} else {
	
		echo "Usuari\tPàgines\tPuntuació\n";
		
		foreach ( $scores as $user => $score ) {
			
			echo $user."\t".$users[$user]["pages"]."\t".$score."\n";
		}
	
	}
	
}
