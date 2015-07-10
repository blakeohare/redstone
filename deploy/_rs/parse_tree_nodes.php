<?
	abstract class Expression {
		public $first_token;
		public $is_assignable;
		
		public function __construct($first_token) {
			$this->first_token = $first_token;
			$this->is_assignable = false;
		}
		
		public function resolve() { return $this; }
		
		public function resolve_expressions($list) {
			$output = array();
			foreach ($list as $expression) {
				array_push($output, $expression->resolve());
			}
			return $output;
		}
	}
	
	abstract class Executable {
		public $first_token;
		
		public function __construct($first_token) {
			$this->first_token = $first_token;
		}
		
		public function resolve() { return array($this); }
		
		public function resolve_executables($list) {
			$output = array();
			foreach ($list as $item) {
				$resolved = $item->resolve();
				foreach ($resolved as $resolved_item) {
					array_push($output, $resolved_item);
				}
			}
			return $output;
		}
	}

	////////////////////////////////////////////////////////////////
	
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
	
	class Assignment extends Executable {
		public $target;
		public $value;
		public $op_token;
		public $op;
		
		public function __construct($target, $op_token, $value) {
			parent::__construct($target->first_token);
			$this->target = $target;
			$this->value = $vaule;
			$this->op_token = $op_token;
			$this->op = $op_token->value;
		}
		
		public function resolve() {
			$this->target = $this->target->resolve();
			if (!$this->target->is_assignable) {
				throw new RedstoneCompilerException($this->target->first_token, "Cannot assign to this type of expression.");
			}
			$this->value = $this->value->resolve();
			return array($this);
		}
	}
	
	class BinaryOperations extends Expression {
		public $expressions;
		public $ops;
		
		public function __construct($expressions, $ops) {
			$this->expressions = $expressions;
			$this->ops = $ops;
		}
		
		public function resolve() {
			$this->expressions = $this->resolve_expressions($this->expressions);
			// TODO: run this through the op interpreter if literals
			return $this;
		}
	}
	
	class ExpressionAsExecutable extends Executable {
		public $expression;
		
		public function __construct($expression) {
			parent::__construct($expression->first_token);
			$this->expression = $expression;
		}
		
		public function resolve() {
			$this->expression = $this->expression->resolve();
			return array($this);
		}
	}
	
	class ForStatement extends Executable {
		public $init;
		public $condition;
		public $step;
		public $body;
		
		public function __construct($for_token, $init, $condition, $step, $body) {
			parent::__construct($for_token);
			$this->init = $init;
			$this->condition = $condition;
			$this->step = $step;
			$this->body = $body;
		}
		
		public function resolve() {
			$this->init = $this->resolve_executables($this->init);
			$this->condition = $this->condition == null
				? new BooleanLiteral($this->first_token, true)
				: $this->condition->resolve();
			$this->step = $this->resolve_executables($this->step);
			$this->body = $this->resolve_executables($this->body);
			if ($this->condition instanceof BooleanLiteral && !$this->condition->value) {
				return $this->init;
			}
			return array($this);
		}
	}
	
	class FunctionDefinition extends Executable {
		
		public function __construct($function_token, $name_token, $arg_names, $arg_values, $annotations, $body) {
			parent::__construct($function_token);
			$this->name_token = $name_token;
			$this->name = $name_token->value;
			$this->arg_name_tokens = $arg_name_tokens;
			$this->arg_names = array();
			$this->arg_values = $arg_values;
			$this->annotations = $annotations;
			$duplicate_check = array();
			for ($i = 0; $i < count($this->arg_name_tokens); ++$i) {
				$token = $this->arg_name_tokens[$i];
				$name = $token->value;
				if ($duplicate_check[$name]) 
					throw new RedstoneCompilerException($token, "Duplicate arg name for function: '" . $name . "'");
				array_push($this->arg_names, $name);
				$duplicate_check[$name] = true;
			}
			$this->body = $body;
		}
		
		public function resolve() {
			$this->body = $this->resolve_executables($this->body);
			return array($this);
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
	
	class Increment extends Expression {
		public $root;
		public $op_token;
		public $is_prefix;
		
		public function __construct($op_token, $root, $is_prefix) {
			parent::__construct($is_prefix ? $op_token : $root->first_token);
			$this->root = $root;
			$this->op_token = $op_token;
			$this->is_prefix = $is_prefix;
		}
		
		public function resolve() {
			$root = $this->root->resolve();
			if (!$root->is_assignable) {
				throw new RedstoneCompilerException($this->root->first_token, "Cannot use " . $this->op_token->value . " on this type of expression.");
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
			$this->is_assignable = true;
			if ($name === null) {
				$name = $token->value;
			}
			$this->name = $name;
		}
	}
?>