<?php
/*/**
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
namespace Utils\Database\Type;

use Cake\Database\Type;

class FileType extends Type
{
    /**
     * marshal
     *
     * @param array $value Value.
     * @return mixed
     */
    public function marshal($value)
    {
        return $value;
    }
}
