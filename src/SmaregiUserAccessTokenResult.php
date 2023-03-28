<?php
namespace Kanryu\SmaregiAuth
{
    /** SmaregiAuthによるリクエスト処理結果 */
    class SmaregiUserAccessTokenResult
    {
        /** @var EntityEnclosingRequest */
        public $request;
        /** @var Response */
        public $response;
        /** @var Response body */
        public $body;
        /** @var boolean 成功時にtrue */
        public $success = false;
    }
}