# FailRun · Backend

API REST de la plataforma FailRun, desenvolupada amb Symfony 7.4.
Gestiona els clips, usuaris, valoracions, peticions i el panel d'administració.

## Stack tècnic

- PHP 8.3
- Symfony 7.4
- Doctrine ORM 3.6
- MariaDB (producció) / MySQL (local)
- Lexik JWT Authentication Bundle (autenticació)
- Nelmio API Doc Bundle (documentació Swagger)
- Nelmio CORS Bundle (gestió CORS)
- Twig (panel d'administració)

## Arquitectura

L'API segueix el patró MVC amb estructura estàndard de Symfony:

```src/
├── Controller/    — Controladors API i panel admin
├── Entity/        — Entitats Doctrine
├── Repository/    — Repositoris per a consultes BBDD
├── Security/      — Autenticadors JWT i API Key
└── Form/          — Formularis del panel admin

## Sistemes d'autenticació

L'API té 3 sistemes d'autenticació segons el cas d'ús:

1. JWT — per als usuaris del frontend (Authorization: Bearer)
2. Sessió — per al panel d'administració (form login)
3. API Key — per a serveis externs (header X-API-KEY)

## Documentació de l'API

Documentació pública i interactiva amb Swagger:GET /api/doc

## Requisits previs

- PHP 8.2 o superior
- Composer
- MySQL / MariaDB
- Symfony CLI (opcional)

## Instal·lació local

```bash1. Clonar el repositori
git clone <url-del-repo>
cd failrun-backend2. Instal·lar dependències
composer install3. Crear .env.local amb les variables d'entorn
cp .env .env.local4. Generar claus JWT
php bin/console lexik:jwt:generate-keypair5. Crear la base de dades i executar migracions
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate6. Arrencar el servidor
symfony server:start

## Variables d'entorn

| Variable | Descripció |
|---|---|
| `APP_ENV` | Entorn (`prod` / `dev`) |
| `APP_SECRET` | Clau secreta de Symfony |
| `DATABASE_URL` | URL de connexió a la BBDD |
| `JWT_SECRET_KEY` | Clau privada JWT (base64) |
| `JWT_PUBLIC_KEY` | Clau pública JWT (base64) |
| `JWT_PASSPHRASE` | Passphrase de les claus |
| `OPENROUTER_API_KEY` | API Key per al chatbot del panel admin |

En producció, les variables s'injecten des d'AWS Secrets Manager.

## Estructura de branques

- `main` — versió de producció
- `feature/*` — desenvolupament de noves funcionalitats

## Desplegament

El desplegament és automàtic via GitHub Actions a cada push a `main`:

1. Build de la imatge Docker
2. Push de la imatge a AWS ECR
3. Actualització de la task definition d'ECS
4. Desplegament al servei ECS amb llançament Fargate

L'aplicació corre a AWS ECS darrere d'un Application Load Balancer.

## Funcionalitats principals

- API REST completa de clips, jocs, valoracions i peticions
- Autenticació JWT amb RS256
- Sistema d'API Keys per a consumidors externs
- Panel d'administració amb gestió de totes les entitats
- Chatbot IA al panel admin (proxy a OpenRouter)
- Documentació automàtica amb Swagger

## Autors

- Àlex Carrasco
- Houssam Essarhiar
