# FormaFlow Backend - DDD

Production-ready backend с полной DDD архитектурой.

## Структура

```
src/FormaFlow/Forms/
├── Domain/          # Бизнес-логика (Aggregates, ValueObjects, Events)
├── Application/     # Use Cases (Commands, Queries, Handlers)
└── Infrastructure/  # Persistence, HTTP Controllers
```

## Setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
make test
```

## API Endpoints

```
GET    /api/v1/forms
POST   /api/v1/forms
GET    /api/v1/forms/{id}
POST   /api/v1/forms/{id}/publish
POST   /api/v1/forms/{id}/fields
```
