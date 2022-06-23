<?php

/**
 * @copyright
 * @package     Easy Image Resizer - EIR for Joomla! 3.x
 * @author      Viktor Vogel <admin@kubik-rubik.de>
 * @version     3.7.0-FREE - 2020-05-23
 * @link        https://kubik-rubik.de/eir-easy-image-resizer
 *
 * @license     GNU/GPL
 *
 * Modified PHP Library for the Optimus API
 *
 * optimus.io - Lossless compression and optimization of your images
 *
 * @author      KeyCDN
 * @version     0.1
 */

namespace EasyImageResizer;

defined('JPATH_PLATFORM') || die('Restricted access');

use Joomla\CMS\{Http\HttpFactory, Version, Uri\Uri};
use Joomla\Registry\Registry;

/**
 * Class Optimus
 *
 * @since 3.3.0-FREE
 */
class Optimus
{
    /**
     * @var string $apiKey
     * @since 3.3.0-FREE
     */
    private $apiKey;

    /**
     * @var string $endPoint
     * @since 3.3.0-FREE
     */
    private $endPoint;

    /**
     * @param string $apiKey
     *
     * @since 3.3.0-FREE
     */
    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->endPoint = 'http://api.optimus.io';

        if (!empty($apiKey)) {
            $this->endPoint = 'https://api.optimus.io';
        }
    }

    /**
     * Calculates the maximum request quota to avoid unnecessary requests
     *
     * @return int
     * @since 3.3.0-FREE
     */
    public function getRequestQuota(): int
    {
        $quota = 100 * 1024;

        if (!empty($this->apiKey)) {
            $quota = 10000 * 1024;
        }

        return $quota;
    }

    /**
     * Does the main request to optimus.io
     *
     * @param string $imagePath
     * @param string $option
     *
     * @return bool|string
     * @since 3.3.0-FREE
     */
    public function optimize(string $imagePath, string $option = 'optimize')
    {
        // First check whether cURL is activated
        if (HttpFactory::getAvailableDriver(new Registry, 'curl') == false || empty($imagePath)) {
            return false;
        }

        $endPoint = $this->endPoint . '/' . $this->apiKey . '?' . $option;
        $version = new Version();
        $host = Uri::getInstance()->getHost();

        if (!preg_match('@http.?://@', $host)) {
            $host = 'http://' . $host;
        }

        $userAgent = 'Joomla/' . $version->getShortVersion() . '; ' . $host;
        $headers = ['User-Agent: ' . $userAgent, 'Accept: image/*'];

        // Use native cURL implementation because not all needed options can be set through the Joomla! API
        $ch = curl_init();
        curl_setopt_array(
            $ch,
            [
                CURLOPT_URL            => $endPoint,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_POSTFIELDS     => file_get_contents($imagePath),
                CURLOPT_BINARYTRANSFER => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            ]
        );

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        if (!empty($curlError)) {
            return false;
        }
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);

        if (empty($body)) {
            return false;
        }

        return $body;
    }
}
