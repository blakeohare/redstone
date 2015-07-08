
@Handle(GET, POST)
function handle() {
	println('<html><body>');
	
	println("<p>Hello, World!</p>");
	println('<ul>');
	for (i = 0; i < 10; ++i) {
		print('<li>');
		print(i);
		println('</li>');
	}
	println('</ul>');
	println('</body></html>');
}