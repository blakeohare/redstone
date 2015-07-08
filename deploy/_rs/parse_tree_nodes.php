<?
	abstract class Expression {
		public $first_token;
		
		public function __construct($first_token) {
			$this->first_token = $first_token;
		}
		
		public abstract function resolve();
	}
	
	class Annotation extends Expression {
		public $name;
		public $args;
		
		public function __construct($token, $args) {
			parent::__construct($token);
			$this->name = substr($token->value, 1);
			$this->args = $args;
		}
		
		public function resolve() {
			for ($i = 0; $i < count($args); ++$i) {
				$args[$i] = $args[$i]->resolve();
			}
			
			return $this;
		}
	}
	
	class Variable extends Expression {
		public $name;
		
		public function __construct($token, $name = null) {
			parent::__construct($token);
			if ($name === null) {
				$name = $token->value;
			}
			$this->name = $name;
		}
		
		public function resolve() {
			return $this;
		}
	}
?>