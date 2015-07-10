<?
	require 'byte_code.php';
	require 'exceptions.php';
	require 'util.php';
	require 'tokenizer.php';
	require 'parse_tree_nodes.php';
	require 'parser.php';
	require 'byte_code_serializer.php';
	
	function compile($rs_url) {
		try {
			$path = canonicalize_path('/'.$rs_url, false);
			if (file_exists('..'.$path)) {
				$code = file_get_contents('..'.$path);
				$tokens = new TokenStream($rs_url, $code);
				$parser = new Parser();
				$parse_tree = $parser->parse($tokens);
				
				$resolved_parse_tree = array();
				foreach ($parse_tree as $executable) {
					$resolved_executable = $executable->resolve();
					array_push($resolved_parse_tree, $resolved_executable);
				}
				
				$serializer = new ByteCodeSerializer();
				$structured_byte_code = $serializer->serialize_parse_tree($resolved_parse_tree);
				$byte_code_for_database = convert_to_storage_format($structured_byte_code);
				return $byte_code_for_database;
			} else {
				throw new RedstoneCompilerException(null, 'Source file does not exist: ' . $path);
			}
		} catch (RedstoneCompilerException $exception) {
			return $exception->message;
		}
	}
	
	$ALPHA_NUMS = array();
	$t = 'abcdefghijklmnopqrstuvwxyz';
	for ($i = 0; $i < strlen($t); ++$i) {
		$ALPHA_NUMS[strtoupper($t[$i])] = true;
		$ALPHA_NUMS[$t[$i]] = true;
	}
	for ($i = 0; $i < 10; ++$i) {
		$ALPHA_NUMS[''.$i] = true;
	}
	
	function convert_to_storage_format($byte_code) {
		global $ALPHA_NUMS;
		$output = array();
		foreach ($byte_code as $structured_row) {
			// This encoding is quick, simple, and bloated. I will make it Fancy later.
			$int_values = $structured_row->int_values;
			$string_value = $structured_row->string_value;
			$string_suffix = '';
			if ($string_value != null) {
				$utf8_string = convert_php_string_to_utf8_char_array($string_value);
				$string_builder = array();
				for ($i = 0; $i < count($utf8_string); ++$i) {
					$c = $string_value[$i];
					if (strlen($c) == 1) {
						if ($ALPHA_NUMS[$c]) {
							array_push($string_builder, $c);
						} else {
							array_push($string_builder, '#' . convert_int_to_2_digit_hex(ord($c)));
						}
					} else {
						array_push($string_builder, '%');
						for ($j = 0; $j < strlen($c); ++$j) {
							array_push($string_builder, convert_int_to_2_digit_hex(ord($c[$j])));
						}
						array_push($string_builder, ';');
					}
				}
				$string_suffix = ':'.implode('', $string_builder);
			}
			$row = implode('|', $int_values).$string_suffix;
			
			array_push($output, $row);
		}
		return implode(',', $output);
	}
	
	$output = compile($_GET['rs_url']);
	if ($output === null) $output = "Built successfully";
	
	echo '<pre>';
	echo htmlspecialchars($output);
	echo '</pre>';
	
?>