<?php
/**
 * Class WRTrait
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */

namespace WRUtils\Controller;
use WRUtils\Event\LoggedInCustomerListener;

trait WRTrait
{
    /**
     * {@inheritDoc}
     */
    public function loadModel($modelClass = null, $type = 'Table') {
        $model = parent::loadModel($modelClass, $type);
        $listener = new LoggedInCustomerListener($this->request);
        $model->eventManager()->attach($listener);
        return $model;
    }
}