# smaregi-auth
A library for performing initial authentication required when using the "Smaregi Platform API v1.0" (PFAPI) published by Smaregi Inc.

Reference:

- スマレジ・プラットフォームAPI 共通仕様書
  - https://developers.smaregi.dev/apidoc/common/
- 【スマレジアプリを作ってみた#3】認証機能の実装(Qiita)
  - https://qiita.com/JoBins/items/5b3eee45324466b01a64


## Installing

```bash
composer require kanryu/smaregi-auth
```

## Sample Code


```php
use Kanryu\SmaregiAuth\SmaregiAuth;

$config = array(
    'logger' => null, // EchoLogger is automatically configured
    'contract_id' => 'Contract ID',
    'client_id' => 'app client id',
    'client_secret' => 'app client secret',
    'scope' => 'Scope used by the app (space separated or Array)',
);


$client = new SmaregiAuth($config);
$client->getAccessToken();
```

## Precautions for use

The AccessToken currently seems to be valid for 3600 seconds (1 hour).
Obtain and update it for the first time and about every hour, otherwise access PFAPI with the obtained AccessToken.

## Author

Copyright 2023 KATO Kanryu(k.kanryu@gmail.com)
