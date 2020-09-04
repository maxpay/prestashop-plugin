<?php

/**
 * @package       MAXPAY Payment Module for Prestashop
 * @copyright     (c) 2020 MAXPAY. All rights reserved.
 * @license       BSD 2 License
 */

class AdminMaxpayController extends ModuleAdminController
{
    public function __construct()
    {
        $this->table = 'maxpay_pminfo';
        $this->className = 'MaxpayPaymentMethod';
        $this->position_identifier = 'position';
        $this->show_toolbar = false;
        $this->_orderBy = 'position';
        $this->bulk_actions = array();
        $this->shopLinkType = 'shop';
        $this->list_simple_header = true;

        parent::__construct();

        // Enable bootstrap
        $this->bootstrap = true;

    }

    public function initToolbar()
    {
        $this->toolbar_btn = array();
    }
}
