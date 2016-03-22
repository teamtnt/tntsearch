<?php

namespace TeamTNT\Support;

class Hihglighter
{   
    protected $options = [
        'simple'        => false,
        'wholeWord'     => false, 
        'caseSensitive' => false, 
        'stripLinks'    => false
    ];

    public function highlight($text, $needle, $tag = 'em', $options = [])
    {
        $this->options = array_merge($this->options, $options);

        $highlight = '<'.$tag.'>\1</'.$tag.'>';
        $needle = preg_split('/\PL+/u', $needle, -1, PREG_SPLIT_NO_EMPTY);

        // Select pattern to use
        if ($this->options['simple']) {
            $pattern = '#(%s)#';
            $sl_pattern = '#(%s)#';
        } else {
            $pattern = '#(?!<.*?)(%s)(?![^<>]*?>)#';
            $sl_pattern = '#<a\s(?:.*?)>(%s)</a>#';
        }

        // Case sensitivity
        if (!($this->options['caseSensitive'])) {
            $pattern .= 'i';
            $sl_pattern .= 'i';
        }

        $needle = (array) $needle;
        foreach ($needle as $needle_s) {
            $needle_s = preg_quote($needle_s);

            // Escape needle with optional whole word check
            if ($this->options['wholeWord']) {
                $needle_s = '\b' . $needle_s . '\b';
            }

            // Strip links
            if ($this->options['stripLinks']) {
                $sl_regex = sprintf($sl_pattern, $needle_s);
                $text = preg_replace($sl_regex, '\1', $text);
            }

            $regex = sprintf($pattern, $needle_s);
            $text = preg_replace($regex, $highlight, $text);
        }

        return $text;
    }
}