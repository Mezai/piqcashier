<?php
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


class PiqCashierVerifyUserModuleFrontController extends ModuleFrontController
{
    
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

        
        //$log = new Logger('piqcashier');
        //$log->pushHandler(new StreamHandler('var/logs/dev.log', Logger::WARNING));
        
        //$log->warning(print_r($request, true));
        
        //$log->warning(print_r($parameters, true));
        
        $sid = $parameters['sessionId'];
        $uid = $parameters['userId'];
        //$log->warning(print_r($uid, true));
      
        
        $cart = new Cart((int)$sid);
        $currency = new Currency((int)$cart->id_currency);
        $customer = new Customer((int)$uid);
        /** Array of addresses **/
        $addresses = $customer->getAddresses((int)$cart->id_lang);



        if ($uid && null == CustomerCore::customerIdExistsStatic($uid)) {
            $response = new JsonResponse();
            $response->setData(['userId' => (string)$uid, 'success' => false, 'errCode' => '2', 'errMsg' => 'Unknown userId']);
            $response->send();

        }
        
        if (null !== CustomerCore::customerIdExistsStatic((int)$uid) && $cart->orderExists()) {
            $response = new JsonResponse();
            $response->setData(['userId' => (string)$uid, 'success' => true, 'balance' => '0', 'balanceCy' => (string)$currency->iso_code]);
            $response->send();
        } 
        
        $data = array();
        
        $data['userId'] = (string)$uid;
        $data['sessionId'] = (string)$sid;
        $data['balance'] = "1000";
        $data['email'] = (string)$customer->email;
        $data['balanceCy'] = (string)$currency->iso_code;
        
        
        
        if(!Tools::isEmpty($customer->birthday)) {
            //$data['dob'] = $customer->birthday;
        }
        
        foreach ($addresses as $address)  {
            
            if (!Tools::isEmpty($address['firstname'])) {
                    $data['firstName'] = $address['firstname'];
            }
            if (!Tools::isEmpty($address['lastname'])) {
                    $data['lastName'] = $address['lastname'];
            }
            if (!Tools::isEmpty($address['address1'])) {
                    $data['street'] = $address['address1'];
            }
            if (!Tools::isEmpty($address['city'])) {
                    $data['city'] = $address['city'];
            }
            if (!Tools::isEmpty($address['state'])) {
                    $data['state'] = $address['state'];
            }
            if (!Tools::isEmpty($address['postcode'])) {
                    $data['zip'] = $address['postcode'];
            }
            if (!Tools::isEmpty($address['country'])) {
                    $country = $this->module->countryIso($address['country']);
                    $data['country'] = $country;
            }
            if (!Tools::isEmpty($address['phone'])) {
                    $data['mobile'] = $address['phone'];
            }
            
        }
        
        $data['sex'] = ((int)$customer->id_gender === 1) ? "MALE" : "FEMALE";
        //$data['locale'] = 'sv_SE';
        $data['success'] = (bool)true;
        
        //$log->warning(print_r($data, true));
        
        $response = new JsonResponse();
        $response->setData($data);
        $response->send();
        
        //$log->warning(print_r($response, true));

    }

}
