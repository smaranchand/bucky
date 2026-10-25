.PHONY: install serve test test-php test-addon reset clean service service-stop service-status service-remove

install:
	composer install
	cp -n .env.example .env || true
	@echo "Edit .env with your AWS credentials and auth settings."

serve:
	php -S 127.0.0.1:13337 -t public/ public/index.php

test: test-php test-addon

test-php:
	./vendor/bin/phpunit

test-addon:
	node --test tests/addon/*.test.js

service:
	./deploy/service.sh install

service-stop:
	./deploy/service.sh stop

service-status:
	./deploy/service.sh status

service-remove:
	./deploy/service.sh uninstall

reset:
	rm -f bucky.db
	php dbreset.php

clean:
	rm -f bucky.db logs/bucky.log
