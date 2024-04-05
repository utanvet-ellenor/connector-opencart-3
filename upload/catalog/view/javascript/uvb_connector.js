(function (){
    const inputEmailID = 'input-email';
    const checkoutConfirmButtonID = 'quick-checkout-button-confirm';
    const confirmAlertMessage = 'Érvénytelen fizetési mód!';
    const errorTextIDPrefix = 'payment-error-'
    let inputEmail = undefined;
    let buttonQuickCheckout = undefined;
    let errors = 0;
    let disabledMethods = []

    /**
     * UVB Connector for OpenCart 3.x Journal 3 Theme - Quick Checkout
     * @type {{init: uvb_connector.init, addInputEmailEventListener(): void, addQuickCheckoutButtonConfirmEventListener(): void}}
     */
    uvb_connector = {
        init:function (){
            console.log('uvb_init')
            // Add Input Email Event Listener
            this.addInputEmailEventListener();
            // Add Checkout Button Event Listener
            this.addQuickCheckoutButtonConfirmEventListener();
        },
        addInputEmailEventListener(){
            inputEmail = document.getElementById(inputEmailID);
            if (inputEmail.value !== ''){
                checkEmail();
            }
            inputEmail.addEventListener('blur',function (event){
                checkEmail();
            }, true)
        },
        addQuickCheckoutButtonConfirmEventListener(){
            buttonQuickCheckout = document.getElementById(checkoutConfirmButtonID);
            buttonQuickCheckout.addEventListener('click',function (event){
                const payment_code = parent.window['_QuickCheckout']['order_data']['payment_code']
                if(disabledMethods.includes(payment_code)){
                    alert(confirmAlertMessage)
                    event.stopPropagation();
                }
            }, true)
        }
    }
    function checkEmail(){
        $.ajax({
            url: 'index.php?route=extension/module/uvb_connector/checkCustomerEmailAjax',
            type: 'post',
            data: 'uvb_email=' + inputEmail.value,
            dataType: 'json',
            beforeSend: function() {
                disabledMethods = [];
                // Set Enabled
                const paymentMethods = document.querySelectorAll('input[name=\"payment_method\"]')
                paymentMethods.forEach(function(paymentMethod) {
                    setEnabled(paymentMethod);
                });
                // Remove Errors
                const paymentErrors = document.querySelectorAll('[id^="' + errorTextIDPrefix + '"]');
                paymentErrors.forEach(function(textError) {
                    textError.remove();
                });
            },
            complete: function() {},
            success: function(json) {
                if(json['disabled_payment_methods']){
                    const disabledPaymentMethods = json['disabled_payment_methods'];
                    disabledPaymentMethods.forEach(function(paymentMethod) {
                        disabledMethods.push(paymentMethod['code']);
                        const disabledMethod = document.querySelector('input[value="' + paymentMethod['code'] + '"]');
                        setDisabled(disabledMethod);
                        updateActivePaymentMethod();
                        addErrorMessage(disabledMethod,paymentMethod['text_error'])
                    });
                }
            },
            error: function(xhr, ajaxOptions, thrownError) {
                alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
            }
        });
    }

    function updateActivePaymentMethod(){
        const availablePaymentMethods = document.querySelectorAll('input[name=\"payment_method\"]');
        if(availablePaymentMethods.length){
            availablePaymentMethods.forEach(function (paymentMethod){
                if(!disabledMethods.includes(paymentMethod.value)){
                    parent.window['_QuickCheckout']['order_data']['payment_code'] = paymentMethod.value
                    paymentMethod.checked = true;
                }
            })
        }
    }

    function setDisabled(target){
        target.checked = false;
        target.disabled = true;
        target.closest('.radio').style.opacity = 0.5
    }
    function setEnabled(target){
        target.disabled = false;
        target.closest('.radio').style.opacity = 1
    }

    function addErrorMessage(target,message){
        errors++
        // Add text
        const errorText = document.createElement('div');
        errorText.id = errorTextIDPrefix + errors
        errorText.innerHTML = message;
        errorText.style.fontSize = '10px';
        errorText.style.color = '#ff0000';
        target.closest('.section-body').after(errorText)
    }

    document.addEventListener('DOMContentLoaded',function (){
        uvb_connector.init();
    })
}())