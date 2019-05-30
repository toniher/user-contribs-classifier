<?php

use \Mediawiki\Api as MwApi;

function assignScores( $count, $wpapi, $props ) {
	
	$scores = array();
	$scoresys = array();
	$pagefilter = array();
	
	if ( $props ) {
		
		if ( array_key_exists( "pagefilter", $props ) ) {
			
			foreach ( $props["pagefilter"] as $filterkey => $filter ) {
				
				if ( array_key_exists( "pages", $filter ) ) {
					$pagefilter[$filterkey] = $filter["pages"];
				}
				
				if ( array_key_exists( "source", $filter ) ) {
					$pagefilter[$filterkey] = retrievePagesListFromSource( $filter["source"], $wpapi );
				}
			}
			
		}
		
		if ( array_key_exists( "scores", $props ) ) {

			$scoresys = $props["scores"];
		}
		
	}

	foreach ( $count as $user => $pages ) {
		
		$scores[$user] = 0;
		
		foreach ( $pages as $page => $c ) {
			
			$scores[$user]+= assignScoreFromPage( $page, $c, $pagefilter, $scoresys );
		}
		
	}
	
	return $scores;
	
}

function assignScoreFromPage( $page, $count, $pagefilter, $scoresys ) {
	
	$schema = "standard";
	$score = 0;
	
	foreach( $pagefilter as $filter => $pages ) {
		
		if ( in_array( $page, $pages ) ) {
			$schema = $filter;
		}
		
	}
	
	if ( $count >= $scoresys[$schema]["min"] ) {
		$score = $scoresys[$schema]["minsum"];
		
		$score+= floor( ( $count - $scoresys[$schema]["min"] ) / $scoresys[$schema]["range"] ) * $scoresys[$schema]["sum"];
	}
	
	return $score;
	
}

function retrievePagesListFromSource( $source, $wpapi ) {
	
	$pages = array();
	#https://ca.wikipedia.org/w/api.php?action=query&prop=revisions&rvprop=content&format=jsonfm&formatversion=2&titles=$source
	
	$params = array( "titles" => $source, "rvprop" => "content", "prop" => "revisions" );
	$userContribRequest = new Mwapi\SimpleRequest( 'query', $params  );
	$outcome = $wpapi->postRequest( $userContribRequest );
		
	if ( array_key_exists( "query", $outcome ) ) {
		
		if ( array_key_exists( "pages", $outcome["query"] ) ) {
			
			if ( count( $outcome["query"]["pages"] > 0 ) ) {
				
				foreach ( $outcome["query"]["pages"] as $pageid => $page ) {
				
					if ( array_key_exists( "revisions", $page ) ) {
						
						if ( count( $page["revisions"] > 0 ) ) {
	
							$revision = $page["revisions"][0];

							if ( array_key_exists( "*", $revision ) ) {

								$pages = processContentList( $revision["*"] );
							}
						
						}
					}
				
				}
				
			}
			
		}
		
	}
	
	return $pages;
		
}

function processContentList( $content ) {
	
	$pages = array();
	
	$lines = explode( "\n", $content );
	
	foreach ( $lines as $line ) {
		if ( strpos( $line, "{{" ) === false ) {
			
			$line = str_replace( "[[", "", $line );
			$line = str_replace( "]]", "", $line );
			$line = str_replace( "*", "", $line );
			$line = trim( $line );

			if ( $line !== "" ) {
				array_push( $pages, $line );
			}
		}
	}
	
	return $pages;
	
}

function printPags( $pags ) {
	
	$str = array();
	
	foreach ( $pags as $pag ) {
		
		array_push( $str, "[[".$pag."]]" );
		
	}
	
	return implode( ", ", $str );
	
}

function printScores( $scores, $mode="wiki", $wpapi, $counts, $target ) {
	
	if ( $mode === "wiki" ) {

		$string = "{| class='sortable mw-collapsible wikitable'
! Participant !! Articles || Puntuació\n";
		
		foreach ( $scores as $user => $score ) {
			
			$string.= "|-\n";
			$string.= "| {{Utot|". $user."|".$user."}} || ".printPags( array_keys( $counts[$user] ) )." ||".$score."\n";
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
	
		echo "Usuari\tPuntuació\n";
		
		foreach ( $scores as $user => $score ) {
			
			echo $user."\t".$score."\n";
		}
	
	}
	
}

