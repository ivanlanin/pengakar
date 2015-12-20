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
error_reporting(E_ALL & ~E_NOTICE);
require_once 'src/pengakar.php';

// Parameters
$pengakar = new \Kateglo\Pengakar();
$url = $_GET['url'] ? $_GET['url'] : $_POST['url'];
if ($url) {
    $q = $pengakar->getContent($url);
} else {
    $q = stripslashes($_GET['q'] ? $_GET['q'] : $_POST['q']); // Suhosin?
}
// Process API
if (isset($_GET['api'])) {
    die($pengakar->getApi($q));
}
// Process HTML
if ($q) {
    $result .= '<h3>Daftar kata</h3>';
    $result .= $pengakar->getHtml($q);
}
// Form
$form .= '<form action="./" method="post" style="margin-bottom: 20px;">';
$form .= 'URL:<br /><input type="text" id="url" name="url" value="' . $url . '" />';
$form .= 'Teks:<br /><textarea id="q" name="q">' . $q . '</textarea>';
$form .= '<br />';
$form .= '<input type="submit" value="Proses" />';
$form .= '</form>';
// HTML
$ret = $form . $result;
$info = 'Seret dan lepaskan tautan ini ke bilah markah peramban untuk membuat ' .
    'bookmarklet yang dapat dipakai untuk menjalankan pengakar untuk ' .
    'menganalisis isi situs yang sedang Anda kunjungi.';
$bookmarklet = 'javascript:(function(){window.open(\'' .
    'http://' . $_SERVER['SERVER_NAME'] . '/pengakar/' .
    '?url=\'+encodeURIComponent(location.href));})();';
?>
<html>
<head>
<title>Pengakar</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<style>
a { text-decoration: none; }
hr { margin-top: 20px; height: 1px; border-width:0; background: #999; }
.instance { color:#666; font-style:italic; font-size:80%; }
.wclass { color:#666; font-style:italic; font-size:80%; }
.notfound { color:#f00; }
#url { width:100%; }
#q { width:100%; height:200px; }
</style>
<body>
<h2><a href="./">Pengakar</a></h2>

<p><a title="<?php echo($info); ?>" href="<?php echo($bookmarklet); ?>">Pengakar</a>
(<em><a href="http://en.wikipedia.org/wiki/Stemming">stemmer</a></em>)
adalah program pencari akar kata bahasa Indonesia. Program ini dibuat dengan
menyempurnakan beberapa algoritme yang diperoleh dari berbagai sumber, terutama
artikel "<a href="http://dl.acm.org/citation.cfm?id=1082195">Stemming Indonesian</a>"
(Asian, 2005). Tentu saja program ini masih terus disempurnakan dan, karena itu,
mohon bantuan untuk melaporkan kesalahan kepada
<a href="http://twitter.com/ivanlanin">@ivanlanin</a>. Terima kasih.</p>

<p>Masukkan alamat laman web pada kotak "URL" atau teks pada kotak "teks".
Klik "Proses" dan pengakar akan berupaya mencari akar kata tersebut.
Warna <span style="color:#f00">merah</span> berarti kata tersebut tidak ditemukan
di dalam leksikon dan diletakkan paling atas dalam daftar.
Daftar kata diurutkan berdasarkan jumlah kemunculan.</p>
<?php echo($ret); ?>

<hr />
<a href="./README.TXT">README</a> |
<a href="./?api=1">API</a> |
<a href="https://github.com/ivanlanin/pengakar">Kode</a>
(<a href="http://www.gnu.org/licenses/gpl.html">GPL</a>) |
<a href="./kamus.txt">Leksikon</a>
(<a href="http://creativecommons.org/licenses/by-nc/3.0/">BY-NC</a>)
</body>
</html>
