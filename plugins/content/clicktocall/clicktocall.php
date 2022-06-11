<?php
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

class plgContentClicktocall extends JPlugin
{

    /*
     * Deprecated: Methods with the same name as their class will not be constructors in a future version of PHP; plgContentClicktocall has a deprecated constructor in C:\xampp\htdocs\Joomla-3-component\plugins\content\clicktocall\clicktocall.php on line 6
    function plgContentClicktocall( &$subject, $params )
    {
        parent::__construct( $subject, $params );
    }
*/
    public function onContentPrepare($context, &$row, &$params, $page = 0)
    {
        // Do not run this plugin when the content is being indexed
        if ($context == 'com_finder.indexer')
        {
            return true;
        }
        if (is_object($row))
        {
            return $this->clickToCall($row->text, $params);
        }
        return $this->clickToCall($row, $params);
    }

    protected function clickToCall(&$text, &$params)
    {
        // matches 4 numbers followed by an optional hyphen or space,
        // then followed by 4 numbers.
        // phone number is in the form XXXX-XXXX or XXXX XXXX
        $pattern = '/(\W[0-9]{4})-? ?(\W[0-9]{4})/';
        $replacement = '<a href="tel:$1$2">$1$2</a>';
        $text = preg_replace($pattern, $replacement, $text);

        return true;
    }
}
