<?php
declare(strict_types=1);

namespace RTBHouse;

define('API_BASE_URL', 'https://panel.rtbhouse.com/api/');

class ReportsApiSession
{
    private $_username;
    private $_password;
    private $_session;

    function __construct(string $username, string $password)
    {
      $this->_username = $username;
      $this->_password = $password;
    }

    protected function _session()
    {
      if(empty($this->_session)) {
        $this->_session = $this->_create_session();
      }

      return $this->_session;
    }

    protected function _create_session()
    {
      $client = new \GuzzleHttp\Client([
        'base_uri' => API_BASE_URL,
        'timeout'  => 2.0,
        'cookies' => true
      ]);

      $res = $client->request('POST', 'auth/login', ['json' => ['login' => $this->_username, 'password' => $this->_password]]);
      if($res->getStatusCode() !== 200) {
        throw new RuntimeException('Res is not ok');
      }

      return $client;
    }

    protected function _getData($res)
    {
      if($res->getStatusCode() !== 200) {
        throw new RuntimeException('Res is not ok');
      }

      $res_json = json_decode($res->getBody()->getContents(), true);
      return $res_json['data'];
    }

    protected function _get(string $path, array $params=null)
    {
      $res = $this->_session()->request('GET', $path, ['query' => $params]);
      return $this->_getData($res);
    }

    function getUserInfo()
    {
      $data = $this->_get('user/info');
      return [
        'username' => $data['login'],
        'email' => $data['email']
      ];
    }
}
