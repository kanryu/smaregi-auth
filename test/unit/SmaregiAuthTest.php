<?php
use PHPUnit\Framework\TestCase;
use Kanryu\SmaregiAuth\SmaregiAuth;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** SmaregiAuthTestのTestCase */
class SmaregiAuthTest extends TestCase
{
    /**
     * Hoge Object
     *
     * @var Object
     */
    protected $hoge;

    /** @var TestLogger */
    protected $logger;

    /** @var SmaregiAuth */
    protected $client;

    /** @var string */
    protected $contract_id = 'pos_test';

    /** @var string */
    protected $client_id = 'teste300545098d8fcc96af8d58f34ac';

    /** @var string */
    protected $client_secret = 'test79a7d717921e5cf69447d08b7c38584e9a03fcfeb6de038fd6aaab7f2694';

    /** @var string */
    protected $scope = 'pos.orders:read pos.orders:write pos.products:read';

    protected $accessTokenResult = "yyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiJiYzNkZTMwMDU0NTA5OGQ4ZmNjOTZhZjhkNThmMzRhYyIsImp0aSI6IjNjZGNlNDc2MTg4NTgyNTVkNTc0ZGU2NDVhMzgyNjA5ZThmYmFmZGU0OWM1N2RiMDA5N2U1NDNhYTNiNDAwODZkYjExNGFhNzAzNjIzMTBmIiwiaWF0IjoxNjUyNjY3MDczLCJuYmYiOjE2NTI2NjcwNzMsImV4cCI6MTY1MjY3MDY3Mywic3ViIjoic2Jfc2t1MTgxdDkiLCJzY29wZXMiOlsicG9zLm9yZGVyczpyZWFkIiwicG9zLm9yZGVyczp3cml0ZSIsInBvcy5wcm9kdWN0czpyZWFkIl19.1prlKkfYn6ChLQro0oSeDg15qFi1vf-34quQRFoLYFeH74kYtJ5OLFkW-7HvGI7zaxrpXOD1xVhgAjZoJJJ35_ASHUbnAG09IcXMmhQunYCEIsuSzhYhih34mTxtr_A_UuFOOI_48BKyp64dresp_2HU13zcMaY_FkAgcFsAZiWDHjQlVqZFnUtb_a644HTT0SaHiF9eeKZwI66vfDk9g7qdIa1zW_3s6dxu2tA_-Uj8qI9U0XMYUEj25sfHxmCsu8mLpE2leabuIxaftlFdVCImx0w0ZS0glAWunwym-FryX5sx4mUBDt5G0wcuPjWUHHUCzi_IQ0PCkzjXZ1BmuQ";

    /**
     * 各テストメソッドが実行される前に、setUp() という名前のテンプレートメソッドが実行される。
     * （テスト対象のオブジェクトを生成するような処理に使用する。）
     */
    protected function setUp() {
        $this->hoge = 1;
        $this->logger = new TestLogger();
        $config = array(
            'logger' => $this->logger,
            'contract_id' => $this->contract_id,
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'scope' => $this->scope,
            // 本来不要な指定だが、単体テストではcodeVerifier生成を固定する必要があるため固定で指定する。指定しないと自動生成される
            'authorize_state' => '579df1c80099e8bb51b7a10330228175',
            'code_verifier' => 'AZEn3OMCvX9waIyu089mZ8d8TDpmGZBgCtPKqqIpUkI',
        );
        $this->client = new SmaregiAuth($config);
        // Guzzle::Http::Client
        $this->client->client = $this->getMockBuilder('Client')
                                    ->setMethods(array('request'))
                                    ->getMock();
        $this->response = $this->getMockBuilder(ResponseInterface::class)
            ->setMethods([
                'getStatusCode',
                'withStatus',
                'getReasonPhrase',
                'getProtocolVersion',
                'withProtocolVersion',
                'getHeaders',
                'hasHeader',
                'getHeader',
                'getHeaderLine',
                'withHeader',
                'withAddedHeader',
                'withoutHeader',
                'getBody',
                'withBody',
                ])
            ->getMock();
        $this->request = $this->getMockBuilder(RequestInterface::class)
            ->setMethods([
                'getRequestTarget',
                'withRequestTarget',
                'getMethod',
                'withMethod',
                'getUri',
                'withUri',
                'getProtocolVersion',
                'withProtocolVersion',
                'getHeaders',
                'hasHeader',
                'getHeader',
                'getHeaderLine',
                'withHeader',
                'withAddedHeader',
                'withoutHeader',
                'getBody',
                'withBody',
                ])
            ->getMock();
        $this->response->method('getStatusCode')
            ->willReturn(200);
        $this->response->method('getBody')
            ->willReturn(sprintf('{"scope":"pos.orders:read pos.orders:write pos.products:read","token_type":"Bearer","expires_in":3600,"access_token":"%s"}', $this->accessTokenResult));
        $this->clientException = new ClientException('stub', $this->request, $this->response);
        //$this->guzzleException = $this->getMockBuilder('GuzzleException', Exception::class)
        $this->guzzleException = $this->getMockBuilder(GuzzleException::class)
            ->setMethods([
                'getRequest',
                'getMessage',
                'getCode',
                'getFile',
                'getLine',
                'getTrace',
                'getTraceAsString',
                'getPrevious',
                //'__toString',
            ])
            ->getMock();
    }

    /** コンストラクタ終了時の各プロパティの確認 */
    public function test_construct()
    {
        $this->assertEquals(false, $this->client->isDevelopper);
        $this->assertEquals(SmaregiAuth::SMAREGI_CLIENT_UA, $this->client->clientUa);
        $this->assertEquals($this->contract_id, $this->client->contractId);
    }

    /**
     * ClientException
     * SmaregiAuth::getAccessToken() NG Exception
     */
    public function test_getAccessToken_NG1_ClientException()
    {
        $this->client->client->method('request')->will($this->throwException($this->clientException));
        $result = $this->client->getAccessToken();
        $this->assertFalse($result->success);
        $this->assertEquals($this->logger->logs['error'], [
            "[SmaregiAuth] リクエストに失敗しました。(ClientException)",
            "[SmaregiAuth][message] stub",
            '[SmaregiAuth][body] {"scope":"pos.orders:read pos.orders:write pos.products:read","token_type":"Bearer","expires_in":3600,"access_token":"yyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiJiYzNkZTMwMDU0NTA5OGQ4ZmNjOTZhZjhkNThmMzRhYyIsImp0aSI6IjNjZGNlNDc2MTg4NTgyNTVkNTc0ZGU2NDVhMzgyNjA5ZThmYmFmZGU0OWM1N2RiMDA5N2U1NDNhYTNiNDAwODZkYjExNGFhNzAzNjIzMTBmIiwiaWF0IjoxNjUyNjY3MDczLCJuYmYiOjE2NTI2NjcwNzMsImV4cCI6MTY1MjY3MDY3Mywic3ViIjoic2Jfc2t1MTgxdDkiLCJzY29wZXMiOlsicG9zLm9yZGVyczpyZWFkIiwicG9zLm9yZGVyczp3cml0ZSIsInBvcy5wcm9kdWN0czpyZWFkIl19.1prlKkfYn6ChLQro0oSeDg15qFi1vf-34quQRFoLYFeH74kYtJ5OLFkW-7HvGI7zaxrpXOD1xVhgAjZoJJJ35_ASHUbnAG09IcXMmhQunYCEIsuSzhYhih34mTxtr_A_UuFOOI_48BKyp64dresp_2HU13zcMaY_FkAgcFsAZiWDHjQlVqZFnUtb_a644HTT0SaHiF9eeKZwI66vfDk9g7qdIa1zW_3s6dxu2tA_-Uj8qI9U0XMYUEj25sfHxmCsu8mLpE2leabuIxaftlFdVCImx0w0ZS0glAWunwym-FryX5sx4mUBDt5G0wcuPjWUHHUCzi_IQ0PCkzjXZ1BmuQ"}',
        ]);
    }
    /**
     * GuzzleException
     * SmaregiAuth::getAccessToken() NG Exception
     */
    public function test_getAccessToken_NG2_GuzzleException()
    {
        $this->client->client->method('request')->will($this->throwException($this->guzzleException));
        $result = $this->client->getAccessToken();
        $this->assertFalse($result->success);
        $this->assertEquals($this->logger->logs['error'], [
            "[SmaregiAuth] リクエストに失敗しました。(GuzzleException)",
            "[SmaregiAuth][message] ",
        ]);
    }
    /**
     * SmaregiAuth::getAccessToken() NG Exception
     */
    public function test_getAccessToken_NG3_Exception()
    {
        $this->client->client->method('request')->will($this->throwException(new \Exception('stub')));
        $result = null;
        try {
            $result = $this->client->getAccessToken();
			$this->fail('PHPUnit fail');
        } catch (\Exception $e) {
            $this->assertEquals($e->getMessage(),
                "[SmaregiAuth] エラー発生: stub"
            );    
        }
    }
    /**
     * SmaregiAuth::getAccessToken() OK
     */
    public function test_getAccessToken_OK()
    {
        $this->client->client->method('request')->willReturn($this->response);
        $result = $this->client->getAccessToken();
        $this->assertTrue($result->success);
        $this->assertEquals($result->body->scope, $this->scope);
        $this->assertEquals($result->body->token_type, 'Bearer');
        $this->assertEquals($result->body->expires_in, 3600);
        $this->assertEquals($result->body->access_token, $this->accessTokenResult);
    }

    /**
     * SmaregiAuth::getAuthorizeUri() NG RuntimeException
     */
    public function test_getAuthorizeUri_NG_RuntimeException()
    {
        try {
            $this->client->getAuthorizeUri(null);
			$this->fail('PHPUnit fail');
        } catch (\Exception $e) {
            $this->assertEquals($e->getMessage(),
                "[SmaregiAuth] scopeは必須です。e.g.'openid email offline_access'"
            );    
        }
    }
    /**
     * SmaregiAuth::getAuthorizeUri() OK
     */
    public function test_getAuthorizeUri_OK()
    {
        $this->client->codeVerifier = 'jYw8zsfzLZPrjeh4D0Lhq5otdkduJO9ddXsfSm-fP8U';
        $result = $this->client->getAuthorizeUri('openid email offline_access');
        $this->assertEquals($result->codeVerifier, 'jYw8zsfzLZPrjeh4D0Lhq5otdkduJO9ddXsfSm-fP8U');
        $this->assertEquals($result->uri, "https://id.smaregi.jp/authorize?response_type=code&client_id=teste300545098d8fcc96af8d58f34ac&scope=openid+email+offline_access&state=579df1c80099e8bb51b7a10330228175&code_challenge=LMl35NFdW4gyHJRGavcm19Nv3F19qLBsRj9rQkTq-7E&code_challenge_method=S256");

    }
    

}
