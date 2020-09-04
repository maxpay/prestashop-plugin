<?php

/**
 * @package       MAXPAY Payment Module for Prestashop
 * @copyright     (c) 2020 MAXPAY. All rights reserved.
 * @license       BSD 2 License
 */

class MaxpayGetContentController
{
    public function __construct($module, $file, $path)
    {
        $this->file = $file;
        $this->module = $module;
        $this->context = Context::getContext();
        $this->_path = $path;
    }

    /**
     * Save configuration values
     */
    public function processConfiguration()
    {
        if (Tools::isSubmit('general_settings_form')) {
            Configuration::updateValue('MAXPAY_PUBLICKEY', Tools::getValue('MAXPAY_PUBLICKEY'));
            Configuration::updateValue('MAXPAY_SECRETKEY', Tools::getValue('MAXPAY_SECRETKEY'));
            Configuration::updateValue('MAXPAY_PUBLICKEY_TEST', Tools::getValue('MAXPAY_PUBLICKEY_TEST'));
            Configuration::updateValue('MAXPAY_SECRETKEY_TEST', Tools::getValue('MAXPAY_SECRETKEY_TEST'));
            Configuration::updateValue('MAXPAY_TESTMODE', Tools::getValue('MAXPAY_TESTMODE'));
            $this->context->smarty->assign('confirmation', 'ok');
        }
    }

    /**
     * Render Module Configuration and Postback URLs forms
     */
    public function renderForms()
    {
        $general_settings_inputs = array(
            array('name' => 'MAXPAY_PUBLICKEY', 'label' => $this->module->l('Public Key'), 'type' => 'text', 'required' => true),
            array('name' => 'MAXPAY_SECRETKEY', 'label' => $this->module->l('Secret Key'), 'type' => 'text', 'required' => true),
            array('name' => 'MAXPAY_PUBLICKEY_TEST', 'label' => $this->module->l('Test Public Key'), 'type' => 'text', 'required' => false),
            array('name' => 'MAXPAY_SECRETKEY_TEST', 'label' => $this->module->l('Test Secret Key'), 'type' => 'text', 'required' => false),
            array('name' => 'MAXPAY_TESTMODE', 'type' => 'switch', 'label' => $this->module->l('Enable Test mode'),
                'values' => array(
                    array(
                        'id' => 'enable_test_mode_1',
                        'value' => 1,
                        'label' => $this->module->l('Enabled')
                    ),
                    array(
                        'id' => 'enable_test_mode_0',
                        'value' => 0,
                        'label' => $this->module->l('Disabled')
                    ),
                ),
            ),

        );

        $general_settings_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->module->l('MAXPAY Module configuration'),
                    'icon' => 'icon-wrench'
                ),
                'input' => $general_settings_inputs,
                'submit' => array('title' => $this->module->l('Save'))
            )
        );

        $helper = new HelperForm();
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int)Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->submit_action = 'general_settings_form';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->module->name . '&tab_module=' . $this->module->tab . '&module_name=' . $this->module->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => array(
                'MAXPAY_PUBLICKEY' => Tools::getValue('MAXPAY_PUBLICKEY', Configuration::get('MAXPAY_PUBLICKEY')),
                'MAXPAY_SECRETKEY' => Tools::getValue('MAXPAY_SECRETKEY', Configuration::get('MAXPAY_SECRETKEY')),
                'MAXPAY_PUBLICKEY_TEST' => Tools::getValue('MAXPAY_PUBLICKEY_TEST', Configuration::get('MAXPAY_PUBLICKEY_TEST')),
                'MAXPAY_SECRETKEY_TEST' => Tools::getValue('MAXPAY_SECRETKEY_TEST', Configuration::get('MAXPAY_SECRETKEY_TEST')),
                'MAXPAY_TESTMODE' => Tools::getValue('MAXPAY_TESTMODE', Configuration::get('MAXPAY_TESTMODE')),

            ),
            'languages' => $this->context->controller->getLanguages()
        );

        return $helper->generateForm(array($general_settings_form));
    }

    public function run()
    {
        $this->processConfiguration();
        $html_confirmation_message = $this->module->display($this->file, 'getContent.tpl');
        $html_form = $this->renderForms();

        return $html_confirmation_message . $html_form;
    }
}
