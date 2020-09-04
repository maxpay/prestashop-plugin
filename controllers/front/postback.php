<?php
/**
 * @package       MAXPAY Payment Module for Prestashop
 * @copyright     (c) 2020 MAXPAY. All rights reserved.
 * @license       BSD 2 License
 */

use Maxpay\Scriney;

class MaxpayPostbackModuleFrontController extends ModuleFrontController
{
    const STATUS_SUCCESS = 'success';
    
    private $orderId;
    private $transactionId;
    private $userId;
    private $amount;
    private $responseStatus;
    private $responseMessage;
    private $responseCode;
    
    public function __construct()
    {
        parent::__construct();
    }

    public function initContent()
    {
        //skip parent::initContent()
    }

    public function display()
    {
        //Display empty page
    }

    public function postProcess()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            exit('Incorrect request type');
        }
        
        $headers = $this->getHeaders();
        $dataJson = file_get_contents('php://input');
        
        $scriney = new Scriney($this->module->publicKey, $this->module->secretKey);
        
        try {

            if ($scriney->validateCallback($dataJson, $headers)) {
                
                $data = json_decode($dataJson, true);
                
                $this->retrieveCallbackParams($data);
                
                $order = new Order($this->orderId);
                
                if (!$order || !Validate::isLoadedObject($order)) {
                    PrestaShopLogger::addLog('Order validation failed on postback!', 3, null, 'Order', $this->orderId, true);
                    $this->showBadRequestError();
                }
                
                $currentOrderState = $this->getCurrentOrderStatusName($order);
                
                $message = $this->getTransactionMessage() ." | Old state: {$currentOrderState}; ";
                
                PrestaShopLogger::addLog($message, 1, null, 'Order', $this->orderId, true);
                
                if ($this->responseStatus == self::STATUS_SUCCESS && $this->responseCode === 0) {
                    $order->addOrderPayment(floatval($this->amount), 'maxpay', $this->transactionId);
                    $order->setInvoice(true);
                    $order->setCurrentState(Configuration::get('PS_OS_PAYMENT'));
                } else {
                    $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
                }
                
            } else {
                $this->showBadRequestError('Failed to validate request');
            }
        } catch (Exception $e) {
            $this->showBadRequestError();
        }
    }
    
    /**
     * Read callback params from response
     * @param array $data
     * @return void
     */
    private function retrieveCallbackParams(array $data): void
    {
        $this->orderId          = rtrim($data['uniqueTransactionId'], '_' . $data['uniqueUserId']);
        $this->transactionId    = $data['reference'];
        $this->userId           = $data['uniqueUserId'];
        $this->amount           = $data['totalAmount'];
        $this->responseStatus   = $data['status'];
        $this->responseMessage  = $data['message'];
        $this->responseCode     = $data['code'];
    }

    /**
     * Bad request message output.
     * @param string $message
     * @return void
     */
    private function showBadRequestError($message = null): void
    {
        header('HTTP/1.1 400 Bad Request', true, 400);
        header('Status: 400 Bad Request');
        exit($message);
    }
    
    /**
     * Create a message string on order payment transaction.
     * @return string
     */
    private function getTransactionMessage(): string
    {
        return sprintf(
                "Paymentmethod: MaxPay | OrderID: %s | Status: %s | Code: %d | TransactionID: %s | Amount: %.2f", 
                $this->orderId, $this->responseStatus, $this->responseCode, $this->transactionId, $this->amount
        );
    }
    
    /**
     * Retrieve order status name for a first lang found in array.
     * @param Order $order
     * @return string
     */
    private function getCurrentOrderStatusName(Order $order): string
    {
        $status = $order->getCurrentOrderState();
        
        return $status->name[1] ?? 'undefined';
    }
    
    /**
     * Retrieve headers from request.
     * @return array
     */
    private function getHeaders()
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}
