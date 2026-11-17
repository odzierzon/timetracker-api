# Zeiterfassung: Organisations-API

Symfony-REST-API für Mitarbeiter, Teams und Positionen mit MySQL. Redis ist als Docker-Dienst vorbereitet, wird derzeit aber nicht verwendet. Ein Frontend ist nicht enthalten.

## Lokal starten

Voraussetzung: Docker mit Compose. Das Setup und die in `.env` und `compose.yaml` enthaltenen Zugangsdaten sind ausschließlich für die lokale Entwicklung gedacht.

```bash
export APP_UID="$(id -u)" APP_GID="$(id -g)"
docker compose build app
docker compose up -d --wait database redis
docker compose run --rm --no-deps app composer install
docker compose run --rm --no-deps app php bin/console lexik:jwt:generate-keypair --skip-if-exists
docker compose run --rm --no-deps app php bin/console doctrine:migrations:migrate --no-interaction
docker compose run --rm app php bin/console app:create-admin
docker compose up -d app
```

Der Admin-Befehl fragt E-Mail und Passwort interaktiv ab; das Passwort wird nicht als Kommandozeilenargument übergeben. Er kann bei Bedarf weitere Admins anlegen. Die API ist unter `http://127.0.0.1:8000` erreichbar. Ist Port 8000 belegt, kann für den letzten Befehl beispielsweise `APP_PORT=8001` gesetzt werden. Docker-Dateien sind für die Entwicklung mit eingebundenem Projektverzeichnis vorgesehen, nicht als Produktions-Deployment.

Lokale Einstellungen können in `.env.local` überschrieben werden; diese Datei und die erzeugten JWT-Schlüssel werden nicht eingecheckt. Vor einem Einsatz außerhalb der lokalen Entwicklung müssen insbesondere `APP_SECRET`, `JWT_PASSPHRASE`, Datenbankzugangsdaten und JWT-Schlüssel durch sichere Werte ersetzt und auch die Compose-Datenbankeinstellungen entsprechend angepasst werden. Verwende HTTPS und `APP_ENV=prod`/`APP_DEBUG=0`; der eingebaute PHP-Webserver ist kein Produktionsserver.

## Authentifizierung und Rollen

`POST /api/login` erhält JSON mit `email` und `password` und liefert bei Erfolg `{"token":"..."}`. Geschützte Endpunkte erwarten `Authorization: Bearer <token>`. JWTs sind 15 Minuten gültig; danach ist ein erneuter Login erforderlich. Es gibt weder Refresh-Token noch eine serverseitige Token-Sperrliste: Ein einzelnes Token kann nicht vorzeitig abgemeldet werden. Gelöschte Konten werden bei Anfragen abgelehnt; ein neu angelegtes Konto mit derselben E-Mail übernimmt keine alten Tokens. Login-Versuche sind gedrosselt.

Admins verwalten alle Organisationsdaten und richten für Mitarbeiter Konten ein. Mitarbeiter dürfen nur Mitarbeiter ihres aktuellen Teams einschließlich ihrer selbst lesen. Dabei werden Geburtstag und Telefon nicht ausgegeben. Ebenso dürfen sie ihr eigenes Team und die Positionen ihrer Teammitglieder lesen. Änderungen an Teamzuordnungen wirken sich bei der nächsten Anfrage aus. Passwörter haben 12 bis 72 Bytes; Passwort-Reset, öffentliche Registrierung und E-Mail-Versand sind nicht implementiert.

## API

Alle Daten werden als JSON übertragen. Erfolgreiche Erstellungen liefern `201`, Löschungen `204`. Ungültiges JSON liefert `400`, fehlende oder ungültige Authentifizierung `401`, fehlende Berechtigung `403`, nicht vorhandene oder nicht sichtbare Ressourcen `404`, Konflikte `409` und ungültige Eingaben `422`. Fehlerantworten enthalten `{"error":"..."}`.

| Methode | Pfad | Zugriff |
|---|---|---|
| POST | `/api/login` | Öffentlich |
| GET | `/api/employees`, `/api/employees/{id}` | Admin: alle; Mitarbeiter: eigenes Team |
| POST | `/api/employees` | Admin |
| PUT, DELETE | `/api/employees/{id}` | Admin |
| POST | `/api/employees/{id}/account` | Admin; Body: `{"password":"..."}` |
| GET | `/api/teams`, `/api/teams/{id}` | Admin: alle; Mitarbeiter: eigenes Team |
| GET | `/api/positions`, `/api/positions/{id}` | Admin: alle; Mitarbeiter: Positionen des eigenen Teams |
| POST | `/api/teams`, `/api/positions` | Admin |
| PUT, DELETE | `/api/teams/{id}`, `/api/positions/{id}` | Admin |

`PUT` ersetzt den jeweiligen Datensatz vollständig; ausgelassene optionale Felder werden auf `null` gesetzt. Listen sind JSON-Arrays.

Team- und Positions-Body:

```json
{"name":"Entwicklung","description":"Optional"}
```

Mitarbeiter-Body:

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

`firstName`, `lastName`, `email`, `teamId` und `positionId` sind Pflichtfelder. E-Mail-Adressen werden auf Kleinbuchstaben normalisiert und müssen unter Mitarbeitern eindeutig sein. `birthday` ist optional und verwendet `YYYY-MM-DD`; `phone` ist optional. Team und Position müssen existieren und können nicht gelöscht werden, solange Mitarbeiter zugeordnet sind. Das Löschen eines Mitarbeiters löscht auch dessen Konto. Wird die Mitarbeiter-E-Mail geändert, ändert sich auch die Login-E-Mail des zugehörigen Kontos.

Mitarbeiterantworten enthalten `id`, `firstName`, `lastName`, `email`, `team` und `position`; Admins erhalten zusätzlich `birthday` und `phone`. `team` und `position` sind Objekte mit `id`, `name` und `description`.

## Tests

Die Integrationstests verwenden einen separaten MySQL-Container mit flüchtigem Speicher. Sie löschen vor jedem Test ausschließlich dessen Testdaten; die Suite verweigert die Ausführung gegen andere Datenbank-Hosts oder -Namen.

```bash
docker compose --profile test up -d --wait database_test
docker compose --profile test run --rm --no-deps \
  -e 'DATABASE_URL=mysql://timetracker:timetracker@database_test:3306/timetracker_test?serverVersion=8.4.0&charset=utf8mb4' \
  app php bin/console doctrine:migrations:migrate --no-interaction
docker compose --profile test run --rm --no-deps \
  -e APP_ENV=test \
  -e 'DATABASE_URL=mysql://timetracker:timetracker@database_test:3306/timetracker_test?serverVersion=8.4.0&charset=utf8mb4' \
  app php vendor/bin/phpunit
```

Die Tests benötigen die oben erzeugten lokalen JWT-Schlüssel. `docker compose --profile test stop database_test` beendet die Testdatenbank; ihr flüchtiger Inhalt geht dabei verloren.
