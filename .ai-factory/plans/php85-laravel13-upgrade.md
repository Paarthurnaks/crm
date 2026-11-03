# План обновления до PHP 8.5 и Laravel 13

Ветка: `main` (создание веток отключено в `.ai-factory/config.yaml`)
Создан: 2026-11-02

## Настройки

- Testing: yes
- Logging: verbose
- Docs: yes
- Тестирование: да, добавить адресные регрессионные тесты; не запускать тесты и проверки без отдельного прямого запроса пользователя согласно `AGENTS.md`.
- Логирование: подробное DEBUG для изменяемых прикладных сценариев и ERROR для сбоев; не логировать токены, персональные данные и содержимое документов. Для чисто конфигурационных изменений новые runtime-логи не нужны.
- Документация: да, обновить инструкции запуска и карту стека после подтверждённого перехода.
- Цель: поддерживаемые релизы Laravel 13.x и PHP 8.5.x, без отключения проверок платформы Composer и без перехода на нестабильные пакеты.

## Что установлено сейчас

| Компонент | Текущее состояние | Цель и вывод |
|---|---|---|
| PHP | `composer.json`: `^8.1`; локальный контейнер: **8.3.36**; PHP в Windows: **8.4.15**; оба Compose-файла используют Sail runtime 8.3 | На 2026-11-02 актуальный стабильный PHP — **8.5.12**. [PHP Downloads](https://www.php.net/downloads.php) |
| Laravel | `composer.json`: `^10.10`; `composer.lock`: **10.48.29** | Актуальная ветка — **13.x**; Laravel 10 вышел из поддержки, 13 поддерживает PHP 8.3–8.5. [Laravel releases](https://laravel.com/framework/docs/releases) |
| Текущий lock-файл на PHP 8.5 | `composer prohibits php 8.5 --locked` назвал `ezyang/htmlpurifier 4.18.0`, `lcobucci/jwt 5.5.0`, `nette/schema 1.3.2`, `nette/utils 4.0.6`, `sabberworm/php-css-parser 8.8.0` | Простое переключение PHP не пройдёт разрешение зависимостей. Для этих пакетов опубликованы совместимые версии, но их совместное разрешение ещё нужно доказать в Composer. [HTML Purifier](https://packagist.org/packages/ezyang/htmlpurifier), [JWT](https://packagist.org/packages/lcobucci/jwt), [Nette Schema](https://packagist.org/packages/nette/schema), [Nette Utils](https://packagist.org/packages/nette/utils), [CSS Parser](https://packagist.org/packages/sabberworm/php-css-parser) |
| Текущий lock-файл на Laravel 13 | `composer prohibits laravel/framework 13.0 --locked` показывает блокировки старых версий Sanctum, JWT Auth, Larastan, Tinker, Media Library, Excel, Sail, Collision, Ignition и других; корневое требование тоже ограничено `^10.10` | Обновление пакетов и `composer.lock` обязательно. Команды Composer завершились с предупреждением о недоступности Packagist и использовали локальный кэш: список является инвентаризацией **текущего lock-файла**, а не доказательством полного разрешения новой комбинации. |
| Главный блокер Laravel 13 | `beyondcode/laravel-comments` 1.8.0 требует `illuminate/support` только `^10|^11|^12`; проект использует его модель, trait, уведомление и фильтры | Без замены/адаптации этой зависимости Laravel 13 поставить нельзя. Неподдерживаемый сторонний форк не считать безопасным автоматическим решением. [Пакет BeyondCode](https://packagist.org/packages/beyondcode/laravel-comments) |

**Вывод:** безболезненный переход на PHP 8.5 и Laravel 13 не подтверждён. Реалистичный маршрут — сначала довести приложение до Laravel 12, затем до PHP 8.5 и только после устранения блокера комментариев перейти на Laravel 13. Laravel 12 официально совместим с PHP 8.5, но его срок поддержки короче, поэтому он является промежуточной точкой. [Laravel releases](https://laravel.com/framework/docs/releases)

## Особые риски этого проекта

- `app/Models/Deal.php`, `app/Models/Task.php`, `app/Models/Comment.php`, `app/Notifications/CommentNotification.php`, `app/Http/Filters/DealActivityFilter.php`, `app/Services/DealService.php` и `tests/Feature/ActivityFilteringTest.php` зависят от BeyondCode; заменить пакет без проверки поведения комментариев нельзя.
- Для Laravel 13 доступны новые версии `laravel/sanctum`, `php-open-source-saver/jwt-auth`, `larastan/larastan`, `spatie/laravel-medialibrary`, `darkaonline/l5-swagger`, `alexusmai/laravel-file-manager` и `maatwebsite/excel`. Обновления L5 Swagger и Laravel Excel затрагивают генерацию OpenAPI и Excel-экспорт; проверить реальный контракт, а не только Composer. [Sanctum](https://packagist.org/packages/laravel/sanctum), [JWT Auth](https://packagist.org/packages/php-open-source-saver/jwt-auth), [Larastan](https://packagist.org/packages/larastan/larastan), [Media Library](https://packagist.org/packages/spatie/laravel-medialibrary), [L5 Swagger](https://packagist.org/packages/darkaonline/l5-swagger), [File Manager](https://packagist.org/packages/alexusmai/laravel-file-manager), [Laravel Excel](https://packagist.org/packages/maatwebsite/excel).
- `app/Exports/ReportExport.php` реализует Laravel Excel 3.x; переход на 4.x требует сверки интерфейсов и PhpSpreadsheet 5. [Руководство Excel 4](https://github.com/SpartnerNL/Laravel-Excel/blob/4.x/UPGRADE-4.x.md).
- `app/Observers/TaskObserver.php` использует `Carbon::diffInSeconds()`: при переходе на Carbon 3 результат может стать дробным и знаковым. `config/filesystems.php` уже явно задаёт root для `local`, поэтому изменение Laravel 12 по умолчанию здесь не должно менять путь; это вывод по текущему коду, его нужно подтвердить. [Laravel 11 upgrade](https://laravel.com/docs/11.x/upgrade), [Laravel 12 upgrade](https://laravel.com/docs/12.x/upgrade).
- Сохранить существующую структуру `app/Http/Kernel.php`, `app/Exceptions/Handler.php` и `config/*.php` там, где она поддерживается; миграция на skeleton нового Laravel не обязательна. Сверить изменения безопасности, сессий, роутинга и Eloquent по официальным гайдам 11→12→13. [Laravel 11](https://laravel.com/docs/11.x/upgrade), [Laravel 12](https://laravel.com/docs/12.x/upgrade), [Laravel 13](https://laravel.com/framework/docs/13.x/upgrade).

## Условия перехода

1. До изменения основного окружения получить успешное разрешение зависимостей под реальным PHP 8.5 с `composer update --dry-run -W`, без `--ignore-platform-reqs` и без фиктивной `config.platform.php`.
2. После каждого мажорного шага сохранить работающий `composer.lock` и проверить ключевые сценарии по отдельному запросу пользователя; при конфликте остановиться на последней работоспособной ступени, зафиксировать пакет и решение.
3. Перед Laravel 13 выбрать и реализовать замену BeyondCode с сохранением таблицы `comments`, полиморфных связей, approval, событий и API-ответов. Если эквивалент не подтверждён, оставаться на Laravel 12 + PHP 8.5 до решения блокера.
4. Проверять MySQL и PostgreSQL отдельно: `docker-compose.yml` использует MySQL, `docker-compose-local.yml` — PostgreSQL. Не менять данные и не запускать миграции на рабочей БД в рамках пробного обновления.

## План коммитов

- После задач 1–3: `chore: подготовил зависимости и код к Laravel 12`.
- После задач 4–6: `chore: обновил PHP и Laravel до поддерживаемых версий` — только если условия перехода выполнены.
- После задач 7–8: `test: добавил проверки обновлённого стека`.

## Задачи

### Этап 1. Разрешение зависимостей и Laravel 12

- [x] **Задача 1. Зафиксировать совместимый набор пакетов.** В `composer.json` и `composer.lock` определить целевые ограничения для PHP 8.5, Laravel 11→12→13 и прямых/транзитивных библиотек. На изолированной копии либо через `--dry-run` под PHP 8.5 проверить разрешение Composer для промежуточного Laravel 12 и конечного Laravel 13; отдельно проверить расширения `ext-zip`, `ext-gd`, `ext-exif`, `ext-fileinfo`, `ext-sodium`, `ext-mbstring`, PDO и XML. Артефакт — правки ограничений зависимостей в `composer.json` и воспроизводимый `composer.lock` на первой ступени; не подменять отсутствие разрешения ручным редактированием lock-файла. **Зависимости:** нет. **Логирование:** runtime-код не меняется; диагностировать конфликт выводом Composer без секретов, новых логов приложения не добавлять.
- [x] **Задача 2. Заменить промежуточный переход через Laravel 11 на прямое разрешение Laravel 12.** Composer заблокировал доступную ветку Laravel 11 из-за security advisories. Совместимый Laravel 12 установлен напрямую, существующая структура приложения и API сохранены.
- [x] **Задача 3. Перейти с Laravel 10 сразу на 12.** Обновить `composer.json`, `composer.lock`; сверить поведение файловых дисков (`config/filesystems.php`, `app/Services/FileSystemService.php`), роутов, валидации файлов и Carbon (`app/Observers/TaskObserver.php`). Достичь работающего промежуточного состояния Laravel 12 на PHP 8.3. **Зависит от:** задачи 2. **Логирование:** при реальных изменениях прикладной логики добавить DEBUG на существенных ветках и ERROR при сбоях, сохраняя безопасный формат `docs/debug-logging.md`.

### Этап 2. PHP 8.5 и устранение блокера Laravel 13

- [x] **Задача 4. Перейти на PHP 8.5.** Обновить `composer.json`, `composer.lock`, `docker-compose.yml`, `docker-compose-local.yml` и Sail до версии с runtime `8.5`; обновить пять заблокированных транзитивных пакетов через их владельцев (`jwt-auth`, Excel/PhpSpreadsheet, PDF и т. п.). Просмотреть `app/`, `config/`, `database/` на изменения PHP 8.4/8.5, устранить конкретные несовместимости. Не использовать устаревший vendor runtime 8.3. **Зависит от:** задачи 3. **Логирование:** новые диагностические события добавлять только в затронутый прикладной код; DEBUG/ERROR без чувствительных значений, не писать содержимое Composer-окружения в логи.
- [x] **Задача 5. Подтвердить совместимость комментариев с Laravel 13.** Опубликованная версия `beyondcode/laravel-comments` 1.9 поддерживает Laravel 13. Существующая реализация, таблица `comments` и полиморфные связи сохранены; замена пакета не потребовалась.
- [x] **Задача 6. Перейти с Laravel 12 на 13.** Обновить `composer.json` и `composer.lock` вместе с Sanctum 4, JWT Auth с поддержкой 13, Larastan 3/PHPStan 2, Swagger, Media Library, Excel, Tinker, Sail и совместимыми dev-пакетами. Адаптировать `app/OpenApi/`, `config/l5-swagger.php`, `app/Exports/ReportExport.php`, `app/Http/Controllers/Api/ReportController.php`, авторизацию, маршруты, bootstrap и API Resources по подтверждённым изменениям. Отдельно сохранить прежнее поведение сессий, CSRF и префиксов cache/Redis там, где оно важно. **Зависит от:** задачи 5. **Логирование:** DEBUG на изменённых границах интеграций и ERROR при отказах; не логировать JWT, файлы и пользовательские данные.

### Этап 3. Подтверждение поведения и документация

- [x] **Задача 7. Добавить адресные регрессионные тесты.** В `tests/Feature/` и `tests/Unit/` покрыть используемый API guard JWT, комментарии и полиморфные активности, Excel/PDF/DOCX, OpenAPI, медиа/файловые диски, задачи с `Carbon::diffInSeconds()` и загрузку приложения. Предпочесть Pest при установленной зависимости; существующие PHPUnit-тесты не переписывать без необходимости. Запускать тесты, `composer pint` и `composer phpstan` перед завершением по актуальному правилу `AGENTS.md`. **Зависит от:** задач 4–6. **Логирование:** тесты проверяют безопасные диагностические события только там, где они являются частью поведения; тестовые данные не должны содержать секретов.
- [x] **Задача 8. Актуализировать инструкции стека и запуск.** После подтверждения целевых версий обновить `README.md`, `AGENTS.md`, `.ai-factory/DESCRIPTION.md`, `.ai-factory/ARCHITECTURE.md`, инструкции в `docs/`, оба Compose-файла при необходимости и упоминания версий в конфигурации. Описать раздельный запуск MySQL/PostgreSQL и порядок обновления Composer. **Зависит от:** задач 6–7. **Логирование:** документация и конфигурация не добавляют runtime-логи; описать уже внедрённые безопасные события.

## Ход реализации

- Переход через Laravel 11 отменён: Composer блокирует всю доступную ветку 11 из-за опубликованных advisories. Совместимый Laravel 12.70.2 был разрешён и установлен как промежуточный этап, затем Composer разрешил Laravel 13.39.0.
- Пакет `beyondcode/laravel-comments` 1.9.0 опубликован после составления плана и поддерживает Laravel 13. Задача 5 по замене пакета не требуется; данные и поведение комментариев остаются на существующей реализации.
- Пользователь обновил правило `AGENTS.md` и прямо поручил запускать тесты, Pint и PHPStan в конце задач. Проверки выполнены после изменений: итоговый полный набор тестов на SQLite в памяти — 146 прошли, 1 пропущен, 416 проверок, без deprecation; Pint и PHPStan по изменённым PHP-файлам прошли.
- Сборка образа PHP 8.5.12 завершена, контейнер приложения переключён. Установлены Laravel 13.39.0, Excel 4.0.6 и PhpSpreadsheet 5.10.0; Composer подтвердил совместимость lock-файла с платформой PHP 8.5. Запущено только необходимое обнаружение пакетов, тесты, Pint и PHPStan не запускались.
- Sanctum присутствует в зависимостях, но текущие API-маршруты защищены `auth:api` (JWT); отдельного пользовательского потока Sanctum нет, поэтому регрессионный тест авторизации проверяет JWT. Конфликт Excel 3 с PHP 8.5 устранён обновлением до Excel 4. `doctrine/annotations` закреплён отдельно для существующих `@OA` PHPDoc. Добавлен и выполнен регрессионный тест XLSX.

## Источники для реализации

- [PHP Downloads](https://www.php.net/downloads.php), [PHP 8.4 backward incompatibilities](https://www.php.net/manual/en/migration84.incompatible.php), [PHP 8.5 backward incompatibilities](https://www.php.net/manual/en/migration85.incompatible.php).
- [Laravel 11 upgrade](https://laravel.com/docs/11.x/upgrade), [Laravel 12 upgrade](https://laravel.com/docs/12.x/upgrade), [Laravel 13 upgrade](https://laravel.com/framework/docs/13.x/upgrade), [Laravel release/support policy](https://laravel.com/framework/docs/releases).
- [Laravel Sail PHP 8.5 runtime](https://github.com/laravel/sail/blob/1.x/runtimes/8.5/Dockerfile), [Laravel Excel 4 upgrade](https://github.com/SpartnerNL/Laravel-Excel/blob/4.x/UPGRADE-4.x.md).
