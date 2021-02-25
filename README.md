# Lucinda Migrations

Table of contents:

- [About](#about)
    - [Methodology Used](#methodology-used)
    - [Implementation](#implementation)
- [Installation](#installation)
    - [Setting Cache](#setting-cache)
    - [Execution](#execution)
- [Console Commands](#console-commands)
    - [generate](#how-does-generate-command-work)
    - [migrate](#how-does-migrate-command-work)
    - [up](#how-does-up-command-work)
    - [down](#how-does-down-command-work)
- [Reference Guide](#reference-guide)

## About

This API serves as platform able to automate **data migration** between development environments (eg: changes in database structure) useful in systems powered by continuous integration/delivery. Modeled on Symfony [DoctrineMigrationsBundle](https://symfony.com/doc/current/bundles/DoctrineMigrationsBundle/index.html) operations, it performs its task in a starkly different way:

- like other Lucinda libraries, it is built with simplicity, flexibility and efficiency in mind (thus light weight as an effect). It is completely independent, not bundled to any library or framework, thus can be used in *any* PHP context whether Lucinda Framework is used or not
- it works like a skeleton onto which various migration patterns need to be built on (eg: SQL tables migration). This means it doesn't assume by default that database is to be migrated: *anything subject of being synchronized between development environments falls subject to migrations*

### Methodology Used

When developing this API I asked myself: *what do all migrations, regardless of what they migrate, have in common*? Following aspects came to my mind:

- a **migration** should a class able to execute one or more related operations on multiple environments
- class operations should be *transactional*, meaning it must have COMMIT/ROLLBACK actions
- API needs an ability to *generate* a migration class on developer request
- developer must *program* generated migration (eg: with queries)
- API needs an ability to *find* and *execute* migrations.
- API needs an ability to *track* migrations progress across environments
- tracking requires assigning every migration class to a status: PENDING, FAILED, PASSED
- once developer executed migrations on his environment and made sure all is fine, he must *commit* it (eg: to GIT)
- other developers pull new changes, see that new migrations were added, then execute migrations to keep their environment updated
- continuous integration/deployment system (eg: TeamCity) will automatically execute migrations on every build, thus keeping all environments up to date

This API only performs the logistics for API-level operations above without taking any assumption on:

- what is the subject of migrations (eg: is it SQL table related?)
- what is the storage medium of tracking migrations progress (eg: is it SQL table?)

Migration operations supported will be:

- generating migration
- running all migrations (whose status is PENDING or FAILED), which equates to a global commit on each
- committing individual migration (whose status is PENDING or FAILED)
- rolling back individual migration (whose status is PASSED)

Note that unlike DoctrineMigrationsBundle, **diff** and **dump-schema** operations will not be supported by default because they make both *subject and implementation assumption* (that SQL database is subject and that DAO implementation uses ORM model).

### Implementation

True to its goal of creating a *migration skeleton* for specialization to be built upon, this API only defines common logistics:

- [Cache](https://github.com/aherne/migration/blob/master/src/Cache.php): blueprint for structure where migration progress is saved and tracked
- [Script](https://github.com/aherne/migration/blob/master/src/Script.php): blueprint for a migration class supporting commit/rollback operations
- [Result](https://github.com/aherne/migration/blob/master/src/Result.php): class that implements results of a migration (class operation) execution
    - [Status](https://github.com/aherne/migration/blob/master/src/Status.php): enum that collects possible migration statuses (PENDING, PASSED, FAILED)
- [Wrapper](https://github.com/aherne/migration/blob/master/src/Wrapper.php): class that binds all four above in order to find and execute migration operations
- [ConsoleExecutor](https://github.com/aherne/migration/blob/master/src/ConsoleExecutor.php): class that envelopes [Wrapper](https://github.com/aherne/migration/blob/master/src/Wrapper.php) to display migration operation results on console.

API is fully PSR-4 compliant, unit tested, only requiring PHP7.1+ interpreter and [Console Table API](https://github.com/aherne/console_table) (for displaying migration results on console). All classes inside belong to namespace **Lucinda\Migration**! To quickly see how it works, check:

- **installation**: downloading API using composer, creating folder to store migrations into
- **setting cache**: setting up a [Cache](https://github.com/aherne/migration/blob/master/src/Cache.php) that stores migration progress
- **execution**: using [Wrapper](https://github.com/aherne/migration/blob/master/src/Wrapper.php) to run migrations or [ConsoleExecutor](https://github.com/aherne/migration/blob/master/src/ConsoleExecutor.php) to display results in console and one or more migration [Script](https://github.com/aherne/migration/blob/master/src/Script.php)s

## Installation

To install this API, go to your project root and run:

```bash
composer require lucinda/migrations
```

Then create a **migrations.php** script that will execute migrations:

```php
require(__DIR__."/vendor/autoload.php");

// defines folder to store migrations and creates it if not exists
$folder = "migrations";
if (!file_exists($folder)) {
  mkdir($folder);
}

// TODO: instance a Lucinda\Migration\Cache into $cache variable

// run migrations based on console input
$executor = new Lucinda\Migration\ConsoleExecutor($folder, $cache);
$executor->execute((isset($argv[1])?$argv[1]:"migrate"), (isset($argv[2])?$argv[2]:""));
```

### Setting Cache

Implementing migration [Cache](https://github.com/aherne/migration/blob/master/src/Cache.php) is outside the scope of skeleton API, which makes no assumption on what is the subject of migrations or how should cache be stored (eg: could be MySQL, could be Amazon DynamoDB).

An example of a [Cache](https://github.com/aherne/migration/blob/master/src/Cache.php) implementation binding to [SQL Data Access API ](https://github.com/aherne/php-sql-data-access-api), using a MySQL table to store info:

```php
class TableCache implements \Hlis\Migration\Cache
{    
    private $tableName;

    public function __construct(string $tableName)
    {
        $this->tableName = $tableName;
    }

    public function exists(): bool
    {
        return !empty(SQL("SHOW TABLES LIKE '".$this->tableName."'")->toRow());
    }

    public function create(): void
    {
        SQL("
        CREATE TABLE ".$this->tableName."
        (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        class_name VARCHAR(255) NOT NULL,
        is_successful BOOLEAN NOT NULL DEFAULT TRUE,
        date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        UNIQUE(class_name)
        ) Engine=INNODB");
    }

    public function read(): array
    {
        return SQL("SELECT class_name, IF(is_successful=0,2,3) AS status FROM ".$this->tableName."")->toMap("class_name", "status");
    }

    public function add(string $className, int $statusCode): void
    {
        $isSuccessful = ($statusCode==\Hlis\Migration\Status::PASSED);
        $results = SQL("UPDATE ".$this->tableName." SET is_successful=:status, date=NOW() WHERE class_name=:name", [
            ":status"=>$isSuccessful,
            ":name"=>$className            
        ])->getAffectedRows();
        if ($results == 0) {
            SQL("INSERT INTO ".$this->tableName." SET is_successful=:status, class_name=:name", [
                ":status"=>$isSuccessful,
                ":name"=>$className
            ]);
        }
    }

    public function remove(string $className): void
    {
        SQL("DELETE FROM ".$this->tableName." WHERE class_name=:name", [":name"=>$className]);
    }
}
```

Code for SQL function used above:

```php
function SQL(string $query, array $boundParameters = array()): \Lucinda\SQL\StatementResults
{
    $preparedStatement = \Lucinda\SQL\ConnectionSingleton::getInstance()->createPreparedStatement();
    $preparedStatement->prepare($query);
    return $preparedStatement->execute($boundParameters);
}
```

### Execution

Once a [Cache](https://github.com/aherne/migration/blob/master/src/Cache.php) is implemented, you can complete migration script and perform migrations. Example using the two classes in previous section:

```php
require(__DIR__."/vendor/autoload.php");

// loads dependencies that bind Cache and Script to SQL Data Access API
require(__DIR__."/TableCache.php");
require(__DIR__."/SQL.php");

// defines folder to store migrations and creates it if not exists
$folder = "migrations";
if (!file_exists($folder)) {
  mkdir($folder);
}

// sets up Lucinda SQL Data Access API for current development environment based on XML
new Lucinda\SQL\Wrapper(simplexml_load_file("xml/servers.xml"), getenv("ENVIRONMENT"));

// run migrations based on console input, saving cache to SQL table "migrations"
$executor = new Lucinda\Migration\ConsoleExecutor($folder, new TableCache("migrations"));
$executor->execute((isset($argv[1])?$argv[1]:"migrate"), (isset($argv[2])?$argv[2]:""));
```

Above example will work if:

- an environment variable called "ENVIRONMENT" is set already, whose value is your development environment name
- mysql connection credentials are set for current development environment as required in [configuration](https://github.com/aherne/php-sql-data-access-api#configuration) section of SQL Data Access API
- current database user has full rights on target schema (incl. CREATE). If not, run code @ *create* method manually!

You can now go to folder where API was installed and execute *generate* commands, go to **migrations** folder and fill up/down methods accordingly. Once at least one migration [Script](https://github.com/aherne/migration/blob/master/src/Script.php) is filled, you will be able to execute migrations from console.

To maximize flexibility, developers that do not want to use console output provided by [ConsoleExecutor](https://github.com/aherne/migration/blob/master/src/ConsoleExecutor.php) can work with [Wrapper](https://github.com/aherne/migration/blob/master/src/Wrapper.php) directly and handle output by themselves.

## Console Commands

API allows following console commands:

| Argument#1 | Argument#2 | Description |
| --- | --- | --- |
| generate |  | Generates a template [Script](https://github.com/aherne/migration/blob/master/src/Script.php) class in **migrations** folder outputs its name |
| migrate |  | (default) Loops through all [Script](https://github.com/aherne/migration/blob/master/src/Script.php) classes in **migrations** folder and executes their *up* method |
| up | (classname) | Runs *up* method for [Script](https://github.com/aherne/migration/blob/master/src/Script.php) identified by its class name |
| down | (classname) | Runs *down* method for [Script](https://github.com/aherne/migration/blob/master/src/Script.php) identified by its class name  |

### How does generate command work

This will generate a migration [Script](https://github.com/aherne/migration/blob/master/src/Script.php) class in **migrations** folder. Open that class and fill *up* and *down* methods with queries, as in this example:

```php
class Version20210205105634 implements \Hlis\Migration\Script
{
  public function up(): void
  {
    SQL("
      CREATE TABLE test(
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      primary key(id)
      ) ENGINE=INNODB
      ")
  }
  public function up(): void
  {
    SQL("DROP TABLE test");
  }
}
```
Example:

```bash
> php migrations.php generate
```

This will output name of [Script](https://github.com/aherne/migration/blob/master/src/Script.php)  created in **migrations** folder whose up/down methods you must fill.

### How does migrate command work

When *migrate* is ran, API will locate all  [Script](https://github.com/aherne/migration/blob/master/src/Script.php)s classes in **migrations** folder and match each to [Cache](https://github.com/aherne/migration/blob/master/src/Cache.php):

- if found in [Cache](https://github.com/aherne/migration/blob/master/src/Cache.php) and migration [Status](https://github.com/aherne/migration/blob/master/src/Status.php) is PASSED: *up* is skipped (since it ran already)
- otherwise: *up* is ran

If *up* throws no \Throwable, migration is saved in [Cache](https://github.com/aherne/migration/blob/master/src/Cache.php) with PASSED status. Otherwise it's saved with FAILED status and \Throwable message will be shown to caller in results summary.

Example:

```bash
> php migrations.php migrate
```

The end will be a console table with following columns:

- [Script](https://github.com/aherne/migration/blob/master/src/Script.php) class name
- [Status](https://github.com/aherne/migration/blob/master/src/Status.php) of *up* method execution
- error message, if [Script](https://github.com/aherne/migration/blob/master/src/Script.php) threw a \Throwable


### How does up command work

When *up* (commit) command is ran, API will first check if [Script](https://github.com/aherne/migration/blob/master/src/Script.php) name received as 2nd argument exists on disk in **migrations** folder:

- if found on disk
    - if found in [Cache](https://github.com/aherne/migration/blob/master/src/Cache.php) and migration [Status](https://github.com/aherne/migration/blob/master/src/Status.php) is PASSED: *up* is skipped (since it ran already)
    - otherwise: *up* is ran
- otherwise: program exits with error

Example:

```bash
> php migrations.php up Version20210205105634
```

The end result will be a console table with following columns:

- [Status](https://github.com/aherne/migration/blob/master/src/Status.php) of [Script](https://github.com/aherne/migration/blob/master/src/Script.php)'s *up* method execution
- error message, if [Script](https://github.com/aherne/migration/blob/master/src/Script.php) threw a \Throwable

### How does down command work

When *down* (rollback) command is ran, API will first check if [Script](https://github.com/aherne/migration/blob/master/src/Script.php) name received as 2nd argument exists on disk in **migrations** folder:

- if found on disk
    - if found in [Cache](https://github.com/aherne/migration/blob/master/src/Cache.php) and migration [Status](https://github.com/aherne/migration/blob/master/src/Status.php) is PASSED: *down* is ran
    - otherwise: *down* is skipped
- otherwise: program exits with error


Example:

```bash
> php migrations.php down Version20210205105634
```

The end result will be a console table with following columns:

- [Status](https://github.com/aherne/migration/blob/master/src/Status.php) of [Script](https://github.com/aherne/migration/blob/master/src/Script.php)'s *up* method execution
- error message, if [Script](https://github.com/aherne/migration/blob/master/src/Script.php) threw a \Throwable

## Reference Guide
