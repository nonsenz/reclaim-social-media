<?php
/*  Copyright 2013-2014 diplix

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

function reclaim_text_add_more($text, $ellipsis, $read_more) {
    // New filter in WP2.9, seems unnecessary for now
    //$ellipsis = apply_filters('excerpt_more', $ellipsis);

    if ($read_more)
    $ellipsis .= sprintf(' <a href="%s" class="read_more">%s</a>', get_permalink(), $read_more);

    $pos = strrpos($text, '</');
    if ($pos !== false) // Inside last HTML tag
    $text = substr_replace($text, $ellipsis, $pos, 0);
    else // After the content
    $text .= $ellipsis;

    return $text;
}


function reclaim_text_excerpt($text, $length, $use_words, $finish_word, $finish_sentence) {
    $tokens = array();
    $out = '';
    $w = 0;

    // Divide the string into tokens; HTML tags, or words, followed by any whitespace
    // (<[^>]+>|[^<>\s]+\s*)
    preg_match_all('/(<[^>]+>|[^<>\s]+)\s*/u', $text, $tokens);
    foreach ($tokens[0] as $t) { // Parse each token
        if ($w >= $length && !$finish_sentence) { // Limit reached
            break;
        }
    if ($t[0] != '<') { // Token is not a tag
        if ($w >= $length && $finish_sentence && preg_match('/[\?\.\!]\s*$/uS', $t) == 1) { // Limit reached, continue until ? . or ! occur at the end
            $out .= trim($t);
            break;
        }
        if (1 == $use_words) { // Count words
            $w++;
        } else { // Count/trim characters
            $chars = trim($t); // Remove surrounding space
            $c = strlen($chars);
            if ($c + $w > $length && !$finish_sentence) { // Token is too long
                $c = ($finish_word) ? $c : $length - $w; // Keep token to finish word
                $t = substr($t, 0, $c);
            }
            $w += $c;
        }
    }
    // Append what's left of the token
    $out .= $t;
    }

    return trim(strip_tags($out));
}
