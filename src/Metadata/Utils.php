<?php

namespace Botkaplus;

class Utils {
    function Bold($text){
        return "**$text**";
    }

    function Hyperlink($text, $link){
        return "[" . $text . "](" . trim($link) . ")";
    }

    function Italic($text){
        return "__" . $text . "__";
    }

    function Underline($text){
        return "--" . $text . "--";
    }

    function Mono($text){
        return "`" . $text . "`";
    }

    function Strike($text){
        return "~~" . $text . "~~";
    }

    function Spoiler($text){
        return "||" . $text . "||";
    }

    function Code($text){
        return "```" . $text . "```";
    }

    function Quote($text){
        return "##" . $text . "##";
    }
}

?>
