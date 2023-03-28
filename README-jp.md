# smaregi-auth

スマレジ社が公開している『スマレジプラットフォームAPI v1.0』(PFAPI)を利用する際に必要となる初期認証を実行するためのライブラリです。

- スマレジ・プラットフォームAPI 共通仕様書
  - https://developers.smaregi.dev/apidoc/common/
- 【スマレジアプリを作ってみた#3】認証機能の実装(Qiita)
  - https://qiita.com/JoBins/items/5b3eee45324466b01a64


## インストール

```bash
composer require kanryu/smaregi-auth
```

## サンプルコード


```php
use Kanryu\SmaregiAuth\SmaregiAuth;

$config = array(
    'logger' => null, // EchoLoggerが設定される
    'contract_id' => '契約ID',
    'client_id' => 'アプリのクライアントID',
    'client_secret' => 'アプリのクライアントシークレット',
    'scope' => 'アプリが使用するスコープ(スペース区切りまたはArray)',
);


$client = new SmaregiAuth($config);
$client->getAccessToken();
```

## 使用上の注意

AccessTokenは現在のところ3600秒(1時間)有効であるようです。
初回及び約1時間ごとに取得、更新し、それ以外は取得されたAccessTokenでPFAPIにアクセスしましょう。

## Author

Copyright 2023 KATO Kanryu(k.kanryu@gmail.com)







