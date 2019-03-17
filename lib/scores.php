<?php

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
					$pagefilter[$filterkey] = retrivePagesListFromSource( $filter["source"], $wpapi );
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

function retrivePagesListFromSource( $source, $wapi ) {
	
	
}

function printScores( $scores ) {
	
	echo "Usuari\tPuntuació\n";
	
	foreach ( $scores as $user => $score ) {
		
		echo $user."\t".$score."\n";
	}
	
}

