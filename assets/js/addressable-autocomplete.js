jQuery(document).ready(function($) {
    // Only run on checkout page
    if (!$('form.woocommerce-checkout').length) {
        return;
    }

    // Get the Addressable API key from localized script
    const addressableApiKey = addressableConfig.apiKey;
    const countryCode = addressableConfig.defaultCountry;

    if (!addressableApiKey) {
        console.warn('Addressable API key not configured');
        return;
    }

    // Function to handle address autocomplete
    function initAddressAutocomplete(inputSelector, resultSelector) {
        const input = $(inputSelector);
        const resultsContainer = $('<div class="addressable-results"></div>');
        
        // Add results container after input
        input.after(resultsContainer);
        
        // Style the results container
        resultsContainer.css({
            'position': 'absolute',
            'z-index': '1000',
            'background': 'white',
            'border': '1px solid #ddd',
            'max-height': '200px',
            'overflow-y': 'auto',
            'width': input.outerWidth() + 'px',
            'display': 'none'
        });

        let debounceTimer;

        input.on('input', function() {
            const query = $(this).val();
            
            if (query.length < 4) {
                resultsContainer.hide();
                return;
            }

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                // Make API request to Addressable
                $.ajax({
                    url: 'https://api.addressable.dev/v2/autocomplete',
                    data: {
                        api_key: addressableApiKey,
                        country_code: countryCode,
                        q: query,
                        max_results: 10
                    },
                    success: function(results) {
                        if (!results.length) {
                            resultsContainer.hide();
                            return;
                        }

                        // Clear and populate results
                        resultsContainer.empty();
                        results.forEach(result => {
                            const item = $('<div class="addressable-result"></div>')
                                .text(result.formatted)
                                .css({
                                    'padding': '8px',
                                    'cursor': 'pointer',
                                    'border-bottom': '1px solid #eee'
                                })
                                .hover(
                                    function() { $(this).css('background-color', '#f5f5f5'); },
                                    function() { $(this).css('background-color', 'white'); }
                                )
                                .data('address', result);

                            resultsContainer.append(item);
                        });
                        resultsContainer.show();
                    },
                    error: function(xhr, status, error) {
                        console.error('Addressable API error:', error);
                        resultsContainer.hide();
                    }
                });
            }, 300); // Debounce delay
        });

        // Handle click on result
        resultsContainer.on('click', '.addressable-result', function() {
            const address = $(this).data('address');
            
            // Fill in the address fields
            $('#billing_address_1').val(address.street_number + ' ' + address.street);
            $('#billing_city').val(address.city || address.locality);
            $('#billing_postcode').val(address.postcode);
            
            // If shipping to different address is enabled, also fill shipping fields
            if ($('#ship-to-different-address-checkbox').is(':checked')) {
                $('#shipping_address_1').val(address.street_number + ' ' + address.street);
                $('#shipping_city').val(address.city || address.locality);
                $('#shipping_postcode').val(address.postcode);
            }
            
            resultsContainer.hide();
            input.val($(this).text());
        });

        // Hide results when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.addressable-results, ' + inputSelector).length) {
                resultsContainer.hide();
            }
        });
    }

    // Initialize autocomplete for billing and shipping address fields
    initAddressAutocomplete('#billing_address_1');
    initAddressAutocomplete('#shipping_address_1');
}); 