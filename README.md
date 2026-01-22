# FormaFlow Backend

Production-ready backend построенный на принципах **Domain-Driven Design (DDD)** и **Clean Architecture**.

## Архитектура (DDD)

Проект разделен на ограниченные контексты (Bounded Contexts), каждый из которых следует классической четырехслойной архитектуре:

```
src/FormaFlow/
├── Forms/              # Контекст управления формами и полями
├── Entries/            # Контекст записей и ответов пользователей
├── Reports/            # Контекст аналитики и генерации отчетов
├── Identity/           # Контекст аутентификации и пользователей
└── Shared/             # Общий код (Domain, Application, Infrastructure)
```

Каждый модуль внутри `src/FormaFlow/ModuleName/` содержит:
- **Domain**: Сущности, агрегаты, доменные события и интерфейсы репозиториев.
- **Application**: Команды, запросы (CQRS) и их обработчики (Handlers).
- **Infrastructure**: Реализация репозиториев, контроллеры, миграции и внешние сервисы.

## Стек технологий

- **PHP 8.3** + Laravel 11
- **PostgreSQL** (Production/Dev)
- **SQLite** (Testing)
- **Sanctum** для аутентификации
- **PHPUnit** для тестирования
- **Psalm** для статического анализа

## Быстрый старт

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
```

## Доступные команды (Makefile)

- `make serve` - запуск миграций и локального сервера
- `make test` - запуск тестов (PHPUnit)
- `make lint` - запуск всех проверок качества кода (CS, Psalm, Prettier)
- `make cs-fix` - автоматическое исправление стиля кода
- `make format` - форматирование кода через Prettier
- `make analyze` - статический анализ кода (Psalm)

## API Эндпоинты (v1)

### Аутентификация
- `POST /api/v1/register` - Регистрация
- `POST /api/v1/login` - Вход
- `POST /api/v1/logout` - Выход (Auth)
- `GET /api/v1/profile` - Профиль пользователя (Auth)
- `PATCH /api/v1/profile` - Обновление профиля (Auth)

### Формы
- `GET /api/v1/forms` - Список форм
- `POST /api/v1/forms` - Создать форму
- `GET /api/v1/forms/{id}` - Детали формы
- `PATCH /api/v1/forms/{id}` - Обновить форму
- `DELETE /api/v1/forms/{id}` - Удалить форму
- `POST /api/v1/forms/{id}/publish` - Опубликовать
- `POST /api/v1/forms/{id}/fields` - Добавить поле
- `PATCH /api/v1/forms/{id}/fields/{fieldId}` - Обновить поле
- `DELETE /api/v1/forms/{id}/fields/{fieldId}` - Удалить поле

### Записи
- `GET /api/v1/entries` - Список записей
- `POST /api/v1/entries` - Создать запись
- `GET /api/v1/entries/{id}` - Детали записи
- `PATCH /api/v1/entries/{id}` - Обновить запись
- `DELETE /api/v1/entries/{id}` - Удалить запись
- `GET /api/v1/entries/stats` - Общая статистика

### Отчеты и Дашборд
- `GET /api/v1/dashboard/week` - Суммарно за неделю
- `GET /api/v1/dashboard/trends` - Тренды за период
- `POST /api/v1/reports/summary` - Сводный отчет
- `POST /api/v1/reports/time-series` - Временные ряды
- `GET /api/v1/reports/predefined/budget` - Пресет: Бюджет

## Тестирование

**Важно:** При запуске тестов вручную всегда используйте конфигурационный файл или запускайте из директории `backend`:
```bash
# Из корня проекта
./backend/vendor/bin/phpunit -c backend/phpunit.xml

# Или через Makefile
make test
```
## Деплой

```bash
make deploy
```
*Команда выполняет обновление кода на сервере и запуск миграций.*
