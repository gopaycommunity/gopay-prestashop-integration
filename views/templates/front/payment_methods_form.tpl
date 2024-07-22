{*
*  Payment methods list form
*
*  @author   GoPay
*  @license  https://www.gnu.org/licenses/gpl-2.0.html  GPLv2 or later
*}

<div style="border-radius: 10px; padding-left: 25px; padding-bottom: 40px; margin: 15px; background-color: #f5f5f5;">
    <form id="payment-form" method="post" action="{$action|escape:'htmlall':'UTF-8'}">
        <p style="padding-top: 10px;">{l s=$description mod='prestashopgopay'}</p>
        {assign var="i" value=true}
        {foreach from=$payment_methods key=payment_method_code item=payment_method_name_image}
            <div style="border-bottom: 3px solid white; padding: 12px; position: center;
            overflow: hidden; margin-right: 15px; display: flex; flex-wrap: wrap;" name="{$payment_method_code|escape:'htmlall':'UTF-8'}">
                <input name="gopay_payment_method" type="radio"
                       id="{$payment_method_code|escape:'htmlall':'UTF-8'}" value="{$payment_method_code|escape:'htmlall':'UTF-8'}"
                       style="margin-right: 10px;"
                        {if !empty($i) }
                            checked="checked"
                            {assign var="i" value=false}
                        {/if}/>
                <label for="{$payment_method_code|escape:'htmlall':'UTF-8'}">{l s=$payment_method_name_image['name']|escape:'htmlall':'UTF-8' mod='prestashopgopay'}</label>
                <img src="{$payment_method_name_image['image']|escape:'htmlall':'UTF-8'}" alt="ico" style="height: auto; max-height: 30px; width: auto; margin-left: auto;"/>
            </div>
        {/foreach}
        <script>
            var applePay = document.getElementsByName('APPLE_PAY');
            if (applePay.length !== 0){
              if(location.protocol !== 'https:' || typeof window.ApplePaySession === 'undefined' || !window.ApplePaySession.canMakePayments()) {
                applePay[0].remove();
              }
            }
        </script>
    </form>
</div>