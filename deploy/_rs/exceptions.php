<?
	
	class RedstoneCompileException extends Exception {
		private $token;
		
		public function __construct($token, $message, $code = 0, Exception $previous = null) {
			parent::__construct($message, $code, $previous);
			$this->token = $token;
		}
		
		public function __toString() {
			$header = "Compilation failed!";
			$message = $this->message;
			
			if ($this->token == null) {
				$output = array($header, $message);
			} else {
				$lines = explode("\n", $this->token->file_contents);
				$line = $lines[$this->token->line];
				$pointer = '';
				for ($i = 0; $i < $this->token->col; ++$i) {
					$pointer .= ' ';
				}
				$pointer .= '^';
				$output = array(
					$header, 
					$this->token->file . ' Line: ' . ($this->token->line + 1) . ', Col: ' . ($this->token->col + 1),
					$line,
					$pointer,
					$message);
			}
			
			return implode("\n", $output);
		}
	}
	
	class RedstoneRuntimeException extends Exception {
		private $stack;
		
		public function __construct($stack, $message, $code = 0, Exception $previous = null) {
			parent::__construct($message, $code, $previous);
			$this->stack = $stack;
		}
		
		public function __toString() {
			return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
		}
	}
?>