<?php
namespace Kanryu\SmaregiAuth
{
    if (!defined('SMAREGI_AUTH_URI')) {
        define('SMAREGI_AUTH_URI', 'https://id.smaregi.jp');
    }
    if (!defined('SMAREGI_AUTH_DEV_URI')) {
        define('SMAREGI_AUTH_DEV_URI', 'https://id.smaregi.dev');
    }
    use GuzzleHttp\Client;
    use GuzzleHttp\Exception\ClientException;
    use GuzzleHttp\Exception\GuzzleException;
    use RuntimeException;
    use Psr\Http\Message\RequestInterface;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Log\LoggerInterface;

    /**
     * Guzzleを用いて Smaregi Platform APIの初期認証を行う
     */
    class SmaregiAuth
    {
        const SMAREGI_CLIENT_UA = 'Smaregi Client 1.0';
        /** @var string クライアントID */
        public $contractId;
        /** @var string 契約ID */
        public $clientId;
        /** @var string クライアントシークレット */
        public $clientSecret;
        /** @var string スコープ */
        public $scope;
        /** @var Client Guzzle HTTP Client */
        public $client;
        /** @var string リクエスト時の User Agent */
        public $clientUa;
        /** @var boolean 開発者モードの場合にtrue */
        public $isDevelopper = false;
        /** @var string 認可エンドポイントURL用state値 */
        public $authorizeState;
        /** @var string PKCE（Proof Key for Code Exchange by OAuth Public Clients）用のcode_verifier */
        public $codeVerifier;

        public function __construct(array $config)
        {
            $this->logger = isset($config['logger']) ? $config['logger'] : new EchoLogger();
            $this->clientUa = isset($config['client_ua']) ? $config['client_ua'] : static::SMAREGI_CLIENT_UA;
            $this->isDevelopper = isset($config['is_developper']) ? $config['is_developper'] : false;
            $this->authorizeState = isset($config['authorize_state']) ? $config['authorize_state'] : md5(openssl_random_pseudo_bytes(32));
            $this->codeVerifier = isset($config['code_verifier']) ? $config['code_verifier'] : $this->generateCodeVerifier();

            if (!isset($config['contract_id'])) {
                throw new RuntimeException('config:contract_id is required');
            }
            $this->contractId = $config['contract_id'];

            if (!isset($config['client_id'])) {
                throw new RuntimeException('config:client_id is required');
            }
            $this->clientId = $config['client_id'];

            if (!isset($config['client_secret'])) {
                throw new RuntimeException('config:client_secret is required');
            }
            $this->clientSecret = $config['client_secret'];

            if (!isset($config['scope'])) {
                throw new RuntimeException('config:scope is required');
            }
            // e.g. 'pos.orders:read pos.orders:write pos.products:read'
            $this->scope = is_array($config['scope']) ? implode(' ', $config['scope']) : $config['scope'];
        }

        /** スマレジAPI認証サーバーのURIを取得する */
        public function getAuthDomainUri(): string
        {
            return $this->isDevelopper ? SMAREGI_AUTH_DEV_URI : SMAREGI_AUTH_URI;
        }

        /** RFC7636 のcode_verifierを生成する */
        public function generateCodeVerifier(int $byteLength = 32)
        {
            $randomBytesString = openssl_random_pseudo_bytes($byteLength);
            $encodedRandomString = base64_encode($randomBytesString);
            $urlSafeEncoding = [
                '=' => '',
                '+' => '-',
                '/' => '_',
            ];
            return strtr($encodedRandomString, $urlSafeEncoding);
        }

        /** RFC7636 code_challenge */
        public function generateCodeChallenge($code_verifier)
        {
            $hash = hash('sha256', $code_verifier, true);
            return str_replace('=', '', strtr(base64_encode($hash), '+/', '-_'));
        }

        /**
         * スマレジAPI認証サーバーからAccessTokenを取得するリクエストを発行する
         *
         * https://developers.smaregi.dev/apidoc/common/#section/%E3%82%A2%E3%82%AF%E3%82%BB%E3%82%B9%E3%83%88%E3%83%BC%E3%82%AF%E3%83%B3
         * @param Array $options Request Options
         * @return SmaregiAuthResult AccessToken リクエスト処理結果
         */
        public function getAccessToken($options=null): SmaregiAccessTokenResult
        {
            $auth_bas64 = base64_encode("{$this->clientId}:{$this->clientSecret}");
            $headers = array(
                'User-Agent' => $this->clientUa,
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => "Basic {$auth_bas64}",
            );
            if (!$options) {
                $options = array(
                    'connect_timeout' => 1.0,
                    'read_timeout' => 2.0,
                );
            }
            if (!$this->client) {
                $this->client = new Client($options);
            }
            // POST https://id.smaregi.dev/app/{契約ID}/token
            $domain = $this->getAuthDomainUri();
            $this->uri = "{$domain}/app/{$this->contractId}/token";
            $params = [
                'grant_type' => 'client_credentials',
                'scope' => $this->scope,
            ];
            $this->logger->info('[SmaregiAuth]url-' . $this->uri);
            $this->logger->info('[SmaregiAuth]headers-' . json_encode($headers));
            $this->logger->info('[SmaregiAuth]params-' . json_encode($params));
            $result = new SmaregiAccessTokenResult();
            try {
                $result->response = $this->client->request('POST', $this->uri, [
                    'headers' => $headers,
                    'form_params' => $params,
                ]);
                $result->success = true; // 正常終了
                if (!is_null($response)) {
                    $this->logger->info('[SmaregiAuth]status-' . $response->getStatusCode());
                    $this->logger->info('[SmaregiAuth]body-' . $response->getBody());
                }
                $result->body = json_decode($result->response->getBody());

                return $result;
            } catch (ClientException $e) {
                // 認証サーバーが403等のレスポンスコードを返してきた場合
                $result->request = $e->getRequest();
                $result->response = $e->getResponse();
                $result->body = json_decode($result->response->getBody());
                $errorBody = $result->response->getBody();

                $this->logger->error('[SmaregiAuth] リクエストに失敗しました。(ClientException)');
                $this->logger->error('[SmaregiAuth][message] ' . $e->getMessage());
                $this->logger->error('[SmaregiAuth][body] ' . $errorBody);
                return $result;
            } catch (GuzzleException $e) {
                // サーバーに接続できなかった場合のエラー
                $result->request = $e->getRequest();
                $this->logger->error('[SmaregiAuth] リクエストに失敗しました。(GuzzleException)');
                $this->logger->error('[SmaregiAuth][message] ' . $e->getMessage());
                return $result;
            } catch (\Exception $e) {
                $this->logger->error('[SmaregiAuth] リクエストに失敗しました。(Exception)');
                $this->logger->error('[SmaregiAuth][message]' . $e->getMessage());
                $this->logger->error('[SmaregiAuth][stackTrace]' . $e->getTraceAsString());
                $message = $e->getMessage();
                throw new RuntimeException('[SmaregiAuth] エラー発生: ' . $message, 0, $e);
            }
        }

        /**
         * 認可エンドポイントURLを取得する(リダイレクト用)
         *
         * https://developers.smaregi.dev/apidoc/common/#section/%E3%83%AD%E3%82%B0%E3%82%A4%E3%83%B3
         * @param string $scope ユーザーに認可を要求するスコープ。(※AccessToken取得用のscopeとは内容が異なります)
         * @return string 認可エンドポイントURL
         */
        public function getAuthorizeUri($scope, $redirectUri=null): SmaregiAuthorizeResult
        {
            // POST https://id.smaregi.dev/authorize
            $domain = $this->getAuthDomainUri();
            $this->uri = "{$domain}/authorize";
            if (!$scope) {
                throw new RuntimeException("[SmaregiAuth] scopeは必須です。e.g.'openid email offline_access'");
            }

            $params = [
                'response_type' => 'code',
                'client_id' => $this->clientId,
                'scope' => $scope,
                'state' => $this->authorizeState,
                'code_challenge' => $this->generateCodeChallenge($this->codeVerifier),
                'code_challenge_method' => 'S256',
            ];
            if ($redirectUri) {
                $params['redirect_uri'] = $redirectUri;
            }
            $paramString = http_build_query($params);

            $result = new SmaregiAuthorizeResult();
            $result->uri = "{$this->uri}?{$paramString}";
            $result->codeVerifier = $this->codeVerifier;
            return $result;
        }

        /**
         * スマレジAPI認証サーバーからユーザーアクセストークンを取得するリクエストを発行する
         *
         * https://developers.smaregi.dev/apidoc/common/#%E3%83%A6%E3%83%BC%E3%82%B6%E3%83%BC%E3%82%A2%E3%82%AF%E3%82%BB%E3%82%B9%E3%83%88%E3%83%BC%E3%82%AF%E3%83%B3%E3%81%AE%E5%8F%96%E5%BE%97
         * @param Array $options Request Options
         * @return SmaregiAuthResult AccessToken リクエスト処理結果
         */
        public function getUserAccessToken($options=null): SmaregiUserAccessTokenResult
        {
            $auth_bas64 = base64_encode("{$this->clientId}:{$this->clientSecret}");
            $headers = array(
                'User-Agent' => $this->clientUa,
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => "Basic {$auth_bas64}",
            );
            if (!$options) {
                $options = array(
                    'connect_timeout' => 1.0,
                    'read_timeout' => 2.0,
                );
            }
            if (!$this->client) {
                $this->client = new Client($options);
            }
            // POST https://id.smaregi.dev/app/{契約ID}/token
            $domain = $this->getAuthDomainUri();
            $this->uri = "{$domain}/app/{$this->contractId}/token";
            $params = [
                'grant_type' => 'authorization_code',
                'scope' => $this->scope,
                'code_verifier' => $this->codeVerifier,
            ];
            $this->logger->info('[SmaregiAuth]url-' . $this->uri);
            $this->logger->info('[SmaregiAuth]headers-' . json_encode($headers));
            $this->logger->info('[SmaregiAuth]params-' . json_encode($params));
            $result = new SmaregiUserAccessTokenResult();
            try {
                $result->response = $this->client->request('POST', $this->uri, [
                    'headers' => $headers,
                    'form_params' => $params,
                ]);
                $result->success = true; // 正常終了
                if (!is_null($response)) {
                    $this->logger->info('[SmaregiAuth]status-' . $response->getStatusCode());
                    $this->logger->info('[SmaregiAuth]body-' . $response->getBody());
                }
                $result->body = json_decode($result->response->getBody());

                return $result;
            } catch (ClientException $e) {
                // 認証サーバーが403等のレスポンスコードを返してきた場合
                $result->request = $e->getRequest();
                $result->response = $e->getResponse();
                $result->body = json_decode($result->response->getBody());
                $errorBody = $result->response->getBody();

                $this->logger->error('[SmaregiAuth] リクエストに失敗しました。(ClientException)');
                $this->logger->error('[SmaregiAuth][message] ' . $e->getMessage());
                $this->logger->error('[SmaregiAuth][body] ' . $errorBody);
                return $result;
            } catch (GuzzleException $e) {
                // サーバーに接続できなかった場合のエラー
                $result->request = $e->getRequest();
                $this->logger->error('[SmaregiAuth] リクエストに失敗しました。(GuzzleException)');
                $this->logger->error('[SmaregiAuth][message] ' . $e->getMessage());
                return $result;
            } catch (\Exception $e) {
                $this->logger->error('[SmaregiAuth] リクエストに失敗しました。(Exception)');
                $this->logger->error('[SmaregiAuth][message]' . $e->getMessage());
                $this->logger->error('[SmaregiAuth][stackTrace]' . $e->getTraceAsString());
                $message = $e->getMessage();
                throw new RuntimeException('[SmaregiAuth] エラー発生: ' . $message, 0, $e);
            }
        }
    }
}
