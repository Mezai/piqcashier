<?php
/**
* 2007-2021 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2021 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

use Symfony\Component\HttpFoundation\Response;

class PiqCashier extends PaymentModule
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'piqcashier';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Johan Tedenmark';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;
        $config = Configuration::getMultiple(array('PIQ-CASHIER_MID', 'PIQ-CASHIER_LIVE_MODE', 'PIQ-CASHIER_SINGLE_PAGE'));
        if (!empty($config['PIQ-CASHIER_MID'])) {
            $this->mid = $config['PIQ-CASHIER_MID'];
        }

        if (!empty($config['PIQ-CASHIER_LIVE_MODE'])) {
            $this->mode = $config['PIQ-CASHIER_LIVE_MODE'];
        }

        if (!empty($config['PIQ-CASHIER_SINGLE_PAGE'])) {
            $this->singlepage = $config['PIQ-CASHIER_SINGLE_PAGE'];
        }

        parent::__construct();

        $this->displayName = $this->l('PIQ cashier module');
        $this->description = $this->l('A integration with PIQ cashier');

        $this->confirmUninstall = $this->l('');

        $this->limited_countries = array('FR', 'SE');

        $this->limited_currencies = array('EUR', 'SEK');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false)
        {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        $iso_code = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'));

        if (in_array($iso_code, $this->limited_countries) == false)
        {
            $this->_errors[] = $this->l('This module is not available in your country');
            return false;
        }

        Configuration::updateValue('PIQ-CASHIER_LIVE_MODE', false);
        Configuration::updateValue('PIQ-CASHIER_SINGLE_PAGE', false);
        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('payment') &&
            $this->registerHook('paymentReturn') &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('actionPaymentConfirmation') &&
            $this->registerHook('displayOrderConfirmation') &&
            $this->registerHook('displayPaymentByBinaries') &&
            $this->registerHook('actionFrontControllerSetMedia') &&
            $this->registerHook('displayPayment');
    }

    public function uninstall()
    {
        Configuration::deleteByName('PIQ-CASHIER_MID');
        Configuration::deleteByName('PIQ-CASHIER_LIVE_MODE');
        Configuration::deleteByName('PIQ-CASHIER_SINGLE_PAGE');

        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitPiq-cashierModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }
    
    public function countryIso($country) {
        if ($country == 'Sverige') {
            return 'SWE';
        }
            
        return '';
    }

    public function getTemplateVars()
    {

        $cart = $this->context->cart;
        $customer = $this->context->customer;

        $mid = Configuration::get('PIQ-CASHIER_MID');


        return [
            'mid' => Configuration::get('PIQ-CASHIER_MID'),
            'session_id' => $cart->id,
            'user_id' => $customer->id,
            'total' => $cart->getOrderTotal(true, Cart::BOTH),
            'environment' => (bool)Configuration::get('PIQ-CASHIER_LIVE_MODE') ? 'production' : 'test',
            'language' => str_replace('-', '_', $this->context->language->locale),
            'singlepage' => (bool)Configuration::get('PIQ-CASHIER_SINGLE_PAGE'),
            'fetchconfig' => (bool)Configuration::get('PIQ-CASHIER_FETCH_CONFIG'),
        ];
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitPiq-cashierModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'PIQ-CASHIER_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in live mode'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Single page'),
                        'name' => 'PIQ-CASHIER_SINGLE_PAGE',
                        'is_bool' => true,
                        'desc' => $this->l('Show the cashier in single page mode'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable fetch config'),
                        'name' => 'PIQ-CASHIER_FETCH_CONFIG',
                        'is_bool' => true,
                        'desc' => $this->l('Enables the ability to use cashier config from PIQ admin'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-envelope"></i>',
                        'desc' => $this->l('Enter your merchant id'),
                        'name' => 'PIQ-CASHIER_MID',
                        'label' => $this->l('Merchant ID'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'PIQ-CASHIER_LIVE_MODE' => Configuration::get('PIQ-CASHIER_LIVE_MODE'),
            'PIQ-CASHIER_MID' => Configuration::get('PIQ-CASHIER_MID'),
            'PIQ-CASHIER_SINGLE_PAGE' => Configuration::get('PIQ-CASHIER_SINGLE_PAGE'),
            'PIQ-CASHIER_FETCH_CONFIG' => Configuration::get('PIQ-CASHIER_FETCH_CONFIG'),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'views/js/front.js');
        $this->context->controller->addCSS($this->_path.'views/css/front.css');
    }

    public function hookActionFrontControllerSetMedia($params)
    {
        $this->context->controller->registerJavascript(
            'piq-cashier-script',
            'https://static.paymentiq.io/cashier/cashier.js',
            [
                'position' => 'head',
                'inline' => false,
                'priority' => 10,
                'server' => 'remote'
            ]
        );
    }

    /**
     * This method is used to render the payment button,
     * Take care if the button should be displayed or not.
     */
    public function hookPayment($params)
    {
        $currency_id = $params['cart']->id_currency;
        $currency = new Currency((int)$currency_id);

        if (in_array($currency->iso_code, $this->limited_currencies) == false)
            return false;

        $this->smarty->assign('module_dir', $this->_path);

        $this->smarty->assign(
            $this->getTemplateVars()
        );

        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }

    /**
     * This hook is used to display the order confirmation page.
     */
    public function hookPaymentReturn($params)
    {
        if ($this->active == false)
            return;

        $order = $params['objOrder'];

        if ($order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR'))
            $this->smarty->assign('status', 'ok');

        $this->smarty->assign(array(
            'id_order' => $order->id,
            'reference' => $order->reference,
            'params' => $params,
            'total' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
        ));

        return $this->display(__FILE__, 'views/templates/hook/confirmation.tpl');
    }

    /**
     * Return payment options available for PS 1.7+
     *
     * @param array Hook parameters
     *
     * @return array|null
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        
        
        $this->context->smarty->assign(
            $this->getTemplateVars()
        );
        

        $iframeOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $iframeOption->setCallToActionText($this->l('Pay iframe'))
            ->setAction($this->context->link->getModuleLink($this->name, 'iframe', array(), true))
            ->setAdditionalInformation($this->context->smarty->fetch('module:paymentexample/views/templates/front/payment_infos.tpl'))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.jpg'));

        return [
            $iframeOption
        ];
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

    public function hookDisplayPaymentByBinaries() {



       return $this->context->smarty->fetch('module:piqcashier/views/templates/front/iframe.tpl');
    }

    public function hookActionPaymentConfirmation()
    {
        /* Place your code here. */
    }

    public function hookDisplayOrderConfirmation()
    {
        /* Place your code here. */
    }

    public function hookDisplayPayment()
    {
        /* Place your code here. */
    }
    
    public function isValidIp($ip) {
        return in_array($ip, array('54.229.9.44', '54.194.243.247', '52.51.194.179', '52.209.182.232', '34.241.202.249', '52.19.173.50'));
    }


}
