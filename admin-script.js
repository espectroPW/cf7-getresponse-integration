jQuery(document).ready(function($) {
    
    // Dodawanie custom field
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
    $(document).on('click', '.form-header', function(e) {
        // Nie toggle gdy klikamy w toggle switch lub jego label
        if ($(e.target).closest('.form-toggle').length) {
            return;
        }
        
        var card = $(this).closest('.cf7-gr-form-card');
        card.toggleClass('collapsed');
        card.find('.form-body').slideToggle(300);
    });
    
    // Domyślnie zwiń wszystkie formularze przy ładowaniu
    $('.cf7-gr-form-card').addClass('collapsed');
    $('.cf7-gr-form-card .form-body').hide();
    
    // Usuwanie custom field
    $(document).on('click', '.remove-custom-field', function() {
        var container = $(this).closest('.custom-fields-container');
        $(this).closest('.custom-field-row').remove();
        
        // Zostaw przynajmniej jeden wiersz
        if (container.find('.custom-field-row').length === 0) {
            container.closest('.cf7-gr-form-card').find('.add-custom-field').click();
        }
    });
    // Toggle acceptance field visibility
    window.toggleAcceptanceField = function(radio) {
        var wrapper = $(radio).closest('.config-field').find('.acceptance-field-wrapper');
        if (radio.value === 'always') {
            wrapper.slideUp();
        } else {
            wrapper.slideDown();
        }
    };
    
});