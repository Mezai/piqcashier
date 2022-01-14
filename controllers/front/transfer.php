<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


class PiqCashierTransferModuleFrontController extends ModuleFrontController {
   
   public function init() {
        parent::init();
        header('Content-Type: application/json');
    
    }
   
   
    public function postProcess()
    {
        
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
        $uid = $parameters['userId'];
        $txId = $parameters['txId'];
        $attributes = $parameters['attributes'];
        $sid = $attributes['sessionId'];
        
        $order = Order::getByCartId((int)$sid);
        $reference = $order->getUniqReference();
        
        $order->current_state = (int)Configuration::get('PS_OS_PAYMENT');
        $order->save();
        
        $response = new JsonResponse();
        $response->setData(['userId' => (string)$uid, 'success' => true, 'txId' => (string)$txId, 'merchantTxId' => (string)$reference]);
        $response->send();

    }
}
