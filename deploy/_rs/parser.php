<?
	class Parser {
		
		private $parser_context;
		
		function __construct($parser_context = null) {
			if ($parser_context === null) {
				$parser_context = this;
			}
			$this->parser_context = $parser_context;
		}
		
		function parse($tokens) {
			$executables = array();
			
			while ($tokens->has_more()) {
				$executable = $this->parse_executable($tokens);
				array_push($executables, $executable);
			}
			
			return $executables;
		}
		
		function parse_annotation($tokens) {
			$annotation = $tokens->pop();
			if ($annotation->value[0] != '@' || !is_valid_identifier(substr($annotation->value, 1))) {
				throw new RedstoneCompileException($annotation, "Expected annotation.");
			}
			
			$args = array();
			
			if ($tokens->pop_if_present('(')) {
				while (!$tokens->pop_if_present(')')) {
					if (count($args) > 0) $tokens->pop_expected(',');
					
					array_push($args, $this->parse_expression($tokens));
				}
			}
			
			return new Annotation($annotation, $args);
		}
		
		
		function parse_executable($tokens, $semicolon_expected = true) {
			$next = $tokens->peek_value();
			$annotations = array();
			while ($next[0] == '@') {
				$annotation = $this->parse_annotation($tokens);
				$next = $tokens->peek_value();
				if ($next[0] == '@') {
					// annotations can be chained. Keep going.
				} else if ($next != 'function' && $next != 'class') {
					throw new RedstoneCompileException($tokens->peek(), "Annotations can only be applied to functions and classes.");
				}
				
				array_push($annotations, $annotation);
			}
			
			switch ($next) {
				case 'break': return $this->parse_break($tokens);
				case 'class': return $this->parse_class($tokens, $annotations);
				case 'continue': return $this->parse_continue($tokens);
				case 'do': return $this->parse_do_while($tokens);
				case 'for': return $this->parse_for($tokens);
				case 'function': return $this->parse_function($tokens, $annotations);
				case 'if': return $this->parse_if($tokens);
				case 'import': return $this->parse_import($tokens);
				case 'return': return $this->parse_return($tokens);
				case 'switch': return $this->parse_switch($tokens);
				case 'throw': return $this->parse_throw($tokens);
				case 'try': return $this->parse_try($tokens);
				case 'while': return $this->parse_while($tokens);
				default: break;
			}
			
			$expression = $this->parse_expression($tokens);
			switch ($this->peek_value()) {
				case '=':
				case '+=':
				case '-=':
				case '*=':
				case '/=':
				case '%=':
				case '**=':
				case '&=':
				case '|=':
				case '^=':
				case '<<=':
				case '>>=':
					$op = $tokens->pop();
					$value = $this->parse_expression($tokens);
					if ($semicolon_expected) {
						$tokens->pop_expected(';');
					}
					return new Assignment($expression, $op, $value);
				default:
					return new ExpressionAsExecutable($expression);
			}
		}
		
		function parse_expression($tokens) {
			return $this->parse_ternary($tokens);
		}
		
		function parse_function($tokens, $annotations) {
			$function_token = $tokens->pop_expected('function');
			$name_token = $tokens->pop_identifier();
			$tokens->pop_expected('(');
			$arg_names = array();
			$arg_values = array();
			while (!$tokens->pop_if_present(')')) {
				if (count($arg_names) > 0) {
					$tokens->pop_expected(',');
				}
				$arg_name = $tokens->pop_identifier();
				$arg_value = null;
				if ($tokens->pop_if_present('=')) {
					$arg_value = $this->parse_expression($tokens);
				}
				array_push($arg_names, $arg_name);
				array_push($arg_values, $arg_value);
			}
			
			$tokens->pop_expected('{');
			$body = array();
			while (!$tokens->pop_if_present('}')) {
				$line = $this->parse_executable($tokens);
				if ($line != null) {
					array_push($body, $line);
				}
			}
			
			return new FunctionDefinition($function_token, $name_token, $arg_names, $arg_values, $annotations, $body);
		}
		
		// Expression parsing functions go below this comment in order of operations (lowest priority first)
		
		function parse_ternary($tokens) {
			$expression = $this->parse_null_coalescing($tokens);
			if ($tokens->is_next('?')) {
				$ternary_token = $tokens->pop();
				$true_expression = $this->parse_expression($tokens);
				$token->pop_expected(':');
				$false_expression = $this->parse_expression($tokens);
				$expression = new TernaryExpression($expression, $ternary_token, $true_expression, $false_expression);
			}
			return $expression;
		}
		
		function parse_null_coalescing($tokens) {
			$expression = $this->parse_boolean_combination($tokens);
			if ($tokens->is_next('??')) {
				$coalescer = $tokens->pop();
				$fallback_expr = $this->parse_null_coalescing($tokens);
				return new NullCoalescingExpression($expression, $coalescer, $fallback_expr);
			}
			return $expression;
		}
		
		function parse_boolean_combination($tokens) {
			$expression = $this->parse_bitwise($tokens);
			$next = $tokens->peek_value();
			if ($next == '&&' || $next == '||') {
				$expressions = array($expression);
				$ops = array();
				while ($next == '&&' || $next == '||') {
					$op = $tokens->pop();
					$expression = $this->parse_bitwise($tokens);
					array_push($ops, $op);
					array_push($expressions, $expression);
					$next = $tokens->peek_value();
				}
				return BooleanCombinationList($expressions, $ops);
			}
			return $expression;
		}
		
		function parse_bitwise($tokens) {
			$expression = $this->parse_equality_comparison($tokens);
			$next = $tokens->peek_value();
			if ($next == '&' || $next == '|' || $next == '^') {
				$expressions = array($expression);
				$ops = array();
				while ($next == '&' || $next == '|' || $next == '^') {
					array_push($ops, $tokens->pop());
					array_push($expressions, $this->parse_equality_comparison($tokens));
					$next = $this->peek_value();
				}
				return BinaryOperations($expressions, $ops);
			}
			return $expression;
		}
		
		function parse_equality_comparison($tokens) {
			$expression = $this->parse_inequality_comparison($tokens);
			$next = $tokens->peek_value();
			if ($next == '==' || $next == '!=') {
				$op = $tokens->pop();
				$right_expression = $this->parse_inequality_comparison($tokens);
				return BinaryOperation($expression, $op, $left_expression);
			}
			return $expression;
		}
		
		function parse_inequality_comparison($tokens) {
			$expression = $this->parse_bitshift($tokens);
			$next = $tokens->peek_value();
			if ($next == '<' || $next == '>' || $next == '<=' || $next == '>=') {
				$op = $tokens->pop();
				$right_expression = $this->parse_bitshift($tokens);
				return BinaryOperations(array($expression, $right_expression), array($op));
			}
			return $expression;
		}
		
		function parse_bitshift($tokens) {
			$expression = $this->parse_addition($tokens);
			$next = $tokens->peek_value();
			if ($next == '<<' || $next == '>>') {
				$expressions = array($expression);
				$ops = array();
				while ($next == '<<' || $next == '>>') {
					array_push($ops, $tokens->pop());
					array_push($expressions, $this->parse_addition($tokens));
					$next = $this->peek_value();
				}
				return BinaryOperations($expressions, $ops);
			}
			return $expression;
		}
		
		function parse_addition($tokens) {
			$expression = $this->parse_multiplication($tokens);
			$next = $tokens->peek_value();
			if ($next == '+' || $next == '-') {
				$expressions = array($expression);
				$ops = array();
				while ($next == '+' || $next == '-') {
					array_push($ops, $tokens->pop());
					array_push($expressions, $this->parse_multiplication($tokens));
					$next = $this->peek_value();
				}
				return BinaryOperations($expressions, $ops);
			}
			return $expression;
		}
		
		function parse_multiplication($tokens) {
			$expression = $this->parse_negation($tokens);
			$next = $tokens->peek_value();
			if ($next == '*' || $next == '/' || $next == '%') {
				$expressions = array($expression);
				$op = array();
				while ($next == '*' || $next == '/' || $next == '%') {
					array_push($ops, $tokens->pop());
					array_push($expressions, $this->parse_negation($tokens));
					$next = $tokens->peek_value();
				}
				return BinaryOperations($expressions, $ops);
			}
			return $expression;
		}
		
		function parse_negation($tokens) {
			$next = $tokens->peek_value();
			if ($next == '-' || $next == '!') {
				return new Negation($this->pop(), $this->parse_negation($tokens));
			}
			return $this->parse_exponents($tokens);
		}
		
		function parse_exponents($tokens) {
			$expression = $this->parse_increment($tokens);
			$next = $tokens->peek_value();
			if ($next == '**') {
				$expressions = array($expression);
				$op = array();
				while ($next == '**') {
					array_push($ops, $tokens->pop());
					array_push($expressions, $this->parse_increment($tokens));
					$next = $tokens->peek_value();
				}
				return BinaryOperations($expressions, $ops);
			}
			return $expression;
		}
		
		function parse_increment($tokens) {
			$next = $tokens->peek_value();
			if ($next == '++' || $next == '--') {
				$increment = $tokens->pop();
				return new Increment($increment, $this->parse_entity($tokens), true);
			}
			
			$expression = $this->parse_entity($tokens);
			$next = $tokens->peek_value();
			if ($next == '++' || $next == '--') {
				$increment = $tokens->pop();
				return new Increment($increment, $expression, false);
			}
			
			return $expression;
		}
		
		function parse_entity($tokens) {
			
			$next = $tokens->peek_value();
			$c = $next[0];
			$cord = ord($c);
			if ($next == '(') $expression = $this->parse_parenthesis($tokens);
			else if ($next == '[') $expression = $this->parse_list($tokens);
			else if ($next == '{') $expression = $this->parse_dictionary($tokens);
			else if ($c == '"' || $c == "'") $expression = new StringLiteral($tokens->pop());
			else if ($c == '0' && substr($next, 0, 2) == '0x') $expression = new IntegerLiteral($tokens->pop());
			else if ($next == 'true' || $next == 'false') $expression = new BooleanLiteral($tokens->pop());
			else if ($next == 'null') $expression = new NullLiteral($tokens->pop());
			else if ($c == '.') $expression = new FloatLiteral($tokens->pop());
			else if (strpos($next, '.') !== false) $expression = new FloatLiteral($tokens->pop());
			else if ($cord >= ord('0') && $cord <= ord('9')) $expression = new IntegerLiteral($tokens->pop());
			else if (is_valid_identifier($next)) $expression = new Variable($tokens->pop());
			else throw new RedstoneCompileException($tokens->peek(), "Invalid expression.");
			
			$next = $tokens->peek_value();
			$anything_interesting = true;
			while ($next != null && $anything_interesting) {
				$anything_interesting = true;
				switch ($next) {
					case '.': // dot field
						$dot = $tokens->pop();
						$field = $tokens->pop_identifier();
						$expression = new DotField($expression, $dot, $field);
						break;
						
					case '[': // indexing or slicing
						$bracket = $tokens->pop();
						$args = array();
						// Python-style list/string slicing OR list indexing
						while (!$tokens->pop_if_present(']')) {
							if ($tokens->pop_if_present(':')) {
								array_push($args, null);
							} else {
								array_push($args, $this->parse_expression($tokens));
								if (!$tokens->is_next(']')) {
									$tokens->pop_expected(':');
								}
							}
						}
						
						if (count($args) == 0) {
							throw new RedstoneCompileException($bracket, "Invalid expression");
						} else if (count($args) == 1) {
							$expression = new ListIndex($expression, $bracket, $args[0]);
						} else {
							$expression = new ListSlice($expression, $bracket, $args);
						}
						
						break;
						
					case '(': // function invocation
						$paren = $tokens->pop();
						$arg_names = array();
						$arg_values = array();
						while (!$tokens->pop_if_present(')')) {
							if (count($arg_names) > 0) {
								$tokens->pop_expected(',');
							}
							$arg_value = $this->parse_expression($tokens);
							$arg_name = null;
							if ($tokens->pop_if_present('=')) {
								$arg_name = $arg_value;
								$arg_value = $this->parse_expression($tokens);
							}
						}
						$expression = new FunctionInvocation($expression, $paren, $arg_names, $arg_values);
						break;
						
					default:
						$anything_interesting = false;
						break;
				}
			}
			return $expression;
		}
		
		function parse_parenthesis($tokens) {
			$tokens->pop_expected('(');
			$expression = $this->parse_expression($tokens);
			$tokens->pop_expected(')');
			return $expression;
			
		}
	}
?>