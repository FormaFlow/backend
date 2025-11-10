test:
	vendor/bin/phpunit

migrate:
	php artisan migrate

fresh:
	php artisan migrate:fresh

key:
	php artisan key:generate

serve:
	php artisan serve

.PHONY: test migrate fresh key serve
