# Degradê — SaaS Gestão de Barbearias

Sistema modular de gestão de barbearias brasileiras, desenvolvido com Laravel 12, Vue 3 e PostgreSQL.

## Setup Local

### Requisitos
- Docker + Docker Compose
- Git

### Início Rápido

```bash
# Clonar repositório
git clone <repo> degrade
cd degrade

# Copiar .env
cp .env.example .env

# Instalar dependências
composer install && npm install

# Build frontend
npm run build

# Gerar chave
php artisan key:generate

# Migrar banco
php artisan migrate

# Servidor
php artisan serve
```

### Docker

```bash
docker-compose up -d
docker-compose logs -f app
```

## Desenvolvimento

### Estrutura

```
app/Modules/           # Módulos por feature
├── Tenant/            # Multi-tenancy
├── Auth/              # Autenticação
├── Customer/          # Clientes
├── Barber/            # Barbeiros
├── Service/           # Serviços
├── Appointment/       # Agendamentos (coração)
├── Commission/        # Comissões
└── ... (10+ módulos)

resources/js/          # Frontend Vue 3 + TypeScript
tests/                 # Testes Pest
```

### Comandos

```bash
composer test           # Testes
composer lint           # Format código
npm run type-check     # Type check TS

php artisan migrate    # Migrar
php artisan db:seed    # Seed
php artisan tinker     # REPL
```

## Stack

- **Backend**: PHP 8.4 + Laravel 12 + PostgreSQL + Redis
- **Frontend**: Vue 3 + TypeScript + Inertia.js + TailwindCSS
- **Testing**: Pest
- **Style**: Pint
- **Deploy**: Coolify (Hostinger Brasil)

## Roadmap

Ver `memory/ROADMAP.md`
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
