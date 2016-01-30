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
use Cake\View\View;

require_once(ROOT .DS. 'src' . DS . 'Lib' . DS . 'aws' . DS .'aws-autoloader.php');

/**
 * S3File helper
 *
 * Configuration
 * 1. Set your path to SDK AWS API aws-autoloader.php in the require_once. In general it is like ROOT .DS. 'src' . DS . 'Lib' . DS . 'aws' . DS .'aws-autoloader.php;
 *
 * 2. Set correct parameter to access your AWS
 * 'S3Key' => ''
 * 'S3Secret' => '',
 * 'S3Region' => '',
 * 'S3Version' => '',
 * 'S3SignatureVersion' => ''
 *
 * 3. Add
 * in your AppController
 * public function initialize() {
 *  $this->helpers[] = 'WRUtils.S3File';
 * }
 * in your bootstrap.php
 * Plugin::load('WRUtils');
 *
 * 4. Usage:
 * $this->S3File->image($bucket, $path, $options);
 *
 * $path is the path of the image in the S3 bucket
 * $options are the same as for HTML image, like ['class'=>'img-responsive']
 * if you want to show a default image when no image is retrieved pass, for example, in $options ['noimageimage'=>'path/to/img/image.jpg']
 * if you want to show an HTML piece of code when no image is retrieved pass, for example, in $options ['noimagehtml'=>'<span>no image</span>']
 *
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
    protected $_defaultConfig = [
        'S3Key' => 'AKIAJMF5RMYVJVFEBOLQ',
        'S3Secret' => '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',
        'S3Region' => 'us-east-1',
        'S3Version' => 'latest',
        'S3SignatureVersion' => 'v4',
        'bucket' => 'whiterabbitsuite.com'
    ];

    /**
     * Instance of Amazon S3 Client.
     *
     * @var S3Client
     */
    protected $_s3Client;

    /**
     * Constructor. Overridden to merge passed args with URL options.
     *
     * @param \Cake\View\View $View The View this helper is being attached to.
     * @param array $config Configuration settings for the helper.
     */
    public function __construct(\Cake\View\View $View, array $config = [])
    {
        // Amazon S3 config
        $config = $this->config();

        $credentials = new Credentials($config['S3Key'], $config['S3Secret']);
        $options = [
            'region'            => $config['S3Region'],
            'version'           => $config['S3Version'],
            'http'    => [
                'verify' => false
            ],
            'signature_version' => $config['S3SignatureVersion'],
            'credentials' => $credentials,
            //'debug'   => true
        ];

        $this->_s3Client = new S3Client($options);

        parent::__construct($View, $config + [
                'helpers' => ['Html'],
            ]);
    }
    /**
     * image
     *
     * Return a file image from Amazon S3.
     *
     * ### Example:
     *
     * `$this->S3File->image($path, $options);`
     *
     * $options are the same as for HTML image, like ['class'=>'img-responsive']
     * if you want to show an HTML piece of code when no image is retrieved pass, for example, in $options ['noimagehtml'=>'<span>no image</span>']
     *
     * $path is the path of the image in the S3 bucket
     *
     * @param string $path
     * @param array $options
     * @return string
     */
    public function image($path, array $options = [])
    {
        $html = '';

        $bucketName = $this->getBucketName($this->request->session()->read('Auth.User.customer_site'));

        if ($path != null && $path != '') {
            try {
                $plainUrl = $this->_s3Client->getObjectUrl($bucketName, $path, '+10 minutes');
                $html .= $this->Html->image($plainUrl, $options);
            } catch(\Exception $e) {
                $html .= $this->getDefaultImage($options);
            }
        } else {
            $html .= $this->getDefaultImage($options);
        }

        return $html;
    }


    /**
     * getDefaultImage
     *
     * Return a default image or html.
     *
     * @param array $options
     * @return string
     */
    private function getDefaultImage(array $options = []) {
        $html = '';

        if (!isset($options['noimagehtml'])) {
            $options['noimagehtml'] = '';
        }

        if (!isset($options['noimageimage'])) {
            $options['noimageimage'] = '';
        }

        if ($options['noimageimage'] != '') {
            $html .= $this->Html->image($options['noimageimage'], $options);
        } else {
            $html .= $options['noimagehtml'];
        }

        return $html;
    }


    /**
     * getBucketName
     * Get the bucket name for Amazon S3
     *
     * @param string $site.
     * @return string
     */
    private function getBucketName($site)
    {
        $config = $this->config();
        $bucket = $config['bucket'];
        $builtBucket = strlen($bucket) > 0 ? $site . '.' . $bucket : $site;

        return $builtBucket;
    }
}
