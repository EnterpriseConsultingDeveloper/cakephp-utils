<?php

/**
 * Class LoggedInCustomerListener
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */

namespace WRUtils\Event;
use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\Event\EventListenerInterface;
use Cake\Network\Request;

class LoggedInCustomerListener implements EventListenerInterface
{
    /**
     * @var RequestComponent
     */
    protected $_Request;
    /**
     * Constructor
     *
     * @param \Cake\Network\Request $Request Request
     */
    public function __construct(Request $Request) {
        $this->_Request = $Request;
    }
    /**
     * {@inheritDoc}
     */
    public function implementedEvents() {
        return [
            'Model.beforeSave' => [
                'callable' => 'beforeSave',
                'priority' => -100
            ]
        ];
    }
    /**
     * Before save listener.
     *
     * @param \Cake\Event\Event $event The beforeSave event that was fired
     * @param \Cake\Datasource\EntityInterface $entity The entity that is going to be saved
     * @param \ArrayObject $options the options passed to the save method
     * @return void
     */
    public function beforeSave(Event $event, EntityInterface $entity, ArrayObject $options) {
        if (empty($options['loggedInCustomer'])) {
            $customerSite = $this->_Request->session()->read('Auth.User.customer_site');
            if($customerSite == null) {
                $customerSite = '';
            }
            $options['loggedInCustomer'] = $customerSite;
        }
    }
}