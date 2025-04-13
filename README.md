# IDE HELPER

## Installation

Using composer:

```bash
composer require --dev -n gabriel-peleskei/oxid-ide-helper
```

## Usage

```bash
vendor/bin/oe-console gp:ide:helper -h
```

Single shop editions:

```bash
vendor/bin/oe-console gp:ide:helper 
```

EE Edition (if other that root shot):

```bash
vendor/ben/oe-console gp:ide:helper --shop-id=3
```

Consider only active modules:

```bash
vendor/bin/oe-console gp:ide:helper --active
vendor/bin/oe-console gp:ide:helper -a
```

Generated file is:

```text
<SHOP-ROOT>/source/modules/.ide-helper.php
```

Except when using root flag:

```bash
vendor/bin/oe-console gp:ide:helper --root
vendor/bin/oe-console gp:ide:helper -r
```

```text
<SHOP-ROOT>/.ide-helper.php
```
