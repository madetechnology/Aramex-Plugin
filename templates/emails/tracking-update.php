<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <?php
        /* translators: %s: Customer first name */
        printf('<p style="margin-bottom: 20px;">' . esc_html__('Hello %s,', 'Aramex-Plugin') . '</p>', 
            esc_html($order->get_billing_first_name()));
        ?>
        
        <p style="margin-bottom: 20px;"><?php esc_html_e('Your order has been processed and is awaiting pickup by our courier partner.', 'Aramex-Plugin'); ?></p>
        <p style="margin-bottom: 20px;"><?php esc_html_e('Pickup usually happens within 1-2 business days.', 'Aramex-Plugin'); ?></p>
        
        <div style="background-color: #f8f8f8; padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin: 20px 0;">
            <p style="margin: 0;">
                <strong><?php esc_html_e('Tracking Number:', 'Aramex-Plugin'); ?></strong>
                <?php echo esc_html( $order->get_meta('aramex_label_no')); ?>
            </p>
            <?php if ($con_id && $con_id !== $label_no) : ?>
            <p style="margin: 5px 0 0 0;">
                <strong><?php esc_html_e('Consignment ID:', 'Aramex-Plugin'); ?></strong>
                <?php echo esc_html($con_id); ?>
            </p>
            <?php endif; ?>
        </div>

        <?php if ($tracking_info['success'] && !empty($tracking_info['events'])) : ?>
            <p style="margin-bottom: 20px;"><?php esc_html_e('Here is the current tracking information for your order:', 'Aramex-Plugin'); ?></p>
            
            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <thead>
                    <tr style="background-color: #f8f8f8;">
                        <th style="padding: 10px; border: 1px solid #ddd; text-align: left;"><?php esc_html_e('Date/Time', 'Aramex-Plugin'); ?></th>
                        <th style="padding: 10px; border: 1px solid #ddd; text-align: left;"><?php esc_html_e('Status', 'Aramex-Plugin'); ?></th>
                        <th style="padding: 10px; border: 1px solid #ddd; text-align: left;"><?php esc_html_e('Description', 'Aramex-Plugin'); ?></th>
                        <th style="padding: 10px; border: 1px solid #ddd; text-align: left;"><?php esc_html_e('Location', 'Aramex-Plugin'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tracking_info['events'] as $event) : ?>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($event['date']); ?></td>
                        <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($event['status']); ?></td>
                        <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($event['scan_description'] ?: $event['description']); ?></td>
                        <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($event['location']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <p style="margin-top: 20px;"><?php esc_html_e('Thank you for choosing our service.', 'Aramex-Plugin'); ?></p>
        
        <p style="margin-top: 20px; font-size: 0.9em; color: #666;">
            <?php esc_html_e("If you have any questions about your shipment, please don't hesitate to contact us.", 'Aramex-Plugin'); ?>
        </p>
    </div>
</body>
</html> 