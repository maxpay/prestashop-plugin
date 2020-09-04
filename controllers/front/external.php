<?php
/**
 * @package       MAXPAY Payment Module for Prestashop
 * @copyright     (c) 2020 MAXPAY. All rights reserved.
 * @license       BSD 2 License
 */

use Maxpay\Lib\Util\SignatureHelper;

class maxpayexternalModuleFrontController extends ModuleFrontController
{
    const PROCESSING_URL = 'https://hpp.maxpay.com/hpp';
    
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $cart = $this->context->cart;
        $currency = $this->context->currency;
        $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
        
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || 
            $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == $this->module->name) {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        // Check if customer exists
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Order validation
        $this->module->validateOrder(
                    (int) $cart->id, 
                    Configuration::get('PS_OS_MAXPAY_OPEN'), 
                    $total, $this->module->displayName, 
                    null, 
                    null, 
                    (int) $currency->id, 
                    true, 
                    $customer->secure_key
                );
        
        $order = new Order($this->module->currentOrder);
        
        $deliveryDetails = new Address((int)($order->id_address_delivery));        

        $params = [
                'key' => $this->module->publicKey,
                'uniqueuserid' => $customer->id,
                'email' => $customer->email,
                'firstname' => $customer->firstname,
                'lastname' => $customer->lastname,
                'locale' => $this->context->language->locale,
                'city' => $deliveryDetails->city,
                'zip' => $deliveryDetails->postcode,
                'address' => $deliveryDetails->address1,
                'country' => $deliveryDetails->country,
            ];
        
        if ($order) {
            $params['uniqueTransactionId'] = $order->id . '_' . $customer->id;
            $params['customProduct'] = '[' . json_encode([
                'productType' => 'fixedProduct',
                'productId'   => $order->id,
                'productName' => 'Order id #' . $order->id,
                'currency'    => $currency->iso_code,
                'amount'      => $total,
            ]) . ']';
            
            $params['signature'] = (new SignatureHelper())->generateForArray($params, $this->module->secretKey, true);
            $params['customProduct'] = htmlspecialchars($params['customProduct']);
        }
        
        try {
            $this->postRedirect(self::PROCESSING_URL, $params);
        } catch (Exception $e) {
            if (isset($order)) {
                $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
            }

            $this->errors[] = $this->l($e->getMessage());
            $this->redirectWithNotifications($this->context->link->getPageLink('order', true, null, array('step' => '3')));
        }

    }
    
    private function postRedirect(string $url, array $data, array $headers = [])
    {
    ?>
        <html xmlns="http://www.w3.org/1999/xhtml">
        <head>
            <script type="text/javascript">
                function closethisasap() {
                    document.forms["redirectpost"].submit();
                }
            </script>
        </head>
        <body onload="closethisasap();">
        <form name="redirectpost" method="post" action="<?= $url; ?>">
            <?php
            if ( !is_null($data) ) {
                foreach ($data as $k => $v) {
                    echo '<input type="hidden" name="' . $k . '" value="' . $v . '"> ';
                }
            }
            ?>
        </form>
        </body>
        </html>
        <?php
        exit;
    }
}
