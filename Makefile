.RECIPEPREFIX +=
.DEFAULT_GOAL := help
model="User"
include .env
help:
	@echo "Useful  commands"
install:
	@composer install
test:
	php artisan test
coverage:
	php artisan test --coverage
migrate:
	php artisan migrate
showmodel:
	php artisan model:show $(model)
migrater:
	php artisan migrate:fresh
seed:
	php artisan db:seed
optimize:
	php artisan optimize
analyse:
	./vendor/bin/phpstan analyse
pint:
	./vendor/bin/pint --preset psr12
database:
	@docker exec -it $(PROJECT_NAME)_database /bin/sh
redis:
	@docker exec -it $(PROJECT_NAME)_redis /bin/sh
up:
	@docker-compose up -d
down:
	@docker-compose down
updev:
	php artisan serve && yarn run dev
