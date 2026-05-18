/**
 * CF7 GetResponse Integration - Admin Scripts
 *
 * Handles AJAX campaign loading, dynamic field management,
 * and UI interactions for the admin settings page.
 *
 * @package CF7_GetResponse_Integration
 * @since 3.0.0
 */

jQuery(document).ready(function($) {

    /**
     * Load campaigns from GetResponse API via AJAX
     *
     * Fetches all available campaigns and populates the select dropdowns
     * for primary and secondary campaign selection.
     */
    $(document).on('click', '.load-campaigns-btn', function() {
        var btn = $(this);
        var formId = btn.data('form-id');
        var card = btn.closest('.cf7-gr-form-card');
        var apiKeyInput = card.find('.api-key-input');
        var apiKey = apiKeyInput.val().trim();
        var loadingMsg = card.find('.campaigns-loading');
        var errorMsg = card.find('.campaigns-error');
        var primarySelect = card.find('.campaign-select-primary');
        var secondarySelect = card.find('.campaign-select-secondary');

        // Reset komunikatów
        loadingMsg.hide();
        errorMsg.hide().text('');

        if (!apiKey) {
            errorMsg.text('❌ Wpisz najpierw API Key').show();
            return;
        }

        // Wyłącz przycisk i pokaż loading
        btn.prop('disabled', true).text('⏳ Ładowanie...');
        loadingMsg.show();

        $.ajax({
            url: cf7GrAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cf7_gr_get_campaigns',
                nonce: cf7GrAjax.nonce,
                api_key: apiKey
            },
            success: function(response) {
                loadingMsg.hide();
                btn.prop('disabled', false).text('🔄 Załaduj listy');

                if (response.success && response.data.campaigns) {
                    var campaigns = response.data.campaigns;

                    // Zachowaj obecnie wybrane wartości
                    var currentPrimary = primarySelect.val();
                    var currentSecondary = secondarySelect.val();

                    // Wyczyść selecty
                    primarySelect.html('<option value="">-- Wybierz listę --</option>');
                    secondarySelect.html('<option value="">-- Wybierz listę --</option>');

                    // Wypełnij opcjami
                    campaigns.forEach(function(campaign) {
                        var option = '<option value="' + campaign.id + '">' + campaign.name + ' (' + campaign.id + ')</option>';
                        primarySelect.append(option);
                        secondarySelect.append(option);
                    });

                    // Przywróć wybrane wartości jeśli istnieją
                    if (currentPrimary) {
                        primarySelect.val(currentPrimary);
                    }
                    if (currentSecondary) {
                        secondarySelect.val(currentSecondary);
                    }

                    errorMsg.css('color', '#00a32a').text('✅ Załadowano ' + campaigns.length + ' list').show();
                } else {
                    errorMsg.text('❌ ' + (response.data.message || 'Nieznany błąd')).show();
                }
            },
            error: function(xhr, status, error) {
                loadingMsg.hide();
                btn.prop('disabled', false).text('🔄 Załaduj listy');
                errorMsg.text('❌ Błąd połączenia: ' + error).show();
            }
        });
    });

    /**
     * Load custom fields from GetResponse API via AJAX
     */
    $(document).on('click', '.load-custom-fields-btn', function() {
        var btn = $(this);
        var formId = btn.data('form-id');
        var card = btn.closest('.cf7-gr-form-card');
        var apiKey = card.find('.api-key-input').val().trim();
        var loadingMsg = card.find('.custom-fields-loading');
        var statusMsg = card.find('.custom-fields-status');

        loadingMsg.hide();
        statusMsg.hide().text('');

        if (!apiKey) {
            statusMsg.css('color', '#d63638').text('❌ Wpisz najpierw API Key').show();
            return;
        }

        btn.prop('disabled', true);
        loadingMsg.show();

        $.ajax({
            url: cf7GrAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cf7_gr_get_custom_fields',
                nonce: cf7GrAjax.nonce,
                api_key: apiKey
            },
            success: function(response) {
                loadingMsg.hide();
                btn.prop('disabled', false);

                if (response.success && response.data.custom_fields) {
                    var fields = response.data.custom_fields;

                    // Zapisz pola w data atrybucie karty
                    card.data('gr-custom-fields', fields);

                    // Wypełnij wszystkie selecty custom fields w tej karcie
                    card.find('.gr-custom-field-select').each(function() {
                        var select = $(this);
                        var currentVal = select.val();

                        select.html('<option value="">-- Wybierz pole GR --</option>');
                        fields.forEach(function(field) {
                            var label = field.name + ' (' + field.id + ')';
                            if (field.type) {
                                label += ' [' + field.type + ']';
                            }
                            select.append('<option value="' + field.id + '" data-name="' + field.name + '">' + label + '</option>');
                        });

                        if (currentVal) {
                            select.val(currentVal);
                        }
                    });

                    statusMsg.css('color', '#00a32a').text('✅ Załadowano ' + fields.length + ' pól').show();
                } else {
                    statusMsg.css('color', '#d63638').text('❌ ' + (response.data.message || 'Nieznany błąd')).show();
                }
            },
            error: function(xhr, status, error) {
                loadingMsg.hide();
                btn.prop('disabled', false);
                statusMsg.css('color', '#d63638').text('❌ Błąd połączenia: ' + error).show();
            }
        });
    });

    /**
     * Update hidden gr_field_name when GR custom field select changes
     */
    $(document).on('change', '.gr-custom-field-select', function() {
        var selectedOption = $(this).find('option:selected');
        var name = selectedOption.data('name') || '';
        $(this).closest('.custom-field-row').find('.gr-custom-field-name').val(name);
    });

    /**
     * Add new custom field mapping row
     *
     * Clones the custom field template and appends it to the container
     * with properly updated field names and indices.
     */
    $(document).on('click', '.add-custom-field', function() {
        var formId = $(this).data('form-id');
        var container = $('.custom-fields-container[data-form-id="' + formId + '"]');
        var template = $('#custom-field-template').html();
        var currentRows = container.find('.custom-field-row').length;

        // Pobierz opcje pól CF7 dla tego formularza
        var firstCf7Select = container.find('select[name*="cf7_field"]').first();
        var fieldsOptions = firstCf7Select.html();

        // Pobierz opcje pól GR (jeśli załadowane)
        var grFieldsOptions = '';
        var firstGrSelect = container.find('.gr-custom-field-select').first();
        if (firstGrSelect.find('option').length > 1) {
            grFieldsOptions = firstGrSelect.html();
        }

        template = template.replace(/FORM_ID/g, formId);
        template = template.replace(/INDEX/g, currentRows);
        template = template.replace('FIELDS_OPTIONS', fieldsOptions);
        template = template.replace('GR_FIELDS_OPTIONS', grFieldsOptions);

        container.append(template);
    });

    /**
     * Toggle form card expansion/collapse
     *
     * Allows clicking on form header to expand/collapse the form body,
     * except when clicking on the enable/disable toggle switch.
     */
    $(document).on('click', '.form-header', function(e) {
        // Nie toggle gdy klikamy w toggle switch lub jego label
        if ($(e.target).closest('.form-toggle').length) {
            return;
        }
        
        var card = $(this).closest('.cf7-gr-form-card');
        card.toggleClass('collapsed');
        card.find('.form-body').slideToggle(300);
    });
    
    // Collapse all form cards by default on page load
    $('.cf7-gr-form-card').addClass('collapsed');
    $('.cf7-gr-form-card .form-body').hide();

    // Auto-load GR custom fields for cards that have API key saved
    $('.cf7-gr-form-card.enabled').each(function() {
        var card = $(this);
        var apiKey = card.find('.api-key-input').val().trim();
        if (apiKey && card.find('.gr-custom-field-select').length) {
            card.find('.load-custom-fields-btn').trigger('click');
        }
    });

    /**
     * Remove custom field mapping row
     *
     * Removes the clicked row and ensures at least one row remains.
     */
    $(document).on('click', '.remove-custom-field', function() {
        var container = $(this).closest('.custom-fields-container');
        $(this).closest('.custom-field-row').remove();
        
        // Zostaw przynajmniej jeden wiersz
        if (container.find('.custom-field-row').length === 0) {
            container.closest('.cf7-gr-form-card').find('.add-custom-field').click();
        }
    });

    /**
     * Toggle acceptance field and dual campaign wrapper visibility
     *
     * Shows/hides the acceptance field selector and secondary campaign field
     * based on the selected operation mode (always/checkbox/dual).
     *
     * @param {HTMLElement} radio The clicked radio button element
     */
    window.toggleAcceptanceField = function(radio) {
        var card = $(radio).closest('.cf7-gr-form-card');
        var acceptanceWrapper = card.find('.acceptance-field-wrapper');
        var dualCampaignWrapper = card.find('.dual-campaign-wrapper');

        if (radio.value === 'always') {
            acceptanceWrapper.slideUp();
            dualCampaignWrapper.slideUp();
        } else if (radio.value === 'checkbox') {
            acceptanceWrapper.slideDown();
            dualCampaignWrapper.slideUp();
        } else if (radio.value === 'dual') {
            acceptanceWrapper.slideDown();
            dualCampaignWrapper.slideDown();
        }
    };
    
});