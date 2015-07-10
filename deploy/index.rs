
x = 42;

@Handle(GET, POST)
function handle() {
	IO.println('<html><body>');
	
	IO.println("<p>Hello, World!</p>");
	IO.println('<ul>');
	for (i = 0; i < 10; ++i) {
		IO.print('<li>');
		IO.print(i);
		IO.println('</li>');
	}
	IO.println('</ul>');
	IO.println('</body></html>');
}