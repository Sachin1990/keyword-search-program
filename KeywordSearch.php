<?php
include("PorterStemmer.php");
$urls_recorded = file_exists("url_listing.txt");
if(!$urls_recorded){
	echo "INDEX MODE:";
	echo "\n";
	echo "Please enter the url:";
	$input = trim(fgets(STDIN));
	$url_file = fopen("url_listing.txt", "a+");
	fwrite($url_file, $input.",");
	fclose($url_file);
	indexMode($input);
	navigation();
}else{
	navigation();
}

function navigation(){
	echo "Please enter 1 for INDEX MODE or 2 for SEARCH MODE";
	echo "\n";
	$input = trim(fgets(STDIN));
	if(empty($input)){
		echo "please enter the url";
	}
	if($input == 1){
		echo "INDEX MODE:";
		echo "\n";
		echo "Please enter the url:";		
		$url_input = trim(fgets(STDIN));
		$list_urls = explode(",",file_get_contents("url_listing.txt"));
		foreach($list_urls as $list_url){
			if($url_input == $list_url){
				break;
			}else{
				$url_file = fopen("url_listing.txt", "a+");
				fwrite($url_file, $url_input.",");
				fclose($url_file);
			}			
		}
		indexMode($url_input);
		navigation();
	}else{
		echo "SEARCH MODE:";
		echo "\n";
		echo "Please enter the search key:";
		$search_key = trim(fgets(STDIN));
		search_mode($search_key);
		$s_results = search_mode($search_key);
		if(empty($s_results)){
			echo "no search results found";
			echo "\n";
		}else{
			foreach($s_results as $key => $value){
				echo $key;
				echo "\n";
			}
		}
		exit();

	}
}

function indexMode($url){
	$result = get_web_page($url);
	if ( $result['errno'] != 0 ){
    		echo "... Error:  bad URL, timeout, or redirect loop ...";
	}
	if ( $result['http_code'] != 200 ){
    		echo "... Error:  no server, no permissions, or no page ...";
	} 
	$encodedText = $result['content'];
	$contentType = $result['content_type'];
	preg_match( '@([\w/+]+)(;\s+charset=(\S+))?@i', $contentType, $matches );
	$charset = $matches[1];
	$text = mb_convert_encoding( $encodedText, 'utf-8', $charset );
	$text = strip_html_tags( $text );
	$text = html_entity_decode( $text, ENT_QUOTES, "utf-8" );
	$text = strip_punctuation( $text );
	$text = strip_symbols( $text );
	$text = strip_numbers( $text );
	$text = mb_strtolower( $text, "utf-8" );
	mb_regex_encoding( "utf-8" );
	$words = mb_split( ' +', $text );
	foreach ( $words as $key => $word ){
    		$words[$key] = PorterStemmer::Stem( $word, true );
	}
	$stopText  = file_get_contents("stopwords.txt"); 
	$stopWords = mb_split( ',', mb_strtolower( $stopText, 'utf-8' ) );
	foreach ( $stopWords as $key => $word ){
    		$stopWords[$key] = PorterStemmer::Stem( $word, true );
	}
	$words = array_diff( $words, $stopWords );
	$keywordCounts = array_count_values( $words ); 
	arsort( $keywordCounts, SORT_NUMERIC );
	$keyword_array = '';
	foreach($keywordCounts as $key => $value){
		$keyword_array .= $key. ":" . $value . ",";
	} 

	$handle = fopen($url.".txt", "w+");
	fwrite($handle, $keyword_array);
	fclose($handle);
	print_r($keywordCounts);
	$keyword_array = '';

}
function get_web_page( $url )
{
    $options = array(
        CURLOPT_RETURNTRANSFER => true,     // return web page
        CURLOPT_HEADER         => false,    // don't return headers
        CURLOPT_FOLLOWLOCATION => true,     // follow redirects
        CURLOPT_ENCODING       => "",       // handle all encodings
        CURLOPT_USERAGENT      => "spider", // who am i
        CURLOPT_AUTOREFERER    => true,     // set referer on redirect
        CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
        CURLOPT_TIMEOUT        => 120,      // timeout on response
        CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
    );

    $ch      = curl_init( $url );
    curl_setopt_array( $ch, $options );
    $content = curl_exec( $ch );
    $err     = curl_errno( $ch );
    $errmsg  = curl_error( $ch );
    $header  = curl_getinfo( $ch );
    curl_close( $ch );

    $header['errno']   = $err;
    $header['errmsg']  = $errmsg;
    $header['content'] = $content;
    return $header;
}


/**
 * Remove HTML tags, including invisible text such as style and
 * script code, and embedded objects.  Add line breaks around
 * block-level tags to prevent word joining after tag removal.
 */
function strip_html_tags( $text )
{
    $text = preg_replace(
        array(
          // Remove invisible content
            '@<head[^>]*?>.*?</head>@siu',
            '@<style[^>]*?>.*?</style>@siu',
            '@<script[^>]*?.*?</script>@siu',
            '@<object[^>]*?.*?</object>@siu',
            '@<embed[^>]*?.*?</embed>@siu',
            '@<applet[^>]*?.*?</applet>@siu',
            '@<noframes[^>]*?.*?</noframes>@siu',
            '@<noscript[^>]*?.*?</noscript>@siu',
            '@<noembed[^>]*?.*?</noembed>@siu',
          // Add line breaks before and after blocks
            '@</?((address)|(blockquote)|(center)|(del))@iu',
            '@</?((div)|(h[1-9])|(ins)|(isindex)|(p)|(pre))@iu',
            '@</?((dir)|(dl)|(dt)|(dd)|(li)|(menu)|(ol)|(ul))@iu',
            '@</?((table)|(th)|(td)|(caption))@iu',
            '@</?((form)|(button)|(fieldset)|(legend)|(input))@iu',
            '@</?((label)|(select)|(optgroup)|(option)|(textarea))@iu',
            '@</?((frameset)|(frame)|(iframe))@iu',
        ),
        array(
            ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ',
            "\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0",
            "\n\$0", "\n\$0",
        ),
        $text );
    return strip_tags( $text );
}


/**
 * Strip punctuation from text.
 */
function strip_punctuation( $text )
{
    $urlbrackets    = '\[\]\(\)';
    $urlspacebefore = ':;\'_\*%@&?!' . $urlbrackets;
    $urlspaceafter  = '\.,:;\'\-_\*@&\/\\\\\?!#' . $urlbrackets;
    $urlall         = '\.,:;\'\-_\*%@&\/\\\\\?!#' . $urlbrackets;
 
    $specialquotes  = '\'"\*<>';
 
    $fullstop       = '\x{002E}\x{FE52}\x{FF0E}';
    $comma          = '\x{002C}\x{FE50}\x{FF0C}';
    $arabsep        = '\x{066B}\x{066C}';
    $numseparators  = $fullstop . $comma . $arabsep;
 
    $numbersign     = '\x{0023}\x{FE5F}\x{FF03}';
    $percent        = '\x{066A}\x{0025}\x{066A}\x{FE6A}\x{FF05}\x{2030}\x{2031}';
    $prime          = '\x{2032}\x{2033}\x{2034}\x{2057}';
    $nummodifiers   = $numbersign . $percent . $prime;
 
    return preg_replace(
        array(
        // Remove separator, control, formatting, surrogate,
        // open/close quotes.
            '/[\p{Z}\p{Cc}\p{Cf}\p{Cs}\p{Pi}\p{Pf}]/u',
        // Remove other punctuation except special cases
            '/\p{Po}(?<![' . $specialquotes .
                $numseparators . $urlall . $nummodifiers . '])/u',
        // Remove non-URL open/close brackets, except URL brackets.
            '/[\p{Ps}\p{Pe}](?<![' . $urlbrackets . '])/u',
        // Remove special quotes, dashes, connectors, number
        // separators, and URL characters followed by a space
            '/[' . $specialquotes . $numseparators . $urlspaceafter .
                '\p{Pd}\p{Pc}]+((?= )|$)/u',
        // Remove special quotes, connectors, and URL characters
        // preceded by a space
            '/((?<= )|^)[' . $specialquotes . $urlspacebefore . '\p{Pc}]+/u',
        // Remove dashes preceded by a space, but not followed by a number
            '/((?<= )|^)\p{Pd}+(?![\p{N}\p{Sc}])/u',
        // Remove consecutive spaces
            '/ +/',
        ),
        ' ',
        $text );
}

/**
 * Strip symbols from text.
 */
function strip_symbols( $text )
{
    $plus   = '\+\x{FE62}\x{FF0B}\x{208A}\x{207A}';
    $minus  = '\x{2012}\x{208B}\x{207B}';
 
    $units  = '\\x{00B0}\x{2103}\x{2109}\\x{23CD}';
    $units .= '\\x{32CC}-\\x{32CE}';
    $units .= '\\x{3300}-\\x{3357}';
    $units .= '\\x{3371}-\\x{33DF}';
    $units .= '\\x{33FF}';
 
    $ideo   = '\\x{2E80}-\\x{2EF3}';
    $ideo  .= '\\x{2F00}-\\x{2FD5}';
    $ideo  .= '\\x{2FF0}-\\x{2FFB}';
    $ideo  .= '\\x{3037}-\\x{303F}';
    $ideo  .= '\\x{3190}-\\x{319F}';
    $ideo  .= '\\x{31C0}-\\x{31CF}';
    $ideo  .= '\\x{32C0}-\\x{32CB}';
    $ideo  .= '\\x{3358}-\\x{3370}';
    $ideo  .= '\\x{33E0}-\\x{33FE}';
    $ideo  .= '\\x{A490}-\\x{A4C6}';
 
    return preg_replace(
        array(
        // Remove modifier and private use symbols.
            '/[\p{Sk}\p{Co}]/u',
        // Remove mathematics symbols except + - = ~ and fraction slash
            '/\p{Sm}(?<![' . $plus . $minus . '=~\x{2044}])/u',
        // Remove + - if space before, no number or currency after
            '/((?<= )|^)[' . $plus . $minus . ']+((?![\p{N}\p{Sc}])|$)/u',
        // Remove = if space before
            '/((?<= )|^)=+/u',
        // Remove + - = ~ if space after
            '/[' . $plus . $minus . '=~]+((?= )|$)/u',
        // Remove other symbols except units and ideograph parts
            '/\p{So}(?<![' . $units . $ideo . '])/u',
        // Remove consecutive white space
            '/ +/',
        ),
        ' ',
        $text );
}

/**
 * Strip numbers from text.
 */
function strip_numbers( $text )
{
    $urlchars      = '\.,:;\'=+\-_\*%@&\/\\\\?!#~\[\]\(\)';
    $notdelim      = '\p{L}\p{M}\p{N}\p{Pc}\p{Pd}' . $urlchars;
    $predelim      = '((?<=[^' . $notdelim . '])|^)';
    $postdelim     = '((?=[^'  . $notdelim . '])|$)';
 
    $fullstop      = '\x{002E}\x{FE52}\x{FF0E}';
    $comma         = '\x{002C}\x{FE50}\x{FF0C}';
    $arabsep       = '\x{066B}\x{066C}';
    $numseparators = $fullstop . $comma . $arabsep;
    $plus          = '\+\x{FE62}\x{FF0B}\x{208A}\x{207A}';
    $minus         = '\x{2212}\x{208B}\x{207B}\p{Pd}';
    $slash         = '[\/\x{2044}]';
    $colon         = ':\x{FE55}\x{FF1A}\x{2236}';
    $units         = '%\x{FF05}\x{FE64}\x{2030}\x{2031}';
    $units        .= '\x{00B0}\x{2103}\x{2109}\x{23CD}';
    $units        .= '\x{32CC}-\x{32CE}';
    $units        .= '\x{3300}-\x{3357}';
    $units        .= '\x{3371}-\x{33DF}';
    $units        .= '\x{33FF}';
    $percents      = '%\x{FE64}\x{FF05}\x{2030}\x{2031}';
    $ampm          = '([aApP][mM])';
 
    $digits        = '[\p{N}' . $numseparators . ']+';
    $sign          = '[' . $plus . $minus . ']?';
    $exponent      = '([eE]' . $sign . $digits . ')?';
    $prenum        = $sign . '[\p{Sc}#]?' . $sign;
    $postnum       = '([\p{Sc}' . $units . $percents . ']|' . $ampm . ')?';
    $number        = $prenum . $digits . $exponent . $postnum;
    $fraction      = $number . '(' . $slash . $number . ')?';
    $numpair       = $fraction . '([' . $minus . $colon . $fullstop . ']' .
        $fraction . ')*';
 
    return preg_replace(
        array(
        // Match delimited numbers
            '/' . $predelim . $numpair . $postdelim . '/u',
        // Match consecutive white space
            '/ +/u',
        ),
        ' ',
        $text );
}

function search_mode($search_key){
	$urls_list = explode(",",file_get_contents("url_listing.txt"));
	$search_results = array();
	foreach($urls_list as $url_list){
		$url_record = file_exists($url_list.".txt");
		if($url_record){
			$exploded_keywords = explode(",",file_get_contents($url_list.".txt"));
			foreach($exploded_keywords as $exploded_keyword){
				$matching_keyword = explode(":", $exploded_keyword);
				if($matching_keyword[0] == $search_key){
					$search_results[$url_list] = $matching_keyword[1];
					break;

				}
			}
		}	
		
	}
	arsort($search_results);
	return $search_results;	
	
}

 
?>