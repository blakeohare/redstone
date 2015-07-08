<?
	class RedstoneCompileException extends Exception {
		private $token;
		
		public function __construct($token, $message, $code = 0, Exception $previous = null) {
			parent::__construct($message, $code, $previous);
			$this->token = $token;
		}
		
		public function __toString() {
			return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
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