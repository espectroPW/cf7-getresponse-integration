<?php
/**
 * Plugin Name: CF7 GetResponse Integration
 * Plugin URI: https://iql.pl
 * Description: Professional integration between Contact Form 7 and GetResponse with dual-list support, custom fields mapping, and automatic campaign loading.
 * Version: 3.2.0
 * Author: IQLevel vel Espectro
 * Author URI: https://iql.pl
 * Text Domain: cf7-getresponse
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package CF7_GetResponse_Integration
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class for CF7 GetResponse Integration
 *
 * @since 3.0.0
 */
class CF7_GetResponse_Integration {

    /**
     * Option name for storing form mappings
     *
     * @var string
     * @since 3.0.0
     */
    private $option_name = 'cf7_gr_mappings';

    /**
     * Plugin version
     *
     * @var string
     * @since 3.1.0
     */
    private $version = '3.2.0';

    /**
     * Option name for storing logs
     *
     * @var string
     * @since 3.2.0
     */
    private $log_option = 'cf7_gr_logs';

    /**
     * Maximum number of log entries to keep
     *
     * @var int
     * @since 3.2.0
     */
    private $max_logs = 200;

    /**
     * Constructor - Initialize plugin hooks
     *
     * @since 3.0.0
     */
    public function __construct() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_save'));
        add_action('wpcf7_before_send_mail', array($this, 'handle_form_submission'));
        add_filter('wpcf7_skip_mail', array($this, 'maybe_skip_mail'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_cf7_gr_get_campaigns', array($this, 'ajax_get_campaigns'));
        add_action('wp_ajax_cf7_gr_get_custom_fields', array($this, 'ajax_get_custom_fields'));
        add_action('wp_ajax_cf7_gr_clear_logs', array($this, 'ajax_clear_logs'));
    }

    /**
     * Load plugin text domain for translations
     *
     * @since 3.1.0
     * @return void
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'cf7-getresponse',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }

    /**
     * Add admin menu page
     *
     * @since 3.0.0
     * @return void
     */
    public function add_admin_menu() {
        add_menu_page(
            __('CF7 GetResponse', 'cf7-getresponse'),
            __('CF7 → GR', 'cf7-getresponse'),
            'manage_options',
            'cf7-getresponse',
            array($this, 'settings_page'),
            'dashicons-email-alt',
            80
        );

        add_submenu_page(
            'cf7-getresponse',
            __('Ustawienia', 'cf7-getresponse'),
            __('Ustawienia', 'cf7-getresponse'),
            'manage_options',
            'cf7-getresponse',
            array($this, 'settings_page')
        );

        add_submenu_page(
            'cf7-getresponse',
            __('Logi', 'cf7-getresponse'),
            __('📋 Logi', 'cf7-getresponse'),
            'manage_options',
            'cf7-getresponse-logs',
            array($this, 'logs_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @since 3.0.0
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_cf7-getresponse' && $hook !== 'cf7-gr_page_cf7-getresponse-logs') {
            return;
        }

        wp_enqueue_style(
            'cf7-gr-admin',
            plugin_dir_url(__FILE__) . 'admin-style.css',
            array(),
            $this->version
        );

        wp_enqueue_script(
            'cf7-gr-admin',
            plugin_dir_url(__FILE__) . 'admin-script.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script('cf7-gr-admin', 'cf7GrAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('cf7_gr_ajax_nonce'),
        ));
    }

    /**
     * Handle form settings save
     *
     * @since 3.0.0
     * @return void
     */
    public function handle_save() {
        // Verify nonce
        if (!isset($_POST['cf7_gr_save']) || !wp_verify_nonce($_POST['cf7_gr_nonce'], 'cf7_gr_save_settings')) {
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'cf7-getresponse'));
        }
        
        $mappings = array();
        
        if (isset($_POST['mappings']) && is_array($_POST['mappings'])) {
            foreach ($_POST['mappings'] as $form_id => $data) {
                if (!empty($data['enabled'])) {
                    
                    // Custom fields mapping
                    $custom_fields = array();
                    if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
                        foreach ($data['custom_fields'] as $cf) {
                            if (!empty($cf['cf7_field']) && !empty($cf['gr_field_id'])) {
                                $custom_fields[] = array(
                                    'cf7_field' => sanitize_text_field($cf['cf7_field']),
                                    'gr_field_id' => sanitize_text_field($cf['gr_field_id']),
                                    'gr_field_name' => sanitize_text_field($cf['gr_field_name'])
                                );
                            }
                        }
                    }
                    
                    $mode = isset($data['mode']) ? sanitize_text_field($data['mode']) : 'checkbox';

                    $mappings[$form_id] = array(
                        'enabled' => true,
                        'api_key' => sanitize_text_field($data['api_key']),
                        'campaign_id' => sanitize_text_field($data['campaign_id']),
                        'campaign_id_secondary' => isset($data['campaign_id_secondary']) ? sanitize_text_field($data['campaign_id_secondary']) : '',
                        'mode' => $mode,
                        'acceptance_field' => isset($data['acceptance_field']) ? sanitize_text_field($data['acceptance_field']) : '',
                        'email_field' => sanitize_text_field($data['email_field']),
                        'name_field' => isset($data['name_field']) ? sanitize_text_field($data['name_field']) : '',
                        'skip_mail' => !empty($data['skip_mail']),
                        'custom_fields' => $custom_fields
                    );
                }
            }
        }
        
        update_option($this->option_name, $mappings);

        add_settings_error(
            'cf7_gr_messages',
            'cf7_gr_message',
            esc_html__('Settings saved successfully!', 'cf7-getresponse'),
            'updated'
        );
    }

    /**
     * Render settings page
     *
     * @since 3.0.0
     * @return void
     */
    public function settings_page() {
        $mappings = get_option($this->option_name, array());
        
        $cf7_forms = get_posts(array(
            'post_type' => 'wpcf7_contact_form',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        settings_errors('cf7_gr_messages');
        
        ?>
        <div class="wrap cf7-gr-wrap">
            <h1>📧 CF7 → GetResponse Integration</h1>
            <p class="description">Skonfiguruj automatyczne wysyłanie kontaktów do GetResponse po zaznaczeniu checkboxa</p>
            
            <form method="post" action="">
                <?php wp_nonce_field('cf7_gr_save_settings', 'cf7_gr_nonce'); ?>
                
                <div class="cf7-gr-forms">
                    <?php foreach ($cf7_forms as $form): ?>
                        <?php
                        $form_id = $form->ID;
                        $mapping = isset($mappings[$form_id]) ? $mappings[$form_id] : array();
                        $enabled = isset($mapping['enabled']) && $mapping['enabled'];
                        $form_fields = $this->parse_form_fields($form->ID);
                        ?>
                        
                        <div class="cf7-gr-form-card <?php echo $enabled ? 'enabled' : ''; ?>" data-form-id="<?php echo $form_id; ?>">
                            <div class="form-header">
                                <label class="form-toggle">
                                    <input type="checkbox" 
                                           name="mappings[<?php echo $form_id; ?>][enabled]" 
                                           value="1" 
                                           <?php checked($enabled); ?>
                                           onchange="this.closest('.cf7-gr-form-card').classList.toggle('enabled', this.checked)">
                                    <span class="toggle-switch"></span>
                                </label>
                                
                                <div class="form-title">
                                    <h2><?php echo esc_html($form->post_title); ?></h2>
                                    <div class="form-meta">
                                        <span class="form-id">ID: <?php echo $form_id; ?></span>
                                        <?php if ($enabled): ?>
                                            <span class="form-config-info">
                                                📧 <?php echo esc_html($mapping['email_field'] ?? 'brak'); ?>
                                                <?php if (!empty($mapping['custom_fields'])): ?>
                                                    • <?php echo count($mapping['custom_fields']); ?> custom fields
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <span class="status-badge">
                                    <?php echo $enabled ? '✅ Aktywne' : '⚪ Nieaktywne'; ?>
                                </span>
                            </div>
                            
                            <div class="form-body">
                                <!-- Podstawowe ustawienia -->
                                <div class="section">
                                    <h3>🔧 Podstawowe ustawienia</h3>
                                    <div class="config-field api-key-field">
                                        <label>🔑 GetResponse API Key</label>
                                        <div style="display: flex; gap: 10px; align-items: flex-start;">
                                            <input type="text"
                                                   class="api-key-input"
                                                   name="mappings[<?php echo $form_id; ?>][api_key]"
                                                   value="<?php echo esc_attr($mapping['api_key'] ?? ''); ?>"
                                                   placeholder="Wklej API Key"
                                                   data-form-id="<?php echo $form_id; ?>"
                                                   style="flex: 1;"
                                                   <?php echo $enabled ? 'required' : ''; ?>>
                                            <button type="button" class="button load-campaigns-btn" data-form-id="<?php echo $form_id; ?>">
                                                🔄 Załaduj listy
                                            </button>
                                        </div>
                                        <small>GetResponse → Menu → Integracje i API → API</small>
                                        <div class="campaigns-loading" style="display: none; margin-top: 10px; color: #2271b1;">
                                            ⏳ Pobieranie list z GetResponse...
                                        </div>
                                        <div class="campaigns-error" style="display: none; margin-top: 10px; color: #d63638;"></div>
                                    </div>

                                        <?php
                                    $mode = isset($mapping['mode']) ? $mapping['mode'] : 'checkbox';
                                    ?>
                                    <div class="config-grid">
                                        <div class="config-field campaign-select-wrapper">
                                            <label>📋 Lista główna (kontakt)</label>
                                            <select class="campaign-select-primary"
                                                    name="mappings[<?php echo $form_id; ?>][campaign_id]"
                                                    data-form-id="<?php echo $form_id; ?>"
                                                    <?php echo $enabled ? 'required' : ''; ?>>
                                                <option value="">-- Najpierw załaduj listy --</option>
                                                <?php if (!empty($mapping['campaign_id'])): ?>
                                                    <option value="<?php echo esc_attr($mapping['campaign_id']); ?>" selected>
                                                        <?php echo esc_html($mapping['campaign_id']); ?>
                                                    </option>
                                                <?php endif; ?>
                                            </select>
                                            <small>Zawsze zapisywana (tryb dual) lub jedyna lista (tryby always/checkbox)</small>
                                        </div>
                                    </div>

                                    <div class="config-grid dual-campaign-wrapper" style="<?php echo $mode !== 'dual' ? 'display:none;' : ''; ?>">
                                        <div class="config-field campaign-select-wrapper">
                                            <label>📬 Lista dodatkowa (newsletter)</label>
                                            <select class="campaign-select-secondary"
                                                    name="mappings[<?php echo $form_id; ?>][campaign_id_secondary]"
                                                    data-form-id="<?php echo $form_id; ?>">
                                                <option value="">-- Najpierw załaduj listy --</option>
                                                <?php if (!empty($mapping['campaign_id_secondary'])): ?>
                                                    <option value="<?php echo esc_attr($mapping['campaign_id_secondary']); ?>" selected>
                                                        <?php echo esc_html($mapping['campaign_id_secondary']); ?>
                                                    </option>
                                                <?php endif; ?>
                                            </select>
                                            <small>Zapisywana tylko gdy checkbox zaznaczony (tryb dual)</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Tryb działania -->
                                <div class="section highlight-section">
                                    <h3>⚡ Tryb działania</h3>
                                    <div class="config-field">
                                        <label>Kiedy wysyłać do GetResponse?</label>
                                        
                                        <?php
                                        $mode = isset($mapping['mode']) ? $mapping['mode'] : 'checkbox';
                                        ?>
                                        
                                        <label class="radio-option">
                                            <input type="radio" 
                                                name="mappings[<?php echo $form_id; ?>][mode]" 
                                                value="always" 
                                                <?php checked($mode, 'always'); ?>
                                                onchange="toggleAcceptanceField(this)">
                                            <strong>🚀 Zawsze przy wysłaniu formularza</strong>
                                            <small>Każde wysłanie formularza = automatyczny zapis do GetResponse</small>
                                        </label>
                                        
                                        <label class="radio-option">
                                            <input type="radio"
                                                name="mappings[<?php echo $form_id; ?>][mode]"
                                                value="checkbox"
                                                <?php checked($mode, 'checkbox'); ?>
                                                onchange="toggleAcceptanceField(this)">
                                            <strong>✅ Tylko gdy checkbox/acceptance zaznaczony</strong>
                                            <small>Użytkownik musi zaznaczyć zgodę</small>
                                        </label>

                                        <label class="radio-option">
                                            <input type="radio"
                                                name="mappings[<?php echo $form_id; ?>][mode]"
                                                value="dual"
                                                <?php checked($mode, 'dual'); ?>
                                                onchange="toggleAcceptanceField(this)">
                                            <strong>🎯 Dwie listy - zależnie od zgody</strong>
                                            <small>Zawsze zapisz na listę główną + dodatkowo na drugą listę jeśli zaznaczy zgodę</small>
                                        </label>

                                        <div class="acceptance-field-wrapper" style="margin-top: 20px; <?php echo $mode === 'always' ? 'display:none;' : ''; ?>">
                                            <label><strong>Pole wyzwalające (checkbox/acceptance):</strong></label>
                                            <?php if (!empty($form_fields['acceptance'])): ?>
                                                <select name="mappings[<?php echo $form_id; ?>][acceptance_field]">
                                                    <option value="">-- Wybierz pole --</option>
                                                    <?php foreach ($form_fields['acceptance'] as $field): ?>
                                                        <option value="<?php echo esc_attr($field['name']); ?>"
                                                                <?php selected($mapping['acceptance_field'] ?? '', $field['name']); ?>>
                                                            <?php echo esc_html($field['label']); ?> 
                                                            (<?php echo $field['name']; ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small>⚡ Tylko gdy to pole jest zaznaczone, dane zostaną wysłane</small>
                                            <?php else: ?>
                                                <p class="no-fields">❌ Brak pól acceptance/checkbox w tym formularzu</p>
                                                <small>Dodaj: <code>[acceptance newsletter "Zapisz na newsletter"]</code></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="config-field" style="margin-top: 20px; padding: 15px; background: #fff0f0; border: 1px solid #d63638; border-radius: 6px;">
                                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                            <input type="checkbox"
                                                name="mappings[<?php echo $form_id; ?>][skip_mail]"
                                                value="1"
                                                <?php checked(!empty($mapping['skip_mail'])); ?>>
                                            <strong>🚫 Nie wysyłaj emaila z CF7</strong>
                                        </label>
                                        <small style="margin-left: 30px;">Formularz pokaże sukces użytkownikowi, dane trafią do GetResponse, ale email do admina nie zostanie wysłany</small>
                                    </div>
                                </div>

                                <!-- Standardowe pola -->
                                <div class="section">
                                    <h3>📝 Standardowe pola GetResponse</h3>
                                    <div class="config-grid">
                                        <div class="config-field">
                                            <label>📧 Pole Email (wymagane)</label>
                                            <select name="mappings[<?php echo $form_id; ?>][email_field]" required>
                                                <?php if (!empty($form_fields['email'])): ?>
                                                    <?php foreach ($form_fields['email'] as $field): ?>
                                                        <option value="<?php echo esc_attr($field['name']); ?>"
                                                                <?php selected($mapping['email_field'] ?? $field['name'], $field['name']); ?>>
                                                            <?php echo esc_html($field['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <option value="your-email">your-email</option>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="config-field">
                                            <label>👤 Pole Imię (opcjonalne)</label>
                                            <select name="mappings[<?php echo $form_id; ?>][name_field]">
                                                <option value="">-- Nie wysyłaj --</option>
                                                <?php foreach ($form_fields['all'] as $field): ?>
                                                    <option value="<?php echo esc_attr($field['name']); ?>"
                                                            <?php selected($mapping['name_field'] ?? '', $field['name']); ?>>
                                                        <?php echo esc_html($field['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Custom Fields -->
                                <div class="section">
                                    <h3>➕ Dodatkowe pola (Custom Fields)</h3>
                                    <p class="description">Możesz wysłać dodatkowe pola z formularza jako custom fields do GetResponse</p>

                                    <div style="margin-bottom: 15px;">
                                        <button type="button" class="button load-custom-fields-btn" data-form-id="<?php echo $form_id; ?>">
                                            🔄 Załaduj pola z GetResponse
                                        </button>
                                        <span class="custom-fields-loading" style="display: none; margin-left: 10px; color: #2271b1;">
                                            ⏳ Pobieranie pól...
                                        </span>
                                        <span class="custom-fields-status" style="display: none; margin-left: 10px;"></span>
                                    </div>

                                    <div class="custom-fields-container" data-form-id="<?php echo $form_id; ?>">
                                        <?php
                                        $custom_fields = isset($mapping['custom_fields']) ? $mapping['custom_fields'] : array();
                                        if (empty($custom_fields)) {
                                            $custom_fields = array(array('cf7_field' => '', 'gr_field_id' => '', 'gr_field_name' => ''));
                                        }
                                        foreach ($custom_fields as $idx => $cf):
                                        ?>
                                        <div class="custom-field-row">
                                            <div class="cf-input">
                                                <label>Pole z CF7:</label>
                                                <select name="mappings[<?php echo $form_id; ?>][custom_fields][<?php echo $idx; ?>][cf7_field]">
                                                    <option value="">-- Wybierz pole --</option>
                                                    <?php foreach ($form_fields['all'] as $field): ?>
                                                        <option value="<?php echo esc_attr($field['name']); ?>"
                                                                <?php selected($cf['cf7_field'] ?? '', $field['name']); ?>>
                                                            <?php echo esc_html($field['name']); ?> (<?php echo $field['type']; ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <span class="arrow">→</span>

                                            <div class="cf-input">
                                                <label>Pole w GetResponse:</label>
                                                <select class="gr-custom-field-select"
                                                        name="mappings[<?php echo $form_id; ?>][custom_fields][<?php echo $idx; ?>][gr_field_id]"
                                                        data-form-id="<?php echo $form_id; ?>">
                                                    <option value="">-- Załaduj pola --</option>
                                                    <?php if (!empty($cf['gr_field_id'])): ?>
                                                        <option value="<?php echo esc_attr($cf['gr_field_id']); ?>" selected>
                                                            <?php echo esc_html($cf['gr_field_name'] ?: $cf['gr_field_id']); ?> (<?php echo esc_html($cf['gr_field_id']); ?>)
                                                        </option>
                                                    <?php endif; ?>
                                                </select>
                                                <input type="hidden"
                                                       class="gr-custom-field-name"
                                                       name="mappings[<?php echo $form_id; ?>][custom_fields][<?php echo $idx; ?>][gr_field_name]"
                                                       value="<?php echo esc_attr($cf['gr_field_name'] ?? ''); ?>">
                                            </div>

                                            <button type="button" class="button remove-custom-field" title="Usuń">🗑️</button>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <button type="button" class="button add-custom-field" data-form-id="<?php echo $form_id; ?>">
                                        ➕ Dodaj kolejne pole
                                    </button>
                                </div>
                                
                                <!-- Dostępne pola -->
                                <?php if (!empty($form_fields['all'])): ?>
                                <details class="field-reference">
                                    <summary>📋 Wszystkie pola w formularzu (<?php echo count($form_fields['all']); ?>)</summary>
                                    <ul>
                                        <?php foreach ($form_fields['all'] as $field): ?>
                                            <li>
                                                <code><?php echo esc_html($field['name']); ?></code>
                                                <span class="field-type"><?php echo $field['type']; ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                    <?php endforeach; ?>
                </div>
                
                <div class="cf7-gr-footer">
                    <?php submit_button('💾 Zapisz wszystkie ustawienia', 'primary large', 'cf7_gr_save'); ?>
                </div>
            </form>
        </div>
        
        <!-- Template dla custom fields -->
        <script type="text/template" id="custom-field-template">
            <div class="custom-field-row">
                <div class="cf-input">
                    <label>Pole z CF7:</label>
                    <select name="mappings[FORM_ID][custom_fields][INDEX][cf7_field]">
                        <option value="">-- Wybierz pole --</option>
                        FIELDS_OPTIONS
                    </select>
                </div>
                <span class="arrow">→</span>
                <div class="cf-input">
                    <label>Pole w GetResponse:</label>
                    <select class="gr-custom-field-select"
                            name="mappings[FORM_ID][custom_fields][INDEX][gr_field_id]"
                            data-form-id="FORM_ID">
                        <option value="">-- Załaduj pola --</option>
                        GR_FIELDS_OPTIONS
                    </select>
                    <input type="hidden"
                           class="gr-custom-field-name"
                           name="mappings[FORM_ID][custom_fields][INDEX][gr_field_name]"
                           value="">
                </div>
                <button type="button" class="button remove-custom-field" title="Usuń">🗑️</button>
            </div>
        </script>
        <?php
    }

    /**
     * Parse Contact Form 7 fields
     *
     * @since 3.0.0
     * @param int $form_id CF7 form ID
     * @return array Parsed form fields categorized by type
     */
    private function parse_form_fields($form_id) {
        $contact_form = WPCF7_ContactForm::get_instance($form_id);
        if (!$contact_form) return array();
        
        $form_content = $contact_form->prop('form');
        $manager = WPCF7_FormTagsManager::get_instance();
        $tags = $manager->scan($form_content);
        
        $fields = array(
            'email' => array(),
            'text' => array(),
            'acceptance' => array(),
            'all' => array()
        );
        
        foreach ($tags as $tag) {
            if (empty($tag->name)) continue;
            
            $field = array(
                'type' => $tag->type,
                'name' => $tag->name,
                'label' => $tag->content ?: $tag->name
            );
            
            $fields['all'][] = $field;
            
            if ($tag->basetype === 'email') {
                $fields['email'][] = $field;
            } elseif ($tag->basetype === 'text') {
                $fields['text'][] = $field;
            } elseif (in_array($tag->basetype, array('acceptance', 'checkbox'))) {
                $fields['acceptance'][] = $field;
            }
        }
        
        return $fields;
    }

    /**
     * Skip CF7 mail sending if configured
     *
     * @since 3.1.1
     * @param bool $skip_mail Current skip mail value
     * @param WPCF7_ContactForm $contact_form CF7 contact form object
     * @return bool
     */
    public function maybe_skip_mail($skip_mail, $contact_form) {
        $mappings = get_option($this->option_name, array());
        $form_id = $contact_form->id();

        if (isset($mappings[$form_id]) && $mappings[$form_id]['enabled'] && !empty($mappings[$form_id]['skip_mail'])) {
            return true;
        }

        return $skip_mail;
    }

    /**
     * Handle Contact Form 7 submission
     *
     * Processes form data and sends to GetResponse based on configured settings.
     * Supports three modes: always, checkbox, and dual.
     *
     * @since 3.0.0
     * @param WPCF7_ContactForm $contact_form CF7 contact form object
     * @return void
     */
    public function handle_form_submission($contact_form) {
        $mappings = get_option($this->option_name, array());
        $form_id = $contact_form->id();
        // Sprawdź czy formularz ma aktywne mapowanie
        if (!isset($mappings[$form_id]) || !$mappings[$form_id]['enabled']) {
            return;
        }
        
        $mapping = $mappings[$form_id];
        $submission = WPCF7_Submission::get_instance();
        if (!$submission) return;
        
        $posted_data = $submission->get_posted_data();

        // KROK 1: Sprawdź tryb działania
        $mode = isset($mapping['mode']) ? $mapping['mode'] : 'checkbox';
        $acceptance_checked = false;

        if ($mode === 'checkbox') {
            // Sprawdź czy pole acceptance/trigger jest zaznaczone
            $acceptance_field = isset($mapping['acceptance_field']) ? $mapping['acceptance_field'] : '';
            if (empty($acceptance_field) || empty($posted_data[$acceptance_field])) {
                return;
            }
            $acceptance_checked = true;
        } elseif ($mode === 'dual') {
            // Tryb dual - sprawdź checkbox ale nie przerywaj jeśli nie zaznaczony
            $acceptance_field = isset($mapping['acceptance_field']) ? $mapping['acceptance_field'] : '';

            // CF7 acceptance: "1" gdy zaznaczony, brak klucza lub pusty gdy nie zaznaczony
            $acceptance_checked = false;
            if (!empty($acceptance_field) && isset($posted_data[$acceptance_field])) {
                $val = $posted_data[$acceptance_field];
                if (is_array($val)) {
                    $acceptance_checked = !empty($val[0]);
                } else {
                    $acceptance_checked = !empty($val);
                }
            }
        } else {
            // Tryb 'always' - zawsze wysyłaj
        }
        
        // KROK 2: Pobierz email (wymagane)
        $email = isset($posted_data[$mapping['email_field']]) ? $posted_data[$mapping['email_field']] : '';
        if (empty($email) || !is_email($email)) {
            return;
        }
        
        // KROK 3: Pobierz imię (opcjonalne)
        $name = '';
        if (!empty($mapping['name_field']) && isset($posted_data[$mapping['name_field']])) {
            $name = $posted_data[$mapping['name_field']];
        }
        
        // KROK 4: Przygotuj custom fields
        $custom_fields = array();
        if (!empty($mapping['custom_fields'])) {
            foreach ($mapping['custom_fields'] as $cf) {
                if (empty($cf['cf7_field']) || empty($cf['gr_field_id'])) continue;
                
                if (isset($posted_data[$cf['cf7_field']])) {
                    $value = $posted_data[$cf['cf7_field']];
                    
                    // Jeśli to array (checkbox z wieloma wartościami), przekonwertuj
                    if (is_array($value)) {
                        $value = implode(', ', $value);
                    }
                    
                    $custom_fields[] = array(
                        'customFieldId' => $cf['gr_field_id'],
                        'value' => array($value)
                    );
                    
                }
            }
        }
        
        // KROK 5: Wyślij do GetResponse
        if ($mode === 'dual') {
            // Tryb dual - zawsze zapisz na listę główną
            $result_primary = $this->add_to_getresponse(
                $mapping['api_key'],
                $mapping['campaign_id'],
                $email,
                $name,
                $custom_fields,
                $form_id
            );

            if (!$result_primary) {
                error_log("CF7→GR [Form {$form_id}]: Błąd przy dodawaniu '{$email}' do listy głównej ({$mapping['campaign_id']})");
            }

            // Jeśli checkbox zaznaczony, zapisz też na drugą listę
            if ($acceptance_checked && !empty($mapping['campaign_id_secondary'])) {
                $result_secondary = $this->add_to_getresponse(
                    $mapping['api_key'],
                    $mapping['campaign_id_secondary'],
                    $email,
                    $name,
                    $custom_fields,
                    $form_id
                );

                if (!$result_secondary) {
                    error_log("CF7→GR [Form {$form_id}]: Błąd przy dodawaniu '{$email}' do listy dodatkowej ({$mapping['campaign_id_secondary']})");
                }
            }
        } else {
            // Tryby: always lub checkbox - standardowa wysyłka
            $result = $this->add_to_getresponse(
                $mapping['api_key'],
                $mapping['campaign_id'],
                $email,
                $name,
                $custom_fields,
                $form_id
            );

            if (!$result) {
                error_log("CF7→GR [Form {$form_id}]: Błąd przy dodawaniu '{$email}' ({$mapping['campaign_id']})");
            }
        }
    }

    /**
     * Add contact to GetResponse campaign
     *
     * @since 3.0.0
     * @param string $api_key GetResponse API key
     * @param string $campaign_id GetResponse campaign ID
     * @param string $email Contact email address
     * @param string $name Contact name (optional)
     * @param array  $custom_fields Custom field values (optional)
     * @return bool True on success, false on failure
     */
    private function add_to_getresponse($api_key, $campaign_id, $email, $name = '', $custom_fields = array(), $form_id = 0) {
        $data = array(
            'email' => $email,
            'campaign' => array('campaignId' => $campaign_id)
        );

        if (!empty($name)) {
            $data['name'] = $name;
        }

        if (!empty($custom_fields)) {
            $data['customFieldValues'] = $custom_fields;
        }

        $json_data = json_encode($data);

        $ch = curl_init('https://api.getresponse.com/v3/contacts');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'X-Auth-Token: api-key ' . $api_key,
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        $success = in_array($http_code, array(201, 202, 409));

        // Loguj do wp_options
        $log_entry = array(
            'time'        => current_time('mysql'),
            'form_id'     => $form_id,
            'email'       => $email,
            'name'        => $name,
            'campaign_id' => $campaign_id,
            'request'     => $data,
            'http_code'   => $http_code,
            'response'    => $response !== false ? json_decode($response, true) : null,
            'curl_error'  => $curl_error ?: null,
            'success'     => $success,
        );
        $this->save_log($log_entry);

        if ($response === false) {
            error_log("CF7→GR cURL Error: " . $curl_error);
            return false;
        }

        if (!$success) {
            error_log("CF7→GR API Error [{$http_code}]: " . $response);
        }

        return $success;
    }

    /**
     * Save a log entry
     *
     * @since 3.2.0
     * @param array $entry Log entry data
     * @return void
     */
    private function save_log($entry) {
        $logs = get_option($this->log_option, array());
        array_unshift($logs, $entry);

        if (count($logs) > $this->max_logs) {
            $logs = array_slice($logs, 0, $this->max_logs);
        }

        update_option($this->log_option, $logs, false);
    }

    /**
     * AJAX handler to clear logs
     *
     * @since 3.2.0
     * @return void
     */
    public function ajax_clear_logs() {
        check_ajax_referer('cf7_gr_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error();
            return;
        }

        delete_option($this->log_option);
        wp_send_json_success();
    }

    /**
     * Render logs page
     *
     * @since 3.2.0
     * @return void
     */
    public function logs_page() {
        $logs = get_option($this->log_option, array());
        ?>
        <div class="wrap cf7-gr-wrap">
            <h1>📋 CF7 → GetResponse Logi</h1>
            <p class="description">Historia wysyłek do GetResponse API (ostatnie <?php echo $this->max_logs; ?> wpisów)</p>

            <div style="margin: 20px 0; display: flex; gap: 10px; align-items: center;">
                <button type="button" class="button" id="cf7-gr-clear-logs">🗑️ Wyczyść logi</button>
                <button type="button" class="button" onclick="location.reload()">🔄 Odśwież</button>
                <span style="margin-left: auto; color: #666;">
                    <?php echo count($logs); ?> wpisów
                </span>
            </div>

            <?php if (empty($logs)): ?>
                <div style="padding: 40px; text-align: center; background: #fff; border: 1px solid #ddd; border-radius: 8px;">
                    <p style="font-size: 16px; color: #666;">Brak logów. Logi pojawią się po wysłaniu formularza.</p>
                </div>
            <?php else: ?>
                <table class="widefat striped" style="border-radius: 8px; overflow: hidden;">
                    <thead>
                        <tr>
                            <th style="width: 150px;">Data</th>
                            <th style="width: 60px;">Form</th>
                            <th style="width: 80px;">Status</th>
                            <th>Email</th>
                            <th>Imię</th>
                            <th>Kampania</th>
                            <th>Custom Fields</th>
                            <th>Odpowiedź API</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $status_icon = $log['success'] ? '✅' : '❌';
                            $status_color = $log['success'] ? '#00a32a' : '#d63638';
                            $http_code = isset($log['http_code']) ? $log['http_code'] : '?';

                            $status_label = $http_code;
                            if ($http_code == 201) $status_label = '201 Created';
                            elseif ($http_code == 202) $status_label = '202 Accepted';
                            elseif ($http_code == 409) $status_label = '409 Exists';

                            $cf_summary = '';
                            if (!empty($log['request']['customFieldValues'])) {
                                $parts = array();
                                foreach ($log['request']['customFieldValues'] as $cfv) {
                                    $val = is_array($cfv['value']) ? implode(', ', $cfv['value']) : $cfv['value'];
                                    $parts[] = $cfv['customFieldId'] . ': ' . mb_strimwidth($val, 0, 30, '…');
                                }
                                $cf_summary = implode('; ', $parts);
                            }

                            $response_text = '';
                            if (!empty($log['curl_error'])) {
                                $response_text = 'cURL: ' . $log['curl_error'];
                            } elseif (!$log['success'] && !empty($log['response'])) {
                                $resp = $log['response'];
                                $response_text = isset($resp['message']) ? $resp['message'] : json_encode($resp);
                            }
                            ?>
                            <tr>
                                <td><code style="font-size: 12px;"><?php echo esc_html($log['time']); ?></code></td>
                                <td><strong>#<?php echo esc_html($log['form_id']); ?></strong></td>
                                <td>
                                    <span style="color: <?php echo $status_color; ?>; font-weight: 600;">
                                        <?php echo $status_icon; ?> <?php echo esc_html($status_label); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($log['email']); ?></td>
                                <td><?php echo esc_html($log['name'] ?: '—'); ?></td>
                                <td><code style="font-size: 11px;"><?php echo esc_html($log['campaign_id']); ?></code></td>
                                <td>
                                    <?php if ($cf_summary): ?>
                                        <small title="<?php echo esc_attr($cf_summary); ?>"><?php echo esc_html(mb_strimwidth($cf_summary, 0, 50, '…')); ?></small>
                                    <?php else: ?>
                                        <span style="color: #999;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($response_text): ?>
                                        <small style="color: #d63638;"><?php echo esc_html(mb_strimwidth($response_text, 0, 60, '…')); ?></small>
                                    <?php else: ?>
                                        <span style="color: #999;">OK</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#cf7-gr-clear-logs').on('click', function() {
                if (!confirm('Na pewno wyczyścić wszystkie logi?')) return;
                var btn = $(this);
                btn.prop('disabled', true).text('⏳ Czyszczenie...');
                $.ajax({
                    url: cf7GrAjax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'cf7_gr_clear_logs',
                        nonce: cf7GrAjax.nonce
                    },
                    success: function() {
                        location.reload();
                    },
                    error: function() {
                        btn.prop('disabled', false).text('🗑️ Wyczyść logi');
                        alert('Błąd przy czyszczeniu logów');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler to fetch campaigns from GetResponse
     *
     * @since 3.1.0
     * @return void Sends JSON response
     */
    public function ajax_get_campaigns() {
        check_ajax_referer('cf7_gr_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => esc_html__('Insufficient permissions.', 'cf7-getresponse')
            ));
            return;
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        if (empty($api_key)) {
            wp_send_json_error(array(
                'message' => esc_html__('API Key is required.', 'cf7-getresponse')
            ));
            return;
        }

        $campaigns = $this->get_campaigns_from_api($api_key);

        if ($campaigns === false) {
            wp_send_json_error(array(
                'message' => esc_html__('Error fetching campaigns from GetResponse.', 'cf7-getresponse')
            ));
            return;
        }

        wp_send_json_success(array('campaigns' => $campaigns));
    }

    /**
     * Fetch campaigns from GetResponse API
     *
     * @since 3.1.0
     * @param string $api_key GetResponse API key
     * @return array|false Array of campaigns on success, false on failure
     */
    private function get_campaigns_from_api($api_key) {
        $ch = curl_init('https://api.getresponse.com/v3/campaigns');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'X-Auth-Token: api-key ' . $api_key,
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("CF7→GR API Error (get campaigns): " . $curl_error);
            return false;
        }

        if ($http_code !== 200) {
            error_log("CF7→GR API Error (get campaigns) [{$http_code}]: " . $response);
            return false;
        }

        $campaigns_data = json_decode($response, true);
        if (!is_array($campaigns_data)) {
            return false;
        }

        // Zwróć w formacie: array(array('id' => 'XyZ', 'name' => 'Nazwa listy'))
        $campaigns = array();
        foreach ($campaigns_data as $campaign) {
            $campaigns[] = array(
                'id' => $campaign['campaignId'],
                'name' => $campaign['name']
            );
        }

        return $campaigns;
    }

    /**
     * AJAX handler to fetch custom fields from GetResponse
     *
     * @since 3.2.0
     * @return void Sends JSON response
     */
    public function ajax_get_custom_fields() {
        check_ajax_referer('cf7_gr_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => esc_html__('Insufficient permissions.', 'cf7-getresponse')
            ));
            return;
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        if (empty($api_key)) {
            wp_send_json_error(array(
                'message' => esc_html__('API Key is required.', 'cf7-getresponse')
            ));
            return;
        }

        $custom_fields = $this->get_custom_fields_from_api($api_key);

        if ($custom_fields === false) {
            wp_send_json_error(array(
                'message' => esc_html__('Error fetching custom fields from GetResponse.', 'cf7-getresponse')
            ));
            return;
        }

        wp_send_json_success(array('custom_fields' => $custom_fields));
    }

    /**
     * Fetch custom fields from GetResponse API
     *
     * @since 3.2.0
     * @param string $api_key GetResponse API key
     * @return array|false Array of custom fields on success, false on failure
     */
    private function get_custom_fields_from_api($api_key) {
        $all_fields = array();
        $page = 1;
        $per_page = 100;

        do {
            $url = 'https://api.getresponse.com/v3/custom-fields?page=' . $page . '&perPage=' . $per_page;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'X-Auth-Token: api-key ' . $api_key,
                'Content-Type: application/json'
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                error_log("CF7→GR API Error (get custom fields): " . $curl_error);
                return false;
            }

            if ($http_code !== 200) {
                error_log("CF7→GR API Error (get custom fields) [{$http_code}]: " . $response);
                return false;
            }

            $fields_data = json_decode($response, true);
            if (!is_array($fields_data)) {
                return false;
            }

            foreach ($fields_data as $field) {
                $all_fields[] = array(
                    'id' => $field['customFieldId'],
                    'name' => $field['name'],
                    'type' => isset($field['fieldType']) ? $field['fieldType'] : '',
                    'format' => isset($field['format']) ? $field['format'] : '',
                );
            }

            $page++;
        } while (count($fields_data) === $per_page);

        return $all_fields;
    }
}

new CF7_GetResponse_Integration();