@lint:
	./vendor/bin/ecs check --fix
	./vendor/bin/php-cs-fixer fix
	./vendor/bin/rector process

@test:
	echo "Running unit and integration tests"; \
	vendor/bin/pest

# Run tests and create code-coverage report with Xdebug
@coverage:
	echo "Running unit and integration tests with coverage"; \
	herd coverage ./vendor/bin/pest --coverage-html reports