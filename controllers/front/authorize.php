<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


class PiqCashierAuthorizeModuleFrontController extends ModuleFrontController {
        
    public function init() {
        parent::init();
        header('Content-Type: application/json');
    
    }
        
    public function postProcess() {

        /*
             * If the module is not active anymore, no need to process anything.
             */
        if ($this->module->active == false) {
            die;
        }

        $request = Request::createFromGlobals();
        
        if (!$this->module->isValidIp($request->getClientIp())) {
            $response = new Response();
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->send();
        }
        
        $content = $request->getContent();
        
        $parameters = json_decode($content, true);
        

        $attributes = $parameters['attributes'];
        $sid = $attributes['sessionId'];
        $uid = $parameters['userId'];
        $amount = $parameters['txAmount'];
        

        
        if ($this->isValidOrder((int)$sid, (int)$uid) === false) {
                $log = new Logger('piqcashier');
                $log->pushHandler(new StreamHandler('var/logs/dev.log', Logger::WARNING));
        
                $log->warning('sent fail to piq uid:'. $uid.' session id:'.$sid);
                
                $response = new Response();
                $response->setStatusCode(Response::HTTP_BAD_REQUEST);
                $response->send();
        }
        
        
        $cart = new Cart((int) $sid);
        $customer = new Customer((int) $uid);
        $currency = new Currency((int) $cart->id_currency);
        $language = new Language((int) $customer->id_lang);
        $secure_key = $customer->secure_key;
        $module_name = $this->module->displayName;
        $currency_id = (int) $currency->id;
        $message = 'Transaction initiated with authorize';
        
        try {
        $order = $this->module->validateOrder((int)$sid, Configuration::get('PS_OS_ERROR'), (float)$amount, $module_name, $message, array('txId' => $parameters['txId'], 'accountId' => $parameters['accountId']), $currency_id, false, $secure_key);
        if ((bool)$order === true) {
                $order = Order::getByCartId((int)$sid);
                $reference = $order->getUniqReference();
                
               
                $response = new JsonResponse();
                $response->setData(['userId' => (string)$uid, 'success' => true, 'authCode' => (string)$secure_key, 'merchantTxId' => (string)$reference]);
                $response->send();
        }
        
        } catch(PrestaShopException $e) {
                $response = new JsonResponse();
                $response->setData(['userId' => (string)$uid, 'success' => false, 'authCode' => (string)$secure_key, 'merchantTxId' => (string)$reference, 'errCode' => '01', 'errMsg' => 'Authorize failed']);
                $response->send();
        }
    }

    protected function isValidOrder($cart, $customer)
    {
        $cart = new Cart((int)$cart);

        return !$cart->orderExists() && !is_null(Customer::customerIdExistsStatic($customer));
    }

}
