<?
	$tokens = explode(' ', "++ -- += -= *= /= %= &= |= ^= == != <= >= << >> <<= >>= >>> ** && || **=");
	$TOKENIZER_MULTICHAR_TOKENS = array();
	foreach ($tokens as $token) {
		$TOKENIZER_MULTICHAR_TOKENS[$token] = true;
	}
	
	class Token {
		public $value;
		public $col;
		public $line;
		public $file;
		public $file_contents;
		public $unicode_chars;
		
		public function __construct($value, $col, $line, $file, $file_contents) {
			$this->value = $value;
			$this->col = $col;
			$this->line = $line;
			$this->file = $file;
			$this->file_contents = $file_contents;
		}
	}
	
	class TokenStream {
		private $tokens;
		private $index;
		private $length;
		
		public function __construct($file, $file_contents) {
			global $TOKENIZER_MULTICHAR_TOKENS;
			
			$file_contents = str_replace("\r\n", "\n", $file_contents);
			$file_contents = str_replace("\r", "\n", $file_contents);
			$this->file = $file;
			$this->contents = $file_contents;
			$file_contents .= "\0";
			$length = strlen($file_contents);
			
			$this->tokens = array();
			
			$lines = array();
			$cols = array();
			$line = 0;
			$col = 0;
			for ($i = 0; $i < $length; ++$i) {
				array_push($lines, $line);
				array_push($cols, $col);
				$c = $file_contents[$i];
				if ($c == "\0" && $i < $length - 1) {
					// TODO: error
				}
				
				if ($c == "\n") {
					$col = 0;
					$line++;
				} else {
					$col++;
				}
			}
			
			$token_start = null;
			$mode = 'none';
			$comment_type = null;
			$string_type = null;
			
			$ord_a = ord('a');
			$ord_z = ord('z');
			$ord_A = ord('A');
			$ord_Z = ord('Z');
			$ord_0 = ord('0');
			$ord_9 = ord('9');
			
			$clean_exit = false;
			
			for ($i = 0; $i < $length; ++$i) {
				$c = $file_contents[$i];
				$c2 = substr($file_contents, $i, 2);
				$c3 = substr($file_contents, $i, 3);
				switch ($mode) {
					case 'none':
						if ($c == " " || $c == "\t" || $c == "\n") {
							// skip
						} else if ($TOKENIZER_MULTICHAR_TOKENS[$c2]) {
							$this->add_token($c2, $i);
							++$i;
						} else if ($TOKENIZER_MULTICHAR_TOKENS[$c3]) {
							$this->add_token($c3, $i);
							$i += 2;
						} else if ($c == "'" || $c == '"') {
							$mode = 'string';
							$string_type = $c;
							$token_start = $i;
						} else if ($c2 == "//" || $c2 == "/*") {
							$mode == 'comment';
							$comment_type = $c2;
						} else {
							$cvalue = ord($c);
							if (($cvalue >= $ord_a && $cvalue <= $ord_z) ||
								($cvalue >= $ord_A && $cvalue <= $ord_Z) ||
								$c == '_' ||
								$c == '@' ||
								$c2 == '0x') {
								$mode = 'word';
								$token_start = $i;
								--$i;
							} else if ($cvalue >= $ord_0 && $cvalue <= $ord_9) {
								$mode = 'number';
								$token_start = $i;
								--$i;
							} else if ($c == '.' && ord($c2[1]) >= $ord_0 && ord($c2[1]) <= $ord_9) {
								$mode = 'number';
								$token_start = $i;
							} else if ($c == "\0") {
								// EOF
								$clean_exit = true;
							} else {
								$this->add_token($c, $i);
							}
						}
						break;
					case 'word':
						$cvalue = ord($c);
						if (($cvalue >= $ord_a && $cvalue <= $ord_z) ||
							($cvalue >= $ord_A && $cvalue <= $ord_Z) ||
							($cvalue >= $ord_0 && $cvalue <= $ord_9) ||
							$c == '_' ||
							($c == '@' && $i == $token_start)) { // @ is only valid as the first character
							// continue
						} else {
							$mode = 'none';
							$this->add_token(substr($file_contents, $token_start, $i - $token_start), $token_start);
							--$i;
						}
						break;
					case 'string':
						if ($c == "\\") {
							++$i;
						} else if ($c == $string_type) {
							$value = substr($file_contents, $token_start, $i - $token_start + 1);
							$this->add_token($value, $token_start);
							$mode = 'none';
						}
						break;
					case 'comment':
						if ($comment_type == "/*" && $c2 == '*/') {
							++$i;
							$mode = 'none';
						} else if ($comment_type == '//' && $c == "\n" || $c == "\0") {
							$mode = 'none';
							--$i;
						}
						break;
					case 'number':
						$cvalue = ord($c);
						if (($cvalue >= $ord_0 && $cvalue <= $ord_9) ||
							$c == '.' || $c == 'e' || $c == 'E') {
							// continue
						} else {
							$this->add_token(substr($file_contents, $token_start, $i - $token_start), $token_start);
							--$i;
							$mode = 'none';
						}
						break;
				}
			}
			
			if (!$clean_exit) {
				$this->add_token('EOF', strlen($file_contents) - 1);
				$token = $this->tokens[count($this->tokens) - 1];
				throw new CompilerException($token, "Unexpected EOF. Unclosed string or comment.");
			}
			
			$this->length = count($this->tokens);
			$this->index = 0;
		}
		
		private function add_token($value, $token_start_index) {
			array_push($this->tokens, new Token($value, $this->cols[$token_start_index], $this->lines[$token_start_index], $this->file, $this->contents));
		}
		
		public function has_more() {
			return $this->index < $this->length;
		}
		
		public function peek_value() {
			if ($this->index < $this->length) {
				return $this->tokens[$this->index]->value;
			}
			return null;
		}
		
		public function pop() {
			if ($this->index < $this->length) {
				return $this->tokens[$this->index++];
			}
			throw new RedstoneCompileException(null, "Unexpected EOF.");
		}
		
		public function pop_expected($expected_value) {
			if ($this->index < $this->length) {
				$token = $this->tokens[$this->index++];
				if ($token->value == $expected_value) {
					return $token;
				}
				throw new RedstoneCompileException($token, "Expected '" . $value ."', found '" . $token->value . "' instead.");
			}
			throw new RedstoneCompileException(null, "Expected '" . $value . "', found EOF.");
		}
		
		public function pop_if_present($value) {
			if ($this->index < $this->length) {
				$token = $this->tokens[$this->index];
				if ($token->value == $value) {
					$this->index++;
					return true;
				}
			}
			return false;
		}
		
		public function pop_identifier() {
			if ($this->index < $this->length) {
				$token = $this->tokens[$this->index++];
				if (is_valid_identifier($token->value)) {
					return $token;
				}
				throw new RedstoneCompileException($token, "Expected identifier. Found '" . $token->value ."'");
			}
			throw new RedstoneCompileException($token, "Expected identifier. Found EOF.");
		}
		
		public function is_next($value) {
			if ($this->index < $this->length) {
				if ($this->tokens[$this->index]->value == $value) {
					return true;
				}
			}
			return false;
		}
	}
?>