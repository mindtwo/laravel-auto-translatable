# Complete Laravel Tutorial

## Introduction

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling.

## Installation

### Requirements

Before you begin, make sure you have:

- PHP 8.1 or higher
- Composer
- Node.js and NPM

### Steps

1. Install via Composer:

```bash
composer create-project laravel/laravel example-app
```

2. Navigate to your project:

```bash
cd example-app
```

3. Start the development server:

```bash
php artisan serve
```

## Configuration

Edit your `.env` file with database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=
```

## Routing

Laravel makes routing simple. Here's an example:

```php
Route::get('/welcome', function () {
    return view('welcome');
});
```

## Controllers

Create a controller using artisan:

```bash
php artisan make:controller UserController
```

Then define your methods:

```php
class UserController extends Controller
{
    public function index()
    {
        return User::all();
    }
}
```

## Models

Models represent your database tables:

```php
class User extends Model
{
    protected $fillable = ['name', 'email', 'password'];
}
```

## Migrations

Create database tables with migrations:

```bash
php artisan make:migration create_users_table
```

## Eloquent ORM

Laravel's Eloquent ORM makes database interactions elegant:

```php
$users = User::where('active', 1)->get();
```

## Blade Templates

Use Blade for powerful templating:

```blade
@extends('layouts.app')

@section('content')
    <h1>Welcome {{ $name }}</h1>
@endsection
```

## Middleware

Protect your routes with middleware:

```php
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

## Validation

Validate incoming requests:

```php
$request->validate([
    'email' => 'required|email',
    'password' => 'required|min:8',
]);
```

## Testing

Laravel makes testing easy with PHPUnit:

```php
public function test_user_can_register()
{
    $response = $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $response->assertStatus(201);
}
```

## Deployment

Deploy your application to production with confidence. Remember to:

- Set `APP_ENV=production`
- Enable caching: `php artisan config:cache`
- Optimize autoloader: `composer install --optimize-autoloader --no-dev`

For more information, visit [Laravel Documentation](https://laravel.com/docs).