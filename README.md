# make-pos-client

Symfony PHP backend API for the make-pos SaaS POS platform.

## Setup

```bash
composer install
cp .env.example .env
# Edit .env with your database credentials
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:warmup
symfony serve
```
