<?
	abstract class Expression {
		public $first_token;
		
		public function __construct($first_token) {
			$this->first_token = $first_token;
		}
		
		public function resolve() { return $this; }
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
	
	class FunctionInvocation extends Expression {
		public $arg_names;
		public $arg_values;
		public $root;
		public $paren_token;
		
		public function __construct($root, $paren_token, $arg_names, $arg_values) {
			parent::__construct($root->first_token);
			$this->root = $root;
			$this->paren_token = $paren_token;
			$this->arg_names = $arg_names;
			$this->arg_values = $arg_values;
		}
		
		public function resolve() {
			for ($i = 0; $i < count($arg_values); ++$i) {
				$arg_values[$i] = $arg_values[$i]->resolve();
			}
			return $this;
		}
	}
	
	class IntegerLiteral extends Expression {
		public $value;
		
		public function __construct($token, $value = null) {
			parent::__construct($token);
			if ($value === null) $value = intval($token->value);
			$this->value = $value;
		}
	}
	
	class StringLiteral extends Expression {
		public $value;
		
		public function __construct($token, $value = null) {
			parent::__construct($token);
			if ($value === null) $value = $token->value;
			$this->value = $value;
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
	}
?>