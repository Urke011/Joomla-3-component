<?php

/**
 * @copyright
 * @package     Easy Image Resizer - EIR for Joomla! 3.x
 * @author      Viktor Vogel <admin@kubik-rubik.de>
 * @version     3.7.0-PRO - 2020-05-23
 * @link        https://kubik-rubik.de/eir-easy-image-resizer
 *
 * @license     GNU/GPL
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
defined('_JEXEC') || die('Restricted access');

class PlgSystemEasyImageResizerInstallerScript
{
    const MIN_VERSION_JOOMLA = '3.9.0';
    const MIN_VERSION_PHP = '7.3.0';

    /**
     * Name of extension that is used in the error message
     *
     * @var string
     */
    protected $extensionName = 'Easy Image Resizer';

    /**
     * Checks compatibility in the preflight event
     *
     * @param $type
     * @param $parent
     *
     * @return bool
     * @throws Exception
     */
    public function preflight($type, $parent)
    {
        if (!$this->checkVersionJoomla()) {
            return false;
        }

        if (!$this->checkVersionPhp()) {
            return false;
        }

        return true;
    }

    /**
     * Checks whether the Joomla! version meets the requirement
     *
     * @return bool
     * @throws Exception
     */
    private function checkVersionJoomla()
    {
        // Using deprecated JVersion, JFactory and JText classes to avoid exceptions in old Joomla! versions
        $version = new JVersion();

        if (!$version->isCompatible(self::MIN_VERSION_JOOMLA)) {
            JFactory::getApplication()->enqueueMessage(JText::sprintf('KRJE_FREE_ERROR_JOOMLA_VERSION', $this->extensionName, self::MIN_VERSION_JOOMLA), 'error');

            return false;
        }

        return true;
    }

    /**
     * Checks whether the PHP version meets the requirement
     *
     * @return bool
     * @throws Exception
     */
    private function checkVersionPhp()
    {
        if (!version_compare(phpversion(), self::MIN_VERSION_PHP, 'ge')) {
            JFactory::getApplication()->enqueueMessage(JText::sprintf('KRJE_FREE_ERROR_PHP_VERSION', $this->extensionName, self::MIN_VERSION_PHP), 'error');

            return false;
        }

        return true;
    }
}
