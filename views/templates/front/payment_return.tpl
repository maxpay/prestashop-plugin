{*
 * @package       MAXPAY Payment Module for Prestashop
 * @copyright     (c) 2020 MAXPAY. All rights reserved.
 * @license       BSD 2 License
*}

{extends file='page.tpl'}

{block name='content'}
    {if isset($status) && $status == 'ok'}
        <p>
            <h3>{l s='Your payment is being processed' mod='maxpay'}</h3>
            <br/>
            <span class="bold">{l s='The order has been placed and awaiting payment verification.' mod='maxpay'}</span><br/><br/>
            <span class="bold">{l s='Please, check your order status in your order history in a few minutes.' mod='maxpay'}</span>
        </p>
    {/if}
    
    {if isset($error)}
        <h3>{l s='There was an error' mod='maxpay'}</h3>

        <p class="warning">
            {l s='We have noticed that there is a problem with your order. If you think this is an error, you can contact us' mod='maxpay'}
        </p>
        <p>
            {$error}
        </p>
    {/if}
    <br/>
{/block}
