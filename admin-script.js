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
        
        // Pobierz opcje pól dla tego formularza
        var firstSelect = container.find('select').first();
        var fieldsOptions = firstSelect.html();
        
        template = template.replace(/FORM_ID/g, formId);
        template = template.replace(/INDEX/g, currentRows);
        template = template.replace('FIELDS_OPTIONS', fieldsOptions);
        
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