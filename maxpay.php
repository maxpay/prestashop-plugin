<?php
/**
 * @package       MAXPAY Payment Module for Prestashop
 * @copyright     (c) 2020 MAXPAY. All rights reserved.
 * @license       BSD 2 License
 */

require_once(__DIR__ . '/vendor/autoload.php');

use Maxpay\Scriney;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Maxpay extends PaymentModule
{
    
    const SUCCESS_STATUS = 'Success';
    
    protected $_html = '';
    protected $_postErrors = array();

    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;

    public function __construct()
    {
        $this->name = 'maxpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'Maxpay';
        $this->controllers = array('external');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        
        parent::__construct();

        $this->displayName = 'MAXPAY';
        $this->description = 'MAXPAY Payment Module for PrestaShop';
        
        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
        
        $this->setModuleSettings();
        $this->checkModuleRequirements();
    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook('paymentOptions') || 
                !$this->registerHook('actionOrderStatusPostUpdate') ||
                !$this->registerHook('actionOrderSlipAdd')) {
            return false;
        }
        
        if (!$this->installMaxpayOpenOrderState()) {
            return false;
        }
        
        if (!$this->installTab('AdminPayment', 'AdminMaxpay', 'MAXPAY')) {
            return false;
        }
        
        return true;
    }
    
    public function hookPaymentOptions($params)
    {        
        if (!$this->active || !$this->publicKey || !$this->secretKey) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $payment_options = [
            $this->getExternalPaymentOption(),
        ];

        return $payment_options;
    }
    
    /**
     * Full order refund, based on order status change to PS_OS_REFUND
     * @param array $params
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        if ($params['newOrderStatus']->id == Configuration::get('PS_OS_REFUND')) {

            $order = new Order($params['id_order']);
            $customer = $order->getCustomer();
            
            if (Validate::isLoadedObject($order) && ($order->module === 'maxpay')) {
                
                $scriney = new Scriney($this->publicKey, $this->secretKey);
                
                $transactionId = $params['id_order'] . '_' . $customer->id;
                $amount = number_format($order->total_paid, 2, '.', '');
                $currency = new Currency($order->id_currency);
                $currencyCode = $currency->iso_code;
                
                $result = $scriney->refund($transactionId, $amount, $currencyCode);
 
                if ($scriney->validateApiResult($result) && ($result['status'] == self::SUCCESS_STATUS)) {
                    $status = 1;
                    $message = "Order #" . $order->id . ", full refund message: " . $result['message'];
                } else {
                    $status = 3;
                    $message = "Order #" . $order->id . ", full refund failed (" . ($result['message'] ?? '') . ')';
                }
                PrestaShopLogger::addLog($message, $status, null, 'Order', $order->id, true);
            }
        }
    }
    
    /**
     * Partial refund hook
     * @param array $params
     */
    public function hookactionOrderSlipAdd($params)
    {
        
        if (isset($params['order']) && ($params['order']->module === 'maxpay') && Tools::isSubmit('partialRefundProduct')
            && ($refunds = Tools::getValue('partialRefundProduct'))
            && is_array($refunds)
        ) {

            $amount = 0;
            
            foreach ($params['productList'] as $product) {
                $amount += $product['amount'];
            }
            
            if (Tools::getValue('partialRefundShippingCost')) {
                $amount += Tools::getValue('partialRefundShippingCost');
            }
            
            $customer = $params['order']->getCustomer();
            $scriney = new Scriney($this->publicKey, $this->secretKey);
            
            $transactionId = $params['order']->id . '_' . $customer->id;
            $currency = new Currency($params['order']->id_currency);
            $currencyCode = $currency->iso_code;
            
            $result = $scriney->refund($transactionId, number_format($amount, 2, '.', ''), $currencyCode);

            if ($scriney->validateApiResult($result) && ($result['status'] == self::SUCCESS_STATUS)) {
                $status = 1;
                $message = "Order #" . $params['order']->id . ", partial refund message: " . $result['message'] . ", refund sum: {$amount} {$currencyCode}";
            } else {
                $status = 3;
                $message = "Order #" . $params['order']->id . ", partial refund failed (" . ($result['message'] ?? '') . ')';
            }

            PrestaShopLogger::addLog($message, $status, null, 'Order', $params['order']->id, true);
            
        }
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getExternalPaymentOption()
    {
        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText($this->l('MaxPay'))
                       ->setAction($this->context->link->getModuleLink($this->name, 'external', array(), true));

        return $externalOption;
    }
    
    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }

        if (!$this->uninstallTab('AdminMaxpay')) {
            return false;
        }

        $this->unregisterHook('paymentOptions');
        $this->unregisterHook('actionOrderStatusPostUpdate');
        $this->unregisterHook('actionOrderSlipAdd');

        $this->deleteModuleSettings();

        return true;
    }
    
    private function setModuleSettings()
    {
        if (Configuration::get('MAXPAY_TESTMODE')) {
            $this->publicKey   = Configuration::get('MAXPAY_PUBLICKEY_TEST');
            $this->secretKey   = Configuration::get('MAXPAY_SECRETKEY_TEST');
        } else {
            $this->publicKey   = Configuration::get('MAXPAY_PUBLICKEY');
            $this->secretKey   = Configuration::get('MAXPAY_SECRETKEY');
        }
    }

    private function deleteModuleSettings()
    {
        Configuration::deleteByName('MAXPAY_PUBLICKEY');
        Configuration::deleteByName('MAXPAY_SECRETKEY');
        Configuration::deleteByName('MAXPAY_PUBLICKEY_TEST');
        Configuration::deleteByName('MAXPAY_SECRETKEY_TEST');
        Configuration::deleteByName('MAXPAY_TESTMODE');
    }
    
    private function checkModuleRequirements()
    {
        $this->_errors = array(); 

        if (!$this->publicKey || !$this->secretKey) {
            $this->_errors['merchantERR'] = $this->l('To configure payment methods we need to know the mandatory fields in the configuration above');
        }
    }
    
    public function getHookController($hook_name)
    {
        // Include the controller file
        require_once(dirname(__FILE__).'/controllers/hook/'. $hook_name.'.php');

        // Build dynamically the controller name
        $controller_name = $this->name.$hook_name.'Controller';

        // Instantiate controller
        $controller = new $controller_name($this, __FILE__, $this->_path);

        // Return the controller
        return $controller;
    }
    
    public function getContent()
    {
        if (!Tools::getValue('ajax')) {
            $controller = $this->getHookController('getContent');
            return $controller->run();
        }
    }
    
    private function installMaxpayOpenOrderState()
    {
        if (Configuration::get('PS_OS_MAXPAY_OPEN') < 1) {
            $order_state = new OrderState();
            $order_state->name = array();
            
            foreach (Language::getLanguages(false) as $language) {
                $order_state->name[(int)$language['id_lang']] = $this->l('Awaiting Maxpay payment');
            }
            
            $order_state->invoice     = false;
            $order_state->send_email  = false;
            $order_state->module_name = $this->name;
            $order_state->color       = "RoyalBlue";
            $order_state->unremovable = true;
            $order_state->hidden      = false;
            $order_state->logable     = false;
            $order_state->delivery    = false;
            $order_state->shipped     = false;
            $order_state->paid        = false;
            $order_state->deleted     = false;

            if ($order_state->add()) {
                Configuration::updateValue("PS_OS_MAXPAY_OPEN", $order_state->id);
            } else {
                return false;
            }
        }
        return true;
    }
    
    private function installTab($parent, $class_name, $name)
    {
        // Create new admin tab
        $tab = new Tab();
        $tab->id_parent = (int)Tab::getIdFromClassName($parent);
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $name;
        }
        $tab->class_name = $class_name;
        $tab->module = $this->name;
        $tab->active = 1;
        return $tab->add();
    }

    private function uninstallTab($class_name)
    {
        $id_tab = (int)Tab::getIdFromClassName($class_name);
        $tab = new Tab((int)$id_tab);
        return $tab->delete();
    }
}