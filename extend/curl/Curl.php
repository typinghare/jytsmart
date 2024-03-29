<?php

namespace curl;

/**
 * Class Curl
 * @package utility\service
 * @method Curl post(string $url, $postFields = [], array $param = [], array $options = []) static
 * @method Curl patch(string $url, $postFields = [], array $param = [], array $options = []) static
 * @method Curl delete(string $url, $postFields = [], array $param = [], array $options = []) static
 */
class Curl
{
    /**
     * 请求地址
     * @var string
     */
    protected $url;

    /**
     * 选项
     * @var array
     */
    protected $options = [
        // 是否验证证书
        'verify' => false,
        // 证书的路径
        'cainfo' => '',
        // 设置获取的信息以文件流的形式返回，而不是直接输出
        'return_transfer' => true,
        // 设置头文件信息作为数据流输出
        'header' => false,
        // 头文件信息
        'http_header' => [],
        // 提交方法
        'method' => 'get',
        // 响应超时时间（毫秒）
        'timeout' => 300,
        // 路由参数
        'param' => [],
        // POST参数
        'post_fields' => [],
        // POST参数形式 (binary, json, xml)
        'post_fields_form' => 'json',
        // 参数返回格式 (binary, json, xml)
        'return_form' => 'json'
    ];

    /**
     * Curl constructor.
     * @param array $options 选项
     */
    public function __construct(array $options = [])
    {
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
    }

    /**
     * @param $name
     * @param $arguments
     * @return Curl
     */
    public static function __callStatic($name, $arguments)
    {
        return self::on($name, $arguments[0], $arguments[1]??[], $arguments[2]??[], $arguments[3]??[]);
    }

    /**
     * 获取实例化对象
     * @param array $options
     * @return Curl
     */
    public static function instance(array $options = []) : self
    {
        return new static($options);
    }

    /**
     * GET方法
     * @param string $url       请求地址
     * @param array $param      请求参数
     * @param array $options    选项
     * @return Curl
     */
    public static function get(string $url, array $param = [], array $options = []) : self
    {
        return self::on('GET', $url, [], $param, $options);
    }

    /**
     * 其他方法
     * @param $method
     * @param string $url       请求地址
     * @param mixed $postFields 请求参数(post)
     * @param array $param      请求参数
     * @param array $options    选项
     * @return Curl
     */
    public static function on(string $method, string $url, $postFields = [], array $param = [], array $options = []) : self
    {
        $instance = self::instance($options);
        $instance->url = $url;
        $instance->options['method'] = $method;
        $instance->options['post_fields'] = $postFields;
        $instance->options['param'] = $param;

        return $instance;
    }

    /**
     * 设置选项
     * @param array $option 选项
     * @return Curl
     */
    public function setOption(array $option) : self
    {
        $this->options = array_merge($this->options, $option);
        return $this;
    }

    /**
     * @param $ch
     * @param string $url
     */
    protected function setOpt($ch, string $url) : void
    {
        curl_setopt($ch, CURLOPT_URL, $url);

        // 证书相关
        $verify = $this->options['verify'] === true;
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify);
        if ($verify) {
            curl_setopt($ch,CURLOPT_CAINFO, $this->options['cainfo']);
            if ($this->options['post_fields_form'] == 'json') {
                $this->options['post_fields'] = json_encode($this->options['post_fields']);
            }
            elseif ($this->options['post_fields_form'] == 'xml') {
                $this->options['post_fields'] = $this->arrayToXml($this->options['post_fields']);
            }
        }

        // 头信息及返回模式相关
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, $this->options['return_transfer']);
        curl_setopt($ch,CURLOPT_HEADER, $this->options['header']);
        curl_setopt($ch,CURLOPT_HTTPHEADER, $this->options['http_header']);

        if (is_int($this->options['timeout']) && (int)($this->options['timeout']) > 0) {
            curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->options['timeout']);
        }

        // 请求方法及请求参数
        $method = strtoupper($this->options['method']);
        switch ($method) {
            case 'GET' : break;
            case 'POST' :
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->options['post_fields']);
                break;
            default :
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->options['post_fields']);
        }
    }

    /**
     * 执行
     * @return mixed|false when error
     */
    public function execute()
    {
        // 初始化CURL并设置选项及URL
        $ch = curl_init();
        $this->url = $this->parseParam($this->url, $this->options['param']);
        $this->setOpt($ch, $this->url);

        // 执行
        $return = curl_exec($ch);

        if (false === $return) return false;

        // 返回数据
        switch ($this->options['return_form']) {
            case 'binary'   : break;
            case 'json'     : @$return = json_decode($return, true); break;
            case 'xml'      : @$return = $this->xmlToArray($return); break;
        }

        return $return;
    }

    /**
     * 将请求参数绑定到url上
     * @param string $url
     * @param array $param
     * @return string url
     */
    protected function parseParam(string $url, array $param) : string
    {
        if (empty($param)) {
            return $url;
        } else {
            $url .= '?';
            foreach ($param as $key => $value) {
                $url .= $key . '=' . $value . '&';
            }
            return $url = substr($url, 0, -1);
        }
    }

    /**
     * 将数组转化为xml数据
     *
     * @param array $array
     * @return string
     */
    public static function arrayToXml(array $array) : string
    {
        $xml = "<xml>";
        foreach ($array as $key => $val){
            if(is_array($val)){
                $xml .= "<{$key}>" . self::arrayToXml($val) . "</{$key}>";
            } else {
                $xml .= "<{$key}>" . $val . "</{$key}>";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /**
     * 将xml数据转化为数组
     *
     * @param string $xml
     * @return array
     */
    public static function xmlToArray(string $xml) : array
    {
        if (is_null($xml)) return [];

        $ob= simplexml_load_string($xml,'SimpleXMLElement', LIBXML_NOCDATA);
        $json = json_encode($ob);
        $configData = json_decode($json, true);

        return $configData;
    }
}