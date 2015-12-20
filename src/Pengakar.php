<?php
/**
 * Pengakar: Indonesian stemmer
 * (c) 2012–2015 Ivan Lanin <ivanlanin at gmail dot com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace Kateglo;

/**
 * Main class
 */
class Pengakar
{
    private $dict;
    private $rules;
    private $options;

    /**
     * Konstruktor
     */
    public function __construct()
    {
        // Read dictionary and create associative array
        $dict = file_get_contents('./data/kamus.txt');
        $tmp = explode("\n", $dict);
        foreach ($tmp as $entry) {
            $attrib = explode("\t", strtolower($entry)); // 0: lemma; 1: class
            $key = str_replace(' ', '', $attrib[0]); // remove space
            $this->dict[$key] = array('lemma' => $attrib[0], 'class' => $attrib[1]);
        }
        // Options
        $this->options = array(
            'SORT_INSTANCE' => false, // sort by number of instances
            'NO_NO_MATCH'   => false, // hide no match entry
            'NO_DIGIT_ONLY' => true, // hide digit only
            'STRICT_CONFIX' => false, // use strict disallowed_confixes rules
        );
        // Define rules
        $VOWEL = 'a|i|u|e|o'; // vowels
        $CONSONANT = 'b|c|d|f|g|h|j|k|l|m|n|p|q|r|s|t|v|w|x|y|z'; // consonants
        $ANY = $VOWEL . '|' . $CONSONANT; // any characters
        $this->rules = array(
            'affixes' => array(
                array(1, array('kah', 'lah', 'tah', 'pun')),
                array(1, array('mu', 'ku', 'nya')),
                array(0, array('ku', 'kau')),
                array(1, array('i', 'kan', 'an')),
            ),
            'prefixes' => array(
                array(0, "(di|ke|se)({$ANY})(.+)", ""), // 0
                array(0, "(ber|ter)({$ANY})(.+)", ""), // 1, 6 normal
                array(0, "(be|te)(r)({$VOWEL})(.+)", ""), // 1, 6 be-rambut
                array(0, "(be|te)({$CONSONANT})({$ANY}?)(er)(.+)", ""), // 3, 7 te-bersit, te-percaya
                array(0, "(bel|pel)(ajar|unjur)", ""), // ajar, unjur
                array(0, "(me|pe)(l|m|n|r|w|y)(.+)", ""), // 10, 20: merawat, pemain
                array(0, "(mem|pem)(b|f|v)(.+)", ""), // 11 23: membuat, pembuat
                array(0, "(men|pen)(c|d|j|z)(.+)", ""), // 14 27: mencabut, pencabut
                array(0, "(meng|peng)(g|h|q|x)(.+)", ""), // 16 29: menggiring, penghasut
                array(0, "(meng|peng)({$VOWEL})(.+)", ""), // 17 30 meng-anjurkan, peng-anjur
                array(0, "(mem|pem)({$VOWEL})(.+)", "p"), // 13 26: memerkosa, pemerkosa
                array(0, "(men|pen)({$VOWEL})(.+)", "t"), // 15 28 menutup, penutup
                array(0, "(meng|peng)({$VOWEL})(.+)", "k"), // 17 30 mengalikan, pengali
                array(0, "(meny|peny)({$VOWEL})(.+)", "s"), // 18 31 menyucikan, penyucian
                array(0, "(mem)(p)({$CONSONANT})(.+)", ""), // memproklamasikan
                array(0, "(pem)({$CONSONANT})(.+)", "p"), // pemrogram
                array(0, "(men|pen)(t)({$CONSONANT})(.+)", ""), // mentransmisikan pentransmisian
                array(0, "(meng|peng)(k)({$CONSONANT})(.+)", ""), // mengkristalkan pengkristalan
                array(0, "(men|pen)(s)({$CONSONANT})(.+)", ""), // mensyaratkan pensyaratan
                array(0, "(menge|penge)({$CONSONANT})(.+)", ""), // swarabakti: mengepel
                array(0, "(mempe)(r)({$VOWEL})(.+)", ""), // 21
                array(0, "(memper)({$ANY})(.+)", ""), // 21
                array(0, "(pe)({$ANY})(.+)", ""), // 20
                array(0, "(per)({$ANY})(.+)", ""), // 21
                array(0, "(pel)({$CONSONANT})(.+)", ""), // 32 pelbagai, other?
                array(0, "(mem)(punya)", ""), // Exception: mempunya
                array(0, "(pen)(yair)", "s"), // Exception: penyair > syair
            ),
            'disallowed_confixes' => array(
                array('ber-', '-i'),
                array('ke-', '-i'),
                array('pe-', '-kan'),
                array('di-', '-an'),
                array('meng-', '-an'),
                array('ter-', '-an'),
                array('ku-', '-an'),
            ),
            'allomorphs' => array(
                'be' => array('be-', 'ber-', 'bel-'),
                'te' => array('te-', 'ter-', 'tel-'),
                'pe' => array('pe-', 'per-', 'pel-', 'pen-', 'pem-', 'peng-', 'peny-', 'penge-'),
                'me' => array('me-', 'men-', 'mem-', 'meng-', 'meny-', 'menge-'),
            ),
        );

    }

    /**
     * Ambil konten
     *
     * @param string $url
     *
     * @return string
     */
    public function getContent($url)
    {
        // Curl
        $agent = "Mozilla/5.0 (Windows; U; Windows NT 5.0; en; rv:1.9.0.4) Gecko/2009011913 Firefox/3.0.6";
        $domain = 'http://' . parse_url($url, PHP_URL_HOST);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_REFERER, $domain); // pseudo referer
        curl_setopt($curl, CURLOPT_USERAGENT, $agent); // pseudo agent
        $html = curl_exec($curl);
        curl_close($curl);
        // Process HTML
        $ret = $html;
        $ret = preg_replace('/<(script|style)\b[^>]*>(.*?)<\/\1>/is', "", $ret); // remove script & style
        $ret = preg_replace('/<(br|p)[^>]*>/i', "\n", $ret); // new line for br & p
        $ret = trim(strip_tags($ret)); // strip tags
        $ret = preg_replace('/^\s*/m', '', $ret); // trim left
        $ret = preg_replace('/\s*$/m', '', $ret); // trim right
        $ret = preg_replace('/\n+/', "\n\n", $ret); // two new line: readability

        return $ret;
    }

    /**
     * Ambil hasil API
     *
     * @param string $query
     *
     * @return string
     */
    public function getApi($query)
    {
        $words = $this->stem($query);
        if ($query != '') {
            $ret = json_encode($words);
        } else {
            $ret = 'API Pengakar<br /><br />' .
                'Sintaks:<br />' .
                '* <a href="./?api=1&q=pengakar">?api=1&q=...</a><br />' .
                '* <a href="./?api=1&url=http://ivan.lanin.org/pengakar/">?api=1&url=...</a><br /><br />' .
                'Hasil:<br />' .
                'lemma => { <br />' .
                '&nbsp;&nbsp;count,<br />' .
                '&nbsp;&nbsp;roots => { <br />' .
                '&nbsp;&nbsp;&nbsp;&nbsp;root => lemma, affixes {}, suffixes {}, prefixes {} <br />' .
                '&nbsp;&nbsp;} <br />' .
                '}' .
                '';
        }

        return $ret;
    }

    /**
     * Ambil hasil dalam format HTML
     *
     * @param string $query
     *
     * @return string
     */
    public function getHtml($query)
    {
        $words = $this->stem($query);
        $url_template = 'http://kateglo.com/?mod=dict&action=view&phrase=%1$s';

        // Render display
        $word_count = count($words);
        foreach ($words as $key => $word) {
            $roots = $word['roots'];
            $root_count = count($roots);
            //if ($root_count <= 1) continue; // display disambig only
            if ($word['count'] > 1) {
                $instances = ' <span class="instance">x' . $word['count'] . '</span>';
            } else {
                $instances = '';
            }
            if ($root_count == 0) { // no match
                $lost .= sprintf(
                    '<li><span class="notfound">%s</span>%s</li>',
                    $key,
                    $instances
                );
            } else {
                $i = 0;
                unset($components);
                foreach ($roots as $lemma => $attrib) {
                    $i++;
                    $affixes = $attrib['affixes'];
                    $url = sprintf($url_template, $attrib['lemma']);
                    $lemma_url = sprintf('<a href="%s" target="kateglo">%s</a>', $url, $attrib['lemma']);
                    $components .= $components ? '; ' : '';
                    if ($key == $lemma && $root_count == 1) { // is baseword
                        $components .= $lemma_url . $instances . $class;
                    } else {
                        // Multiroot
                        if ($root_count > 1 && $i == 1) {
                            $components .= $key . $instances . ': ';
                        }
                        if ($root_count > 1) {
                            $components .= "({$i}) ";
                        }
                        // Prefix, lemma, & suffix
                        if (is_array($attrib['prefixes'])) {
                            $components .= implode('', $attrib['prefixes']);
                        }
                        $components .= $lemma_url;
                        if (is_array($attrib['suffixes'])) {
                            $components .= implode('', $attrib['suffixes']);
                        }
                        // Single root
                        if ($root_count == 1) {
                            $components .= $instances;
                        }
                    }
                }
                $found .= sprintf('<li>%s</li>', $components);
            }
        }
        // Render display
        if ($word_count >= 10) {
            $ret .= '<div style="-webkit-column-count: 3; -moz-column-count: 3;">';
        }
        $ret .= '<ol style="margin:0;">';
        $ret .= $lost;
        $ret .= $found;
        $ret .= '</ol>';
        if ($word_count >= 10) {
            $ret .= '</div>';
        }

        return $ret;
    }

    /**
     * Tokenisasi
     *
     * @param string $query
     *
     * @return array
     */
    private function stem($query)
    {
        $words = array();
        $raw = preg_split('/[^a-zA-Z0-9\-]/', $query, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($raw as $r) {
        // Remove all digit "word" if necessary
            if ($this->options['NO_DIGIT_ONLY'] && preg_match('/^\d+$/', $r)) {
                continue;
            }
            $key = strtolower($r);
            $words[$key]['count']++;
        }
        foreach ($words as $key => $word) {
            $words[$key]['roots'] = $this->stemWord($key);
            // If NO_NO_MATCH, remove words that has no root
            if (count($words[$key]['roots']) == 0 && $this->options['NO_NO_MATCH']) {
                unset($words[$key]);
                continue;
            }
            $instances[$key] = $word['count'];
        }
        $word_count = count($words);
        if ($this->options['SORT_INSTANCE']) {
            $keys = array_keys($words);
            array_multisort($instances, SORT_DESC, $keys, SORT_ASC, $words);
        } else {
            ksort($words);
        }

        return $words;
    }

    /**
     * Stem individual word
     *
     * @param string $word
     *
     * @return array
     */
    private function stemWord($word)
    {
        // Preprocess: Create empty affix if original word is in lexicon
        $word = trim($word);
        $roots = array($word => '');
        if (array_key_exists($word, $this->dict)) {
            $roots[$word]['affixes'] = array();
        }
        // Has dash? Try to also find root for each element
        if (strpos($word, '-')) {
            $dash_parts = explode('-', $word);
            foreach ($dash_parts as $dash_part) {
                $roots[$dash_part]['affixes'] = array();
            }
        }

        // Process: Find suffixes, pronoun prefix, and other prefix (3 times, Asian)
        foreach ($this->rules['affixes'] as $group) {
            $is_suffix = $group[0];
            $affixes = $group[1];
            foreach ($affixes as $affix) {
                $pattern = $is_suffix ? "(.+)({$affix})" : "({$affix})(.+)";
                $this->addRoot($roots, array($is_suffix, $pattern, ''));
            }
        }
        for ($i = 0; $i < 3; $i++) {
            foreach ($this->rules['prefixes'] as $rule) {
                $this->addRoot($roots, $rule);
            }
        }

        // Postprocess 1: Select valid affixes
        foreach ($roots as $lemma => $attrib) {
        // Not in dictionary? Unset and exit
            if (!array_key_exists($lemma, $this->dict)) {
                unset($roots[$lemma]);
                continue;
            }
            // Escape if we don't have to check valid confix pairs
            if (!$this->options['STRICT_CONFIX']) {
                continue;
            }
            // Check confix pairs
            $affixes = $attrib['affixes'];
            foreach ($this->rules['disallowed_confixes'] as $pair) {
                $prefix = $pair[0];
                $suffix = $pair[1];
                $prefix_key = substr($prefix, 0, 2);
                if (array_key_exists($prefix_key, $this->rules['allomorphs'])) {
                    foreach ($this->rules['allomorphs'][$prefix_key] as $allomorf) {
                        if (in_array($allomorf, $affixes) && in_array($suffix, $affixes)) {
                            unset($roots[$lemma]);
                        }
                    }
                } elseif (in_array($prefix, $affixes) && in_array($suffix, $affixes)) {
                        unset($roots[$lemma]);
                }
            }
        }

        // Postprocess 2: Handle suffixes and prefixes
        foreach ($roots as $lemma => $attrib) {
            $affixes = $attrib['affixes'];
            $attrib['lemma'] = $this->dict[$lemma]['lemma'];
            $attrib['class'] = $this->dict[$lemma]['class'];
            // Divide affixes into suffixes and prefixes
            foreach ($attrib['affixes'] as $affix) {
                $type = (substr($affix, 0, 1) == '-') ? 'suffixes' : 'prefixes';
                $attrib[$type][] = $affix;
            }
            // Reverse suffix order
            if (is_array($attrib['suffixes'])) {
                krsort($attrib['suffixes']);
            }
            $roots[$lemma] = $attrib;
        }

        return $roots;
    }

    /**
     * Greedy algorithm: add every possible branch
     *
     * @param array $roots
     * @param array $rule
     *
     * @return void
     */
    private function addRoot(&$roots, $rule)
    {
        $is_suffix = $rule[0];
        $pattern = '/^' . $rule[1] . '$/i';
        $variant = $rule[2];
        foreach ($roots as $lemma => $attrib) {
            preg_match($pattern, $lemma, $matches);
            if (count($matches) > 0) {
                unset($new_lemma);
                unset($new_affix);
                $affix_index = $is_suffix ? 2 : 1;

                // Lemma
                for ($i = 1; $i < count($matches); $i++) {
                    if ($i != $affix_index) {
                        $new_lemma .= $matches[$i];
                    }
                }
                if ($variant) {
                    $new_lemma = $variant . $new_lemma;
                }

                // Affix, add - before (suffix), after (prefix)
                $new_affix .= $is_suffix ? '-' : '';
                $new_affix .= $matches[$affix_index];
                $new_affix .= $is_suffix ? '' : '-';
                $new_affix = array($new_affix); // make array
                if (is_array($attrib['affixes'])) { // merge
                    $new_affix = array_merge($attrib['affixes'], $new_affix);
                }

                // Push
                $roots[$new_lemma] = array('affixes' => $new_affix);
            }
        }
    }
}
