<?php

	include "bot.global.php";
	
	function examine_lyrics($url) {
	
		$content=file_get_contents($url);
		
		/* First attempt song name and title */
		$art=explode("artist: \"", $content);$art2=explode("\"",$art[1]); $artist=ucwords(strtolower($art2[0]));
		$art=explode("song: \"", $content);$art2=explode("\"",$art[1]); $song=ucwords(strtolower($art2[0]));
		
		$first=explode("<!-- start of lyrics -->", $content);
		$second=explode("<!-- end of lyrics -->", $first[1]);
		
		$lyrics=$second[0];
		
		$lyrics=strip_tags($lyrics);
		$lyrics=explode("\n", $lyrics);
		
		$results=array(); $skip=false;
		
		if (($artist!="")&&($song!="")) {
		
			for ($z = 0; $z < sizeof($lyrics); $z++) {
							
				$b=trim($lyrics[$z]);
				$a=trim($lyrics[$z-1]);
				
				if ( (substr($a,0,1) == "[")or(substr($b,0,1) == "[") ) { $skip=true; }
			
				if(!$skip) {
							
				if (($a!="")&&($b!="")) {
				
					if ( (str_word_count($a)>3)&&(str_word_count($b)>0) ) {
						if ( (str_word_count($a)<18)&&(str_word_count($b)<18) ) {
							/* Any other checks here.. */
							$new=[ "a"=>reduce_to_text($a),
								   "b"=>$b,
								   "artist"=>$artist,
								   "song"=>$song];
								   
							$results[]=$new;
							$skip=true;
						}				
					}			
				}
				
				} else {$skip=false;}
			
			}
		
		}
		
		return $results;
			
	}

	if ($_GET["url"]!="") {

		if ($_GET["url"]=="multi") {
		
			$content=file_get_contents("import");
			$urls=explode("\n", $content);
			$results=array();
			for ($z = 0; $z < sizeof($urls); $z++) {
				$step=examine_lyrics(trim($urls[$z]));
				$results=array_merge($results, $step);
			}
		
		} else {
		$results = examine_lyrics($_GET["url"]);
		}
		
		if (sizeof($results)>0) {
		
			$content=file_get_contents("lyrics.dat");
			$lyric_array=unserialize($content);
			
			$yay=0;
							
			for ($z = 0; $z < sizeof($results); $z++) {
			
				$search=lyric_search($results[$z]["a"]);
				
				if (!$search["hit"]) { $lyric_array[]=$results[$z]; $yay++; } else { echo "<pre>Lyric exists: ".$results[$z]["a"]." is in ".$search["artist"]." - ".$search["song"]."</pre><hr>"; }
			
			}
					
			file_put_contents("lyrics.dat", serialize($lyric_array));
			
			echo "<pre>Total lyrics added: ".$yay."</pre>";
				
		}
	
	} else {
	
		$content=file_get_contents("lyrics.dat");
		$lyric_array=unserialize($content);
		
		if( ($_GET["delete"]!="")||($_GET["delete_line"]!="")) {
			
			$safe=[];
			
			for ($z = 0; $z < sizeof($lyric_array); $z++) {
			
				$tit=$lyric_array[$z]["artist"]." - ".$lyric_array[$z]["song"];
				
				if ( ($tit!=$_GET["delete"]) && ($z!=$_GET["delete_line"]) ) { $safe[]=$lyric_array[$z]; }
			
			}
			
			$lyric_array=$safe;
			file_put_contents("lyrics.dat", serialize($lyric_array));
		
		}
		
		if ($_GET["check"]!="") {
		
			$search=lyric_search($_GET["check"]);
			
			$search["lyric_searched_for"]=$_GET["check"];
			echo "<pre>";print_r($search);echo"</pre>";
		
		}
		
		
	
		echo "<pre><b>built_to_sing bot</b>
		
?url=http://azlyrics
?url=multi (use /import)
?copy=1
?show=1
?check=lyric

Number of lyrics: ".sizeof($lyric_array)."

Artist/song list:";
	
	$artlist=[];
	for ($z = 0; $z < sizeof($lyric_array); $z++) {
	
		$tit=$lyric_array[$z]["artist"]." - ".$lyric_array[$z]["song"];
		
		if (!in_array($tit, $artlist)) { $artlist[]=$tit; }
	
	}
	
	for ($z = 0; $z < sizeof($artlist); $z++) {
	echo "
".$artlist[$z];
if ($_GET["copy"]!=1) { echo " <a href=\"?delete=".urlencode($artlist[$z])."\">x</a>"; }
	}
	
	
	if ($_GET["show"]=="1") {
	
	echo "

All lyrics:

";
	
		for ($z = 0; $z < sizeof($lyric_array); $z++) {
		echo $lyric_array[$z]["a"]."\n";
		echo $lyric_array[$z]["b"]."\n";
		echo $lyric_array[$z]["artist"]." - ".$lyric_array[$z]["song"];
		echo " <a href=\"?delete_line=".($z)."\">x</a>";
		echo "\n\n\n";
		
		
		}
	
	}

	
	
	
echo "		
</pre>";
	
	}
	
	//echo "<pre>";print_r($results);echo"</pre>";

?>