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
namespace WRUtils\Model\Behavior;

use ArrayObject;
use Cake\Database\Type;
use Cake\Event\Event;
use Cake\Filesystem\File;
use Cake\ORM\Behavior;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Aws\S3\S3Client;
use Aws\Credentials\Credentials;
use Aws\S3\Transfer;
require_once(ROOT .DS. 'src' . DS . 'Lib' . DS . 'aws' . DS .'aws-autoloader.php');

/**
 * Uploadable behavior
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
 * ** See next for other parameters options
 *
 * 3. Add
 * in your bootstrap.php
 * Plugin::load('WRUtils');
 *
 * 4. Usage:
 * $this->S3File->image($path, $options);
 *
 * $path is the path of the image in the S3 bucket
 * $options are the same as for HTML image, like ['class'=>'img-responsive']
 * if you want to show a default image when no image is retrieved pass, for example, in $options ['noimageimage'=>'path/to/img/image.jpg']
 * if you want to show an HTML piece of code when no image is retrieved pass, for example, in $options ['noimagehtml'=>'<span>no image</span>']
 *
 */

class UploadableBehavior extends Behavior
{
    /**
     * Default configuration.
     *
     * 'fileName' => '{ORIGINAL}|{GENERATEDKEY}'
     * 'bucket' => suffix for buckets (like mydomain.com)
     * 'path' => '{ROOT}{DS}{WEBROOT}{DS}uploads{DS}{model}{DS}{field}{DS}',
     * @var array
     */
    protected $_defaultConfig = [
        'defaultFieldConfig' => [
            'fields' => [
                'url' => false,
                'directory' => false,
                'type' => false,
                'size' => false,
                'fileName' => false,
                'filePath' => false,
            ],
            'removeFileOnDelete' => true,
            'field' => 'id',
            'path' => '{model}{DS}{field}{DS}',
            'bucket' => 'whiterabbitsuite.com',
            'fileName' => '{GENERATEDKEY}',
            'S3Key' => 'AKIAJMF5RMYVJVFEBOLQ',
            'S3Secret' => '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',
            'S3Region' => 'us-east-1',
            'S3Version' => 'latest',
            'S3SignatureVersion' => 'v4'
        ]
    ];

    /**
     * Preset cofiguration-keys who will be ignored by getting the fields
     *
     * @var type
     */
    protected $_presetConfigKeys = [
        'defaultFieldConfig',
    ];

    /**
     * List of all uploaded data.
     *
     * @var array
     */
    protected $_uploads = [];

    /**
     * List of saved fields.
     *
     * @var array
     */
    protected $_savedFields = [];

    /**
     * Instance of Amazon S3 Client.
     *
     * @var S3Client
     */
    protected $_s3Client;

    /**
     * Customer site name
     *
     * @var string
     */
    protected $_customerSite = '';

    /**
     * __construct
     *
     * @param Table $table Table.
     * @param array $config Config.
     */
    public function __construct(Table $table, array $config = [])
    {
        parent::__construct($table, $config);

        Type::map('WRUtils.File', 'WRUtils\Database\Type\FileType');

        $schema = $table->schema();
        foreach ($this->getFieldList() as $field => $settings) {
            $schema->columnType($field, 'WRUtils.File');
        }
        $table->schema($schema);

        $this->_Table = $table;

        // Amazon S3 config
        $config = $this->config($field);

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
    }

    /**
     * _getBucketName
     * Get the bucket name for Amazon S3
     *
     * @param string $site.
     * @return string
     */
    protected function _getBucketName($site, $field)
    {
        $config = $this->config($field);
        $bucket = $config['bucket'];

        $builtBucket = strlen($bucket) > 0 ? $site . '.' . $bucket : $site;

        return $builtBucket;
    }

    /**
     * _createBucket
     * Create the bucket, if not exists, for Amazon S3
     *
     * @param string $bucketName name of the bucket.
     * @return void
     */
    protected function _createBucket($bucketName)
    {
        //TODO: migliorare con un ritorno di qualcosa...
        if(!$this->_s3Client->doesBucketExist($bucketName)) {
            $this->_s3Client->createBucket(array('Bucket' => $bucketName));
        }

        // Poll the bucket until it is accessible
        $this->_s3Client->waitUntil('BucketExists', array('Bucket' => $bucketName));
    }

    /**
     * beforeSave callback
     *
     * @param \Cake\Event\Event $event Event.
     * @param \Cake\ORM\Entity $entity The Entity.
     * @param array $options Options.
     * @return void
     */
    public function beforeSave($event, $entity, $options)
    {
        $uploads = [];
        $fields = $this->getFieldList();

        $this->_customerSite = $options['loggedInCustomer'];
        foreach ($fields as $field => $data) {
            if (!is_string($entity->get($field))) {
                $uploads[$field] = $entity->get($field);
                $entity->set($field, null);
            }

            if (!$entity->isNew()) {
                $dirtyField = $entity->dirty($field);
                $originalField = $entity->getOriginal($field);
                if ($dirtyField && !is_null($originalField) && !is_array($originalField)) {
                    $fieldConfig = $this->config($field);
                }
            }
        }
        $this->_uploads = $uploads;

        /*
         * if (empty($options['loggedInUser'])) {
			return;
		}
		if ($entity->isNew()) {
			$entity->set('created_by', $options['loggedInUser']);
		}
		$entity->set('modified_by', $options['loggedInUser']);
         */
    }

    /**
     * afterSave callback
     *
     * @param \Cake\Event\Event $event Event.
     * @param \Cake\ORM\Entity $entity The Entity who has been saved.
     * @param array $options Options.
     * @return void
     */
    public function afterSave($event, $entity, $options)
    {
        $fields = $this->getFieldList();
        $storedToSave = [];

        foreach ($fields as $field => $data) {
            if ($this->_ifUploaded($entity, $field)) {
                if ($this->_uploadFile($entity, $field)) {
                    if (!key_exists($field, $this->_savedFields)) {
                        $this->_savedFields[$field] = true;
                        $storedToSave[] = $this->_setUploadColumns($entity, $field);
                    }
                }
            }
        }

        foreach ($storedToSave as $toSave) {
            $event->subject()->save($toSave);
        }
        $this->_savedFields = [];
    }

    /**
     * beforeDelete callback
     *
     * @param \Cake\Event\Event $event Event.
     * @param \Cake\ORM\Entity $entity Entity.
     * @param array $options Options.
     * @return void
     */
    public function beforeDelete($event, $entity, $options)
    {
        $fields = $this->getFieldList();
        foreach ($fields as $field => $data) {
            $fieldConfig = $this->config($field);
            if ($fieldConfig['removeFileOnDelete']) {
                //$this->_removeFile($entity->get($field));
                $this->_removeFileFromS3($entity->get($field), $entity, $field);
            }
        }
    }

    /**
     * Returns a list of all registered fields to upload
     *
     * ### Options
     * - normalize      boolean if each field should be normalized. Default set to true
     *
     * @param array $options Options.
     * @return array
     */
    public function getFieldList($options = [])
    {
        $_options = [
            'normalize' => true,
        ];

        $options = Hash::merge($_options, $options);

        $list = [];

        foreach ($this->config() as $key => $value) {
            if (!in_array($key, $this->_presetConfigKeys) || is_integer($key)) {
                if (is_integer($key)) {
                    $field = $value;
                } else {
                    $field = $key;
                }

                if ($options['normalize']) {
                    $fieldConfig = $this->_normalizeField($field);
                } else {
                    $fieldConfig = (($this->config($field) == null) ? [] : $this->config($field));
                }

                $list[$field] = $fieldConfig;
            }
        }
        return $list;
    }

    /**
     * _ifUploaded
     *
     * Checks if an file has been uploaded by user.
     *
     * @param \Cake\ORM\Entity $entity Entity to check on.
     * @param string $field Field to check on.
     * @return bool
     */
    protected function _ifUploaded($entity, $field)
    {
        if (array_key_exists($field, $this->_uploads)) {
            $data = $this->_uploads[$field];

            if (!empty($data['tmp_name'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * _uploadFile
     *
     * Uploads the file to the directory
     *
     * @param \Cake\ORM\Entity $entity Entity to upload from.
     * @param string $field Field to use.
     * @param array $options Options.
     * @return bool
     */
    protected function _uploadFile($entity, $field, $options = [])
    {
        // creating the bucket if not exists
        $bucketName = $this->_getBucketName($this->_customerSite, $field);

        $this->_createBucket($bucketName);

        $_upload = $this->_uploads[$field];

        $ext = pathinfo($_upload['name'], PATHINFO_EXTENSION);
        $fileKey = $this->_getS3FolderPath($entity, $field);

        // Upload an object by streaming the contents of a file
        $result = $this->_s3Client->putObject(array(
            'Bucket'     => $bucketName,
            'Key'        => $fileKey,
            'SourceFile' => $_upload['tmp_name'],
            'ContentLength' => $_upload['size'],
            'ContentType'  => $_upload['type'],
            'ACL'          => 'public-read',
            'Metadata'   => array(
                'Author' => 'whiterabbitsuite.com'
            )
        ));

        // We can poll the object until it is accessible
        $this->_s3Client->waitUntil('ObjectExists', array(
            'Bucket' => $bucketName,
            'Key'    => $fileKey
        ));

        //TODO: gestire eccezioni
        return true;
    }

    /**
     * _getFileKey
     *
     * Create a unique file key.
     *
     * @param string $fileName FileName convert.
     * @return string
     */
    protected function _getFileKey($file, $fileExt)
    {
        //$part = str_replace (".", "x", sha1_file($file) . microtime(true));
        //$fileKey = $part . '.' . $fileExt;
        //return $fileKey;
        $fileKey = sha1_file($file) . '.' . $fileExt;
        return $fileKey;
    }

    /**
     * _setUploadColumns
     *
     * Writes all data of the upload to the entity
     *
     * Returns the modified entity
     *
     * @param \Cake\ORM\Entity $entity Entity to check on.
     * @param string $field Field to check on.
     * @param array $options Options.
     * @return \Cake\ORM\Entity
     */
    protected function _setUploadColumns($entity, $field, $options = [])
    {
        $fieldConfig = $this->config($field);
        $_upload = $this->_uploads[$field];

        // set all columns with values
        foreach ($fieldConfig['fields'] as $key => $column) {
            if ($column) {
                if ($key == "url") {
                    $entity->set($column, $this->_getUrl($entity, $field));
                }
                if ($key == "directory") {
                    $entity->set($column, $this->_getPath($entity, $field, ['root' => false, 'file' => false]));
                }
                if ($key == "type") {
                    $entity->set($column, $_upload['type']);
                }
                if ($key == "size") {
                    $entity->set($column, $_upload['size']);
                }
                if ($key == "fileName") {
                    $entity->set($column, $this->_getFileName($entity, $field, $options = []));
                }
                if ($key == "filePath") {
                    //$entity->set($column, $this->_getPath($entity, $field, ['root' => false, 'file' => true]));
                    $entity->set($column, $this->_getS3FolderPath($entity, $field)); // This is the value saved in the main field
                }
            }
        }
        return $entity;
    }

    /**
     * _normalizeField
     *
     * Normalizes the requested field.
     *
     * ### Options
     * - save           boolean if the normalized data should be saved in config
     *                  default set to true
     *
     * @param string $field Field to normalize.
     * @param array $options Options.
     * @return array
     */
    protected function _normalizeField($field, $options = [])
    {
        $_options = [
            'save' => true,
        ];

        $options = Hash::merge($_options, $options);

        $data = $this->config($field);

        if (is_null($data)) {
            foreach ($this->config() as $key => $config) {
                if ($config == $field) {
                    if ($options['save']) {
                        $this->config($field, []);

                        $this->_configDelete($key);
                    }

                    $data = [];
                }
            }
        }

        // adding the default directory-field if not set
        if (is_null(Hash::get($data, 'fields.filePath'))) {
            $data = Hash::insert($data, 'fields.filePath', $field);
        }

        $data = Hash::merge($this->config('defaultFieldConfig'), $data);

        if ($options['save']) {
            $this->config($field, $data);
        }

        return $data;
    }

    /**
     * _getPath
     *
     * Returns the path of the given field.
     *
     * ### Options
     * - `root` - If root should be added to the path.
     * - `file` - If the file should be added to the path.
     *
     * @param \Cake\ORM\Entity $entity Entity to check on.
     * @param string $field Field to check on.
     * @param array $options Options.
     * @return string
     */
    protected function _getPath($entity, $field, $options = [])
    {
        $_options = [
            'root' => true,
            'file' => false,
        ];

        $options = Hash::merge($_options, $options);

        $config = $this->config($field);

        $path = $config['path'];

        $replacements = [
            '{ROOT}' => ROOT,
            '{WEBROOT}' => 'webroot',
            '{field}' => $entity->get($config['field']),
            '{model}' => Inflector::underscore($this->_Table->alias()),
            '{DS}' => DIRECTORY_SEPARATOR,
            '\\' => DIRECTORY_SEPARATOR,
        ];

        $builtPath = str_replace(array_keys($replacements), array_values($replacements), $path);

        if (!$options['root']) {
            $builtPath = str_replace(ROOT . DS . 'webroot' . DS, '', $builtPath);
        }

        if ($options['file']) {
            $builtPath = $builtPath . $this->_getFileName($entity, $field);
        }

        return $builtPath;
    }

    /**
     * _getUrl
     *
     * Returns the URL of the given field.
     *
     * @param \Cake\ORM\Entity $entity Entity to check on.
     * @param string $field Field to check on.
     * @return string
     */
    protected function _getUrl($entity, $field)
    {
        $path = '/' . $this->_getPath($entity, $field, ['root' => false, 'file' => true]);
        return str_replace(DS, '/', $path);
    }

    /**
     * _getS3FolderPath
     *
     * Returns the S3 Folder Path of the given field.
     *
     * @param \Cake\ORM\Entity $entity Entity to check on.
     * @param string $field Field to check on.
     * @return string
     */
    protected function _getS3FolderPath($entity, $field)
    {
        $path = $this->_getPath($entity, $field, ['root' => false, 'file' => true]);
        return str_replace(DS, '/', $path);
    }

    /**
     * _getFileName
     *
     * Returns the fileName of the given field.
     *
     * @param \Cake\ORM\Entity $entity Entity to check on.
     * @param string $field Field to check on.
     * @param array $options Options.
     * @return string
     */
    protected function _getFileName($entity, $field, $options = [])
    {
        $_options = [
        ];

        $options = Hash::merge($_options, $options);

        $config = $this->config($field);

        $_upload = $this->_uploads[$field];

        $fileInfo = explode('.', $_upload['name']);
        $extension = end($fileInfo);

        $fileName = $config['fileName'];
        $ext = pathinfo($_upload['name'], PATHINFO_EXTENSION);
        $replacements = [
            '{ORIGINAL}' => $_upload['name'],
            '{GENERATEDKEY}' => $this->_getFileKey($_upload['tmp_name'], $ext),
            '{field}' => $entity->get($config['field']),
            '{extension}' => $extension,
            '{DS}' => DIRECTORY_SEPARATOR,
            '//' => DIRECTORY_SEPARATOR,
            '/' => DIRECTORY_SEPARATOR,
            '\\' => DIRECTORY_SEPARATOR,
        ];

        $builtFileName = str_replace(array_keys($replacements), array_values($replacements), $fileName);

        return $builtFileName;
    }

    /**
     * _moveUploadedFile
     *
     * moveUploadedFile Wrapper.
     *
     * @param string $source The source of the file (tmp).
     * @param string $path The path to save to.
     * @return bool
     */
    protected function _moveUploadedFile($source, $path)
    {
        return move_uploaded_file($source, $path);
    }

    /**
     * _mkdir
     *
     * mkdir Wrapper.
     *
     * @param string $pathname The path to save to.
     * @param int $mode Mode.
     * @param bool $recursive Recursive.
     * @return bool
     */
    protected function _mkdir($pathname, $mode, $recursive)
    {
        return mkdir($pathname, $mode, $recursive);
    }


    /**
     * _removeFileFromS3
     *
     * @param string $file Path of the file
     * @param \Cake\ORM\Entity $entity Entity to check on.
     * @param string $field Field to check on.
     * @return bool
     */
    protected function _removeFileFromS3($file, $entity, $field)
    {
        if($file != null && $file != '') { // Only if a file exist!
            $bucketName = $this->_getBucketName($this->_customerSite, $field);
            if($this->_s3Client->doesObjectExist($bucketName, $file)) {
                $result = $this->_s3Client->deleteObject(array(
                    'Bucket'  => $bucketName,
                    'Key' => $file
                ));
            }
        }

        //TODO: migliorare il ritorno
        return true;
    }
}
