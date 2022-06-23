<?php

/**
 * @copyright
 * @package     Easy Image Resizer - EIR for Joomla! 3.x
 * @author      Viktor Vogel <admin@kubik-rubik.de>
 * @version     3.7.0-FREE - 2020-05-23
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

use Joomla\CMS\{Plugin\CMSPlugin, Factory, Filesystem\File, Filesystem\Folder, Language\Transliterate};
use Joomla\Input\Files as InputFiles;
use Joomla\Image\Image;
use EasyImageResizer\Optimus;

class PlgSystemEasyImageResizer extends CMSPlugin
{
    /**
     * @var array $allowedMimeTypes
     * @since 3.0.0-FREE
     */
    protected $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];

    /**
     * @var int $compressionPng
     * @since 3.0.0-FREE
     */
    protected $compressionPng;

    /**
     * @var int $enlargeImages
     * @since 3.2.2-FREE
     */
    protected $enlargeImages;

    /**
     * @var string $multisizePath
     * @since 3.5.0-FREE
     */
    protected $multisizePath;

    /**
     * @var array $optimusMimeTypes
     * @since 3.3.0-FREE
     */
    protected $optimusMimeTypes;

    /**
     * @var Optimus $optimusObject
     * @since 3.3.0-FREE
     */
    protected $optimusObject;

    /**
     * @var int $optimusQuota
     * @since 3.3.0-FREE
     */
    protected $optimusQuota;

    /**
     * @var int $qualityJpg
     * @since 3.0.0-FREE
     */
    protected $qualityJpg;

    /**
     * @var int $scaleMethod
     * @since 3.0.0-FREE
     */
    protected $scaleMethod;

    /**
     * PlgSystemEasyImageResizer constructor
     *
     * @param object $subject
     * @param array  $config
     *
     * @since 3.0.0-FREE
     */
    public function __construct(object &$subject, array $config)
    {
        parent::__construct($subject, $config);

        require_once __DIR__ . '/src/Autoload.php';
    }

    /**
     * Optimizes generic image uploads and makes image names safe in the trigger onAfterInitialise()
     *
     * @throws Exception
     * @since 3.0.0-FREE
     */
    public function onAfterInitialise()
    {
        if ($this->params->get('optimus', 1) && $this->params->get('optimusUploads', 1)) {
            $this->uploadImagesOptimus();
        }

        if ($this->params->get('safeNames', 1)) {
            $this->makeNameSafe();
        }
    }

    /**
     * Optimizes images in generic image upload processes with Optimus.io
     *
     * @since 3.4.0-FREE
     */
    private function uploadImagesOptimus()
    {
        $inputFiles = new InputFiles();
        $inputKeys = array_keys($_FILES);

        if (!empty($inputKeys)) {
            foreach ($inputKeys as $inputKey) {
                $fileData = $inputFiles->get($inputKey, [], 'raw');

                if (empty($fileData[0])) {
                    $fileData = [$fileData];
                }

                foreach ($fileData as $file) {
                    if (!empty($file['tmp_name']) && !empty($file['type'])) {
                        $size = false;

                        if (!empty($file['size'])) {
                            $size = $file['size'];
                        }

                        $this->initialiseOptimus();
                        $this->optimizeImageOptimus($file['tmp_name'], $file['type'], $size);
                    }
                }
            }
        }
    }

    /**
     * Initialises the service Optimus.io for lossless compression of images
     *
     * @since 3.3.0-FREE
     */
    private function initialiseOptimus()
    {
        if (empty($this->optimusObject)) {
            $optimusApiKey = $this->params->get('optimusApiKey', '');
            $this->optimusObject = new Optimus($optimusApiKey);
            $this->optimusQuota = $this->optimusObject->getRequestQuota();
            $this->optimusMimeTypes = ['image/jpeg', 'image/png'];
        }
    }

    /**
     * Optimizes the images with Optimus.io
     *
     * @param string $imagePath
     * @param string $mimeType
     * @param int    $fileSize
     *
     * @since 3.3.0-FREE
     */
    private function optimizeImageOptimus(string $imagePath, string $mimeType, int $fileSize = 0)
    {
        if (File::exists($imagePath)) {
            if (empty($fileSize)) {
                $fileSize = filesize($imagePath);
            }

            if (is_int($fileSize) && $this->optimusQuota >= $fileSize) {
                if (in_array($mimeType, $this->optimusMimeTypes)) {
                    $result = $this->optimusObject->optimize($imagePath);

                    if (!empty($result)) {
                        file_put_contents($imagePath, $result);
                    }
                }
            }
        }
    }

    /**
     * Creates safe image names for the Media Manager
     *
     * @throws Exception
     * @since 3.2.1-FREE
     */
    private function makeNameSafe()
    {
        $input = Factory::getApplication()->input;

        if ($input->get('option') == 'com_media' && $input->get('task') == 'file.upload') {
            $inputFiles = new InputFiles();
            $fileData = $inputFiles->get('Filedata', [], 'raw');

            foreach ($fileData as $key => $file) {
                if (!empty($file['name'])) {
                    // UTF8 to ASCII
                    $file['name'] = Transliterate::utf8_latin_to_ascii($file['name']);

                    // Make image name safe with core function
                    $file['name'] = File::makeSafe($file['name']);

                    // Replace whitespaces with underscores
                    $file['name'] = preg_replace('@\s+@', '-', $file['name']);

                    // Make a string lowercase
                    $file['name'] = strtolower($file['name']);

                    // Set the name back directly to the global FILES variable
                    $_FILES['Filedata']['name'][$key] = $file['name'];
                }
            }
        }
    }

    /**
     * Plugin uses the trigger onContentBeforeSave to manipulate the uploaded images
     *
     * @param string $context
     * @param object $object
     * @param bool   $state
     *
     * @since 3.0.0-FREE
     */
    public function onContentBeforeSave($context, $object, $state)
    {
        if ($context == 'com_media.file' && (!empty($object) && is_object($object)) && $state == true) {
            $this->resizeImagePreparation($object);
        }
    }

    /**
     * Prepares image resize process
     *
     * @param object $imageObject
     *
     * @since 3.0.0-FREE
     */
    private function resizeImagePreparation(object $imageObject)
    {
        if (!empty($imageObject->tmp_name) && !empty($imageObject->type)) {
            if (in_array($imageObject->type, $this->allowedMimeTypes)) {
                $this->setQualityJpg();
                $this->setCompressionPng();
                $this->scaleMethod = (int)$this->params->get('scaleMethod', 2);
                $this->enlargeImages = (int)$this->params->get('enlargeImages', 0);
                $width = (int)$this->params->get('width', 0);
                $height = (int)$this->params->get('height', 0);

                // At least one value has to be set and not negative to execute the resizing process
                if ((!empty($width) && $width >= 0) || (!empty($height) && $height >= 0)) {
                    $this->resizeImage($imageObject->type, $imageObject->tmp_name, '', $width, $height, false);
                }
            }
        }
    }

    /**
     * Sets the quality of JPG images - 0 to 100
     *
     * @since 3.0.0-FREE
     */
    private function setQualityJpg()
    {
        $this->qualityJpg = (int)$this->params->get('qualityJpg', 80);

        // Set default value if entered value is out of range
        if ($this->qualityJpg < 0 || $this->qualityJpg > 100) {
            $this->qualityJpg = 80;
        }
    }

    /**
     * Sets the compression level of PNG images - 0 to 9
     *
     * @since 3.0.0-FREE
     */
    private function setCompressionPng()
    {
        $this->compressionPng = (int)$this->params->get('compressionPng', 6);

        // Set default value if entered value is out of range
        if ($this->compressionPng < 0 || $this->compressionPng > 9) {
            $this->compressionPng = 6;
        }
    }

    /**
     * Resizes images using Joomla! core class Image
     *
     * @param string      $objectType
     * @param string      $sourcePath
     * @param string      $targetPath
     * @param int         $width
     * @param int         $height
     * @param bool        $multiresize
     * @param bool|string $multisizeSuffix
     * @param bool|int    $multisizeScaleMethod
     *
     * @return bool|string
     * @since 3.0.0-FREE
     */
    private function resizeImage(string $objectType, string $sourcePath, string $targetPath, int $width = 0, int $height = 0, bool $multiresize = true, $multisizeSuffix = false, $multisizeScaleMethod = false)
    {
        if (in_array($objectType, $this->allowedMimeTypes)) {
            try {
                $imageObject = new Image($sourcePath);
                $scaleMethod = $this->scaleMethod;

                if (!empty($multisizeScaleMethod) && in_array($multisizeScaleMethod, [1, 2, 3, 4, 5, 6])) {
                    $scaleMethod = (int)$multisizeScaleMethod;
                }

                if ($scaleMethod == 4) {
                    $imageObject->crop($width, $height, null, null, false);
                } elseif ($scaleMethod == 5) {
                    $imageObject->cropResize($width, $height, false);
                } else {
                    $imageObject->resize($width, $height, false, $scaleMethod);
                }

                if (empty($this->enlargeImages)) {
                    $imageProperties = $imageObject->getImageFileProperties($sourcePath);

                    if ($imageObject->getWidth() > $imageProperties->width || $imageObject->getHeight() > $imageProperties->height) {
                        return false;
                    }
                }

                $imageSavePath = ($multiresize ? $this->getThumbnailPath($sourcePath, $imageObject->getWidth(), $imageObject->getHeight(), $multisizeSuffix) : (!empty($targetPath) ? $targetPath : $sourcePath));
                $imageInformation = $this->getImageInformation($objectType);
                $imageObject->toFile($imageSavePath, $imageInformation['type'], ['quality' => $imageInformation['quality']]);

                return $imageSavePath;
            } catch (Exception $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * Creates the full path, including the name of the thumbnail
     *
     * @param string      $imagePathOriginal
     * @param int         $width
     * @param int         $height
     * @param bool|string $multisizeSuffix
     *
     * @return string
     * @since 3.5.0-FREE
     */
    private function getThumbnailPath(string $imagePathOriginal, int $width, int $height, $multisizeSuffix = false): string
    {
        $imageExtension = '.' . File::getExt(basename($imagePathOriginal));
        $imageNameOriginal = basename($imagePathOriginal, $imageExtension);

        $imageNameSuffix = $width . 'x' . $height;

        if (!empty($multisizeSuffix)) {
            $imageNameSuffix = $multisizeSuffix;
        }

        return $this->multisizePath . '/' . $imageNameOriginal . '-' . $imageNameSuffix . $imageExtension;
    }

    /**
     * Gets needed information for the image manipulating process
     *
     * @param string $mimeType
     *
     * @return array
     * @since 3.0.0-FREE
     */
    private function getImageInformation(string $mimeType): array
    {
        $imageInformation = ['type' => IMAGETYPE_JPEG, 'quality' => $this->qualityJpg];

        if ($mimeType == 'image/gif') {
            $imageInformation = ['type' => IMAGETYPE_GIF, 'quality' => ''];
        } elseif ($mimeType == 'image/png') {
            $imageInformation = ['type' => IMAGETYPE_PNG, 'quality' => $this->compressionPng];
        }

        return $imageInformation;
    }

    /**
     * Plugin uses the trigger onContentAfterSave to manipulate the multisize images
     *
     * @param string $context
     * @param object $object
     * @param bool   $state
     *
     * @since 3.0.0-FREE
     */
    public function onContentAfterSave($context, $object, $state)
    {
        if ($context == 'com_media.file' && (!empty($object) && is_object($object)) && $state == true) {
            // Optimus.io implementation
            $optimus = $this->params->get('optimus');

            if (!empty($optimus)) {
                $this->initialiseOptimus();
                $this->optimizeImageOptimus($object->filepath, $object->type);
            }

            $multiSizes = $this->params->get('multisizes');

            if (!empty($multiSizes)) {
                $multiSizesLines = [];
                $this->createThumbnailFolder($object->filepath);
                $multiSizes = array_map('trim', explode("\n", $multiSizes));

                foreach ($multiSizes as $multiSizesLine) {
                    $multiSizesLines[] = array_map('trim', explode('|', $multiSizesLine));
                }

                foreach ($multiSizesLines as $multiSizeLine) {
                    // At least one value has to be set and not negative to execute the resizing process
                    if ((!empty($multiSizeLine[0]) && $multiSizeLine[0] >= 0) || (!empty($multiSizeLine[1]) && $multiSizeLine[1] >= 0)) {
                        $multiSizeSuffix = false;

                        if (!empty($multiSizeLine[2])) {
                            $multiSizeSuffix = htmlspecialchars($multiSizeLine[2]);
                        }

                        $multiSizeScaleMethod = $this->scaleMethod;

                        if (!empty($multiSizeLine[3])) {
                            if (in_array((int)$multiSizeLine[3], [1, 2, 3, 4, 5, 6])) {
                                $multiSizeScaleMethod = (int)$multiSizeLine[3];
                            }
                        }

                        $imagePath = $this->resizeImage($object->type, $object->filepath, '', $multiSizeLine[0], $multiSizeLine[1], true, $multiSizeSuffix, $multiSizeScaleMethod);

                        if (!empty($optimus) && !empty($imagePath)) {
                            $this->optimizeImageOptimus($imagePath, $object->type);
                        }
                    }
                }
            }
        }
    }

    /**
     * Creates thumbnail folder if it does not exist yet
     *
     * @param string $imagePathOriginal
     *
     * @since 3.0.0-FREE
     */
    private function createThumbnailFolder(string $imagePathOriginal)
    {
        $this->multisizePath = dirname($imagePathOriginal);
        $multiSizePath = $this->params->get('multisizePath', '');

        if (!empty($multiSizePath)) {
            $this->multisizePath .= '/' . $multiSizePath;
        }

        if (!Folder::exists($this->multisizePath)) {
            Folder::create($this->multisizePath);
        }
    }
}
