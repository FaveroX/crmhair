<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if (isset($subscription_error)) { ?>
<div class="alert alert-warning">
    <?= $subscription_error; ?>
</div>
<?php } ?>
<?= form_open('', ['id' => 'subscriptionForm', 'class' => '_transaction_form']); ?>
<div class="row">
    <div class="col-md-12">
        <div
            class="tw-bg-neutral-50 tw-overflow-hidden tw-rounded-t-md tw-p-6 tw-border-b -tw-mt-6 -tw-mx-6 tw-border-solid tw-border-neutral-200 tw-mb-4">
            <div class="form-group select-placeholder">
                <label
                    for="stripe_plan_id"><?= _l('billing_plan'); ?></label>
                <select id="stripe_plan_id" name="stripe_plan_id" class="selectpicker" data-live-search="true"
                    data-width="100%"
                    data-none-selected-text="<?= _l('stripe_subscription_select_plan'); ?>">
                    <option value=""></option>
                    <?php if (isset($plans->data)) { ?>
                    <?php foreach ($plans->data as $plan) {
                        if (! $plan->active) {
                            if (! isset($subscription)) {
                                continue;
                            }
                            if ($subscription->stripe_plan_id != $plan->id) {
                                continue;
                            }
                        }

                        $selected = '';
                        if (isset($subscription) && $subscription->stripe_plan_id == $plan->id) {
                            $selected = ' selected';
                        }
                        $subtext = app_format_money(strcasecmp($plan->currency, 'JPY') == 0 ? $plan->amount : $plan->amount / 100, strtoupper($plan->currency));
                        if ($plan->interval_count == 1) {
                            $subtext .= ' / ' . $plan->interval;
                        } else {
                            $subtext .= ' (every ' . $plan->interval_count . ' ' . $plan->interval . 's)';
                        } ?>
                    <option value="<?= e($plan->id); ?>"
                        data-interval-count="<?= e($plan->interval_count); ?>"
                        data-interval="<?= e($plan->interval); ?>"
                        data-amount="<?= e($plan->amount); ?>"
                        data-subtext="<?= e($subtext); ?>" <?= e($selected); ?>>
                        <?php
                                    if (! empty($plan->nickname)) {
                                        echo $plan->nickname;
                                    } elseif (isset($plan->product->name)) {
                                        echo $plan->product->name;
                                    } else {
                                        echo '[Plan Name Not Set in Stripe, ID:' . $plan->id . ']';
                                    } ?>
                    </option>
                    <?php
                    } ?>
                    <?php } ?>
                </select>
            </div>
            <?= render_input('quantity', _l('item_quantity_placeholder'), isset($subscription) ? $subscription->quantity : 1, 'number'); ?>
            <?php
                            $params = ['data-lazy' => 'false', 'data-date-min-date' => date('Y-m-d', strtotime('+1 days', strtotime(date('Y-m-d'))))];
if (isset($subscription) && ! empty($subscription->stripe_subscription_id)) {
    $params['disabled'] = true;
}
echo '<div id="first_billing_date_wrapper">';
if (! isset($params['disabled'])) {
    echo '<i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1" data-toggle="tooltip" data-placement="right" data-title="' . _l('subscription_first_billing_date_info') . '"></i>';
}
echo render_date_input('date', 'first_billing_date', isset($subscription) ? _d($subscription->date) : '', $params);
echo '</div>';
if (isset($subscription) && ! empty($subscription->stripe_subscription_id) && $subscription->status != 'canceled' && $subscription->status != 'future') { ?>
            <div class="checkbox checkbox-info hide" id="prorateWrapper">
                <input type="checkbox" id="prorate" class="ays-ignore" checked name="prorate">
                <label for="prorate"><a href="https://stripe.com/docs/billing/subscriptions/prorations"
                        target="_blank"><i class="fa fa-link"></i></a> Prorate</label>
            </div>
            <?php } ?>
        </div>
        <?php $value = (isset($subscription) ? $subscription->name : ''); ?>
        <?= render_input('name', 'subscription_name', $value, 'text', [], [], '', 'ays-ignore'); ?>
        <?php $value = (isset($subscription) ? $subscription->description : ''); ?>
        <?= render_textarea('description', 'subscriptions_description', $value, [], [], '', 'ays-ignore'); ?>
        <div class="form-group">
            <div class="checkbox checkbox-primary">
                <input type="checkbox" id="description_in_item" class="ays-ignore" name="description_in_item" <?php if (isset($subscription) && $subscription->description_in_item == '1') {
                    echo ' checked';
                } ?>>
                <label for="description_in_item"><i class="fa-regular fa-circle-question" data-toggle="tooltip"
                        data-title="<?= _l('description_in_invoice_item_help'); ?>"></i>
                    <?= _l('description_in_invoice_item'); ?></label>
            </div>
        </div>
        <div class="form-group select-placeholder f_client_id">
            <label for="clientid"
                class="control-label"><?= _l('client'); ?></label>
            <select id="clientid" name="clientid" data-live-search="true" data-width="100%"
                class="ajax-search ays-ignore"
                data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>"
                <?php if (isset($subscription) && ! empty($subscription->stripe_subscription_id)) {
                    echo ' disabled';
                } ?>>
                <?php $selected = (isset($subscription) ? $subscription->clientid : '');
if ($selected == '') {
    $selected = ($customer_id ?? '');
}
if ($selected != '') {
    $rel_data = get_relation_data('customer', $selected);
    $rel_val  = get_relation_values($rel_data, 'customer');
    echo '<option value="' . $rel_val['id'] . '" selected>' . $rel_val['name'] . '</option>';
} ?>
            </select>
        </div>
        <div class="form-group select-placeholder projects-wrapper<?php if ((! isset($subscription)) || (isset($subscription) && ! customer_has_projects($subscription->clientid))) {
            echo ' hide';
        } ?>">
            <label
                for="project_id"><?= _l('project'); ?></label>
            <div id="project_ajax_search_wrapper">
                <select name="project_id" id="project_id" class="projects ajax-search ays-ignore"
                    data-live-search="true" data-width="100%"
                    data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
                    <?php
                 if (isset($subscription) && $subscription->project_id) {
                     echo '<option value="' . $subscription->project_id . '" selected>' . e(get_project_name_by_id($subscription->project_id)) . '</option>';
                 }
?>
                </select>
            </div>
        </div>
        <?php
         $s_attrs = ['disabled' => true, 'data-show-subtext' => true];

foreach ($currencies as $currency) {
    if ($currency['isdefault'] == 1) {
        $s_attrs['data-base'] = $currency['id'];
    }
    if (isset($subscription)) {
        if ($currency['id'] == $subscription->currency) {
            $selected = $currency['id'];
        }
    } else {
        if ($currency['isdefault'] == 1) {
            $selected = $currency['id'];
        }
    }
}
?>
        <?php if (isset($subscription, $stripeSubscription)) { ?>
        <?php
      if (strtolower($subscription->currency_name) != strtolower($stripeSubscription->plan->currency)) {  ?>
        <div class="alert alert-warning">
            <?= _l('subscription_plan_currency_does_not_match'); ?>
        </div>
        <?php } ?>
        <?php } ?>
        <?= render_select('currency', $currencies, ['id', 'name', 'symbol'], 'currency', $selected, $s_attrs, [], '', 'ays-ignore'); ?>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group select-placeholder">
                    <label class="control-label"
                        for="tax"><?= _l('tax_1'); ?>
                        (Stripe)</label>
                    <select class="selectpicker" data-width="100%" name="stripe_tax_id"
                        data-none-selected-text="<?= _l('no_tax'); ?>">
                        <option value=""></option>
                        <?php foreach ($stripe_tax_rates->data as $tax) {
                            if ($tax->inclusive) {
                                continue;
                            }
                            if (! $tax->active) {
                                if (! isset($subscription)) {
                                    continue;
                                }
                                if ($subscription->stripe_tax_id != $tax->id) {
                                    continue;
                                }
                            } ?>
                        <option value="<?= e($tax->id); ?>"
                            data-subtext="<?= ! empty($tax->country) ? $tax->country : ''; ?>"
                            <?php if (isset($subscription) && $subscription->stripe_tax_id == $tax->id) {
                                echo ' selected';
                            } ?>>
                            <?= e($tax->display_name); ?>
                            <?= ! empty($tax->jurisdiction) ? ' - ' . $tax->jurisdiction . ' ' : ''; ?>
                            (<?= e($tax->percentage); ?>%)
                        </option>
                        <?php
                        } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">

                <div class="form-group select-placeholder">
                    <label class="control-label"
                        for="tax"><?= _l('tax_2'); ?>
                        (Stripe)</label>
                    <select class="selectpicker" data-width="100%" name="stripe_tax_id_2"
                        data-none-selected-text="<?= _l('no_tax'); ?>">
                        <option value=""></option>
                        <?php foreach ($stripe_tax_rates->data as $tax) {
                            if ($tax->inclusive) {
                                continue;
                            }
                            if (! $tax->active) {
                                if (! isset($subscription)) {
                                    continue;
                                }
                                if ($subscription->stripe_tax_id_2 != $tax->id) {
                                    continue;
                                }
                            } ?>
                        <option value="<?= e($tax->id); ?>"
                            data-subtext="<?= ! empty($tax->country) ? $tax->country : ''; ?>"
                            <?php if (isset($subscription) && $subscription->stripe_tax_id_2 == $tax->id) {
                                echo ' selected';
                            } ?>>
                            <?= e($tax->display_name); ?>
                            <?= ! empty($tax->jurisdiction) ? ' - ' . $tax->jurisdiction . ' ' : ''; ?>
                            (<?= e($tax->percentage); ?>%)
                        </option>
                        <?php
                        } ?>
                    </select>
                </div>
            </div>
        </div>

        <?php $value = (isset($subscription) ? $subscription->terms : ''); ?>
        <?= render_textarea('terms', 'terms_and_conditions', $value, ['placeholder' => _l('subscriptions_terms_info')], [], '', 'ays-ignore'); ?>
    </div>
</div>
<?php if ((isset($subscription) && staff_can('edit', 'subscriptions')) || ! isset($subscription)) { ?>
<div class="text-right">
    <button type="submit" class="btn btn-primary"
        data-loading-text="<?= _l('wait_text'); ?>"
        data-form="#subscriptionForm">
        <?= _l('save'); ?>
    </button>
</div>
<?php } ?>
<?= form_close(); ?>