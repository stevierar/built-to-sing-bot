<?php

	function reduce_to_text($t) {
	return strtolower(preg_replace('/[^A-Za-z0-9 \-]/', '', $t));
	}
	
	function lyric_search($needle) {
	
		global $lyric_array;
		
		$r=["hit"=>false];
		$needle=reduce_to_text($needle);	
		
		for ($z = 0; $z < sizeof($lyric_array); $z++) {
			
			if ( (!$r["hit"]) && (strlen($needle)<250)  && (strlen($lyric_array[$z]["a"])<250) ) {
			
				$lev = levenshtein($lyric_array[$z]["a"], $needle);		
			
				//if ( ($needle==$lyric_array[$z]["a"]) && (!$r["hit"]) ) {
				
				if ($lev<3) {
				
					$r["hit"]=true;
					$r["b"]=$lyric_array[$z]["b"];
					$r["artist"]=$lyric_array[$z]["artist"];
					$r["song"]=$lyric_array[$z]["song"];
				
				}
			
			}
		
		}
		
		return $r;
	
	}

?>