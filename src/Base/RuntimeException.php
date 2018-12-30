<?php
/**
 * @link http://www.tintsoft.com/
 * @copyright Copyright (c) 2012 TintSoft Technology Co. Ltd.
 * @license http://www.tintsoft.com/license/
 */

namespace XuTL\Supports\Base;


/**
 * Class RuntimeException
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class RuntimeException extends \RuntimeException
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Runtime Exception';
    }
}