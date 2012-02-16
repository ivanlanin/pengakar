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

$q = $_POST['q'];

$ret .= '<form action="./" method="post">';
$ret .= '<textarea id="q" name="q" style="width:90%;" rows="10">' . $q . '</textarea>';
$ret .= '<br />';
$ret .= '<input type="submit" value="Proses" />';
$ret .= '</form>';
if ($q) $ret .= $pengakar->stem($q);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="id">
<head>
<title>Pengakar</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<style>a { text-decoration: none; }</style>
<body>
<h2>Pengakar</h2>
<p>Pengakar (<em><a href="http://en.wikipedia.org/wiki/Stemming">stemmer</a></em>) adalah program pencari akar kata bahasa Indonesia. Program ini dibuat dengan menyempurnakan beberapa algoritme yang diperoleh dari berbagai sumber, terutama artikel "<a href="http://dl.acm.org/citation.cfm?id=1082195">Stemming Indonesian</a>" (Asian, 2005). Tentu saja program ini masih terus disempurnakan dan, karena itu, mohon bantuan untuk melaporkan kesalahan kepada <a href="http://twitter.com/ivanlanin">@ivanlanin</a>. Terima kasih.</p>
<p>Silakan masukkan kata (bisa dipisahkan dengan spasi, koma, atau baris baru) atau salin rekatkan teks pada kotak di bawah ini. Klik "Proses" dan pengakar akan berupaya mencari akar kata tersebut.</p>
<?php echo($ret); ?>
<hr />
Lisensi: <a href="http://www.gnu.org/licenses/gpl.html">GPL</a> (<a href="https://github.com/ivanlanin/pengakar">Kode</a>), <a href="http://creativecommons.org/licenses/by-nc/3.0/">CC-BY-NC</a> (<a href="./kamus.txt">Leksikon</a>) | <a href="./README.TXT">README.TXT</a>
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
		$this->dict = explode("\n", $dict);
		foreach ($this->dict as &$entry)
			$entry = strtolower($entry);
	}

	/**
	 *
	 */
	function stem($query)
	{
		$words = array();
		$raw = explode(' ', $query);
		$raw = preg_split('/\W/', $query, -1, PREG_SPLIT_NO_EMPTY);
		foreach ($raw as $r)
			if (!in_array(strtolower($r), $words))
				$words[] = strtolower($r);
		natcasesort($words);
		$count = count($words);
		foreach ($words as $word)
		{
			$tmp = $this->stem_word($word);
			// FIXME: Stupid hack :P
			if ($tmp)
			{
				if (substr($tmp, 0, 5) == '<span')
					$lost .= '<li>' . $tmp . '</li>';
				else
					$found .= '<li>' . $tmp . '</li>';
			}
		}

		if ($count >= 10)
			$ret .= '<div style="-webkit-column-count: 3; -moz-column-count: 3;">';
		$ret .= '<ol>';
		$ret .= $lost;
		$ret .= $found;
		$ret .= '</ol>';
		if ($count >= 10)
			$ret .= '</div>';
		return($ret);
	}

	/**
	 * Stem individual word
	 */
	function stem_word($word)
	{
		$url_template = 'http://kateglo.bahtera.org/?mod=dict&action=view&phrase=%1$s';

		// Preprocess: Original word
		$word = trim($word);
		$roots = array($word => '');
		if (in_array($word, $this->dict))
			$roots[$word]['affixes'] = array();

		// Process: Find suffixes, pronoun prefix, and other prefix (3 times, Asian)
		$this->find_affixes($roots);
		for ($i = 0; $i < 3; $i++)
			$this->find_prefix($roots);

		// Postprocess, remove when root not found in dictionary
		$i = 0;
		foreach ($roots as $lemma => $attrib)
		{
			if (!in_array($lemma, $this->dict))
				unset($roots[$lemma]);
			else
			{
				// Divide affixes into suffixes and prefixes
				// Reverse suffix order
				foreach ($attrib['affixes'] as $affix)
				{
					$type = (substr($affix, 0, 1) == '-') ? 'suffixes' : 'prefixes';
					$attrib[$type][] = $affix;
				}
				if (is_array($attrib['suffixes']))
					krsort($attrib['suffixes']);
				$roots[$lemma] = $attrib;
			}
		}

		// Return if no root match dictionary
		// FIXME: HTML renderer should be handled by other function
		$root_count = count($roots);
		if ($root_count == 0)
			return('<span style="color: #f00">' . $word .  '</span>');
		else
		{
			$i = 0; unset($components);
			foreach ($roots as $lemma => $attrib)
			{
				$i++;
				$affixes = $attrib['affixes'];
				$url = sprintf($url_template, $lemma);
				$lemma_url = sprintf('<a href="%s">%s</a>', $url, $lemma);
				$components .= $components ? '; ' : '';

				// Return word with link only when baseword, else ...
				if ($word == $lemma && $root_count == 1)
					$components .= $lemma_url;
				else
				{
					if ($i == 1) $components .= $word . ': ';
					if ($root_count > 1) $components .= "({$i}) ";
					if (is_array($attrib['prefixes']))
						$components .= implode('', $attrib['prefixes']);
					$components .= $lemma_url;
					if (is_array($attrib['suffixes']))
						$components .= implode('', $attrib['suffixes']);
				}
			}
			return($components);
		}
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
				$this->add_root($roots, $pattern);
			}
		}
	}

	/**
	 *
	 */
	function find_prefix(&$roots)
	{
		$_V = 'a|i|u|e|o'; // vowels
		$_C = 'b|c|d|f|g|h|j|k|l|m|n|p|q|r|s|t|v|w|x|y|z'; // consonants
		$_A = $_V . '|' . $_C; // any char

		$rules = array(
			array("(di)({$_A})(.+)", ""), // 0
			array("(ke)({$_A})(.+)", ""), // 0
			array("(se)({$_A})(.+)", ""), // 0
			array("(be)(r)({$_V})(.+)", ""), // 1
			array("(ber)({$_A})(.+)", ""), // 1
			array("(be)({$_C})({$_A})(er)(.+)", ""), // 3
			array("(te)(r)({$_V})(.+)", ""), // 6
			array("(ter)({$_A})(.+)", ""), // 6
			array("(ter)({$_C})(er)({$_V})(.+)", ""), // 7
			array("(ter)({$_C})({$_A})(.+)", ""), // 8
			array("(me)(l|m|n|r|w|y)(.+)", ""), // 10
			array("(mem)(b|f|v)(.+)", ""), // 11
			array("(mem)({$_V})(.+)", "p"), // 13
			array("(mem)(p)({$_C})(.+)", ""), // p + consonant: memproklamasikan
			array("(mempe)(r)({$_V})(.+)", ""), // 21
			array("(memper)({$_A})(.+)", ""), // 21
			array("(mempel)({$_A})(.+)", ""), // 21
			array("(men)(c|d|j|z)(.+)", ""), // 14
			array("(men)({$_V})(.+)", "t"), // 15
			array("(men)(t)({$_C})(.+)", ""), // t + consonant: mentransmisikan
			array("(meng)(g|h|q|x)(.+)", ""), // 16
			array("(meng)({$_V})(.+)", ""), // 17 - Start with vocal
			array("(meng)({$_V})(.+)", "k"), // 17
			array("(meng)(k)({$_C})(.+)", ""), // k + consonant: mengkristalkan
			array("(menge)({$_C})(.+)", ""), // swarabakti
			array("(meny)({$_V})(.+)", "s"), // 18
			array("(men)(s)({$_C})(.+)", ""), // s + consonant: mensyaratkan
			array("(pe)({$_A})(.+)", ""), // 20
			array("(per)({$_A})(.+)", ""), // 21
			array("(pel)({$_A})(.+)", ""), // 21
			//array("(per)({$_V})(.+)", ""), // 21 - Disambig
			//array("(per)({$_C})(.+)", ""), // 22
			array("(pe)(l|m|n|r|w|y)(.+)", ""), // 20
			array("(pem)(b|f|v)(.+)", ""), // 25
			array("(pem)({$_V})(.+)", "p"), // 26
			array("(pem)({$_C})(.+)", "p"), // pemrogram
			array("(pen)(c|d|j|z)(.+)", ""), // 27
			array("(pen)({$_V})(.+)", "t"), // 28
			array("(pen)(t)({$_C})(.+)", ""), // t + consonant: pentransmisian
			array("(pen)(s)({$_C})(.+)", ""), // s + consonant: pensyaratan
			array("(peng)(g|h|q|x)(.+)", ""), // 29
			array("(peng)({$_V})(.+)", ""), // 30
			array("(peng)({$_V})(.+)", "k"), // 30
			array("(peng)(k)({$_C})(.+)", ""), // k + consonant: pengkristalan
			array("(penge)({$_C})(.+)", ""), // swarabakti
			array("(peny)({$_V})(.+)", "s"), // 31
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
				$affixes = $attrib['affixes'];

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

				// Asian (2005): Only one instance allowed.
				// Not valid for "seseorang": COMMENT OUT
				//if (is_array($affixes))
				//	if (in_array($new_affix, $affixes))
				//		continue;

				// Put into array and merge with existing affixes
				$new_affix = array($new_affix);
				if (is_array($affixes))
					$new_affix = array_merge($affixes, $new_affix);

				// Push
				$roots[$new_lemma] = array('affixes' => $new_affix);
			}
		}
	}

}