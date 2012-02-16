<?php
/**
 * Pengakar: Indonesian stemmer
 * Copyright (C) 2012 Ivan Lanin <ivan at lanin dot org>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.	If not, see <http://www.gnu.org/licenses/>.
 */
error_reporting(E_ALL & ~E_NOTICE);

$pengakar = new pengakar;

$q = stripslashes($_POST['q']); // Unix server. Suhosin?

$ret .= '<form action="./" method="post" style="margin-bottom: 20px;">';
$ret .= '<textarea id="q" name="q" style="width:90%;" rows="10">' . $q . '</textarea>';
$ret .= '<br />';
$ret .= '<input type="submit" value="Proses" />';
$ret .= '</form>';
if ($q)
{
	$ret .= '<h3>Daftar kata</h3>';
	$ret .= $pengakar->get_html($q);
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="id">
<head>
<title>Pengakar</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<style>
a { text-decoration: none; }
.instance { color: #666; font-style: italic; font-size: 80%; }
</style>
<body>
<h2>Pengakar</h2>
<p>Pengakar (<em><a href="http://en.wikipedia.org/wiki/Stemming">stemmer</a></em>)
adalah program pencari akar kata bahasa Indonesia. Program ini dibuat dengan
menyempurnakan beberapa algoritme yang diperoleh dari berbagai sumber, terutama
artikel "<a href="http://dl.acm.org/citation.cfm?id=1082195">Stemming Indonesian</a>"
(Asian, 2005). Tentu saja program ini masih terus disempurnakan dan, karena itu,
mohon bantuan untuk melaporkan kesalahan kepada
<a href="http://twitter.com/ivanlanin">@ivanlanin</a>. Terima kasih.</p>
<p>Silakan masukkan kata (bisa dipisahkan dengan spasi, koma, atau baris baru)
atau salin rekatkan teks pada kotak di bawah ini. Klik "Proses" dan pengakar
akan berupaya mencari akar kata tersebut. Warna <span style="color:#f00">merah</span>
berarti kata tersebut tidak ditemukan di dalam leksikon dan diletakkan paling atas
dalam daftar. Daftar kata diurutkan berdasarkan jumlah kemunculan.</p>
<?php echo($ret); ?>
<hr style="margin-top: 20px;" />
Lisensi: <a href="http://www.gnu.org/licenses/gpl.html">GPL</a>
(<a href="https://github.com/ivanlanin/pengakar">Kode</a>),
<a href="http://creativecommons.org/licenses/by-nc/3.0/">CC-BY-NC</a>
(<a href="./kamus.txt">Leksikon</a>) | <a href="./README.TXT">README.TXT</a>
</body>
</html>
<?php

/**
 * Main class
 */
class pengakar
{
	var $dict;

	/**
	 *
	 */
	function __construct()
	{
		$dict = file_get_contents('./kamus.txt');
		$tmp = explode("\n", $dict);
		// create associative array
		foreach ($tmp as $entry)
		{
			$entry = strtolower($entry);
			$attrib = explode("\t", $entry);
			$class = $attrib[0];
			$lemma = $attrib[1];
			$key = str_replace(' ', '', $lemma);
			$this->dict[$key] = array('class' => $class, 'lemma' => $lemma);
		}
	}

	/**
	 * Get HTML result
	 */
	function get_html($query)
	{
		$url_template = 'http://kateglo.bahtera.org/?mod=dict&action=view&phrase=%1$s';

		// Process all words
		$words = $this->stem($query);
		$word_count = count($words);
		// ksort($words); // normal sort
		// sort by number of instances and alphabet
		$keys = array_keys($words);
		foreach ($words as $key => $word)
		{
			$instances[$key] = $word['count'];
		}
		array_multisort($instances, SORT_DESC, $keys, SORT_ASC, $words);


		// Render display
		foreach ($words as $key => $word)
		{
			$roots = $word['roots'];
			$root_count = count($roots);
			if ($word['count'] > 1)
				$instances = ' <span class="instance">x' . $word['count'] . '</span>';
			else
				$instances = '';
			if ($root_count == 0) // no match
				$lost .= sprintf('<li><span style="color: #f00">%s</span>%s</li>',
					$key, $instances);
			else
			{
				$i = 0; unset($components);
				foreach ($roots as $lemma => $attrib)
				{
					$i++;
					$affixes = $attrib['affixes'];
					$url = sprintf($url_template, $attrib['lemma']);
					$lemma_url = sprintf('<a href="%s" target="kateglo">%s</a>', $url, $attrib['lemma']);
					$components .= $components ? '; ' : '';
					if ($key == $lemma && $root_count == 1) // is baseword
						$components .= $lemma_url . $instances;
					else
					{
						if ($root_count > 1) // multiroot
						{
							if ($i == 1)
								$components .= $key . $instances . ': ';
							$components .= "({$i}) ";
						}
						if (is_array($attrib['prefixes']))
							$components .= implode('', $attrib['prefixes']);
						$components .= $lemma_url;
						if (is_array($attrib['suffixes']))
							$components .= implode('', $attrib['suffixes']);
						if ($root_count == 1) // single root
							$components .= $instances;
					}
				}
				$found .= sprintf('<li>%s</li>', $components);
			}
		}
		// Render display
		if ($word_count >= 10)
			$ret .= '<div style="-webkit-column-count: 3; -moz-column-count: 3;">';
		$ret .= '<ol style="margin:0;">';
		$ret .= $lost;
		$ret .= $found;
		$ret .= '</ol>';
		if ($word_count >= 10)
			$ret .= '</div>';
		return($ret);
	}

	/**
	 * Tokenization
	 */
	function stem($query)
	{
		$words = array();
		$raw = explode(' ', $query);
		$raw = preg_split('/\W/', $query, -1, PREG_SPLIT_NO_EMPTY);
		foreach ($raw as $r)
		{
			$key = strtolower($r);
			$words[$key]['count']++;
		}
		foreach ($words as $key => $val)
			$words[$key]['roots'] = $this->stem_word($key);
		return($words);
	}

	/**
	 * Stem individual word
	 */
	function stem_word($word)
	{
		// Preprocess: Create empty affix if original word is in lexicon
		$word = trim($word);
		$roots = array($word => '');
		if (array_key_exists($word, $this->dict))
			$roots[$word]['affixes'] = array();

		// Process: Find suffixes, pronoun prefix, and other prefix (3 times, Asian)
		$this->find_affixes($roots);
		for ($i = 0; $i < 3; $i++)
			$this->find_prefix($roots);

		// Postprocess, remove when root not found in dictionary
		$i = 0;
		foreach ($roots as $lemma => $attrib)
		{
			if (!array_key_exists($lemma, $this->dict))
				unset($roots[$lemma]);
			else
			{
				$attrib['lemma'] = $this->dict[$lemma]['lemma'];
				// Divide affixes into suffixes and prefixes
				foreach ($attrib['affixes'] as $affix)
				{
					$type = (substr($affix, 0, 1) == '-') ? 'suffixes' : 'prefixes';
					$attrib[$type][] = $affix;
				}
				if (is_array($attrib['suffixes']))
					krsort($attrib['suffixes']); // Reverse suffix order
				$roots[$lemma] = $attrib;
			}
		}
		return($roots);
	}

	/**
	 * Find particle suffixes (part), pronoun suffixes (pros),
	 * derivational suffixes (derv), and pronoun prefixes (proa)
	 */
	function find_affixes(&$roots)
	{
		$groups = array(
			'part' => array('is_suffix' => 1, 'affixes' => 'kah,lah,tah,pun'),
			'pros' => array('is_suffix' => 1, 'affixes' => 'mu,ku,nya'),
			'derv' => array('is_suffix' => 1, 'affixes' => 'i,kan,an'),
			'proa' => array('is_suffix' => 0, 'affixes' => 'ku,kau'),
		);
		foreach ($groups as $group)
		{
			$affixes = explode(',', $group['affixes']);
			$is_suffix = $group['is_suffix'];
			foreach ($affixes as $affix)
			{
				$pattern = $is_suffix ? "(.+)({$affix})" : "({$affix})(.+)";
				$pattern = "/^{$pattern}$/i";
				$this->add_root($roots, $pattern, '', $is_suffix);
			}
		}
	}

	/**
	 *
	 */
	function find_prefix(&$roots)
	{
		$VOWEL = 'a|i|u|e|o'; // vowels
		$CONSONANT = 'b|c|d|f|g|h|j|k|l|m|n|p|q|r|s|t|v|w|x|y|z'; // consonants
		$ANY = $VOWEL . '|' . $CONSONANT; // any characters
		$rules = array(
			array("(di|ke|se)({$ANY})(.+)", ""), // 0
			array("(ber|ter)({$ANY})(.+)", ""), // 1, 6 normal
			array("(be|te)(r)({$VOWEL})(.+)", ""), // 1, 6 be-rambut
			array("(be|te)({$CONSONANT})({$ANY}?)(er)(.+)", ""), // 3, 7 te-bersit, te-percaya
			array("(bel|pel)(ajar|unjur)", ""), // ajar, unjur
			array("(me|pe)(l|m|n|r|w|y)(.+)", ""), // 10, 20: merawat, pemain
			array("(mem|pem)(b|f|v)(.+)", ""), // 11 23: membuat, pembuat
			array("(men|pen)(c|d|j|z)(.+)", ""), // 14 27: mencabut, pencabut
			array("(meng|peng)(g|h|q|x)(.+)", ""), // 16 29: menggiring, penghasut
			array("(meng|peng)({$VOWEL})(.+)", ""), // 17 30 meng-anjurkan, peng-anjur
			array("(mem|pem)({$VOWEL})(.+)", "p"), // 13 26: memerkosa, pemerkosa
			array("(men|pen)({$VOWEL})(.+)", "t"), // 15 28 menutup, penutup
			array("(meng|peng)({$VOWEL})(.+)", "k"), // 17 30 mengalikan, pengali
			array("(meny|peny)({$VOWEL})(.+)", "s"), // 18 31 menyucikan, penyucian
			array("(mem)(punya)", ""), // Exception: mempunya
			array("(mem)(p)({$CONSONANT})(.+)", ""), // memproklamasikan
			array("(pem)({$CONSONANT})(.+)", "p"), // pemrogram
			array("(men|pen)(t)({$CONSONANT})(.+)", ""), // mentransmisikan pentransmisian
			array("(meng|peng)(k)({$CONSONANT})(.+)", ""), // mengkristalkan pengkristalan
			array("(men|pen)(s)({$CONSONANT})(.+)", ""), // mensyaratkan pensyaratan
			array("(menge|penge)({$CONSONANT})(.+)", ""), // swarabakti: mengepel
			array("(mempe)(r)({$VOWEL})(.+)", ""), // 21
			array("(memper)({$ANY})(.+)", ""), // 21
			array("(pe)({$ANY})(.+)", ""), // 20
			array("(per)({$ANY})(.+)", ""), // 21
			array("(pel)({$CONSONANT})(.+)", ""), // 32 pelbagai, other?
		);
		foreach ($rules as $rule)
		{
			$pattern = '/^' . $rule[0] . '$/i';
			$variant = $rule[1];
			$this->add_root($roots, $pattern, $variant, 0);
		}
	}

	/**
	 * Greedy algorithm: add every possible branch
	 */
	function add_root(&$roots, $pattern, $variant = '', $is_suffix = 1)
	{
		foreach ($roots as $lemma => $attrib)
		{
			preg_match($pattern, $lemma, $matches);
			if (count($matches) > 0)
			{
				unset($new_lemma); unset($new_affix);
				$affix_index = $is_suffix ? 2 : 1;

				// Lemma
				for ($i = 1; $i < count($matches); $i++)
					if ($i != $affix_index)
						$new_lemma .= $matches[$i];
				if ($variant)
					$new_lemma = $variant . $new_lemma;

				// Affix, add - before (suffix), after (prefix)
				$new_affix .= $is_suffix ? '-' : '';
				$new_affix .= $matches[$affix_index];
				$new_affix .= $is_suffix ? '' : '-';
				$new_affix = array($new_affix); // make array
				if (is_array($attrib['affixes'])) // merge
					$new_affix = array_merge($attrib['affixes'], $new_affix);

				// Push
				$roots[$new_lemma] = array('affixes' => $new_affix);
			}
		}
	}
}