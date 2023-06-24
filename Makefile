start_local:
	php -S localhost:8080 -t public public/index.php

PORT ?= 8000
start:
	PHP_CLI_SERVER_WORKERS=5 php -S 0.0.0.0:$(PORT) -t public