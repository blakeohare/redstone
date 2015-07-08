<?
	require 'exceptions.php';
	require 'util.php';
	require 'tokenizer.php';
	
	function compile($rs_url) {
		try {
			$path = canonicalize_path('/'.$rs_url, false);
			if (file_exists('..'.$path)) {
				$code = file_get_contents('..'.$path);
				$tokens = new TokenStream($rs_url, $code);
				return null;
			} else {
				throw new RedstoneCompilerException(null, 'Source file does not exist: ' . $path);
			}
		} catch (RedstoneCompilerException $exception) {
			return $exception->message;
		}
		return null;
	}
	
	$output = compile($_GET['rs_url']);
	if ($output === null) $output = "Built successfully";
	
	echo '<pre>';
	echo htmlspecialchars($output);
	echo '</pre>';
	
?>