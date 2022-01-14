<div id='cashier'></div>

<script type="text/javascript" defer>
      document.querySelector("#conditions_to_approve\\[terms-and-conditions\\]").checked = true;
      showCashier(document.querySelector("#conditions_to_approve\\[terms-and-conditions\\]").checked);
      
      document.querySelector("#conditions_to_approve\\[terms-and-conditions\\]").onclick = function(){
            showCashier(document.querySelector("#conditions_to_approve\\[terms-and-conditions\\]").checked);
      }
     
      function showCashier(show) {
     
      if (show) {
            var CashierInstance = new _PaymentIQCashier('#cashier', {
            merchantId: "{$mid|escape:'htmlall':'UTF-8'}",
            userId: "{$user_id|escape:'htmlall':'UTF-8'}",
            sessionId: "{$session_id|escape:'htmlall':'UTF-8'}",
            environment: "test",
            mode: 'ecommerce',
            method: 'deposit',
            initLoadErrorTimeout: 60000,
            fetchConfig: "{$fetchconfig|escape:'htmlall':'UTF-8'}",
            locale: "{$language|escape:'htmlall':'UTF-8'}",
            singlePageFlow: "{$singlepage|var_export:true}",
            attributes: {
                'sessionId': {$session_id}
            }
        }, (api) => {
            api.on({
                cashierInitLoad: () => console.log(this.merchantId),
                update: data => console.log('The passed in data was set', data),
                success: data => console.log('Transaction was completed successfully', data),
                failure: data => console.log('Transaction failed', data),
                validationFailed: data => setInterval('window.location.reload()', 30000), 
                isLoading: data => console.log('Data is loading', data),
                doneLoading: data => console.log('Data has been successfully downloaded', data),
                newProviderWindow: data => console.log('A new window / iframe has opened', data),
                paymentMethodSelect: data => console.log('Payment method was selected', data),
                paymentMethodPageEntered: data => console.log('New payment method page was opened', data),
                navigate: data => console.log('Path navigation triggered', data)
            })
            api.set({
                config: {
                    amount: {$total}
                }
            })
            api.css(`
          .your-custom-css {
            color: blue;
          }
        `)
        }
    )
      } else {
         window._PaymentIQCashierReset();
      }
    }
    
</script>
