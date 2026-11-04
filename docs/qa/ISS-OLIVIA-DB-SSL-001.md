# ISS-OLIVIA-DB-SSL-001 — Permitir ssl=false na Connection

**Tarefa:** HF-ISS-OLIVIA-DB-SSL-001  
**Issue:** ISS-OLIVIA-DB-SSL-001  
**Baseline:** OLIVIA-PHP85-COMPAT-014  
**Data:** 2026-11-04

## Sintoma

Vendor antigo em `/var/www/html/sistema/vendor/elzobrito/olivia-framework/src/database/Connection.php` lança na linha 35:

`Remote MySQL requires ssl=true with certificate verification.`

Isso ocorre com host remoto e `'ssl' => false`, antes do PDO. MySQL com TLS desligado não chega a conectar.

## Fonte (OliviaApp)

`src/database/Connection.php`:

- construtor só atribui `$this->options = $options;`
- `mysqlClientOptions()` usa `$ssl = !empty($this->options['ssl']);` sem throw de host remoto
- `ssl=true` continua exigindo arquivo CA
- `isRemoteMysql()` removido (não era chamado)

## Patch no servidor (vendor)

Arquivo:

`/var/www/html/sistema/vendor/elzobrito/olivia-framework/src/database/Connection.php`

Remover os dois blocos:

```php
if ($this->isRemoteMysql() && empty($this->options['ssl'])) {
    throw new InvalidArgumentException('Remote MySQL requires ssl=true with certificate verification.');
}
```

```php
if ($this->isRemoteMysql() && !$ssl) {
    throw new InvalidArgumentException('Remote MySQL requires ssl=true with certificate verification.');
}
```

Deixar:

```php
$this->options = $options;
```

```php
$ssl = !empty($this->options['ssl']);
```

No app, `lib/Database.php` deve ter `'ssl' => false` para esse MySQL.

`composer install` / `update` devolve o throw se o pin do pacote for anterior a este hotfix.

## Teste

`php tests/kernel/security_contract_test.php`

- remoto + `ssl=false` não lança a exceção obrigatória de TLS
- `ssl=false` não define `MYSQL_ATTR_SSL_CA`
- `ssl=true` sem CA continua rejeitado
