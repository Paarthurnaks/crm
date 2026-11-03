# Проверка приложения

Тесты запускаются на PHP 8.5 и PHPUnit 12. Для новых тестов в проекте предпочтителен Pest после добавления зависимости. Быстрый набор использует SQLite в памяти. Интеграционные наборы подключаются только к отдельным контейнерам `pgsql-test` и `mysql-test` с базой `crm_testing`; рабочая база локального Compose в них не используется.

## Быстрый прогон

Команды для PowerShell из корня репозитория:

```powershell
docker compose -f docker-compose-local.yml up -d laravel.test
docker compose -f docker-compose-local.yml exec -T laravel.test composer test:fast
```

Адресный прогон: `docker compose -f docker-compose-local.yml exec -T laravel.test composer test:fast -- tests/Feature/DealApiWorkflowTest.php`.

## PostgreSQL и MySQL

Контейнеры тестовых БД имеют собственные тома и не публикуют порты на хост. Конфигурации `phpunit.pgsql.xml` и `phpunit.mysql.xml` принудительно задают `APP_ENV=testing`, имя БД и отдельного пользователя. Не меняйте эти значения на адреса или учётные данные рабочей БД.

```powershell
docker compose -f docker-compose-local.yml -f docker-compose.test-db.yml up -d pgsql-test mysql-test
docker compose -f docker-compose-local.yml -f docker-compose.test-db.yml exec -T laravel.test composer test:pgsql -- tests/Feature/NotificationSearchPortabilityTest.php tests/Feature/ExplicitDomainWorkflowsTest.php
docker compose -f docker-compose-local.yml -f docker-compose.test-db.yml exec -T laravel.test composer test:mysql -- tests/Feature/NotificationSearchPortabilityTest.php tests/Feature/ExplicitDomainWorkflowsTest.php
```

`RefreshDatabase` выполняет миграции только в выбранной тестовой БД. Тест SQL-поиска уведомлений пропускается на SQLite и выполняется на PostgreSQL и MySQL.

## Покрытие и качество

```powershell
docker compose -f docker-compose-local.yml exec -T laravel.test composer test:coverage
docker compose -f docker-compose-local.yml exec -T laravel.test composer pint -- --test
docker compose -f docker-compose-local.yml exec -T laravel.test composer phpstan -- --no-progress
```

Для отдельного изменённого файла добавьте путь после `-- --test` у Pint или после `--` у PHPStan. CI проверяет изменённые PHP-файлы, полный SQLite-набор, PostgreSQL-интеграцию и сохраняет Clover-отчёт покрытия как артефакт. Порог покрытия пока не установлен.

Последний локальный PCOV-прогон: **195 тестов, 796 проверок, 1 пропуск; 71,90% строк, 51,65% методов, 42,81% классов** в `app/`. До расширения набора было 45,84% строк и 29,88% методов. Полная проверка Pint всё ещё обнаруживает 116 файлов со старым форматированием, полный PHPStan — 360 ранее накопленных ошибок; это отдельная работа по базовому качеству кода.
