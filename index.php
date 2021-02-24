<?php
	// https://notapercent.herokuapp.com/?id=81478&cb=ffffff&ct=000000&s=20&f=arial&p=2
	// https://github.com/Geograph-us/notapercent

	require 'vendor/autoload.php';

	$dir_fonts = __DIR__ . '/fonts/';
	$dir_tmp = './tmp/';
	$ttl = 1 * 60 * 60; // seconds

	$available_fonts = array_map(function ($x) { return pathinfo($x, PATHINFO_FILENAME); }, glob($dir_fonts . "*.ttf"));

	$id = isset($_GET["id"]) ? intval($_GET["id"]) : -1; // book id from notabenoid
	$cb = !empty($_GET["cb"]) ? strtolower($_GET["cb"]) : "fff"; // color backgroud (random)
	$ct = !empty($_GET["ct"]) ? strtolower($_GET["ct"]) : "000"; // color text (random)
	$s = !empty($_GET["s"]) ? $_GET["s"] : 14; // size (random or range 14-50)
	$p = isset($_GET["p"]) ? intval($_GET["p"]) : 3; // padding
	$f = !empty($_GET["f"]) ? $_GET["f"] : "arial"; // fonts: arial,calibri,cambria,candara,comic,consolas,courier,georgia,segoe,times (random or list arial,segoe,times)
	$t = isset($_GET["t"]) ? intval($_GET["t"]) : -1; // transparent
	$m = isset($_GET["m"]) ? intval($_GET["m"]) : 0; // mode
	$r = isset($_GET["r"]) ? intval($_GET["r"]) : 0; // refresh

	if (($cb == "fff" || $cb == "ffffff") && $t === -1) $t = 1;

	if ($id > -1)
	{
		//$redis = new Predis\Client(getenv('MYREDISCLOUDURL'));
		$redis = new Predis\Client(getenv('REDISCLOUD_URL'));
		error_log($id);
		@chmod($dir_tmp, 0777);
		$percent = $m === 1 ? get_percentage_bookmark($id) : get_percentage($id);
		if (!$percent) $percent = "err";
		error_log($percent);

		if (stripos($f, ",")) $f = array_rand(array_flip(array_map("trim", explode(",", $f))));
		elseif (strtolower(trim($f)) == "random") $f = rand_font();

		if (strtolower(trim($ct)) == "random") $ct = rand_color();
		if (strtolower(trim($cb)) == "random") $cb = rand_color($ct, 3.0);

		if (stripos($s, "-"))
		{
			$s = array_map("intval", array_map("trim", explode("-", $s, 2)));
			$s = rand($s[0], $s[1]);
		}
		elseif (strtolower(trim($s)) == "random") $s = rand(12, 20);

		text2image($percent, 40, $f, $s, 0, $p, $t === 1, $ct, $cb);
		exit();
	}

	for ($i = 0; $i <= 4; $i ++) $c[] = rand_color();

	$variants = [
		"/?id=81478",
		"/?id=81478&cb=" . rand_color($c[0], 4.5) . "&ct=" . $c[0] . "&s=" . rand(10, 30) . "&f=" . rand_font(),
		"/?id=81478&cb=random&ct=random&s=10-30&f=random",
		"/?id=111&cb=fff&ct=000&s=" . rand(10, 30) . "&f=" . rand_font(),
		"/?id=1111&cb=" . rand_color($c[1], 4.5) . "&ct=" . $c[1] . "&s=" . rand(10, 30) . "&f=" . rand_font(),
		"/?id=11111&ct=" . rand_color("ffffff", 4.5) . "&s=" . rand(10, 30) . "&f=" . rand_font(),
		"/?id=1337&cb=" . rand_color($c[2], 4.5) . "&ct=" . $c[2] . "&s=" . rand(10, 30) . "&f=" . rand_font() . "&t=1&m=1",
		"/?id=31337&cb=" . rand_color($c[3], 4.5) . "&ct=" . $c[3] . "&s=" . rand(10, 30) . "&f=" . rand_font() . "&t=0",
		"/?id=33777&cb=" . rand_color($c[4], 4.5) . "&ct=" . $c[4] . "&s=" . rand(10, 30) . "&f=" . rand_font(),
		"/?id=59633&cb=" . rand_color("ffffff", 4.5) . "&ct=fff&s=" . rand(10, 30) . "&f=segoe,harlow,viner,showcard&p=8&m=1",
	];

	echo <<<HTML
<h2>Notabenoid Percentage</h2><br />

HTML;

	//$book_url = "http://notabenoid.org/book/";
	$book_url = "https://opennota2.duckdns.org/book/";
	foreach ($variants as $v)
	{
		if (!preg_match('~\d+~', $v, $m)) continue;
		echo "<a target='_blank' href='${book_url}${m[0]}'><img src='${v}' /></a> \r\n";
	}
	echo "<br /><p><code>Available fonts: " . join(', ', $available_fonts) . "</code></p>\r\n";
	echo "<br /><p><a target='_blank' href='https://github.com/Geograph-us/notapercent'>https://github.com/Geograph-us/notapercent</a>\r\n";

	function rand_font()
	{
		global $available_fonts;
		return array_rand(array_flip($available_fonts));
	}

	function rand_color($fg = null, $min_ratio = 3.0)
	{
		if ($fg == null) return sprintf('%06x', rand(0, 0xFFFFFF));

		do $bg = sprintf('%06x', rand(0, 0xFFFFFF));
		while (calculate_ratio($fg, $bg) < $min_ratio);

		return $bg;
	}

	function calculate_ratio($foreground, $background)
	{
		// good Ratio 3.0-4.5
		if (!$foreground || !$background) return;
		$fgLuminance = luminance($foreground);
		$bgLuminance = luminance($background);

		return round((max($fgLuminance, $bgLuminance) + 0.05) / (min($fgLuminance, $bgLuminance) + 0.05) * 10) / 10;
	}

	function luminance($color)
	{
		// Get decimal sRGB values
		[$rSrgb, $gSrgb, $bSrgb] = array_map(function ($c) { return hexdec(str_pad($c, 2, $c)) / 255; }, str_split($color, 2));

		// Calculate luminance
		$r = ($rSrgb <= 0.03928) ? $rSrgb / 12.92 : pow((($rSrgb + 0.055) / 1.055), 2.4);
		$g = ($gSrgb <= 0.03928) ? $gSrgb / 12.92 : pow((($gSrgb + 0.055) / 1.055), 2.4);
		$b = ($bSrgb <= 0.03928) ? $bSrgb / 12.92 : pow((($bSrgb + 0.055) / 1.055), 2.4);

		return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
	}

	function text2image($text, $newline_after_letters=40, $font='Arial', $size=24, $rotate=0, $padding=2, $transparent=true, $color="000", $bg_color="fff")
	{
		global $dir_fonts;
		$high_quality = true; // TrueColor - 2x size (better view for tranparent image)

		$amount_of_lines = ceil(strlen($text) / $newline_after_letters) + substr_count($text, '\n') + 1;
		$all_lines = explode("\n", $text); $text=""; $amount_of_lines = count($all_lines);
		$text_final = "";
		foreach($all_lines as $key => $value)
		{
			while(mb_strlen($value,'utf-8') > $newline_after_letters)
			{
				$text_final .= mb_substr($value, 0, $newline_after_letters, 'utf-8') . "\n";
				$value = mb_substr($value, $newline_after_letters, null, 'utf-8');
			}
			$text .= mb_substr($value, 0, $newline_after_letters, 'utf-8') . ($amount_of_lines - 1 == $key ? "" : "\n");
		}

		header("Content-type: image/png");
		$width = $height = $offset_x = $offset_y = 0;
		$font = strtolower($font);
		if (!is_file($dir_fonts . $font . '.ttf')) $font = 'arial';
		$font = $dir_fonts . $font . '.ttf';

		// get the font height.
		$bounds = imagettfbbox($size, $rotate, $font, "W");
		if ($rotate < 0) $font_height = abs($bounds[7] - $bounds[1]);
		elseif ($rotate > 0) $font_height = abs($bounds[1] - $bounds[7]);
		else $font_height = abs($bounds[7] - $bounds[1]);

		// determine bounding box.
		$bounds = imagettfbbox($size, $rotate, $font, $text);
		if ($rotate < 0)
		{
			$width = abs($bounds[4] - $bounds[0]); $height = abs($bounds[3] - $bounds[7]);
			$offset_y = $font_height; $offset_x = 0;
		}
		elseif ($rotate > 0)
		{
			$width = abs($bounds[2] - $bounds[6]); $height = abs($bounds[1] - $bounds[5]);
			$offset_y = abs($bounds[7] - $bounds[5]) + $font_height; $offset_x = abs($bounds[0] - $bounds[6]);
		}
		else
		{
			$width = abs($bounds[4] - $bounds[6]); $height = abs($bounds[7] - $bounds[1]);
			$offset_y = $font_height; $offset_x = 0;
		}

		$image = $high_quality ? imagecreatetruecolor($width + ($padding * 2) + 1, $height + ($padding * 2) + 1) :
								 imagecreate($width + ($padding * 2) + 1, $height + ($padding * 2) + 1);

		[$r, $g, $b] = array_map(function ($c) { return hexdec(str_pad($c, 2, $c)); }, str_split($bg_color, strlen($bg_color) > 4 ? 2 : 1));
		$background = $high_quality ? imagecolorallocatealpha($image, $r, $g, $b, $transparent ? 127 : 0) :
									  imagecolorallocate($image, $r, $g, $b);
		[$r, $g, $b] = array_map(function ($c) { return hexdec(str_pad($c, 2, $c)); }, str_split($color, strlen($color) > 4 ? 2 : 1));
		$foreground = imagecolorallocate($image, $r, $g, $b);

		if ($transparent === true) imagecolortransparent($image, $background);
		imageinterlace($image, true);
		if ($high_quality) imagefill($image, 0, 0, $background);

		imagettftext($image, $size, $rotate, $offset_x + $padding, $offset_y + $padding, $foreground, $font, $text);
		imagealphablending($image, true);
		imagesavealpha($image, true);

		imagepng($image);
	}

	function get_percentage($id)
	{
		global $r, $redis, $dir_tmp, $ttl;

		$url = "http://notabenoid.org/";
		$user = getenv('USERNAME');
		$password = getenv('PASSWORD');
		$data = "login[login]=$user&login[pass]=$password";

		$cookies = $dir_tmp . ".cookies";
		if (!is_file($cookies) || filesize($cookies) < 1)
		{
			if ($redis->exists("cookies"))
			{
				file_put_contents($cookies, $redis->get("cookies"));
			}
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
	  	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
	  	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
	  	//curl_setopt($ch, CURLOPT_PROXY, "http://127.0.0.1:8888");

	  	if ($r !== 1 && $redis->exists($id))
	  	{
	  		error_log("Exists $id");
	  		return $redis->get($id);
	  	}

		$need_login = false;
		if (is_file($cookies) && filesize($cookies) > 1)
		{
			error_log("Cookie exists");
			curl_setopt($ch, CURLOPT_URL, $url . "book/${id}");
			$page = curl_exec($ch);
			if (curl_errno($ch) != CURLE_OK) return "err1";
			if (stripos($page, "200 OK\r\n") === false) $need_login = true;
		}
		else $need_login = true;

		if ($need_login)
		{
			error_log("Login");
			curl_setopt($ch, CURLOPT_POST, 1);
		  	curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			$page = curl_exec($ch);
			if (curl_errno($ch) != CURLE_OK || empty($page)) return "err2";
			$redis->set("cookies", file_get_contents($cookies));

			curl_setopt($ch, CURLOPT_POST, 0);
			curl_setopt($ch, CURLOPT_URL, $url . "book/${id}");
			$page = curl_exec($ch);
			if (curl_errno($ch) != CURLE_OK || empty($page)) return "err3";
			if (stripos($page, "200 OK\r\n") === false) return "err4";
		}
		curl_close($ch);

		if (!preg_match("~<div class='text'>(.*?)</span>~", $page, $m)) return "err5";
		if (!preg_match("~(\d+\.\d+%)~", $m[1], $m)) $percent = "0.00%";
		else $percent = $m[1];
		$redis->setex($id, $ttl, $percent);

		return $percent;
	}

	function get_percentage_bookmark($id)
	{
		global $r, $redis, $dir_tmp, $ttl;

		$url = "http://notabenoid.org/";
		$user = getenv('USERNAME');
		$password = getenv('PASSWORD');
		$data = "login[login]=$user&login[pass]=$password";
		$percent = "0%";

		$cookies = $dir_tmp . ".cookies";
		if (!is_file($cookies) || filesize($cookies) < 1)
		{
			if ($redis->exists("cookies"))
			{
				file_put_contents($cookies, $redis->get("cookies"));
			}
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
	  	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
	  	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
	  	//curl_setopt($ch, CURLOPT_PROXY, "http://127.0.0.1:8888");

	  	curl_setopt($ch, CURLOPT_POST, 1);
	  	if ($r !== 1 && $redis->exists($id))
	  	{
	  		error_log("Exists $id");
	  		return $redis->get($id);
	  	}

		$need_login = false;
		if (is_file($cookies) && filesize($cookies) > 1)
		{
			error_log("Cookie exists");
			curl_setopt($ch, CURLOPT_URL, $url . "my/bookmarks/set");
			curl_setopt($ch, CURLOPT_POSTFIELDS, "book_id=${id}&orig_id=&note=");
			curl_exec($ch);
			if (curl_errno($ch) != CURLE_OK) return "err1";
			if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) $need_login = true;
		}
		else $need_login = true;

		if ($need_login)
		{
			error_log("Login");
		  	curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			curl_exec($ch);
			if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 302) return "err2";
			$redis->set("cookies", file_get_contents($cookies));

			curl_setopt($ch, CURLOPT_URL, $url . "my/bookmarks/set");
			curl_setopt($ch, CURLOPT_POSTFIELDS, "book_id=${id}&orig_id=&note=");
			curl_exec($ch);
			if (curl_errno($ch) != CURLE_OK) return "err3";
		}

		curl_setopt($ch, CURLOPT_POST, 0);
		curl_setopt($ch, CURLOPT_URL, $url . "my/bookmarks/data");
		$page = curl_exec($ch);
		if (curl_errno($ch) != CURLE_OK) return "err4";

		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_URL, $url . "my/bookmarks/remove");
		curl_setopt($ch, CURLOPT_POSTFIELDS, "book_id=${id}&orig_id=0");
		curl_exec($ch);
		if (curl_errno($ch) != CURLE_OK) return "err5";
		curl_close($ch);

		$json = json_decode($page, true);
		foreach ($json as $js)
		{
			if ($js["book"]["id"] === $id)
			{
				if (!preg_match("~(\d+\.\d+%)~", $js["book"]["ready"], $m)) $percent = "0.00%";
				else $percent = $m[1];
				$redis->setex($id, $ttl, $percent);
				return $percent;
			}
		}

		return "err6";
	}
?>