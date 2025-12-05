# Flowaxy CMS

**Flowaxy CMS** is a modern modular content management system (CMS) built on PHP 8.4 with a focus on modularity, security, performance, and extensibility.

## 🎯 Key Features

### Modular Architecture
Flowaxy CMS is built on modular architecture principles, where each component is an independent module:
- **Core** - basic system functionality
- **Plugins** - functionality extensions
- **Themes** - visual design
- **Modules** - additional components

### Plugin System
- **Full Isolation** - plugins run in an isolated environment
- **Lifecycle** - support for install, activate, deactivate, uninstall
- **Hooks & Filters** - extend functionality through hook system
- **Autonomy** - plugins cannot conflict with each other
- **Caching** - optimized caching of plugin metadata

### Theme System
- **Modular Structure** - themes consist of components and blocks
- **Customization** - flexible theme settings system
- **Isolation** - themes are isolated from core and plugins
- **Components** - reusable UI components

### Core System
- **Modular Structure** - divided into Contracts, Database, Events, Filesystem, Hooks, Http, Models, Routing, Security, Services
- **Dependency Injection** - full-featured DI container
- **Event System** - event system for extending functionality
- **Hook System** - powerful hooks and filters system
- **Query Builder** - convenient SQL query builder
- **Multi-level Cache** - multi-level caching

### Component Isolation
- **Plugins isolated** from core and other plugins
- **Themes isolated** from core and plugins
- **Security** - prevents conflicts and crashes
- **Containers** - separate containers for plugins and themes

## 🏗️ Architecture

```
flowaxy.local/
├── engine/                    # System core
│   ├── application/          # Application services
│   │   ├── content/          # Content services (plugins, themes)
│   │   ├── security/         # Security services
│   │   └── testing/          # Testing infrastructure
│   ├── Cache/                # Caching system
│   ├── Console/              # CLI commands
│   ├── Contracts/            # Interfaces and contracts
│   ├── core/                 # Base core
│   │   ├── bootstrap/       # System bootstrapping
│   │   ├── config/           # Configuration
│   │   ├── providers/        # Service providers
│   │   └── system/           # System classes
│   ├── Database/             # Database layer
│   ├── domain/               # Domain models
│   ├── Events/               # Event system
│   ├── Filesystem/           # File operations
│   ├── Hooks/                # Hook system
│   ├── Http/                 # HTTP layer
│   ├── Models/               # Data models
│   ├── Routing/              # Routing
│   ├── Security/             # Security
│   ├── Services/             # Services
│   └── Support/               # Helper classes
│       ├── Facades/          # Facade pattern
│       ├── Helpers/           # Helpers
│       ├── Managers/          # Managers
│       └── Isolation/         # Isolation
├── plugins/                  # Plugins
├── themes/                   # Themes
└── index.php                 # Entry point
```

## 🚀 Main Capabilities

### Security
- **XSS Protection** - automatic data sanitization
- **CSRF Protection** - protection against cross-site requests
- **SQL Injection Protection** - parameterized queries
- **Rate Limiting** - request rate limiting
- **Security Headers** - automatic security headers
- **CSP Generator** - content security policy generator
- **Encryption** - data encryption

### Performance
- **Multi-level Cache** - multi-level caching
- **Query Optimization** - SQL query optimization
- **Lazy Loading** - deferred module loading
- **Class Map** - fast class autoloading
- **Connection Pooling** - database connection pool
- **Cache Warmers** - cache pre-warming

### Extensibility
- **Hook System** - Actions and Filters
- **Event System** - events and listeners
- **Service Providers** - service registration
- **Facades** - convenient access to services
- **CLI Commands** - console commands

### Development
- **Testing Framework** - complete testing infrastructure
  - Unit tests
  - Integration tests
  - Functional tests
  - Performance tests
- **Code Generators** - code generators
  - `make:controller` - create controllers
  - `make:model` - create models
  - `make:plugin` - create plugins
- **CLI Tools** - development tools
  - `code:check` - code checking
  - `code:analyze` - code analysis
  - `isolation:check` - isolation checking
  - `performance:test` - performance testing

## 📋 Requirements

- **PHP** >= 8.4
- **MySQL** >= 5.7 or **MariaDB** >= 10.3
- **Extensions**: PDO, PDO_MySQL, JSON, MBString, OpenSSL
- **Web Server**: Apache with mod_rewrite or Nginx

## 🔧 Installation

1. Clone the repository:
```bash
git clone https://github.com/flowaxy/platform.git
cd platform
```

2. Configure settings:
```bash
cp storage/config/database.ini.example storage/config/database.ini
# Edit database.ini with your database credentials
```

3. Run migrations:
```bash
php flowaxy migrate
```

4. Create administrator:
```bash
php flowaxy user:create --admin
```

## 📖 Documentation

- [Core Architecture](engine/core/README.md)
- [Hook System](engine/Hooks/README.md)
- [Cache System](engine/Cache/README.md)
- [Database](engine/Database/README.md)
- [Security](engine/Security/README.md)
- [Plugins](engine/Models/README.md)
- [CLI Commands](engine/Console/README.md)

## 🛠️ Development

### Running Tests
```bash
php flowaxy test
```

### Code Checking
```bash
php flowaxy code:check
php flowaxy code:analyze
```

### Isolation Checking
```bash
php flowaxy isolation:check
```

### Performance Testing
```bash
php flowaxy performance:test
```

## 📝 Creating a Plugin

```bash
php flowaxy make:plugin my-plugin
```

Plugin structure:
```
plugins/my-plugin/
├── plugin.json          # Plugin metadata
├── my-plugin.php        # Main file
├── db/                  # Database migrations
└── assets/              # Resources
```

## 🎨 Creating a Theme

Themes are located in `themes/` and contain:
- `theme.json` - theme metadata
- `templates/` - templates
- `assets/` - styles and scripts
- `components/` - UI components

## 🔐 Security

Flowaxy CMS includes comprehensive protection:
- Automatic input sanitization
- CSRF tokens for forms
- Parameterized SQL queries
- Validation and sanitization
- Secure sessions
- Rate limiting

## 🚀 Performance

- Optimized class autoloading
- Multi-level caching
- SQL query optimization
- Lazy loading modules
- Connection pooling

## 📄 License

[Specify license]

## 👥 Authors

Flowaxy CMS Team

## 🔗 Links

- [GitHub](https://github.com/flowaxy/platform)
- [Documentation](https://docs.flowaxy.com)
- [Forum](https://forum.flowaxy.com)

---

**Version:** 1.0.0 Alpha prerelease
