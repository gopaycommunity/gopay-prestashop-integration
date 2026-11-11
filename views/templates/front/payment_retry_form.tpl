{*
*  Order confirmation
*
*  @author   GoPay
*  @license  https://www.gnu.org/licenses/gpl-2.0.html  GPLv2 or later
*}

<div class="box" id="retryPayment">
  {if $is_retry }
    <form id="payment-form" method="post" action="{$action|escape:'htmlall':'UTF-8'}">
      <input form="payment-form" type="hidden" id="order_id" name="order_id" value="{$order_id|escape:'htmlall':'UTF-8'}">
      <input form="payment-form" type="hidden" name="key" value="{$order_key|escape:'htmlall':'UTF-8'}">
      <button form="payment-form" type="submit" class="btn btn-primary center-block">
          {l s='retry payment' mod='prestashopgopay'}
      </button>
    </form>
  {else}
    {include file=$payment_methods_form}
    <input form="payment-form" type="hidden" id="order_id" name="order_id" value="{$order_id|escape:'htmlall':'UTF-8'}">
    <input form="payment-form" type="hidden" name="key" value="{$order_key|escape:'htmlall':'UTF-8'}">
    <button form="payment-form" type="submit" class="btn btn-primary center-block">
        {l s='retry payment' mod='prestashopgopay'}
    </button>
  {/if}
</div>
