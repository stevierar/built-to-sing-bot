<?php
	
	file_put_contents("status_threads", "1");

	set_time_limit(0);
	
	error_reporting(E_ALL & ~E_NOTICE);
	
	$comments_total=array();

	include "bot.global.php";

	function activity($a) { global $activity; $activity.="\n".$a; }
	
	function file_status($last,$now) {
	
		if (file_exists("status_".$last)) { unlink("status_".$last); }
		
		file_put_contents("status_".$now, "1");
	
	}
	
	function to_cli($a) { echo $a."\n"; flush(); }

	/* https://github.com/buildist/reddit-mirror-bot */
	
	/* Settings */
	
	$username = "account_name";
	$password = "password_here";
	
	$postsPerPage=100;
	$repeat=1;
	
	/* Log in */
	to_cli('## Connecting to Reddit');
	require_once("reddit.php");
	$reddit = new reddit($username, $password);
	
	
	/* Get threads */
	
			$after=0;
			
			to_cli('## Starting to gather threads: '.$postsPerPage.' x '.$repeat);
				
			$threads=array();
			
			for ($z = 0; $z < $repeat; $z++) {
			
				$after=($z*$postsPerPage);		
				
				to_cli('## Collecting '.$postsPerPage.' hot threads from /me/m/bot/ - from '.$after.' to '.(($postsPerPage+$after)-1));
			
				$posts = $reddit->getMulti('bot/rising', ($postsPerPage-1), $after);
				//$posts = $reddit->getListing('built_to_sing', $postsPerPage, $after);
				$posts = $posts->data->children;
				
				
				
				 if($posts == null || count($posts) == 0) {
					   die('$posts is null or empty :(');
					} else {
					
						foreach($posts as $post) {
							
							/* Add each thread to a nice array so we can get the comments. */
							
							$nice=[ "subreddit"=>$post->data->subreddit,
									"permalink"=>$post->data->permalink,
									"title"=>$post->data->title,
									"num_comments"=>$post->data->num_comments];
									
							$threads[]=$nice;
						
						}
						
					}
				
			}
			
			//echo"<pre>";print_r($threads);echo"</pre><hr>";	
				$commentcount=0;
				
			file_status("threads","comments");
			
			/* Function to recusrively go through each thread, finding comments */
			
			function recursive_comment_wizard($comments, $results) {
			
			global $commentcount, $modhash, $username;
			
				if (is_array($comments)) {
				
					foreach($comments as $c) {
					
						$hasChildren=false;
							
						/* Look inside [children] */	
						if ( sizeof( $c->data->children ) > 0 ) {
							$results = recursive_comment_wizard($c->data->children, $results);		
							$hasChildren=true;
						}
						
						/* [replies] holds another ->data->children, fooof! */
						if ( sizeof( $c->data->replies->data->children ) > 0 ) {	
							$results = recursive_comment_wizard($c->data->replies->data->children, $results);	
							$hasChildren=true;
						}
						
						/* Has body (comment), no children and isn't the bot */
						if ( ($c->data->body != "") && (!$hasChildren) && ( $c->data->author != $username ) && (strlen($c->data->body)<250) ) {
						
							$commentcount++;								
												
							/* Record enough data to reply */
							
							$new=["name"=> $c->data->name,
								  "comment"=>$c->data->body,
								  "subreddit"=>$c->data->subreddit];
								  
							$results[]=$new;
							
						}
					
					}
					
				}
				
				return $results;
			
			}
			
			
			$e=true;
			
			
			to_cli('## Begin comment gathering');
			
			
			file_status("comments","working");
			
			$content=file_get_contents("lyrics.dat");
			$lyric_array=unserialize($content);
			
			//$th_total=sizeof($comments_total);
			//$th=0;
			
			$commented_comments=array(); 
			
			// ---------------------------------------------------
			
			$th_total=sizeof($threads);
			$th=0;
			
			/* Cycle through each thread */
			foreach($threads as $thread) {
			
				$th++;
			
				/*if ($e) {
			$e=false;*/
				
				/* Neaten the permalink to something reddit json understands */
				$location=substr($thread["permalink"],3);
				$location=substr($location,0,-1);
				//echo $location."<hr>";
				
				/* Get comments (custom function by me!) */
				$comments = $reddit->getComments($location, $postsPerPage, $after);
				
				/* Fill that array() with comment info! */
				$comments_neat=recursive_comment_wizard($comments, array() );
				
				to_cli('['.$th.'/'.$th_total.'] Analysing '.sizeof($comments_neat).' comment(s) from /r/'.$thread["subreddit"]);
				
				//if (sizeof($comments_neat)>0) { $comments_total=array_merge($comments_total, $comments_neat); }
				
				/* NOW Scan for lyrics! HAHAHAHAHA */
				foreach($comments_neat as $comment) {
			
				//$th++;
				
				if (!in_array($comment["name"], $commented_comments)) {
			
				//to_cli('['.$th.'/'.$th_total.'] Searching for lyrics for comment '.$comment["name"]);
				
				$search=lyric_search($comment["comment"]);
				$comment_examined++;
					
				if ($search["hit"]) {
				
					$artist="^".str_replace(" "," ^",$search["artist"]);
					$song="^".str_replace(" "," ^",$search["song"]);
				
					$reply="".$search["b"];
					$reply.="
***
".$artist." ^- ".$song." [^built_to_sing](http://www.reddit.com/r/built_to_sing)";
					
					
					to_cli('['.$th.'/'.$th_total.'] MATCH MATCH MATCH MATCH');
					to_cli('['.$th.'/'.$th_total.'] '.$artist.' - '.$song);
					to_cli('['.$th.'/'.$th_total.'] '.$comment["comment"]);
					to_cli('['.$th.'/'.$th_total.'] '.$search["b"]);
					to_cli('['.$th.'/'.$th_total.'] Creating comment');
					
					$response = $reddit->addComment($comment["name"], urlencode($reply));
					
					$commented_comments[]=$comment["name"];
					
					$errText="";
					
					/* Has an error! Eeek. log it */
					if (sizeof($response->json->errors)>0) {
						
						foreach($response->json->errors as $err) {
						$errText.="{".$err[0]."}	";
						}
					
					}
					
					/* Note down ratelimit... it seems important */
					if ( $response->json->ratelimit ) {
						$errText.="ratelimit: ".$response->json->ratelimit."	";
					}
					
					/* Log success or fail */
					if ($errText!="") {			
						activity( "boo	".$comment["subreddit"]."	".$comment["name"]."	".$errText );	
						to_cli('['.$th.'/'.$th_total.'] Failed '.$errText);
					} else {
						activity( "yay	".$comment["subreddit"]."	".$comment["name"]."	".$search["b"] );	
						to_cli('['.$th.'/'.$th_total.'] Success');
					}	
					
					to_cli('['.$th.'/'.$th_total.'] Sleeping for 30 seconds');
					sleep(30);
				
				}
				
				} else { to_cli('['.$th.'/'.$th_total.'] Comment already made! eeeek.'); }
			
			}
				
				
				
				
				
				sleep(3);
				
				//echo "<h1>".$commentcount."</h1>";
				//$comments = $comments->data->children;
				//$comments =  (array) $comments;
				
				//echo"<pre>";print_r($comments_neat);echo"</pre>";
				
			
			}
	
	
	//to_cli('## Hurrah. Comparing collected comments to lyrics.');
	
	//$comment_examined=0;
	// Make sure we don't double-comment. Not sure how that would happen but hey!
	
	/*foreach($comments_total as $comment) {
	
		$th++;
		
		if (!in_array($comment["name"], $commented_comments)) {
	
		to_cli('['.$th.'/'.$th_total.'] Searching for lyrics for comment '.$comment["name"]);
		
		$search=lyric_search($comment["comment"]);
		$comment_examined++;
			
		if ($search["hit"]) {
		
			$artist="^".str_replace(" "," ^",$search["artist"]);
			$song="^".str_replace(" "," ^",$search["song"]);
		
			$reply="".$search["b"];
			$reply.="
***
".$artist." ^- ".$song." [^built_to_sing ^0.1](http://www.reddit.com/r/built_to_sing)";
			
			
			to_cli('['.$th.'/'.$th_total.'] MATCH MATCH MATCH MATCH');
			to_cli('['.$th.'/'.$th_total.'] '.$artist.' - '.$song);
			to_cli('['.$th.'/'.$th_total.'] '.$comment["comment"]);
			to_cli('['.$th.'/'.$th_total.'] '.$search["b"]);
			to_cli('['.$th.'/'.$th_total.'] Creating comment');
			
			$response = $reddit->addComment($comment["name"], urlencode($reply));
			
			$commented_comments[]=$comment["name"];
			
			$errText="";
			
			/ Has an error! Eeek. log it /
			if (sizeof($response->json->errors)>0) {
				
				foreach($response->json->errors as $err) {
				$errText.="{".$err[0]."}	";
				}
			
			}
			
			/* Note down ratelimit... it seems important/
			if ( $response->json->ratelimit ) {
				$errText.="ratelimit: ".$response->json->ratelimit."	";
			}
			
			/* Log success or fail /
			if ($errText!="") {			
				activity( "boo	".$comment["subreddit"]."	".$comment["name"]."	".$errText );	
				to_cli('['.$th.'/'.$th_total.'] Failed '.$errText);
			} else {
				activity( "yay	".$comment["subreddit"]."	".$comment["name"]."	".$search["b"] );	
				to_cli('['.$th.'/'.$th_total.'] Success');
			}	
			
			to_cli('['.$th.'/'.$th_total.'] Sleeping for 30 seconds');
			sleep(30);
		
		}
		
		} else { to_cli('['.$th.'/'.$th_total.'] Comment already made! eeeek.'); }
	
	}*/
	
	//echo "<h3>built_to_sing</h3><h4>Links examined: ".sizeof($threads)."<br />Comments examined: ".$comment_examined."</h4>";
	to_cli('************************');
	to_cli('Complete.');
	to_cli('Threads examined: '.sizeof($threads));
	to_cli('Comments searched: '.$comment_examined);
	to_cli('Activity:');
	to_cli($activity);
	
	file_put_contents("bot_last_activity", $activity);
	unlink("status_working");
	
?>