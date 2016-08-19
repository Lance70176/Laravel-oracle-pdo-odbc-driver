Laravel-oracle-pdo-odbc-driver
============

Adds an ODBC driver to Laravel 4.2, usable with Fluent and Eloquent.

最大用途是透過此套件可以連接自定義的界接層 (EX:SQL Relay)在連到 Oracle

如有在 laravel 5.2 需要搭配 SQL Relay 連到 Oracle 的專案

請寄信跟我聯絡


Installation
============

Add `rexlu/laraveloracleodbc` as a requirement to composer.json:


```javascript
{
    "require": {
        "rexlu/laraveloracleodbc": "dev-master"
    }
}
```

Update your packages with `composer update` or install with `composer install`.

or by command

```php
composer require rexlu/laraveloracleodbc:dev-master -vvv
```


Once Composer has installed or updated your packages you need to register 
LaravelODBC and the package it uses (extradb) with Laravel itself. 
Open up `app/config/app.php` and 
find the providers key towards the bottom.


 Add the following to the list of providers:
```php
'rexlu\Laravelodbc\ODBCServiceProvider'
```

Configuration
=============

There is no separate package configuration file for LaravelODBC.  You'll just add a new array to the `connections` array in `app/config/database.php`.

```
        'odbc' => array(
                'driver'   => 'odbc',
                'dsn'      => 'odbc:datasource',
                'charset'  => 'utf8',
                'username' => 'xxxxxUserxxxxxx',
                'password' => 'xxxxxxpasswordxxxx',
                'database' => 'xxxxxxDBxxxxxx',
                'prefix'   => '',
            ),

        ),

```

The ODBC driver is different from the pre-installed ones in that you're going to pass in the DSN instead of having Laravel build it for you.  There are just too many ways to configure an ODBC database for this package to do it for you.
Some sample configurations are at [php.net](http://php.net/manual/en/ref.pdo-odbc.connection.php).

**Don't forget to update your default database connection.**


