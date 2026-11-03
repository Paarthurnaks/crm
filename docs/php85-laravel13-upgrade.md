# Обновление до PHP 8.5 и Laravel 13

Проект использует PHP `^8.5`, Laravel `^13.0` и версии библиотек из `composer.lock`. Локальный образ приложения собирается из `docker/php85`. `docker-compose-local.yml` подключает PostgreSQL 15 и Redis; `docker-compose.yml` подключает MySQL 8.

## Локальный запуск

В PowerShell выберите нужный Compose-файл. Для PostgreSQL:

```powershell
docker compose -f docker-compose-local.yml up -d --build
docker compose -f docker-compose-local.yml exec -T laravel.test composer install
```

Для MySQL замените имя файла на `docker-compose.yml`. Настройки подключения к базе задаются в локальном окружении проекта. Миграции выполняются отдельно после проверки целевой базы и резервной копии данных. Обновление Composer само миграции не запускает.

## Изменения зависимостей

- Laravel 13 требует совместимых версий Sanctum, Tinker, JWT Auth, Media Library, L5 Swagger и инструментов разработки; они закреплены в lock-файле.
- PHP 8.5 потребовал Laravel Excel 4 и PhpSpreadsheet 5. Экспорт отчётов использует типизированные методы `array()` и `headings()`.
- `beyondcode/laravel-comments` 1.9 поддерживает Laravel 13. Таблица комментариев и полиморфные связи не менялись.
- Текущие описания OpenAPI используют `@OA` в PHPDoc. Для swagger-php 7 сохранён отдельный пакет `doctrine/annotations`, а `config/l5-swagger.php` включает сканирование PHPDoc и атрибутов. Общие метаданные API находятся в `app/OpenApi/ApiDefinition.php`. Генерация проверяется командой `php artisan l5-swagger:generate` и регрессионным тестом.
- Laravel 11 как промежуточная версия не устанавливался: Composer заблокировал всю доступную ветку из-за опубликованных security advisories. Переход выполнен через Laravel 12.

## Поведение приложения

- Задания истечения срока задачи теперь планируются по дате дедлайна. Это исключает зависимость от типа результата `Carbon::diffInSeconds()` в Carbon 3.
- Загрузка SVG-логотипа явно разрешена в правиле `image:allow_svg`, поскольку Laravel 12 изменил стандартную проверку `image`.
- Middleware CSRF наследует `PreventRequestForgery`, рекомендованный Laravel 13. Существующая группа маршрутов `web` сохранена.
- Файловый диск `local` задан явно в `config/filesystems.php`; смена значения по умолчанию Laravel 12 на него не влияет. Префиксы cache/Redis и имя cookie сессии также заданы в конфигурации и сохраняют прежний формат.

После перехода на PHP 8.5 полный набор тестов на SQLite в памяти завершился результатом: 149 прошли, 1 пропущен, 427 проверок. Добавлены регрессионные тесты для JWT, дедлайна задачи и генерации OpenAPI. Пропущенный тест поиска уведомлений требует MySQL или PostgreSQL. Для совместимости с PHP 8.5 обновлены константа MySQL PDO и ключ ACL файлового менеджера. Composer установил lock-файл под PHP 8.5 и подтвердил требования платформы; сетевой аудит уязвимостей при финальной установке не завершился из-за тайм-аута Packagist.
