<?php

namespace XuTL\Supports\Traits;

use DOMDocument;
use DOMElement;
use DOMText;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;
use XuTL\Supports\Util\StringUtil;

/**
 * Trait HasHttpRequest
 *
 * @method  string getBaseUri()
 * @method HandlerStack getHandlerStack()
 * @property string $timeout
 * @property string $connectTimeout
 */
trait HasHttpRequest
{
    /**
     * Http client.
     *
     * @var null|Client
     */
    protected $httpClient = null;

    /**
     * Http client options.
     *
     * @var array
     */
    public $httpOptions = [];

    /**
     * Make a get request.
     *
     * @param string $endpoint
     * @param array $query
     * @param array $headers
     * @return array
     */
    protected function get($endpoint, $query = [], $headers = [])
    {
        return $this->request('get', $endpoint, [
            'headers' => $headers,
            'query' => $query,
        ]);
    }

    /**
     * Make a post request.
     *
     * @param string $endpoint
     * @param string|array $params
     * @param array $headers
     * @return array
     */
    protected function post($endpoint, $params, $headers = [])
    {
        $options = ['headers' => $headers];
        if (!is_array($params)) {
            $options['body'] = $params;
        } else {
            $options['form_params'] = $params;
        }
        return $this->request('post', $endpoint, $options);
    }

    /**
     * make a post xml request
     * @param string $endpoint
     * @param mixed $data
     * @param array $headers
     * @return mixed
     */
    protected function postXML($endpoint, $data, $headers = [])
    {
        if ($data instanceof DOMDocument) {
            $xml = $data->saveXML();
        } elseif ($data instanceof SimpleXMLElement) {
            $xml = $data->saveXML();
        } else {
            $xml = $this->convertArrayToXml($data);
        }
        $header['Content-Type'] = 'application/xml; charset=UTF-8';
        return $this->post($endpoint, $xml, $headers);
    }

    /**
     * Make a post request.
     *
     * @param string $endpoint
     * @param array $params
     * @param array $headers
     * @return array
     */
    protected function postJSON($endpoint, $params = [], $headers = [])
    {
        return $this->request('post', $endpoint, [
            'headers' => $headers,
            'json' => $params,
        ]);
    }

    /**
     * Make a http request.
     *
     * @param string $method
     * @param string $endpoint
     * @param array $options http://docs.guzzlephp.org/en/latest/request-options.html
     * @return mixed
     */
    protected function request($method, $endpoint, $options = [])
    {
        return $this->unwrapResponse($this->getHttpClient()->{$method}($endpoint, $options));
    }

    /**
     * @param Client $client
     * @return $this
     */
    public function setHttpClient(Client $client)
    {
        $this->httpClient = $client;
        return $this;
    }

    /**
     * Return http client.
     *
     * @return \GuzzleHttp\Client
     */
    protected function getHttpClient()
    {
        if (is_null($this->httpClient)) {
            $this->httpClient = $this->getDefaultHttpClient();
        }
        return $this->httpClient;
    }

    /**
     * Get default http client.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @return Client
     */
    protected function getDefaultHttpClient()
    {
        return new Client($this->getOptions());
    }

    /**
     * Return Guzzle options.
     *
     * @return array
     */
    protected function getOptions()
    {
        $options = array_merge([
            'base_uri' => method_exists($this, 'getBaseUri') ? $this->getBaseUri() : '',
            'timeout' => property_exists($this, 'timeout') ? $this->timeout : 5.0,
            'connect_timeout' => property_exists($this, 'connectTimeout') ? $this->connectTimeout : 5.0,
        ], $this->httpOptions);
        if (method_exists($this, 'getHandlerStack')) {
            $options['handler'] = $this->getHandlerStack();
        }
        return $options;
    }

    /**
     * Convert response contents to json.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return array|mixed
     */
    protected function unwrapResponse(ResponseInterface $response)
    {
        $content = $response->getBody()->getContents();
        if (!empty($content)) {
            $contentType = $response->getHeaderLine('Content-Type');
            $format = $this->detectFormatByContentType($contentType);
            if ($format === null) {
                $format = $this->detectFormatByContent($content);
            }
            switch ($format) {
                case 'json':
                    return json_decode((string)$content, true);
                    break;
                case 'urlencoded':
                    $data = [];
                    parse_str((string)$content, $data);
                    return $data;
                    break;
                case 'xml':
                    if (preg_match('/charset=(.*)/i', $contentType, $matches)) {
                        $encoding = $matches[1];
                    } else {
                        $encoding = 'UTF-8';
                    }
                    $dom = new \DOMDocument('1.0', $encoding);
                    $dom->loadXML((string)$content, LIBXML_NOCDATA);
                    return $this->convertXmlToArray(simplexml_import_dom($dom->documentElement));
                    break;
                default:
                    return $content;
            }
        }
        return $content;
    }

    /**
     * Converts XML document to array.
     * @param string|\SimpleXMLElement $xml xml to process.
     * @return array XML array representation.
     */
    protected function convertXmlToArray($xml)
    {
        if (is_string($xml)) {
            $xml = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        }
        $result = (array)$xml;
        foreach ($result as $key => $value) {
            if (!is_scalar($value)) {
                $result[$key] = $this->convertXmlToArray($value);
            }
        }
        return $result;
    }

    /**
     * Converts array to XML document.
     * @param $arr
     * @return string
     */
    protected function convertArrayToXml($arr)
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $root = new DOMElement('xml');
        $dom->appendChild($root);
        $this->buildXml($root, $arr);
        return $dom->saveXML();
    }

    /**
     * Build xml
     * @param DOMElement $element
     * @param mixed $data
     */
    protected function buildXml($element, $data)
    {
        if (is_array($data)) {
            foreach ($data as $name => $value) {
                if (is_int($name) && is_object($value)) {
                    $this->buildXml($element, $value);
                } elseif (is_array($value) || is_object($value)) {
                    $child = new DOMElement(is_int($name) ? 'item' : $name);
                    $element->appendChild($child);
                    $this->buildXml($child, $value);
                } else {
                    $child = new DOMElement(is_int($name) ? 'item' : $name);
                    $element->appendChild($child);
                    $child->appendChild(new DOMText((string)$value));
                }
            }
        } elseif (is_object($data)) {
            $child = new DOMElement(StringUtil::basename(get_class($data)));
            $element->appendChild($child);
            $array = [];
            foreach ($data as $name => $value) {
                $array[$name] = $value;
            }
            $this->buildXml($child, $array);
        } else {
            $element->appendChild(new DOMText((string)$data));
        }
    }

    /**
     * Detects format from headers.
     * @param string $contentType source content-type.
     * @return null|string format name, 'null' - if detection failed.
     */
    protected function detectFormatByContentType($contentType)
    {
        if (!empty($contentType)) {
            if (stripos($contentType, 'json') !== false) {
                return 'json';
            }
            if (stripos($contentType, 'urlencoded') !== false) {
                return 'urlencoded';
            }
            if (stripos($contentType, 'xml') !== false) {
                return 'xml';
            }
        }
        return null;
    }

    /**
     * Detects response format from raw content.
     * @param string $content raw response content.
     * @return null|string format name, 'null' - if detection failed.
     */
    protected function detectFormatByContent($content)
    {
        if (preg_match('/^\\{.*\\}$/is', $content)) {
            return 'json';
        }
        if (preg_match('/^([^=&])+=[^=&]+(&[^=&]+=[^=&]+)*$/', $content)) {
            return 'urlencoded';
        }
        if (preg_match('/^<.*>$/s', $content)) {
            return 'xml';
        }
        return null;
    }
}
