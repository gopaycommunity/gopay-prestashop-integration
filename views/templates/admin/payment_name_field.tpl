{**
 * Payment name in orders - one text input per language,
 * switched with a native <select> (no Bootstrap dropdown needed).
 *}
<div class="row gopay-multilang" id="gopay-payment-name">
    <div class="{if $gopay_languages|count > 1}col-lg-9{else}col-lg-12{/if}">
        {foreach $gopay_languages as $gopay_language}
            <input type="text"
                   class="form-control gopay-lang-input"
                   data-lang="{$gopay_language.id_lang|intval}"
                   id="{$gopay_field_name|escape:'html':'UTF-8'}_{$gopay_language.id_lang|intval}"
                   name="{$gopay_field_name|escape:'html':'UTF-8'}_{$gopay_language.id_lang|intval}"
                   value="{if isset($gopay_values[$gopay_language.id_lang])}{$gopay_values[$gopay_language.id_lang]|escape:'html':'UTF-8'}{/if}"
                   placeholder="{$gopay_placeholder|escape:'html':'UTF-8'}"
                   maxlength="255"
                   {if $gopay_language.id_lang != $gopay_default_lang}style="display:none"{/if} />
        {/foreach}
    </div>
    {if $gopay_languages|count > 1}
        <div class="col-lg-3">
            <select class="form-control gopay-lang-select" aria-label="{l s='Language' mod='prestashopgopay'}">
                {foreach $gopay_languages as $gopay_language}
                    <option value="{$gopay_language.id_lang|intval}"{if $gopay_language.id_lang == $gopay_default_lang} selected="selected"{/if}>
                        {$gopay_language.name|escape:'html':'UTF-8'} ({$gopay_language.iso_code|escape:'html':'UTF-8'})
                    </option>
                {/foreach}
            </select>
        </div>
        <script type="text/javascript">
            (function () {
                var wrapper = document.getElementById('gopay-payment-name');
                if (!wrapper) {
                    return;
                }
                var select = wrapper.querySelector('.gopay-lang-select');
                var inputs = wrapper.querySelectorAll('.gopay-lang-input');
                select.addEventListener('change', function () {
                    for (var i = 0; i < inputs.length; i++) {
                        inputs[i].style.display = inputs[i].getAttribute('data-lang') === select.value ? '' : 'none';
                    }
                });
            })();
        </script>
    {/if}
</div>
