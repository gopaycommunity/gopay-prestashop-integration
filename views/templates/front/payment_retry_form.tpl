<div class="box" id="retryPayment">
  {include file="module:prestashopgopay/views/templates/front/payment_methods_form.tpl"}
  <input form="payment-form" type="hidden" id="order_id" name="order_id" value="{$order_id}">
  <button form="payment-form" type="submit" class="btn btn-primary center-block">
    retry payment
  </button>
</div>