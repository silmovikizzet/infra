# Infra

**Infra** is a Laravel-based network infrastructure management and monitoring platform designed to simplify device management, monitoring, and infrastructure operations from a centralized web dashboard.

The project focuses on network infrastructure, switch and router management, SSH-based device communication, interface monitoring, and extensible infrastructure automation.

---

## Features

- Network device management
- Router and switch inventory
- Device credential management
- Site and location management
- SSH-based device communication
- Remote command execution
- Switch interface monitoring
- Interface status: UP, DOWN, and DISABLED
- Visual switch port mapping
- RJ45, SFP, SFP+, and QSFP port visualization
- Interface search and filtering
- Infrastructure monitoring dashboard
- User-based access control
- Interactive UI powered by Livewire
- Spreadsheet import and export
- Extensible device command architecture
- Extensible parser architecture for multiple network vendors

---

## Technology Stack

- Laravel 12
- PHP 8.2+
- Livewire 3
- Bootstrap
- Vite
- MySQL / MariaDB
- phpseclib
- PhpSpreadsheet

---

## Requirements

Make sure the following software is installed:

- PHP >= 8.2
- Composer
- Node.js
- NPM
- MySQL or MariaDB
- Git

Recommended PHP extensions:

```text
BCMath
Ctype
cURL
DOM
Fileinfo
JSON
Mbstring
OpenSSL
PDO
PDO MySQL
Tokenizer
XML
Zip
```

---

# Installation

## 1. Clone Repository

```bash
git clone https://github.com/silmovikizzet/infra.git
```

Enter the project directory:

```bash
cd infra
```

---

## 2. Install PHP Dependencies

```bash
composer install
```

---

## 3. Create Environment File

Copy the example environment file:

```bash
cp .env.example .env
```

Generate Laravel application key:

```bash
php artisan key:generate
```

---

## 4. Configure Database

Create a new MySQL or MariaDB database.

Then edit the `.env` file:

```env
APP_NAME=Infra
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=infra
DB_USERNAME=root
DB_PASSWORD=
```

Adjust the values according to your environment.

---

## 5. Run Database Migration

```bash
php artisan migrate
```

If database seeders are available:

```bash
php artisan db:seed
```

Or run migrations and seeders together:

```bash
php artisan migrate --seed
```

---

## 6. Install Frontend Dependencies

```bash
npm install
```

For development:

```bash
npm run dev
```

For production:

```bash
npm run build
```

---

## 7. Start Application

Start the Laravel development server:

```bash
php artisan serve
```

Open:

```text
http://127.0.0.1:8000
```

---

# Development

You can also start Laravel development services using:

```bash
composer run dev
```

This can run the application server, queue worker, logs, and Vite development server together.

---

# Network Device Communication

Infra uses SSH communication to interact with supported network devices.

The general workflow is:

```text
Infra
  │
  ├── Device
  │
  ├── Credential
  │
  ├── SSH Connection
  │
  ├── Device Command
  │
  ├── Parser
  │
  └── Dashboard
```

Commands and parsers can be separated by device or vendor, making it easier to add support for additional network platforms.

---

# Switch Interface Monitoring

Infra can retrieve and display switch interface information.

Available interface states can include:

```text
UP
DOWN
DISABLED
```

Interface information can include:

- Interface name
- Interface description
- Link status
- Administrative status
- Port type
- Physical port position

Supported visual port types can include:

```text
RJ45 / Ethernet
SFP
SFP+
QSFP
```

---

# Project Structure

Important application directories:

```text
app/
├── Console/
│   └── Commands/
├── Http/
│   └── Controllers/
├── Livewire/
├── Models/
├── Providers/
└── Services/
```

### `app/Livewire`

Contains interactive dashboard components.

### `app/Models`

Contains application and infrastructure models.

### `app/Services`

Contains infrastructure business logic such as device communication, command processing, and integrations.

### `app/Console/Commands`

Contains CLI commands and scheduled infrastructure operations.

### `app/Http/Controllers`

Contains HTTP controllers and request handling.

---

# Queue Worker

If the application uses Laravel queues, start the worker with:

```bash
php artisan queue:work
```

For development:

```bash
php artisan queue:listen
```

For production environments, using Supervisor or systemd is recommended.

---

# Laravel Scheduler

If scheduled commands are used, add Laravel Scheduler to cron:

```bash
* * * * * cd /path/to/infra && php artisan schedule:run >> /dev/null 2>&1
```

---

# Production Deployment

Install optimized production dependencies:

```bash
composer install --no-dev --optimize-autoloader
```

Install frontend dependencies:

```bash
npm install
```

Build production assets:

```bash
npm run build
```

Run migrations:

```bash
php artisan migrate --force
```

Optimize Laravel:

```bash
php artisan optimize
```

Make sure Laravel writable directories have the correct permissions:

```bash
chmod -R 775 storage bootstrap/cache
```

---

# Security

Infrastructure management systems may contain sensitive data such as:

- SSH credentials
- Device credentials
- Network topology
- Internal IP addresses
- Infrastructure metadata

Never commit your `.env` file or production credentials to Git.

Recommended production security practices:

- Use HTTPS
- Use strong credentials
- Restrict database access
- Restrict SSH access
- Configure firewall rules
- Use role-based access control
- Keep dependencies updated
- Monitor application logs
- Store secrets only in environment variables or secure secret storage

---

# Configuration

Environment-specific configuration should be stored in:

```text
.env
```

Application configuration should be stored inside:

```text
config/
```

Avoid hard-coding passwords, SSH credentials, tokens, internal IP addresses, or secrets directly in the source code.

---

# Useful Commands

Clear Laravel caches:

```bash
php artisan optimize:clear
```

Optimize Laravel:

```bash
php artisan optimize
```

Cache configuration:

```bash
php artisan config:cache
```

Cache routes:

```bash
php artisan route:cache
```

Cache views:

```bash
php artisan view:cache
```

Run tests:

```bash
php artisan test
```

Open Laravel Tinker:

```bash
php artisan tinker
```

---

# Updating

Pull the latest version:

```bash
git pull
```

Install backend dependencies:

```bash
composer install
```

Install frontend dependencies:

```bash
npm install
```

Run database migrations:

```bash
php artisan migrate
```

Build frontend assets:

```bash
npm run build
```

Clear and rebuild Laravel caches:

```bash
php artisan optimize:clear
php artisan optimize
```

---

# Contributing

Create a new branch:

```bash
git checkout -b feature/my-feature
```

Commit your changes:

```bash
git add .
git commit -m "Add new infrastructure feature"
```

Push the branch:

```bash
git push origin feature/my-feature
```

Then create a Pull Request.

---

# GitHub Description

Recommended repository description:

```text
Network infrastructure management, monitoring and automation platform built with Laravel and Livewire.
```

Recommended GitHub topics:

```text
laravel
livewire
networking
network-monitoring
network-automation
network-management
infrastructure
ssh
switch
router
devops
```

---

# Author

**Silmovik**

Infrastructure • Networking • Automation • Software Engineering
