<?php

namespace Simplon\Request;

/**
 * Request
 * @package Simplon\Request
 * @author Tino Ehrich (tino@bigpun.me)
 */
class Request
{
    const POST_VARIANT_POST = 'POST';
    const POST_VARIANT_PUT = 'PUT';
    const POST_VARIANT_DELETE = 'DELETE';
    const DATA_FORMAT_QUERY_STRING = 'query-string';
    const DATA_FORMAT_JSON = 'json';

    /**
     * @param $url
     * @param array $data
     * @param array $optCustom
     *
     * @return RequestResponse
     * @throws RequestException
     */
    public function get($url, array $data = array(), array $optCustom = array())
    {
        if (empty($data) === false)
        {
            $url .= '?' . http_build_query($data);
        }

        $opt = array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => 1,
        );

        return $this->process($opt, $optCustom);
    }

    /**
     * @param $url
     * @param array $data
     * @param array $optCustom
     * @param string $dataType
     *
     * @return RequestResponse
     */
    public function post($url, array $data = array(), array $optCustom = array(), $dataType = self::DATA_FORMAT_QUERY_STRING)
    {
        return $this->postVariant(self::POST_VARIANT_POST, $url, $data, $optCustom, $dataType);
    }

    /**
     * @param $url
     * @param array $data
     * @param array $optCustom
     * @param string $dataType
     *
     * @return RequestResponse
     */
    public function put($url, array $data = array(), array $optCustom = array(), $dataType = self::DATA_FORMAT_QUERY_STRING)
    {
        return $this->postVariant(self::POST_VARIANT_PUT, $url, $data, $optCustom, $dataType);
    }

    /**
     * @param $url
     * @param array $data
     * @param array $optCustom
     * @param string $dataType
     *
     * @return RequestResponse
     */
    public function delete($url, array $data = array(), array $optCustom = array(), $dataType = self::DATA_FORMAT_QUERY_STRING)
    {
        return $this->postVariant(self::POST_VARIANT_DELETE, $url, $data, $optCustom, $dataType);
    }

    /**
     * @param $url
     * @param $method
     * @param array $params
     * @param int $id
     * @param array $optCustom
     *
     * @return RequestResponse
     * @throws RequestException
     */
    public function jsonRpc($url, $method, array $params = array(), $id = 1, array $optCustom = array())
    {
        $opt = array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST           => 1,
            CURLOPT_HTTPHEADER     => array('Content-type: application/json'),
            CURLOPT_POSTFIELDS     => json_encode(array(
                'jsonrpc' => '2.0',
                'id'      => $id,
                'method'  => $method,
                'params'  => $params,
            )),
        );

        // request
        $requestResponse = $this->process(array_merge($opt, $optCustom));

        // decode json
        $decoded = json_decode($requestResponse->getBody(), true);

        // if decoding fails throw exception with received response
        if ($decoded === null)
        {
            throw new RequestException($requestResponse);
        }

        return $requestResponse->setBody($decoded);
    }

    /**
     * @param string $url
     */
    public function redirect($url)
    {
        // redirect now
        header('Location: ' . $url);

        // exit script
        exit;
    }

    /**
     * @param null|string $key
     * @param null|string $fallbackValue
     *
     * @return mixed|null
     */
    public function getGetData($key = null, $fallbackValue = null)
    {
        return $this->readData($_GET, $key, $fallbackValue);
    }

    /**
     * @param null|string $key
     *
     * @return bool
     */
    public function hasGetData($key = null)
    {
        return $this->hasData($_GET, $key);
    }

    /**
     * @return bool
     */
    public function isGet()
    {
        return $this->isRequestMethod('GET');
    }

    /**
     * @param null $key
     * @param null $fallbackValue
     *
     * @return array|string
     */
    public function getPostData($key = null, $fallbackValue = null)
    {
        return $this->readData($_POST, $key, $fallbackValue);
    }

    /**
     * @param null|string $key
     *
     * @return bool
     */
    public function hasPostData($key = null)
    {
        return $this->hasData($_POST, $key);
    }

    /**
     * @return bool
     */
    public function isPost()
    {
        return $this->isRequestMethod('POST');
    }

    /**
     * @param bool $isJson
     *
     * @return array|string
     */
    public function getInputStream($isJson = true)
    {
        $requestJson = (string)file_get_contents('php://input');

        if ($isJson === true)
        {
            return (array)json_decode($requestJson, true);
        }

        return $requestJson;
    }

    /**
     * @return bool
     */
    public function hasInputStream()
    {
        return $this->hasData($this->getInputStream());
    }

    /**
     * @param null|string $key
     * @param null|string $fallbackValue
     *
     * @return mixed|null
     */
    public function getSessionData($key = null, $fallbackValue = null)
    {
        return $this->readData($_SESSION, $key, $fallbackValue);
    }

    /**
     * @param null|string $key
     *
     * @return bool
     */
    public function hasSessionData($key = null)
    {
        return $this->hasData($_SESSION, $key);
    }

    /**
     * @param null|string $key
     * @param null|string $fallbackValue
     *
     * @return mixed|null
     */
    public function getServerData($key = null, $fallbackValue = null)
    {
        return $this->readData($_SERVER, $key, $fallbackValue);
    }

    /**
     * @param $source
     * @param null|string $key
     * @param null|string $fallbackValue
     *
     * @return null|mixed
     */
    private function readData($source, $key = null, $fallbackValue = null)
    {
        if (isset($source))
        {
            if ($key === null)
            {
                return $source;
            }

            if (isset($source[$key]))
            {
                return $source[$key];
            }
        }

        return $fallbackValue;
    }

    /**
     * @param null|array $source
     * @param null $key
     *
     * @return bool
     */
    private function hasData($source, $key = null)
    {
        return $this->readData($source, $key) !== null;
    }

    /**
     * @param $method
     *
     * @return bool
     */
    private function isRequestMethod($method)
    {
        return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === strtoupper($method);
    }

    /**
     * @param $type
     * @param $url
     * @param array $data
     * @param array $optCustom
     * @param string $dataFormat
     *
     * @return RequestResponse
     * @throws RequestException
     */
    private function postVariant($type, $url, array $data = array(), $optCustom = array(), $dataFormat)
    {
        $opt = array(
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $type,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST           => 1,
        );

        if (empty($data) === false)
        {
            switch ($dataFormat)
            {
                case self::DATA_FORMAT_JSON:
                    $data = json_encode($data);
                    break;

                default:
                    $data = http_build_query($data);
            }

            $opt[CURLOPT_POSTFIELDS] = $data;
        }

        return $this->process($opt, $optCustom);
    }

    /**
     * @param array $opt
     * @param array $optCustom
     *
     * @return RequestResponse
     * @throws RequestException
     */
    private function process(array $opt, array $optCustom = array())
    {
        $curl = curl_init();

        // add options to retrieve header
        $opt[CURLOPT_HEADER] = 1;

        // merge options
        foreach ($optCustom as $key => $val)
        {
            $opt[$key] = $optCustom[$key];
        }

        curl_setopt_array($curl, $opt);

        // run request
        $response = curl_exec($curl);

        // parse header
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $header = $this->parseHttpHeaders(substr($response, 0, $header_size));

        // parse body
        $body = substr($response, $header_size);

        // cache error if any occurs
        $error = curl_error($curl);

        // cache http code
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // url on which we eventually might have ended up (301 redirects)
        $lastUrl = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);

        curl_close($curl);

        // throw if request failed
        if ($response === false)
        {
            throw new RequestException($error);
        }

        // --------------------------------------

        $response = new RequestResponse();
        return $response
            ->setHttpCode($httpCode)
            ->setHeader($header)
            ->setBody($body)
            ->setLastUrl($lastUrl);
    }

    /**
     * @param string $headers
     *
     * @return ResponseHeader
     */
    private function parseHttpHeaders($headers)
    {
        $data = array();
        $lines = explode("\r\n", chop($headers));

        $data['http-status'] = array_shift($lines);

        foreach ($lines as $line)
        {
            $parts = explode(':', $line);
            $data[strtolower(array_shift($parts))] = trim(join(':', $parts));
        }

        return new ResponseHeader($data);
    }
}
