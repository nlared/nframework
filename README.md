# nframework

<a href="https://deepwiki.com/nlared/nframework"><img src="https://deepwiki.com/badge.svg" alt="Ask DeepWiki"></a>

A comprehensive PHP web framework designed for rapid application development with MongoDB integration, modern UI components, and robust security features.

## 🚀 Features

- **Modern PHP Architecture** - Built with PHP 8+ features and best practices
- **MongoDB Integration** - Native MongoDB support with BSON document handling
- **User Management** - Complete authentication system with 2FA support (TOTP)
- **UI Components** - Rich set of input components and Metro UI integration
- **File Upload System** - Drag & drop file uploads with progress tracking
- **Template Engine** - Twig integration for clean templating
- **Security First** - CSRF protection, input validation, and XSS prevention
- **RESTful API** - Built-in API routing and JSON responses
- **Internationalization** - Multi-language support
- **Email Integration** - PHPMailer support for transactional emails

## 📋 Requirements

- PHP 8.0 or higher
- MongoDB PHP Extension
- Composer
- Web server (Apache/Nginx)

### PHP Extensions Required
- mongodb
- gd or imagick
- mbstring
- openssl
- json

## 🔧 Installation

1. **Clone the repository:**
```bash
git clone https://github.com/yourusername/nframework.git
cd nframework
```

2. **Install dependencies:**
```bash
composer install
```

3. **Configure your environment:**
```bash
cp config.example.php config.php
```

4. **Set up MongoDB connection in `config.php`:**
```php
$config['mongodb_host'] = 'localhost';
$config['mongodb_port'] = 27017;
$config['mongodb_database'] = 'your_database';
```

5. **Configure web server** to point to the nframework directory

## 🎯 Quick Start

### Basic Usage

```php
<?php
require 'include.php';

// Create a simple form
$form = new inputText([
    'name' => 'username',
    'caption' => 'Username',
    'required' => true
]);

echo $form;
```

### Creating a Route

```php
// In router.php
$router->addRoute('/users/profile', function ($route, $params) {
    global $twig, $user;
    
    $template = $twig->load('profile.html');
    echo $template->render([
        'user' => $user,
        'title' => 'User Profile'
    ]);
}, ['GET', 'POST']);
```

### Working with MongoDB

```php
// Create a user
$user = new User([
    'username' => 'john_doe',
    'email' => 'john@example.com',
    'password' => password_hash('secret', PASSWORD_DEFAULT)
]);
$user->save();

// Find users
$users = $m->users->find(['active' => true]);
```

## 🏗️ Architecture

### Core Components

- **Base Classes** (`class.Base.php`) - Foundation input and UI components
- **User Management** (`class.User.php`) - Authentication and user handling
- **Router** (router.php) - URL routing and request handling
- **Template Engine** - Twig integration for views
- **Database Layer** - MongoDB abstraction and helpers

### Directory Structure

```
nframework/
├── includes/           # Core framework files
│   ├── class.Base.php     # UI components and base classes
│   ├── class.User.php     # User management
│   ├── functions.php      # Utility functions
│   └── include.php        # Main bootstrap file
├── templates/          # Twig templates
├── api/               # API endpoints
├── config.php         # Configuration file
├── router.php         # Main routing file
└── uploadfile.php     # File upload handler
```

## 🎨 UI Components

### Input Components

```php
// Text input
$textInput = new inputText([
    'name' => 'title',
    'caption' => 'Article Title',
    'required' => true,
    'validate' => 'required minlength=5'
]);

// File upload
$fileUpload = new inputFile([
    'name' => 'document',
    'path' => '/uploads/documents/',
    'accept' => '.pdf,.doc,.docx'
]);

// Date picker
$dateInput = new inputDate([
    'name' => 'publish_date',
    'caption' => 'Publish Date',
    'value' => date('Y-m-d')
]);
```

### Forms with CSRF Protection

```php
echo secureform('/submit', false, 'myform');
// Outputs form with automatic CSRF token
```

## 🔐 Security Features

### Two-Factor Authentication (2FA)

```php
// Setup TOTP for user
$totp = TOTP::create();
$user->totp_secret = $totp->getSecret();

// Verify code
if ($totp->verify($_POST['code'])) {
    // Valid code
}
```

### Input Validation

```php
$input = new inputText([
    'validate' => 'required email',
    'pattern' => '[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}'
]);
```

## 📧 Email Integration

```php
use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);
$mail->setFrom('noreply@yoursite.com');
$mail->addAddress('user@example.com');
$mail->Subject = 'Welcome!';
$mail->Body = 'Welcome to our platform!';
$mail->send();
```

## 🌍 Internationalization

```php
// In your templates
{{ lng.welcome_message }}

// In PHP
$message = $nframework->language['welcome_message'];
```

## 📁 File Uploads

```php
$upload = new inputFiles([
    'dir' => '/uploads/gallery/',
    'countlimit' => 10,
    'accept' => 'image/*',
    'preview' => true,
    'delete' => true
]);
```

## 🔌 API Development

```php
// Create API endpoint
$router->addRoute('/api/users', function ($route, $params) {
    header('Content-Type: application/json');
    
    $users = $m->users->find()->toArray();
    echo json_encode(['users' => $users]);
}, 'GET');
```

## 🧪 Testing

The framework includes a comprehensive test suite using PHPUnit.

### Running Tests

Run all tests:
```bash
./run-tests.sh
```

Run only unit tests:
```bash
./run-tests.sh unit
```

Run only feature tests:
```bash
./run-tests.sh feature
```

Generate coverage report:
```bash
./run-tests.sh coverage
```

Or use PHPUnit directly:
```bash
./includes/vendor/bin/phpunit
./includes/vendor/bin/phpunit --testsuite Unit
./includes/vendor/bin/phpunit tests/Unit/FunctionsTest.php
```

### Test Structure

```
tests/
├── bootstrap.php           # Test initialization
├── Unit/                   # Unit tests
│   ├── FunctionsTest.php      # Utility functions tests
│   ├── BaseClassTest.php      # Base classes tests
│   ├── UserTest.php           # User authentication tests
│   └── ComponentsTest.php     # Component tests
└── Feature/                # Integration tests
    └── IntegrationTest.php    # Feature tests
```

See [tests/README.md](tests/README.md) for detailed testing documentation.

## 📖 Documentation

- API Documentation
- Component Reference
- Security Guide
- Deployment Guide

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add amazing feature'`
4. Push to the branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

### Development Setup

```bash
# Install development dependencies
composer install --dev

# Run code quality tools
./vendor/bin/phpcs
./vendor/bin/phpstan analyze
```

## 📝 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🙏 Acknowledgments

- [Twig](https://twig.symfony.com/) - Template engine
- [MongoDB PHP Library](https://github.com/mongodb/mongo-php-library)
- [PHPMailer](https://github.com/PHPMailer/PHPMailer)
- [OTPHP](https://github.com/Spomky-Labs/otphp) - TOTP implementation
- [Intervention Image](https://image.intervention.io/) - Image processing

## 📞 Support

- Create an [Issue](https://github.com/yourusername/nframework/issues) for bug reports
- Join our [Discussions](https://github.com/yourusername/nframework/discussions) for questions
- Check the [Wiki](https://github.com/yourusername/nframework/wiki) for detailed guides

---

**Built with ❤️ for modern PHP web development**