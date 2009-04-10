<?php

/*
Plugin Name: SimpleTwitter Modified
Plugin URI: http://daveschatz.com/simpletwitter/
Description: A plug-in to show the last twitter tweet for a user.
Version: 1.3.1
Author: Dave Schatz
Author URI: http://www.daveschatz.com/
*/

/*  Copyright 2007  David Wood  (email : david.c.wood@gmail.com)
    CC BY Dave Schatz 2009 (email : daveschatz@gmail.com)

    
    This software is licensed under the CC-GNU GPL version 2.0 or later.
    http://creativecommons.org/licenses/GPL/2.0/

*/



/* EXAMPLE USAGE:



<?php get_twitter_msg(); ?>	

*/



$_opt_twitter_msg = 'st_twitter_msg';

$_opt_twitter_id = 'st_twitter_id';

$_opt_cache_mins = 'st_cache_mins';

$_opt_last_cache_time = 'st_last_cache_time';



add_action('wp_head', 'check_twitter_cache');

add_action('admin_menu', 'add_twitter_options');

add_action("plugins_loaded", "init_simpleTwit");



//Widget section



function simpleTwit_widget() {
	global $_opt_twitter_id;

	$twitterId = get_option($_opt_twitter_id);
?>

    <li id="simpleTwit_widget" class="widget widget_recent_entries">

        <h2><a href="http://www.twitter.com/<? echo $twitterId; ?>" title="Twitter <? echo $twitterId; ?>" target="_new">Twitter</a></h2>

        <?php get_twitter_msg(); ?>

    </li>

<?php

}



function init_simpleTwit(){

    register_sidebar_widget("SimpleTwitter", "simpleTwit_widget");    

}



// Options hook

function add_twitter_options() {

    if (function_exists('add_options_page')) {

		add_options_page('SimpleTwitter', 'SimpleTwitter', 8, 'simpletwitter', 'simpletwitter_options_subpanel');

    }

}

 

// Options panel and form processing

function simpletwitter_options_subpanel() {

	echo "<h2>SimpleTwitter</h2>";



	if (!function_exists('curl_init')) {

		_show_simpletwitter_curl_warning();	

	}

	else {

		if (isset($_POST['info_update'])) {

			global $_opt_twitter_id;

			global $_opt_cache_mins;

	

			$twitterId = $_POST['twitter_id'];

			$cacheMins = $_POST['cache_mins'];

			update_option($_opt_twitter_id, $twitterId);

			update_option($_opt_cache_mins, $cacheMins);

		}

		_show_simpletwitter_form();

	}

}



// Displays a form to edit configuration options

function _show_simpletwitter_form() {

	?>

<div class="wrap">

<form method="post">

<fieldset class="options">

<legend><?php _e('Setup') ?></legend>

<table width="100%" cellspacing="2" cellpadding="5" class="editform"> 

<tr valign="top"> 

<th width="33%" scope="row"><label for="twitter_id"><?php _e('Your twitter username:') ?></label></th> 

<td><input type="text" name="twitter_id" id="twitter_id" value="<?php form_option('st_twitter_id'); ?>"/></td> 

</tr>

<tr valign="top">

<th scope="row"><label for="cache_mins"><?php _e('Cache each message for:') ?></label></th>

<td><input type="text" name="cache_mins" id="cache_mins" size="3" value="<?php form_option('st_cache_mins'); ?>"/> <?php _e('minutes') ?></td>

</tr> 

</table> 

<p class="submit">

<input type="submit" name="info_update" value="<?php _e('Update Options') ?> &raquo;" />

</p>

</fieldset>

</form>

</div>

	<?php

}



// Displays a warning message when cURL isn't available

function _show_simpletwitter_curl_warning() {

	?>

<div class="error">

<h3>SimpleTwitter needs the php cURL library to be installed</h3>

<p>SimpleTwitter uses the cURL php library to connect to the Twitter website. 

This doesn't seem to be available with your current php configuration - it has 

possibly been disabled in your php.ini file.<br /><br />Please contact your 

System Administrator or Service Provider for information.</p>

</div>	

	<?php

}



function makeClickableLinks($text) {



	//Convert text: "http://anything.com" into a clickable link

	$text = eregi_replace('(((f|ht){1}tp://)[-a-zA-Z0-9@:%_\+.~#?&//=]+)', '<a target="_blank" rel="nofollow" title="Visit \\1" href="\\1">\\1</a>', $text);

	//Convert text: "www.anything.com" into a clickable link

	$text = eregi_replace('([[:space:]()[{}])(www.[-a-zA-Z0-9@:%_\+.~#?&//=]+)', '\\1<a target="_blank" rel="nofollow" title="Visit http://\\2" href="http://\\2">\\2</a>', $text);

	//Convert @TwitterNames to clickable links to their profiles

	$text = preg_replace('((?<=@)[-a-zA-Z0-9]+)', '<a target="_blank" rel="nofollow" title="Twitter \\0" href="http://www.twitter.com/\\0">\\0</a>', $text);

	//Convert #Topics into clickable links to Twitter Searches for the topics

	$text = preg_replace('((?<=#)[-a-zA-Z0-9]+)', '<a target="_blank" rel="nofollow" title="Twitter Search for #\\0" href="http://search.twitter.com/search?q=%23\\0">\\0</a>', $text);

	//Convert any email address into a clickable link to send them an email

	$text = eregi_replace('([_\.0-9a-z-]+@([0-9a-z][0-9a-z-]+\.)+[a-z]{2,3})', '<a target="_blank" rel="nofollow" title="Email \\1" href="mailto:\\1">\\1</a>', $text);



	return $text;

}



// Returns the stored message

function get_twitter_msg() {

	global $_opt_twitter_msg;

	$msg = get_option($_opt_twitter_msg);

	//$msg = 'test message @twitMember @twitmem2 about http://tinyurl.com/ssf and www.tinyurl.com/rjff today!';

	$msg = makeClickableLinks($msg);

	echo $msg;

}



// Called by hook into wp_head. Checks for message expiry

function check_twitter_cache() {

	global $_opt_cache_mins;

	global $_opt_last_cache_time;

	$cache_mins = get_option($_opt_cache_mins);

	if ($cache_mins == '')

		$cache_mins = 1;

	$cache_time = $cache_mins * 60;



	// Time and file stats

	$now = time();

	$lsmod = get_option($_opt_last_cache_time);

	if ($lsmod == '')

		$lsmod = 0;



	// Cache is expired if the diff between now time and last mod time

	// is greater than cache time

	$cache_expired = ($now - $lsmod) > $cache_time;

	if ($cache_expired) {

		update_twitter_message();

	}

}



// Updates the message cache

function update_twitter_message() {

	// Update cache

	global $_opt_twitter_id;

	global $_opt_twitter_msg;

	global $_opt_last_cache_time;

	$twitterId = get_option($_opt_twitter_id);

	if ($twitterId != '') {

		$url = 'http://twitter.com/statuses/user_timeline/'.$twitterId.'.rss';

		$title = get_message_from_url($url);

		if ($title != '') {

			$msg = extract_message_from_twitter_title($title);

			update_option($_opt_twitter_msg, $msg);

			update_option($_opt_last_cache_time, time());

		}

	}

}

	

// Message comes in the format 'Name : Message'. This removes the 'Name : ' part

function extract_message_from_twitter_title($title) {

	$msg = substr($title, strpos($title, ':') + 2);

	return $msg;

}



// Gets the RSS feed and reads the title of the first item

function get_message_from_url($url, $tag = 'title', $item = 'item') {

	$msg = '';

	

	$page = '';

	if (function_exists('curl_init')) {

		

		$curl_session = curl_init($url);

		curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);

		curl_setopt($curl_session, CURLOPT_CONNECTTIMEOUT, 4);

		curl_setopt($curl_session, CURLOPT_TIMEOUT, 8);

		$page = curl_exec($curl_session);

		curl_close($curl_session);



	}		

	if ($page == '') {

		return '';

	}



	$lines = explode("\n",$page);

	

	$itemTag = "<$item>";

	$startTag = "<$tag>";

	$endTag = "</$tag>";

	

	$inItem = false;

	foreach ($lines as $s) {

		$s = rtrim($s);		

		if (strpos($s, $itemTag)) {

			$inItem = true;

		}

		if ($inItem) {

			$msg .= $s;

		}

		if ($inItem && strpos($s, $endTag)) {

			$msg = substr_replace($msg, '', strpos($msg, $endTag));

			$msg = substr($msg, strpos($msg, $startTag) + strlen($startTag));

			break;

		}

	}

	return $msg;

}

?>