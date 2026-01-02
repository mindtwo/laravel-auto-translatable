# Vollständiges Laravel-Tutorial

## Einführung

Laravel ist ein Webanwendungs-Framework mit ausdrucksstarker, eleganter Syntax. Wir glauben, dass Entwicklung eine angenehme und kreative Erfahrung sein muss, um wirklich erfüllend zu sein.

## Installation

### Anforderungen

Bevor Sie beginnen, stellen Sie sicher, dass Sie haben:

- PHP 8.1 oder höher
- Composer
- Node.js und NPM

### Schritte

1. Installation über Composer:

```bash
composer create-project laravel/laravel example-app
```

2. Navigieren Sie zu Ihrem Projekt:

```bash
cd example-app
```

3. Starten Sie den Entwicklungsserver:

```bash
php artisan serve
```

## Konfiguration

Bearbeiten Sie Ihre `.env` Datei mit Datenbankzugangsdaten:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=
```

## Routing

Laravel macht Routing einfach. Hier ist ein Beispiel:

```php
Route::get('/welcome', function () {
    return view('welcome');
});
```

## Controller

Erstellen Sie einen Controller mit artisan:

```bash
php artisan make:controller UserController
```

Dann definieren Sie Ihre Methoden:

```php
class UserController extends Controller
{
    public function index()
    {
        return User::all();
    }
}
```

## Modelle

Modelle repräsentieren Ihre Datenbanktabellen:

```php
class User extends Model
{
    protected $fillable = ['name', 'email', 'password'];
}
```

## Migrationen

Erstellen Sie Datenbanktabellen mit Migrationen:

```bash
php artisan make:migration create_users_table
```

## Eloquent ORM

Laravels Eloquent ORM macht Datenbankinteraktionen elegant:

```php
$users = User::where('active', 1)->get();
```

## Blade-Templates

Verwenden Sie Blade für leistungsstarke Vorlagen:

```blade
@extends('layouts.app')

@section('content')
    <h1>Welcome {{ $name }}</h1>
@endsection
```

## Middleware

Schützen Sie Ihre Routen mit Middleware:

```php
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```