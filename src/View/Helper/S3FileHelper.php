<?php
/**
 * WhiteRabbit (http://www.whiterabbitsuite.com)
 * Copyright (c) http://www.whiterabbitsuite.com
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) http://www.whiterabbitsuite.com
 * @link          http://www.whiterabbitsuite.com WhiteRabbit Project
 * @since         1.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace WRUtils\View\Helper;

use Cake\View\Helper;
use Aws\S3\S3Client;
use Aws\Credentials\Credentials;
require_once(ROOT .DS. 'src' . DS . 'Lib' . DS . 'aws' . DS .'aws-autoloader.php');

/**
 * Search helper
 */
class S3FileHelper extends Helper
{
    /**
     * Helpers
     *
     * @var array
     */
    public $helpers = ['Html'];

    /**
     * Default configuration.
     *
     * @var array
     */
    protected $_defaultConfig = [];

    /**
     * image
     *
     * Return a file image from Amazon S3.
     *
     * ### Example:
     *
     * `$this->S3File->image($bucket, $path, $options);`
     *
     * $options are the same as for HTML image, like ['class'=>'img-responsive']
     * if you want to show an HTML piece of code when no image is retrieved pass, for example, in $options ['noimagehtml'=>'<span>no image</span>']
     *
     * $path is the path of the image in the S3 bucket
     *
     * @param string $bucket
     * @param string $path
     * @param array $options
     * @return string
     */
    public function image($bucket, $path, array $options = [])
    {
        $html = '';

        //TODO: sistemare nella config
        $credentials = new Credentials('AKIAJMF5RMYVJVFEBOLQ', '/tRq5IkafYk67Xy1OP++f+UUsT/VH1oWe51U/wak');
        $S3options = [
            'region'            => 'us-east-1',
            'version'           => 'latest',
            'http'    => [
                'verify' => false
            ],
            'signature_version' => 'v4',
            'credentials' => $credentials,
            //'debug'   => true
        ];

        if (!isset($options['noimagehtml'])) {
            $options['noimagehtml'] = '';
        }

        $S3Client = new S3Client($S3options);
        //$bucket = 'source_lists.' . $this->id . '.whiterabbitesuite.com'; //TODO: recuperare dal DB
        if ($bucket != null && $bucket != '' && $path != null && $path != '') {
            $plainUrl = $S3Client->getObjectUrl($bucket, $path, '+10 minutes');
            $html .= $this->Html->image($plainUrl, $options);
        } else {
            $html .= $options['noimagehtml'];
        }

        return $html;

    }
}
