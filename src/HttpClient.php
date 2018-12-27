<?php
/**
 * @link http://www.tintsoft.com/
 * @copyright Copyright (c) 2012 TintSoft Technology Co. Ltd.
 * @license http://www.tintsoft.com/license/
 */

namespace XuTL\Supports;

use XuTL\Supports\Traits\HasHttpRequest;

/**
 * Http 客户端
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class HttpClient
{
    use HasHttpRequest {
        post as public;
        get as public;
        postJSON as public;
        request as public;
    }

    /**
     * @var float
     */
    public $timeout = 5.0;

    /**
     * @var string
     */
    protected $baseUri = '';

    /**
     * 获取基础路径
     * @return string
     */
    public function getBaseUri()
    {
        return $this->baseUri;
    }

    /**
     * 设置基础路径
     * @param string $baseUri
     * @return $this
     */
    public function setBaseUri($baseUri)
    {
        $this->baseUri = $baseUri;
        return $this;
    }
}