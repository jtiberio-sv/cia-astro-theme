<?php
/**
 * View Order — cia-astro override.
 *
 * Remove o texto default WC ("Order #X was placed on Y and is currently Z.")
 * e substitui por banner compacto + layout 2-col (order-details + vindi).
 *
 * Substitui woocommerce/templates/myaccount/view-order.php
 */

defined('ABSPATH') || exit;

$notes  = $order->get_customer_order_notes();
$status = $order->get_status();
$status_name = wc_get_order_status_name($status);
$status_cls  = 'cdm-vo-status-' . sanitize_html_class($status);
$order_date  = $order->get_date_created() ? wc_format_datetime($order->get_date_created()) : '';
?>

<div class="cdm-vo-header <?php echo esc_attr($status_cls); ?>">
  <div class="cdm-vo-header-main">
    <span class="cdm-vo-eyebrow">Pedido</span>
    <span class="cdm-vo-num">#<?php echo esc_html($order->get_order_number()); ?></span>
  </div>
  <div class="cdm-vo-header-meta">
    <span class="cdm-vo-date">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <?php echo esc_html($order_date); ?>
    </span>
    <span class="cdm-vo-status-chip"><?php echo esc_html($status_name); ?></span>
  </div>
</div>

<?php if ($notes) : ?>
  <div class="cdm-vo-notes">
    <h3 class="cdm-vo-notes-title">Atualiza&ccedil;&otilde;es do pedido</h3>
    <ol class="cdm-vo-notes-list">
      <?php foreach ($notes as $note) : ?>
        <li class="cdm-vo-note">
          <time class="cdm-vo-note-date"><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($note->comment_date))); ?></time>
          <div class="cdm-vo-note-text"><?php echo wp_kses_post(wpautop(wptexturize($note->comment_content))); ?></div>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
<?php endif; ?>

<div class="cdm-vo-grid">
  <?php do_action('woocommerce_view_order', $order_id); ?>
</div>
