<?php
namespace Kanryu\SmaregiAuth
{
    /** SmaregiAuthによるリクエスト処理結果 */
    class SmaregiAuthorizeResult
    {
        /** @var string リダイレクト認証用URI */
        public $uri;
        /** @var string PKCE（Proof Key for Code Exchange by OAuth Public Clients）用のcode_verifier */
        public $codeVerifier;
    }
}