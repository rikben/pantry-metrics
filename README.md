<!-- /README.md -->
# Pantry Metrics

Pantry Metrics is an extensible PHP, JavaScript, and MySQL/MariaDB application for calculating nutrition values for customized recipes.

The first version provides:

- Products with nutrition values per reference amount
- Recipes and recipe ingredients
- Adjustable ingredient quantities
- Adjustable serving counts
- Nutrition totals and per-serving calculations
- A migration runner
- Docker-based local development
- An authentication boundary prepared for future OIDC/SAML single sign-on

## Repository name

Recommended repository name: `pantry-metrics`

Alternative names:

- `recipe-nutrition-lab`
- `ingredient-metrics`
- `recipe-refinery`

## Requirements

- Docker and Docker Compose, or
- PHP 8.2+ with PDO MySQL and MySQL 8/MariaDB

## Local development

```bash
cp .env.example .env
docker compose up --build -d
docker compose exec app php database/migrate.php
```

Open:

```text
http://localhost:8080
```

The database is exposed locally on port `3307`.

## Project structure

```text
.
├── public_html/          Web document root
│   ├── app/              Application code
│   ├── assets/           CSS and JavaScript
│   ├── config/           Application configuration
│   ├── views/            PHP templates
│   └── index.php         Front controller
├── database/
│   ├── init/             One-time container initialization
│   ├── migrations/       Versioned schema changes
│   └── migrate.php       Migration runner
├── docker/               Apache configuration
├── storage/              Logs and cache
├── storage-backups/      Local database backups
├── Dockerfile
└── docker-compose.yml
```

## Security foundations

- Only `public_html` is exposed through Apache.
- Secrets live in `.env`, which is ignored by Git.
- Database access uses PDO prepared statements.
- State-changing requests use CSRF validation.
- Session cookies are configurable and use `HttpOnly` and `SameSite`.
- Authentication is accessed through an interface, allowing a future OIDC/SAML implementation without rewriting controllers.
- Security headers are set centrally.

## Future SSO integration

Implement a provider such as `OidcAuthService` that implements `AuthServiceInterface`, then replace `DevelopmentAuthService` in the application bootstrap. Store the external identity in `users.external_subject` and the issuer in `users.identity_provider`.

Recommended future packages:

- `jumbojett/openid-connect-php` for generic OpenID Connect
- An identity-provider-specific SDK where appropriate
- Composer autoloading once external packages are introduced

## Database migrations

Add numbered SQL files to `database/migrations`, for example:

```text
002_add_recipe_tags.sql
003_add_product_sources.sql
```

Run:

```bash
docker compose exec app php database/migrate.php
```

## GitHub publishing checklist

1. Copy `.env.example` to `.env`.
2. Never commit `.env`, backups, logs, or production credentials.
3. Initialize Git and commit the generated project.
4. Enable branch protection and dependency scanning.
5. Add a license before making the repository public.
6. Add CI for PHP syntax checks and migrations.
