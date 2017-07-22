<?php
/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */
?>
<?php if (!empty($transactions) || $_filtering) { ?>
<form data-filter-empty="true">
  <?php if ($this->access('transaction_delete')) { ?>
  <div class="btn-toolbar actions">
    <div class="btn-group">
      <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
        <?php echo $this->text('With selected'); ?> <span class="caret"></span>
      </button>
      <ul class="dropdown-menu">
        <li>
          <a data-action="delete" data-action-confirm="<?php echo $this->text('Are you sure? It cannot be undone!'); ?>" href="#">
            <?php echo $this->text('Delete'); ?>
          </a>
        </li>
      </ul>
    </div>
  </div>
  <?php } ?>
  <div class="table-responsive">
    <table class="table transactions">
      <thead>
        <tr>
          <th>
            <input type="checkbox" id="select-all" value="1">
          </th>
          <th>
            <a href="<?php echo $sort_order_id; ?>">
              <?php echo $this->text('Order ID'); ?> <i class="fa fa-sort"></i>
            </a>
          </th>
          <th>
            <a href="<?php echo $sort_payment_method; ?>">
              <?php echo $this->text('Payment method'); ?> <i class="fa fa-sort"></i>
            </a>
          </th>
          <th>
            <a href="<?php echo $sort_gateway_transaction_id; ?>">
              <?php echo $this->text('Gateway transaction ID'); ?> <i class="fa fa-sort"></i>
            </a>
          </th>
          <th>
            <a href="<?php echo $sort_created; ?>">
              <?php echo $this->text('Created'); ?> <i class="fa fa-sort"></i>
            </a>
          </th>
          <th></th>
        </tr>
        <tr class="filters active">
          <th></th>
          <th>
            <input class="form-control" name="order_id" value="<?php echo $filter_order_id; ?>" placeholder="<?php echo $this->text('Any'); ?>">
          </th>
          <th>
            <select name="payment_method" class="form-control">
              <option value=""><?php echo $this->text('Any'); ?></option>
              <?php foreach ($payment_methods as $method_id => $method) { ?>
              <option value="<?php echo $this->e($method_id); ?>"<?php echo $filter_payment_method == $method_id ? ' selected' : '' ?>>
              <?php echo $this->e($method['title']); ?>
              </option>
              <?php } ?>
            </select>
          </th>
          <th>
            <input class="form-control" name="gateway_transaction_id" value="<?php echo $filter_gateway_transaction_id; ?>" placeholder="<?php echo $this->text('Any'); ?>">
          </th>
          <th></th>
          <th>
            <a href="<?php echo $this->url($_path); ?>" class="btn btn-default clear-filter" title="<?php echo $this->text('Reset filter'); ?>">
              <i class="fa fa-refresh"></i>
            </a>
            <button class="btn btn-default filter" title="<?php echo $this->text('Filter'); ?>">
              <i class="fa fa-search"></i>
            </button>
          </th>
        </tr>
      </thead>
      <tbody>
        <?php if ($_filtering && empty($transactions)) { ?>
        <tr>
          <td colspan="6">
            <?php echo $this->text('No results'); ?>
            <a href="<?php echo $this->url($_path); ?>" class="clear-filter"><?php echo $this->text('Reset'); ?></a>
          </td>
        </tr>
        <?php } ?>
        <?php foreach ($transactions as $transaction_id => $transaction) { ?>
        <tr>
          <td class="middle">
            <input type="checkbox" class="select-all" name="selected[]" value="<?php echo $transaction_id; ?>">
          </td>
          <td class="middle">
            <a href="<?php echo $this->url("admin/sale/order/{$transaction['order_id']}"); ?>"><?php echo $this->e($transaction['order_id']); ?></a>
          </td>
          <td class="middle">
            <?php echo isset($payment_methods[$transaction['payment_method']]['title']) ? $this->e($payment_methods[$transaction['payment_method']]['title']) : $this->text('Unknown'); ?>
          </td>
          <td class="middle">
            <?php echo $this->e($transaction['gateway_transaction_id']); ?>
          </td>
          <td class="middle">
            <?php echo $this->date($transaction['created']); ?>
          </td>
          <td></td>
        </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
  <?php if (!empty($_pager)) { ?>
  <?php echo $_pager; ?>
  <?php } ?>
</form>
<?php } else { ?>
<div class="row">
  <div class="col-md-12">
    <?php echo $this->text('There are no items yet'); ?>
  </div>
</div>
<?php } ?>
