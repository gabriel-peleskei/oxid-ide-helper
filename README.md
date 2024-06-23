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

Generated file is:
```
<SHOP-ROOT>/source/modules/.ide-helper.php
```
