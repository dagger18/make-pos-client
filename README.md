# make-cargo-client
## Installation
install Symfony vendors
`composer install`
## Database
to run migration on shared client sqlite on bash
`DATABASE_URL="sqlite:///var/sqlite/<client_id>.db" php bin/console d:m:m --no-interaction`
to run migration on shared client sqlite on powershell
`$env:DATABASE_URL="sqlite:///var/sqlite/<client_id>.db"; php bin/console d:m:m --no-interaction`

### To make new migration on sqlite or mysql, you should change the env
for sqlite:
`DATABASE_URL="sqlite:///var/sqlite/<client_id>.db"`
for mysql:
`DATABASE_URL="mysql://<username>:<password>@<host>/<dbname>"`

then
`php bin/console d:m:di -n --namespace SqlEngineMigrations`

## Backup Media Server
run `python app.py`


## Build css for pdf
`npm install`
`npm run build`

## Translations

Files: `translations/messages+intl-icu.{locale}.po` — locales: `zh`, `vi`, `ja`, `ko`, `de`, `es`, `ar`.

### Extract new strings to all .po files

After adding new `$this->trans('...')` calls in PHP, run once per locale:

```bash
php bin/console translation:extract --force --format=po zh
php bin/console translation:extract --force --format=po vi
php bin/console translation:extract --force --format=po ja
php bin/console translation:extract --force --format=po ko
php bin/console translation:extract --force --format=po de
php bin/console translation:extract --force --format=po es
php bin/console translation:extract --force --format=po ar
```

New entries are appended with `msgstr "__<original string>"` — the `__` prefix marks strings that still need translation.

Then open `translations/messages+intl-icu.{locale}.po`, replace `__` prefixed `msgstr` values with actual translations, and clear the cache:

```bash
php bin/console cache:clear
```