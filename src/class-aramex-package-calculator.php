<?php
defined('ABSPATH') || exit;

class Aramex_Package_Calculator {
    // Package types
    const PACKAGE_TYPE_SATCHEL = 'S';
    const PACKAGE_TYPE_PACKAGE = 'P';

    // Maximum satchel height in cm
    const MAX_SATCHEL_HEIGHT = 5;

    // Satchel sizes with dimensions in cm and weight in kg
    private $satchel_sizes = array(
        '300GM' => array('length' => 22.0, 'width' => 16.5, 'weight' => 0.3, 'regions' => array('au')),
        'DL'    => array('length' => 12.6, 'width' => 24.0, 'weight' => 5.0, 'regions' => array('nz')),
        'A5'    => array('length' => 19.0, 'width' => 26.0, 'weight' => 5.0, 'regions' => array('au', 'nz')),
        'A4'    => array('length' => 25.0, 'width' => 32.5, 'weight' => 5.0, 'regions' => array('au', 'nz')),
        'A3'    => array('length' => 32.5, 'width' => 44.0, 'weight' => 5.0, 'regions' => array('au', 'nz')),
        'A2'    => array('length' => 45.0, 'width' => 61.0, 'weight' => 5.0, 'regions' => array('au', 'nz')),
    );

    // Default box sizes (can be overridden in settings)
    private $default_boxes = array(
        'SMALL'  => array('length' => 20, 'width' => 20, 'height' => 20, 'weight' => 5),
        'MEDIUM' => array('length' => 30, 'width' => 30, 'height' => 30, 'weight' => 10),
        'LARGE'  => array('length' => 40, 'width' => 40, 'height' => 40, 'weight' => 20),
    );

    private $origin_country;
    private $packaging_type;
    private $custom_boxes;
    private $shipping_method;

    public function __construct($origin_country = 'nz', $packaging_type = 'product_dimensions', $shipping_method = null) {
        $this->origin_country = $origin_country;
        $this->packaging_type = $packaging_type;
        $this->shipping_method = $shipping_method;
        
        // If no shipping method provided, try to create one
        if ($this->shipping_method === null) {
            require_once ARAMEX_PLUGIN_DIR . 'src/class-aramex-shipping-method.php';
            $this->shipping_method = new My_Shipping_Method();
        }
        
        $this->custom_boxes = $this->get_custom_boxes();
    }

    /**
     * Get available satchel sizes for the current region
     */
    public function get_available_satchel_sizes() {
        $available_sizes = array();
        foreach ($this->satchel_sizes as $size => $details) {
            // Convert size to option name (e.g., '300GM' to 'enable_satchel_300gm')
            $option_name = 'enable_satchel_' . strtolower(str_replace(' ', '_', $size));
            
            // Check if satchel is enabled and available in the current region
            if ($this->shipping_method->get_option($option_name) === 'yes' && 
                in_array($this->origin_country, $details['regions'])) {
                $available_sizes[$size] = $details;
            }
        }
        return $available_sizes;
    }

    /**
     * Get custom box sizes from settings
     */
    private function get_custom_boxes() {
        $enabled_boxes = array();
        
        // Get custom boxes from settings
        $custom_boxes = get_option('aramex_shipping_aunz_custom_boxes', array());
        if (!empty($custom_boxes)) {
            return $custom_boxes;
        }
        
        // If no custom boxes, use default boxes that are enabled
        foreach ($this->default_boxes as $size => $dimensions) {
            $option_name = 'enable_box_' . strtolower($size);
            if ($this->shipping_method->get_option($option_name) === 'yes') {
                $enabled_boxes[$size] = $dimensions;
            }
        }
        
        return $enabled_boxes;
    }

    /**
     * Calculate optimal packaging for cart items
     */
    public function calculate_optimal_packaging($cart_items) {
        switch ($this->packaging_type) {
            case 'single_box':
                return $this->calculate_single_box($cart_items);
            case 'fixed_size':
                return $this->calculate_fixed_size_boxes($cart_items);
            case 'product_dimensions':
            default:
                return $this->calculate_dynamic_boxes($cart_items);
        }
    }

    /**
     * Calculate single box for all items
     */
    private function calculate_single_box($cart_items) {
        $total_weight = 0;
        $max_length = 0;
        $max_width = 0;
        $max_height = 0;

        foreach ($cart_items as $item) {
            $product = $item['data'];
            $quantity = $item['quantity'];

            $total_weight += (float) $product->get_weight() * $quantity;
            $max_length = max($max_length, (float) $product->get_length());
            $max_width = max($max_width, (float) $product->get_width());
            $max_height = max($max_height, (float) $product->get_height());
        }

        return array(array(
            'PackageType' => self::PACKAGE_TYPE_PACKAGE,
            'Quantity' => 1,
            'WeightDead' => $total_weight,
            'Length' => $max_length,
            'Width' => $max_width,
            'Height' => $max_height,
        ));
    }

    /**
     * Calculate fixed size boxes needed
     */
    private function calculate_fixed_size_boxes($cart_items) {
        $boxes = array();
        $remaining_items = $this->group_items_by_volume($cart_items);
        
        foreach ($this->custom_boxes as $box_size => $box_dims) {
            while (!empty($remaining_items)) {
                $box_volume = $box_dims['length'] * $box_dims['width'] * $box_dims['height'];
                $current_box_items = array();
                $current_box_weight = 0;
                
                foreach ($remaining_items as $key => $item) {
                    if ($current_box_weight + $item['weight'] <= $box_dims['weight']) {
                        $current_box_items[] = $item;
                        $current_box_weight += $item['weight'];
                        unset($remaining_items[$key]);
                    }
                }
                
                if (!empty($current_box_items)) {
                    $boxes[] = array(
                        'PackageType' => self::PACKAGE_TYPE_PACKAGE,
                        'Quantity' => 1,
                        'WeightDead' => $current_box_weight,
                        'Length' => $box_dims['length'],
                        'Width' => $box_dims['width'],
                        'Height' => $box_dims['height'],
                    );
                } else {
                    break;
                }
            }
        }
        
        return $boxes;
    }

    /**
     * Calculate dynamic boxes based on product dimensions
     */
    private function calculate_dynamic_boxes($cart_items) {
        $items = $this->group_items_by_volume($cart_items);
        $boxes = array();
        
        // First, try to fit items in satchels
        $satchel_items = $this->fit_items_in_satchels($items);
        $boxes = array_merge($boxes, $satchel_items['packages']);
        $remaining_items = $satchel_items['remaining'];
        
        // Then, pack remaining items in boxes
        if (!empty($remaining_items)) {
            $box_items = $this->pack_items_in_boxes($remaining_items);
            $boxes = array_merge($boxes, $box_items);
        }
        
        return $boxes;
    }

    /**
     * Group cart items by volume for efficient packing
     */
    private function group_items_by_volume($cart_items) {
        $grouped_items = array();
        
        foreach ($cart_items as $item) {
            $product = $item['data'];
            $quantity = $item['quantity'];
            
            for ($i = 0; $i < $quantity; $i++) {
                $grouped_items[] = array(
                    'length' => (float) $product->get_length(),
                    'width' => (float) $product->get_width(),
                    'height' => (float) $product->get_height(),
                    'weight' => (float) $product->get_weight(),
                    'volume' => (float) $product->get_length() * $product->get_width() * $product->get_height(),
                );
            }
        }
        
        // Sort by volume in descending order
        usort($grouped_items, function($a, $b) {
            return $b['volume'] - $a['volume'];
        });
        
        return $grouped_items;
    }

    /**
     * Try to fit items in available satchels
     */
    private function fit_items_in_satchels($items) {
        $satchel_packages = array();
        $remaining_items = array();
        $available_satchels = $this->get_available_satchel_sizes();
        
        // Group items that could potentially go together in a satchel
        $potential_satchel_groups = array();
        $current_group = array(
            'items' => array(),
            'total_height' => 0,
            'max_length' => 0,
            'max_width' => 0,
            'total_weight' => 0
        );

        // First, try to group items that could fit together in a satchel
        foreach ($items as $item) {
            // If adding this item would exceed the max satchel height, start a new group
            if ($current_group['total_height'] + $item['height'] > self::MAX_SATCHEL_HEIGHT) {
                if (!empty($current_group['items'])) {
                    $potential_satchel_groups[] = $current_group;
                }
                $current_group = array(
                    'items' => array(),
                    'total_height' => 0,
                    'max_length' => 0,
                    'max_width' => 0,
                    'total_weight' => 0
                );
            }

            $current_group['items'][] = $item;
            $current_group['total_height'] += $item['height'];
            $current_group['max_length'] = max($current_group['max_length'], $item['length']);
            $current_group['max_width'] = max($current_group['max_width'], $item['width']);
            $current_group['total_weight'] += $item['weight'];
        }

        // Add the last group if it's not empty
        if (!empty($current_group['items'])) {
            $potential_satchel_groups[] = $current_group;
        }

        // Now try to fit each group into a satchel
        foreach ($potential_satchel_groups as $group) {
            $fitted = false;

            // Only try to fit in satchel if total height is within limit
            if ($group['total_height'] <= self::MAX_SATCHEL_HEIGHT) {
                foreach ($available_satchels as $size => $satchel) {
                    if ($group['max_length'] <= $satchel['length'] && 
                        $group['max_width'] <= $satchel['width'] && 
                        $group['total_weight'] <= $satchel['weight']) {
                        
                        $satchel_packages[] = array(
                            'PackageType' => self::PACKAGE_TYPE_SATCHEL,
                            'Quantity' => 1,
                            'SatchelSize' => $size,
                            'WeightDead' => $group['total_weight'],
                        );
                        $fitted = true;
                        break;
                    }
                }
            }

            // If the group couldn't fit in a satchel, add all items to remaining
            if (!$fitted) {
                $remaining_items = array_merge($remaining_items, $group['items']);
            }
        }
        
        return array(
            'packages' => $satchel_packages,
            'remaining' => $remaining_items,
        );
    }

    /**
     * Pack remaining items in boxes using 3D bin packing algorithm
     */
    private function pack_items_in_boxes($items) {
        $boxes = array();
        $current_box = array(
            'items' => array(),
            'weight' => 0,
            'dimensions' => array(
                'length' => 0,
                'width' => 0,
                'height' => 0,
            ),
        );

        foreach ($items as $item) {
            // If item doesn't fit in current box, create a new box
            if ($this->will_item_fit($current_box, $item)) {
                $this->add_item_to_box($current_box, $item);
            } else {
                if (!empty($current_box['items'])) {
                    $boxes[] = array(
                        'PackageType' => self::PACKAGE_TYPE_PACKAGE,
                        'Quantity' => 1,
                        'WeightDead' => $current_box['weight'],
                        'Length' => $current_box['dimensions']['length'],
                        'Width' => $current_box['dimensions']['width'],
                        'Height' => $current_box['dimensions']['height'],
                    );
                }
                
                // Start a new box with this item
                $current_box = array(
                    'items' => array($item),
                    'weight' => $item['weight'],
                    'dimensions' => array(
                        'length' => $item['length'],
                        'width' => $item['width'],
                        'height' => $item['height'],
                    ),
                );
            }
        }
        
        // Add the last box if it has items
        if (!empty($current_box['items'])) {
            $boxes[] = array(
                'PackageType' => self::PACKAGE_TYPE_PACKAGE,
                'Quantity' => 1,
                'WeightDead' => $current_box['weight'],
                'Length' => $current_box['dimensions']['length'],
                'Width' => $current_box['dimensions']['width'],
                'Height' => $current_box['dimensions']['height'],
            );
        }
        
        return $boxes;
    }

    /**
     * Check if an item will fit in the current box
     */
    private function will_item_fit($box, $item) {
        if (empty($box['items'])) {
            return true;
        }

        // Check weight limit (using the largest box weight limit as reference)
        $max_weight = max(array_column($this->custom_boxes, 'weight'));
        if ($box['weight'] + $item['weight'] > $max_weight) {
            return false;
        }

        // Calculate new dimensions if item is added
        $new_length = max($box['dimensions']['length'], $item['length']);
        $new_width = max($box['dimensions']['width'], $item['width']);
        $new_height = $box['dimensions']['height'] + $item['height']; // Stack vertically

        // Check against largest available box dimensions
        $largest_box = array_reduce($this->custom_boxes, function($carry, $box) {
            if ($box['length'] * $box['width'] * $box['height'] > 
                $carry['length'] * $carry['width'] * $carry['height']) {
                return $box;
            }
            return $carry;
        }, reset($this->custom_boxes));

        return $new_length <= $largest_box['length'] && 
               $new_width <= $largest_box['width'] && 
               $new_height <= $largest_box['height'];
    }

    /**
     * Add an item to the current box
     */
    private function add_item_to_box(&$box, $item) {
        $box['items'][] = $item;
        $box['weight'] += $item['weight'];
        $box['dimensions'] = array(
            'length' => max($box['dimensions']['length'], $item['length']),
            'width' => max($box['dimensions']['width'], $item['width']),
            'height' => $box['dimensions']['height'] + $item['height'], // Stack vertically
        );
    }
} 