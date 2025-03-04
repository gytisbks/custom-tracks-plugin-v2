<?php
/**
 * Template for showing producer settings form
 */
defined('ABSPATH') || exit;

$producer_id = get_current_user_id();

// Make sure settings object is available
if (!isset($settings)) {
    echo '<p>' . __('Settings not available.', 'custom-track-ordering-system') . '</p>';
    return;
}

// Get current service types
$service_types = isset($settings->service_types) ? $settings->service_types : array();

// Get current addons
$addons = isset($settings->addons) ? $settings->addons : array();

// Check if we're in the MarketKing dashboard
$is_marketking_dashboard = isset($_GET['page']) && $_GET['page'] === 'custom-tracks';
?>

<div class="ctos-producer-settings-form">
    <form method="post" action="" class="ctos-form">
        <?php wp_nonce_field('ctos_save_producer_settings', 'ctos_nonce'); ?>
        <input type="hidden" name="action" value="ctos_save_producer_settings">
        <input type="hidden" name="producer_id" value="<?php echo esc_attr($producer_id); ?>">
        
        <div class="ctos-form-section">
            <div class="ctos-form-row">
                <label class="ctos-form-label">
                    <input type="checkbox" name="enable_custom_orders" value="1" <?php checked($settings->enable_custom_orders, 1); ?>>
                    <?php _e('Enable Custom Track Orders', 'custom-track-ordering-system'); ?>
                </label>
                <p class="ctos-form-help"><?php _e('Enable this option to allow customers to order custom tracks from you.', 'custom-track-ordering-system'); ?></p>
            </div>
        </div>
        
        <div class="ctos-form-section">
            <h3><?php _e('Service Types', 'custom-track-ordering-system'); ?></h3>
            <p class="ctos-form-help"><?php _e('Define the types of services you offer and their prices.', 'custom-track-ordering-system'); ?></p>
            
            <div class="ctos-service-types">
                <div class="ctos-service-types-header <?php echo $is_marketking_dashboard ? 'row' : ''; ?>">
                    <div class="<?php echo $is_marketking_dashboard ? 'col-md-3' : 'ctos-service-type-name-col'; ?>"><?php _e('Service Name', 'custom-track-ordering-system'); ?></div>
                    <div class="<?php echo $is_marketking_dashboard ? 'col-md-2' : 'ctos-service-type-price-col'; ?>"><?php _e('Price', 'custom-track-ordering-system'); ?></div>
                    <div class="<?php echo $is_marketking_dashboard ? 'col-md-2' : 'ctos-service-type-deposit-col'; ?>"><?php _e('Deposit %', 'custom-track-ordering-system'); ?></div>
                    <div class="<?php echo $is_marketking_dashboard ? 'col-md-4' : 'ctos-service-type-desc-col'; ?>"><?php _e('Description', 'custom-track-ordering-system'); ?></div>
                    <div class="<?php echo $is_marketking_dashboard ? 'col-md-1' : 'ctos-service-type-action-col'; ?>"><?php _e('Action', 'custom-track-ordering-system'); ?></div>
                </div>
                
                <!-- Service Types Container -->
                <div id="ctos-service-types-container">
                    <?php if (!empty($service_types)) : ?>
                        <?php foreach ($service_types as $index => $service) : ?>
                            <div class="ctos-service-type-row <?php echo $is_marketking_dashboard ? 'row' : ''; ?>">
                                <div class="<?php echo $is_marketking_dashboard ? 'col-md-3' : 'ctos-service-type-name-col'; ?>">
                                    <input type="text" name="service_types[<?php echo $index; ?>][name]" value="<?php echo esc_attr($service['name']); ?>" class="ctos-input" required>
                                </div>
                                <div class="<?php echo $is_marketking_dashboard ? 'col-md-2' : 'ctos-service-type-price-col'; ?>">
                                    <input type="number" name="service_types[<?php echo $index; ?>][price]" value="<?php echo esc_attr($service['price']); ?>" step="0.01" min="0" class="ctos-input" required>
                                </div>
                                <div class="<?php echo $is_marketking_dashboard ? 'col-md-2' : 'ctos-service-type-deposit-col'; ?>">
                                    <input type="number" name="service_types[<?php echo $index; ?>][deposit_percentage]" value="<?php echo esc_attr($service['deposit_percentage']); ?>" min="0" max="100" class="ctos-input" required>
                                </div>
                                <div class="<?php echo $is_marketking_dashboard ? 'col-md-4' : 'ctos-service-type-desc-col'; ?>">
                                    <textarea name="service_types[<?php echo $index; ?>][description]" class="ctos-input"><?php echo esc_textarea($service['description']); ?></textarea>
                                </div>
                                <div class="<?php echo $is_marketking_dashboard ? 'col-md-1' : 'ctos-service-type-action-col'; ?>">
                                    <button type="button" class="ctos-remove-service <?php echo $is_marketking_dashboard ? 'btn btn-danger btn-sm' : 'ctos-button-remove'; ?>">
                                        <?php _e('Remove', 'custom-track-ordering-system'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="ctos-form-row">
                    <button type="button" id="ctos-add-service" class="<?php echo $is_marketking_dashboard ? 'btn btn-primary' : 'ctos-button'; ?>">
                        <?php _e('Add Service', 'custom-track-ordering-system'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <div class="ctos-form-section">
            <h3><?php _e('Additional Services (Add-ons)', 'custom-track-ordering-system'); ?></h3>
            <p class="ctos-form-help"><?php _e('Define additional services that customers can add to their order.', 'custom-track-ordering-system'); ?></p>
            
            <div class="ctos-addons">
                <div class="ctos-addons-header <?php echo $is_marketking_dashboard ? 'row' : ''; ?>">
                    <div class="<?php echo $is_marketking_dashboard ? 'col-md-4' : 'ctos-addon-name-col'; ?>"><?php _e('Add-on Name', 'custom-track-ordering-system'); ?></div>
                    <div class="<?php echo $is_marketking_dashboard ? 'col-md-2' : 'ctos-addon-price-col'; ?>"><?php _e('Price', 'custom-track-ordering-system'); ?></div>
                    <div class="<?php echo $is_marketking_dashboard ? 'col-md-5' : 'ctos-addon-desc-col'; ?>"><?php _e('Description', 'custom-track-ordering-system'); ?></div>
                    <div class="<?php echo $is_marketking_dashboard ? 'col-md-1' : 'ctos-addon-action-col'; ?>"><?php _e('Action', 'custom-track-ordering-system'); ?></div>
                </div>
                
                <!-- Add-ons Container -->
                <div id="ctos-addons-container">
                    <?php if (!empty($addons)) : ?>
                        <?php foreach ($addons as $index => $addon) : ?>
                            <div class="ctos-addon-row <?php echo $is_marketking_dashboard ? 'row' : ''; ?>">
                                <div class="<?php echo $is_marketking_dashboard ? 'col-md-4' : 'ctos-addon-name-col'; ?>">
                                    <input type="text" name="addons[<?php echo $index; ?>][name]" value="<?php echo esc_attr($addon['name']); ?>" class="ctos-input" required>
                                </div>
                                <div class="<?php echo $is_marketking_dashboard ? 'col-md-2' : 'ctos-addon-price-col'; ?>">
                                    <input type="number" name="addons[<?php echo $index; ?>][price]" value="<?php echo esc_attr($addon['price']); ?>" step="0.01" min="0" class="ctos-input" required>
                                </div>
                                <div class="<?php echo $is_marketking_dashboard ? 'col-md-5' : 'ctos-addon-desc-col'; ?>">
                                    <textarea name="addons[<?php echo $index; ?>][description]" class="ctos-input"><?php echo esc_textarea($addon['description']); ?></textarea>
                                </div>
                                <div class="<?php echo $is_marketking_dashboard ? 'col-md-1' : 'ctos-addon-action-col'; ?>">
                                    <button type="button" class="ctos-remove-addon <?php echo $is_marketking_dashboard ? 'btn btn-danger btn-sm' : 'ctos-button-remove'; ?>">
                                        <?php _e('Remove', 'custom-track-ordering-system'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="ctos-form-row">
                    <button type="button" id="ctos-add-addon" class="<?php echo $is_marketking_dashboard ? 'btn btn-primary' : 'ctos-button'; ?>">
                        <?php _e('Add Add-on', 'custom-track-ordering-system'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <div class="ctos-form-section">
            <h3><?php _e('Terms & Conditions', 'custom-track-ordering-system'); ?></h3>
            <div class="ctos-form-row">
                <textarea name="terms_conditions" class="ctos-input ctos-textarea" rows="5"><?php echo esc_textarea($settings->terms_conditions); ?></textarea>
                <p class="ctos-form-help"><?php _e('These terms will be displayed to customers during the ordering process.', 'custom-track-ordering-system'); ?></p>
            </div>
        </div>
        
        <div class="ctos-form-row">
            <button type="submit" class="<?php echo $is_marketking_dashboard ? 'btn btn-lg btn-primary' : 'ctos-button ctos-button-primary'; ?>">
                <?php _e('Save Settings', 'custom-track-ordering-system'); ?>
            </button>
        </div>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Add new service type
    $('#ctos-add-service').on('click', function() {
        var index = $('#ctos-service-types-container .ctos-service-type-row').length;
        var newRow = `
            <div class="ctos-service-type-row <?php echo $is_marketking_dashboard ? 'row' : ''; ?>">
                <div class="<?php echo $is_marketking_dashboard ? 'col-md-3' : 'ctos-service-type-name-col'; ?>">
                    <input type="text" name="service_types[${index}][name]" class="ctos-input" required>
                </div>
                <div class="<?php echo $is_marketking_dashboard ? 'col-md-2' : 'ctos-service-type-price-col'; ?>">
                    <input type="number" name="service_types[${index}][price]" step="0.01" min="0" class="ctos-input" required>
                </div>
                <div class="<?php echo $is_marketking_dashboard ? 'col-md-2' : 'ctos-service-type-deposit-col'; ?>">
                    <input type="number" name="service_types[${index}][deposit_percentage]" value="50" min="0" max="100" class="ctos-input" required>
                </div>
                <div class="<?php echo $is_marketking_dashboard ? 'col-md-4' : 'ctos-service-type-desc-col'; ?>">
                    <textarea name="service_types[${index}][description]" class="ctos-input"></textarea>
                </div>
                <div class="<?php echo $is_marketking_dashboard ? 'col-md-1' : 'ctos-service-type-action-col'; ?>">
                    <button type="button" class="ctos-remove-service <?php echo $is_marketking_dashboard ? 'btn btn-danger btn-sm' : 'ctos-button-remove'; ?>">
                        <?php _e('Remove', 'custom-track-ordering-system'); ?>
                    </button>
                </div>
            </div>
        `;
        
        $('#ctos-service-types-container').append(newRow);
    });
    
    // Remove service type
    $(document).on('click', '.ctos-remove-service', function() {
        $(this).closest('.ctos-service-type-row').remove();
        
        // Renumber the input names
        $('#ctos-service-types-container .ctos-service-type-row').each(function(index) {
            $(this).find('input, textarea').each(function() {
                var name = $(this).attr('name');
                name = name.replace(/service_types\[\d+\]/, 'service_types[' + index + ']');
                $(this).attr('name', name);
            });
        });
    });
    
    // Add new add-on
    $('#ctos-add-addon').on('click', function() {
        var index = $('#ctos-addons-container .ctos-addon-row').length;
        var newRow = `
            <div class="ctos-addon-row <?php echo $is_marketking_dashboard ? 'row' : ''; ?>">
                <div class="<?php echo $is_marketking_dashboard ? 'col-md-4' : 'ctos-addon-name-col'; ?>">
                    <input type="text" name="addons[${index}][name]" class="ctos-input" required>
                </div>
                <div class="<?php echo $is_marketking_dashboard ? 'col-md-2' : 'ctos-addon-price-col'; ?>">
                    <input type="number" name="addons[${index}][price]" step="0.01" min="0" class="ctos-input" required>
                </div>
                <div class="<?php echo $is_marketking_dashboard ? 'col-md-5' : 'ctos-addon-desc-col'; ?>">
                    <textarea name="addons[${index}][description]" class="ctos-input"></textarea>
                </div>
                <div class="<?php echo $is_marketking_dashboard ? 'col-md-1' : 'ctos-addon-action-col'; ?>">
                    <button type="button" class="ctos-remove-addon <?php echo $is_marketking_dashboard ? 'btn btn-danger btn-sm' : 'ctos-button-remove'; ?>">
                        <?php _e('Remove', 'custom-track-ordering-system'); ?>
                    </button>
                </div>
            </div>
        `;
        
        $('#ctos-addons-container').append(newRow);
    });
    
    // Remove add-on
    $(document).on('click', '.ctos-remove-addon', function() {
        $(this).closest('.ctos-addon-row').remove();
        
        // Renumber the input names
        $('#ctos-addons-container .ctos-addon-row').each(function(index) {
            $(this).find('input, textarea').each(function() {
                var name = $(this).attr('name');
                name = name.replace(/addons\[\d+\]/, 'addons[' + index + ']');
                $(this).attr('name', name);
            });
        });
    });
});
</script>
