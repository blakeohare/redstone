<?
	function canonicalize_path($path, $allow_upwalk=true) {
		while (strpos($path, '//') !== false) {
			$path = str_replace('//', '/', $path);
		}
		$upwalk = 0;
		$is_absolute = $path[0] == '/';
		if ($is_absolute) {
			$path = substr($path, 1);
		}
		$parts = explode('/', $path);
		$builder = array();
		foreach ($parts as $part) {
			if ($part == '.' && $allow_upwalk) {
				// do nothing
			} else if ($part == '..' && $allow_upwalk) {
				if (count($builder) == 0) {
					$upwalk++;
				} else {
					array_pop($builder);
				}
			} else {
				array_push($builder, $part);
			}
		}
		
		if ($is_absolute && $upwalk > 0) return null;
		$path = implode('/', $builder);
		if ($is_absolute) {
			$path = '/'.$path;
		} else {
			while ($upwalk-- > 0) {
				$path = '../'.$path;
			}
		}
		return $path;
	}
	
	function convert_php_string_to_utf8_char_array($string) {
		$output = array();
		// TODO: strip out BOM
		for ($i = 0; $i < strlen($string); ++$i) {
			$c = $string[$i];
			$value = ord($c);
			
			if (($value & 0x80) != 0) {
				if (($value & 0xE0) == 0xC0) {
					$extra_byte_count = 1;
				} else if (($value & 0xF0) == 0xE0) {
					$extra_byte_count = 2;
				} else if (($value & 0xF8) == 0xF0) {
					$extra_byte_count = 3;
				} else if (($value & 0xFC) == 0xF8) {
					$extra_byte_count = 4;
				} else if (($vlaue & 0xFE) == 0xFC) {
					$extra_byte_count = 5;
				}
				
				while ($extra_byte_count-- > 0) {
					$c .= $string[++$i];
				}
			}
			array_push($output, $c);
		}
		
		return $output;
	}
	
	$t = 'abcdefghijklmnopqrstuvwxyz';
	$t .= strtoupper($t);
	$t .= '0123456789_';
	$_VALID_IDENTIFIER_CHARS = array();
	for ($i = 0; $i < strlen($t); ++$i) {
		$_VALID_IDENTIFIER_CHARS[$t[$i]] = true;
	}
	
	function is_valid_identifier($string) {
		global $_VALID_IDENTIFIER_CHARS;
		
		if (strlen($string) == 0) return false;
		
		for ($i = 0; $i < strlen($string); ++$i) {
			if (!$_VALID_IDENTIFIER_CHARS[$string[$i]]) {
				return false;
			}
		}
		
		$first = ord($string[0]);
		
		if ($first >= ord('0') && $first <= ord('9')) return false;
		
		return true;
	}
	
	function convert_int_to_2_digit_hex($num) {
		$hex = '0123456789ABCDEF';
		return $hex[($num >> 4) & 15] . $hex[$num & 15];
	}
	
?>