<?php

use \Mediawiki\Api as MwApi;

function assignScores( $count, $edits, $elements_counts, $wpapi, $props, $newpages=[], $oldpages=[] ) {

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

				if ( array_key_exists( "new", $filter ) ) {
					$pagefilter[$filterkey] = array_keys( $newpages );
				}

				if ( array_key_exists( "old", $filter ) ) {
					$pagefilter[$filterkey] = array_keys( $oldpages );
				}

			}

		}

		if ( array_key_exists( "scores", $props ) ) {

			$scoresys = $props["scores"];
		}

	}

	foreach ( $count as $user => $pages ) {

		$scores[$user] = 0;

		echo "* ".$user."\n";

		foreach ( $pages as $page => $c ) {

			$scorePage = assignScoreFromPage( $page, $c, $pagefilter, $scoresys, "standard" );
			echo $page." ".$scorePage."\n";
			$scores[$user]+= $scorePage;

		}

		if ( array_key_exists( $user, $elements_counts ) ) {

			# Assign score from elements such as biblio, images, etc.
			$scoreElement = assignScoreFromElements( $elements_counts[ $user ], $scoresys );
			$scores[$user]+= $scoreElement;

		}

	}

	foreach ( $edits as $user => $numedits ) {

		if ( array_key_exists( "count", $scoresys ) ) {

			$scoreEdits = assignScoreFromEdits( $numedits, $scoresys, "count" );
			$scores[$user]+= $scoreEdits;
		}
	}


	return $scores;

}

function assignScoreFromPage( $page, $count, $pagefilter, $scoresys, $default = "standard" ) {

	$score = 0;

	// Now it's either pagefilter or standard
	if ( count( array_keys( $pagefilter ) ) > 0 ) {

		foreach( $pagefilter as $filter => $pages ) {

			#echo "FILTER: ".$filter."\n";

			if ( in_array( $page, $pages ) ) {
				$schema = $filter;
				#echo "HUIS\n";
			} else {
				continue;
			}

			if ( array_key_exists( $schema, $scoresys ) ) {

				if ( $count >= $scoresys[$schema]["min"] ) {
					#echo "MIN:".$scoresys[$schema]["minsum"]."\n";
					$score+= $scoresys[$schema]["minsum"];

					$score+= floor( ( $count - $scoresys[$schema]["min"] ) / $scoresys[$schema]["range"] ) * $scoresys[$schema]["sum"];
				}

				echo $filter.": ".$score."\n";
			}
		}

	}

	if ( array_key_exists( $default, $scoresys ) ) {

		$schema = $default;

		if ( $count >= $scoresys[$schema]["min"] ) {
			$score+= $scoresys[$schema]["minsum"];

			$score+= floor( ( $count - $scoresys[$schema]["min"] ) / $scoresys[$schema]["range"] ) * $scoresys[$schema]["sum"];
		}

		echo $default.": ".$score."\n";


	}


	return $score;

}

function assignScoreFromElements( $elements_count, $scoresys ) {

	$score = 0;
	$count = array();

	foreach( $elements_count as $page => $struct ) {

		foreach( $struct as $schema => $val ) {

			// Avoid notice
			if ( ! array_key_exits( $schema, $count ) ) {
				$count[$schema] = 0;
			}

			$count[$schema] = $count[$schema] + $val;

		}

	}

	foreach( array_keys( $count ) as $schema ) {

		if ( array_key_exists( $schema, $scoresys ) ) {

			if ( $count[$schema] >= $scoresys[$schema]["min"] ) {
				// $score = $scoresys[$schema]["minsum"];

				$score+= floor( ( $count[$schema] - $scoresys[$schema]["min"] ) / $scoresys[$schema]["range"] ) * $scoresys[$schema]["sum"];
			}

		}

	}


	return $score;

}

function assignScoreFromEdits( $numedits, $scoresys, $schema = "count" ) {

	$score = 0;

	if ( array_key_exists( $schema, $scoresys ) ) {

		if ( $numedits >= $scoresys[$schema]["min"] ) {
			$score+= $scoresys[$schema]["minsum"];

			$score+= floor( ( $numedits - $scoresys[$schema]["min"] ) / $scoresys[$schema]["range"] ) * $scoresys[$schema]["sum"];
		}

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
			$line = str_replace( "'''", "", $line );
			$line = str_replace( "''", "", $line );
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

function printScores( $scores, $mode="wiki", $wpapi, $counts, $elements_counts, $edits, $props ) {

	$target = null;

	$total = [];
	$total['edits'] = 0;

	$pagesout = [];

	$totalBytes = [];

	$elements = [];
	if ( array_key_exists( "checkcontent", $props ) ) {
		$elements = array_keys( $props["checkcontent"] );
	}

	if ( array_key_exists( "checkcomment", $props ) ) {
		$elements = array_merge( $elements, array_keys( $props["checkcomment"] ) );
	}

	$summary = "Viquiestirada";

	if ( array_key_exists( "summary", $props ) ) {
		$summary = $props["summary"];
	}

	if ( array_key_exists( "target", $props ) ) {
		$target = $props["target"];
	}

	// $target = null;

	if ( array_key_exists( "filterout", $props ) ) {

		foreach ( $props["filterout"] as $filter ) {
			if ( array_key_exists( "pages", $filter ) ) {
				$pagesout = $filter["pages"];
			}

		}
	}

	$bytes = false;
	$numedits = false;

	if ( array_key_exists( "bytes", $props ) && $props["bytes"] === true ) {
		$bytes = true;
	}

	if ( array_key_exists( "numedits", $props ) && $props["numedits"] === true ) {
		$numedits = true;
	}

	$locale = null;
	if ( array_key_exists( "locale", $props ) ) {
		$locale = $props["locale"];
	}

	if ( ! $scores || $bytes ) {

		foreach( $counts as $user => $pages ) {

			$totalBytes[$user] = 0;

			foreach ( $pages as $page => $bytes ) {

				$totalBytes[$user] = $totalBytes[$user] + $bytes;
				if ( ! array_key_exists( 'bytes', $total ) ) {
					$total['bytes'] = 0;
				}
				$total['bytes'] = $total['bytes'] + $totalBytes[$user];
			}
		}
	}

	$bytesHead = "";
	if ( $bytes ) {
		$bytesHead = "!! Octets totals ";
	}

	$numeditsHead = "";
	if ( $numedits ) {
		$numeditsHead = "!! Nombre d'edicions ";
	}

	$elementsHead = "";
	if ( count( $elements ) > 0 ) {

		foreach ( $elements as $element ) {
			$elementsHead.= "!! $element ";
		}
	}

	if ( $mode === "wiki" ) {

		$string = "";

		if ( count( $pagesout ) > 0 ) {
			$toprint =  array( );

			foreach ( $pagesout as $page ) {
				array_push( $toprint, "[[".$page."]]" );
			}

			var_dump( $toprint );
			sort( $toprint );

			$string.= "EXCLUSIÓ: ". implode( ", ", $toprint )."\n\n";
		}

		if ( $scores ) {

			$string.= "{| class='sortable mw-collapsible wikitable'
	! Participant !! Articles $numeditsHead $bytesHead $elementsHead!! Puntuació\n";

			foreach ( $scores as $user => $score ) {

				$bytesScore = "";
				if ( $bytes ) {
					$bytesScore = "|| ".$totalBytes[$user];
				}

				$numeditsScore = "";
				if ( $numedits ) {
					$numeditsScore = "|| ".$edits[$user];
					$total['edits'] = $total['edits'] + $edits[$user];
				}

				$elementsScore = "";
				foreach ( $elements as $element ) {

					if ( array_key_exists( $user, $elements_counts ) ) {

						$elcount = 0;

						foreach( $elements_counts[$user] as $page => $struct ) {

							if ( array_key_exists( $element, $struct ) ) {

								$elcount = $elcount + $struct[ $element ];

							}

						}


					}

					if ( count( $elements ) ) {
						$elementsScore = $elementsScore . "|| ". $elcount;
						if ( ! array_key_exists( $element, $total ) ) {
							$total[$element] = 0;
						}
						$total[$element] = $total[$element] + $elcount;
					}

				}

				$string.= "|-\n";
				$string.= "| {{Utot|". $user."|".$user."}} || ".printPags( array_keys( $counts[$user] ) )."$numeditsScore $bytesScore $elementsScore ||".formatNumber( $score, $locale )."\n";
			}
			$string.= "|}";

		} else {

			$string.= "{| class='sortable mw-collapsible wikitable'
	! Participant !! Articles $numeditsHead $bytesHead $elementsHead\n";

			foreach ( $counts as $user => $pages ) {

				$bytesScore = "";
				if ( $bytes ) {
					$bytesScore = "|| ".$totalBytes[$user];
				}

				$numeditsScore = "";
				if ( $numedits ) {
					$numeditsScore = "|| ".$edits[$user];
					$total['edits'] = $total['edits'] + $edits[$user];
				}

				$elementsScore = "";
				foreach ( $elements as $element ) {

					if ( array_key_exists( $user, $elements_counts ) ) {

						$elcount = 0;

						foreach( $elements_counts[$user] as $page => $struct ) {

							if ( array_key_exists( $element, $struct ) ) {

								$elcount = $elcount + $struct[ $element ];

							}

						}


					}

					if ( count( $elements ) ) {
						$elementsScore = $elementsScore . "|| ". $elcount;
						if ( ! array_key_exists( $element, $total ) ) {
							$total[$element] = 0;
						}
						$total[$element] = $total[$element] + $elcount;
					}

				}


				$string.= "|-\n";
				$string.= "| {{Utot|". $user."|".$user."}} || ".printPags( array_keys( $counts[$user] ) )."$numeditsScore $bytesScore $elementsScore"."\n";
			}
			$string.= "|}";
		}

		if ( $target && $wpapi ) {

			https://en.wikipedia.org/w/api.php?action=query&format=json&meta=tokens
			$params = array( "meta" => "tokens" );
			$getToken = new Mwapi\SimpleRequest( 'query', $params  );
			$outcome = $wpapi->postRequest( $getToken );

			if ( array_key_exists( "query", $outcome ) ) {
				if ( array_key_exists( "tokens", $outcome["query"] ) ) {
					if ( array_key_exists( "csrftoken", $outcome["query"]["tokens"] ) ) {

						$token = $outcome["query"]["tokens"]["csrftoken"];
						$params = array( "title" => $target, "summary" => $summary, "text" => $string, "token" => $token );
						$sendText = new Mwapi\SimpleRequest( 'edit', $params  );
						$outcome = $wpapi->postRequest( $sendText );

					}
				}
			}

		} else {
			echo $string;
		}

	} else {

		if ( $score ) {

			echo "Usuari\tPuntuació\n";

			foreach ( $scores as $user => $score ) {

				echo $user."\t".$score."\n";
			}

		}

	}

	return( $total );

}

function uploadStats( $wpapi, $props, $total ) {

	$target = null;

	if ( array_key_exists( "target", $props ) ) {
		$target = $props["target"];
	}

	if ( $target && $wpapi ) {

		foreach ( $total as $key => $value ) {

			$params = array( "meta" => "tokens" );
			$getToken = new Mwapi\SimpleRequest( 'query', $params );
			$outcome = $wpapi->postRequest( $getToken );

			if ( array_key_exists( "query", $outcome ) ) {
				if ( array_key_exists( "tokens", $outcome["query"] ) ) {
					if ( array_key_exists( "csrftoken", $outcome["query"]["tokens"] ) ) {

						$token = $outcome["query"]["tokens"]["csrftoken"];
						$params = array( "title" => $target."/".$key, "summary" => $summary, "text" => $value, "token" => $token );
						$sendText = new Mwapi\SimpleRequest( 'edit', $params );
						$outcome = $wpapi->postRequest( $sendText );

					}
				}
			}
		}

	} else {
		print_r($total);
	}
}

function formatNumber( $number, $locale, $formatStyle=NumberFormatter::DECIMAL ){


	if ( $locale ) {
		$formatter= new NumberFormatter( $locale, $formatStyle );
		$output = $formatter->format( $number );

	} else {
		$output = $number;
	}

	return $output;
}
