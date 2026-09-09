# Пользовательский API

Модуль user во внешнем Yii-проекте. Авторизация реализована, CRUD-операции остаются заглушками HTTP 501.

## Авторизация

- POST /user/auth/login — вход по username или email и password; доступен без токена.
- GET /user/auth/me — id и username текущего пользователя.
- POST /user/auth/logout — отзыв токена, ответ {"success":true}.

Вход принимает JSON, например {"username":"login","password":"password"} или {"email":"user@example.com","password":"password"}.
Ответ содержит auth_key, token_type: Bearer и user (id, username). В базе сохраняется только SHA-256 хеш токена.
Новый вход заменяет прежний токен. Автоматического срока истечения пока нет. За пределами локальной разработки используйте HTTPS.
400 — неверный формат данных, 401 — неверные реквизиты или токен. Cookies и CSRF-токен для входа не требуются.
Проверки авторизации на SQLite в памяти: php tests/user-auth.php.

Защищенные маршруты уже проверяют Authorization: Bearer <auth_key> через существующую модель User.
Без действующего токена — 401. Хранилище учетных записей и auth_key общее с admin API:
вход через любой модуль заменяет прежний токен этой учетной записи. Cookies не заменяют Bearer-токен.
Проверка владельца теста еще не реализована; до ее добавления операции не должны возвращать или изменять данные.

## CRUD-маршруты

Для каждого ресурса доступны:
GET /user/{resource} — список;
GET /user/{resource}/{id} — одна запись;
POST /user/{resource} — создание;
PUT или PATCH /user/{resource}/{id} — редактирование;
DELETE /user/{resource}/{id} — удаление.

Ресурсы: courses, categories, tests, active-tests, topics, questions, question-types.

Списки по родителю:
- GET /user/courses/{course_id}/categories
- GET /user/categories/{category_id}/topics
- GET /user/topics/{topic_id}/questions

На общих списках categories, topics и questions планируются соответственно query-параметры course_id, category_id и topic_id.
tests и active-tests предназначены для текущего пользователя: user_id должен определяться на сервере из токена при реализации.

OPTIONS поддерживается для CORS. Разрешенные источники задаются в modules.user.corsOrigins.
Маршруты требуют настройки веб-сервера на web/index.php. Прямые Yii-маршруты user/course/index и аналогичные также доступны.

Проверка маршрутов без базы: php tests/user-api-routes.php.

## История подписок

`GET /user/subscriptions?page=1` возвращает записи из `user_subscription` только для владельца Bearer-токена, с названием тарифа. Размер страницы фиксирован: 10 записей, сортировка по `starts_at DESC, id DESC`. Ответ содержит `items`, `pagination` (`page`, `page_size`, `total`, `page_count`) и `coupons` (`total`, `used`, `available`). Страница за пределами списка приводится к последней, неверный номер даёт 400. Параметр `user_id` не влияет на владельца выборки.

Купоны суммируются по всем подпискам пользователя со статусом `active`, для которых `starts_at <= now < expires_at`; лимит берётся из подписки, а не из текущего тарифа. Использовано (`used`) — количество записей user_course текущего пользователя со статусом active. Неактивные курсы и курсы других пользователей не учитываются. Доступно (`available`) = max(0, total - used), в том числе после истечения подписок. В таблице нет сумм платежей: API показывает историю подписок, без выдуманных платёжных данных.

Проверка: `php tests/user-subscriptions.php`. Проверка через реальный Apache включена в `php tests/user-auth-http.php --local-apache` и создаёт временные подписки и тариф тестового пользователя с удалением в `finally`.

Для Apache с CGI/FastCGI в `web/.htaccess` включён `CGIPassAuth On`: он передаёт заголовок Authorization в PHP. Без него вход по паролю работает, но `/user/auth/me` и `/user/auth/logout` получают запрос без токена и возвращают 401.

Проверка через работающий Open Server: `php tests/user-auth-http.php --local-apache`. Она использует `http://nl.local` и БД из `config/db.php`, создаёт временную учётную запись и удаляет её в `finally`. Проверяются CORS, вход, три независимых восстановления сессии, выход, удаление токена в БД и отказ для отозванного токена. Существующие учётные записи не изменяются.
