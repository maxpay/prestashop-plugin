<?php
/**
 * @package       MAXPAY Payment Module for Prestashop
 * @copyright     (c) 2020 MAXPAY. All rights reserved.
 * @license       BSD 2 License
 */

class MaxpayPaymentReturnModuleFrontController extends ModuleFrontController
{
    const DECLINE_STATUS = 'decline';
    
    public function initContent()
    {
        parent::initContent();

        $this->setTemplate('module:maxpay/views/templates/front/payment_return.tpl');
    }

    public function postProcess()
    {
        
        if (!$this->module->active || !$_POST) {
            return Tools::redirect('/');
        }
        
        // Check if module is enabled
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == $this->module->name) {
                $authorized = true;
            }
        }
        
        if (!$authorized) {
            die('This payment method is not available.');
        }
        
        Tools::safePostVars();
        
        $transactionId = Tools::getValue('transactionId');
        $customerId = Tools::getValue('uniqueUserId');
        $transactionStatus = Tools::getValue('status');
        
        $orderId = !empty($transactionId) ? rtrim($transactionId, '_' . $customerId) : null;
        $order = new Order($orderId);
        
        $currentOrderState = $order->getCurrentOrderState();
        
        if (($transactionStatus != self::DECLINE_STATUS) && ($currentOrderState->id != Configuration::get('PS_OS_ERROR'))) {
            $this->context->smarty->assign(array(
                'status' => 'ok',
            ));
        } else {
            
            $declineMessage = Tools::getValue('message');
            $declineCode = Tools::getValue('code');
            
            $logMessage = 'Payment declined. ' . $declineMessage . ' (' . $declineCode . ')';
            
            $this->context->smarty->assign(array(
                'error' => 'Something went wrong during payment processing',
            ));
            
            PrestaShopLogger::addLog($logMessage, 3, null, 'Order', $order->id, true);
        }
        
        return;
    }
}
