{*
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
*}

<div id='cashier'></div>

<script type="text/javascript">
	var CashierInstance = new _PaymentIQCashier('#cashier', {
				merchantId: "{$mid|escape:'htmlall':'UTF-8'}",
				userId: "{$user_id|escape:'htmlall':'UTF-8'}",
				sessionId: "{$session_id|escape:'htmlall':'UTF-8'}",
				environment: "{$environment|escape:'htmlall':'UTF-8'}"
			}, (api) => {
				api.on({
					cashierInitLoad: () => console.log('Cashier init load'),
					update: data => console.log('The passed in data was set', data),
					success: data => console.log('Transaction was completed successfully', data),
					failure: data => console.log('Transaction failed', data),
					isLoading: data => console.log('Data is loading', data),
					doneLoading: data => console.log('Data has been successfully downloaded', data),
					newProviderWindow: data => console.log('A new window / iframe has opened', data),
					paymentMethodSelect: data => console.log('Payment method was selected', data),
					paymentMethodPageEntered: data => console.log('New payment method page was opened', data),
					navigate: data => console.log('Path navigation triggered', data)
				})
				api.set({
					config: {
						amount: 10
					}
				})
				api.css(`
          .your-custom-css {
            color: blue;
          }
        `)
			}
	)
</script>
