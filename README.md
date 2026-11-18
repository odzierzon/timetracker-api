> [!WARNING]
> **Work in Progress (WIP):** This project is still in an early stage of development. Features are incomplete and APIs may change at any time – **not suitable for production use!**


# Time Tracking: Organization API

🇬🇧 English | [🇩🇪 Deutsch](README.de.md)

A Symfony REST API for employees, teams, and positions using MySQL. Redis is configured as a Docker service but is not currently used. No frontend is included.

## Running locally

Prerequisite: Docker with Compose. The setup is intended exclusively for local development. Before starting, add the database credentials to `.env.local`, for example:

```dotenv
MYSQL_USER=timetracker
MYSQL_PASSWORD=dein-passwort
MYSQL_ROOT_PASSWORD=dein-root-passwort
DATABASE_URL="mysql://timetracker:dein-passwort@database:3306/timetracker?serverVersion=8.4.0&charset=utf8mb4"
```

The username and password in `DATABASE_URL` must match `MYSQL_USER` and `MYSQL_PASSWORD`. Special characters in the credentials may need to be URL-encoded in the URL. Docker Compose does not read `.env.local` automatically, so specify `--env-file .env.local` with every Compose command.

If the database volume has already been initialized, new values in `.env.local` do not change the existing MySQL credentials. Use the previous credentials or change them in MySQL first.

```bash
export APP_UID="$(id -u)" APP_GID="$(id -g)"
docker compose --env-file .env.local build app
docker compose --env-file .env.local up -d --wait database redis
docker compose --env-file .env.local run --rm --no-deps app composer install
docker compose --env-file .env.local run --rm --no-deps app php bin/console lexik:jwt:generate-keypair --skip-if-exists
docker compose --env-file .env.local run --rm --no-deps app php bin/console doctrine:migrations:migrate --no-interaction
docker compose --env-file .env.local run --rm app php bin/console app:create-admin
docker compose --env-file .env.local up -d app
```

The admin command prompts interactively for an email address and password; the password is not passed as a command-line argument. It can also create additional admins when needed. The API is available at `http://127.0.0.1:8000`. If port 8000 is occupied, you can set `APP_PORT=8001` for the last command, for example. The Docker files are intended for development with the project directory mounted, not for production deployment.

Local settings can be overridden in `.env.local`; this file and the generated JWT keys are not checked in. Before using the application outside local development, replace `APP_SECRET`, `JWT_PASSPHRASE`, database credentials, and JWT keys with secure values, and adjust the Compose database settings accordingly. Use HTTPS and `APP_ENV=prod`/`APP_DEBUG=0`; the built-in PHP web server is not a production server.

## Authentication and roles

`POST /api/login` accepts JSON containing `email` and `password` and returns `{"token":"..."}` on success. Protected endpoints expect `Authorization: Bearer <token>`. JWTs are valid for 15 minutes; after that, a new login is required. There is no refresh token or server-side token blocklist: an individual token cannot be logged out before it expires. Requests from deleted accounts are rejected; a newly created account with the same email address does not inherit old tokens. Login attempts are rate-limited.

Admins manage all organization data and create accounts for employees. Employees may only read employees in their current team, including themselves. Birthdays and phone numbers are not returned. They may also read their own team and the positions of their team members. Changes to team assignments take effect on the next request. Passwords must be 12 to 72 bytes long; password reset, public registration, and email delivery are not implemented.

## API

All data is transferred as JSON. Successful creations return `201`, and deletions return `204`. Invalid JSON returns `400`, missing or invalid authentication `401`, insufficient permissions `403`, nonexistent or invisible resources `404`, conflicts `409`, and invalid input `422`. Error responses contain `{"error":"..."}`.

| Method | Path | Access |
|---|---|---|
| POST | `/api/login` | Public |
| GET | `/api/employees`, `/api/employees/{id}` | Admin: all; employee: own team |
| POST | `/api/employees` | Admin |
| PUT, DELETE | `/api/employees/{id}` | Admin |
| POST | `/api/employees/{id}/account` | Admin; body: `{"password":"..."}` |
| GET | `/api/teams`, `/api/teams/{id}` | Admin: all; employee: own team |
| GET | `/api/positions`, `/api/positions/{id}` | Admin: all; employee: positions of own team |
| POST | `/api/teams`, `/api/positions` | Admin |
| PUT, DELETE | `/api/teams/{id}`, `/api/positions/{id}` | Admin |

`PUT` completely replaces the respective record; omitted optional fields are set to `null`. Lists are JSON arrays.

Team and position body:

```json
{"name":"Entwicklung","description":"Optional"}
```

Employee body:

```json
{
  "firstName": "Mara",
  "lastName": "Beispiel",
  "email": "mara@example.test",
  "teamId": 1,
  "positionId": 1,
  "birthday": "1990-05-21",
  "phone": null
}
```

`firstName`, `lastName`, `email`, `teamId`, and `positionId` are required. Email addresses are normalized to lowercase and must be unique among employees. `birthday` is optional and uses `YYYY-MM-DD`; `phone` is optional. The team and position must exist and cannot be deleted while employees are assigned to them. Deleting an employee also deletes their account. If an employee's email address changes, the login email of the associated account changes as well.

Employee responses contain `id`, `firstName`, `lastName`, `email`, `team`, and `position`; admins also receive `birthday` and `phone`. `team` and `position` are objects with `id`, `name`, and `description`.

## Tests

The integration tests use a separate MySQL container with ephemeral storage. Before each test, they delete only that container's test data; the suite refuses to run against other database hosts or names.

```bash
docker compose --env-file .env.local --profile test up -d --wait database_test
docker compose --env-file .env.local --profile test run --rm --no-deps \
  -e 'DATABASE_URL=mysql://timetracker:timetracker@database_test:3306/timetracker_test?serverVersion=8.4.0&charset=utf8mb4' \
  app php bin/console doctrine:migrations:migrate --no-interaction
docker compose --env-file .env.local --profile test run --rm --no-deps \
  -e APP_ENV=test \
  -e 'DATABASE_URL=mysql://timetracker:timetracker@database_test:3306/timetracker_test?serverVersion=8.4.0&charset=utf8mb4' \
  app php vendor/bin/phpunit
```

The tests require the local JWT keys generated above. `docker compose --env-file .env.local --profile test stop database_test` stops the test database; its ephemeral contents are lost.
