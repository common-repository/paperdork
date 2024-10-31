<?php

/**
 * 
 * Metabox Class
 * 
 *  
 * @author Roefja | www.roefja.com
 * @copyright 2021
 * 
 * 
 * 
 */

define('PAPERDORK_METABOX_VERSION', '1.0');

class PaperdorkMetaBox {

    protected $title = 'Roefja Metabox';
    protected $id = 'roefja_metabox';
    protected $post_type = 'page';
    protected $position = 'normal';
    protected $priority = 'low';
    protected $meta_key = 'roefja_metabox';
    protected $loaded_from = '';

    public function __construct($id, $title = '', $post_type = 'page', $position = 'normal', $priority = 'low') {
        if (is_admin()) {
            if ($title != '') $this->title = $title;

            $this->id = $id;

            if ($post_type != '') $this->post_type = $post_type;
            if ($position != '') $this->position = $position;
            if ($priority != '') $this->priority = $priority;

            add_action('load-post.php',     array($this, 'init_metabox'));
            add_action('load-post-new.php', array($this, 'init_metabox'));

            $this->set_loaded_from();
        }
    }

    public function init_metabox() {
        add_action('add_meta_boxes', array($this, 'add_metabox'));
        add_action('save_post',      array($this, 'save_metabox'), 10, 2);
    }

    public function add_metabox() {
        add_meta_box(
            $this->id,
            __($this->title, 'astra-by-roefja'),
            array($this, 'render_metabox'),
            $this->post_type,
            $this->position,
            $this->priority
        );
    }

    public function render_metabox($post) {
        // Add nonce for security and authentication.
        wp_nonce_field('roefja_metabox_nonce_action', 'roefja_metabox_nonce');
        //wp_enqueue_style('thickbox'); // call to media files in wp
        //wp_enqueue_script('thickbox');
        //wp_enqueue_script('media-upload');
        //wp_enqueue_script('roefja-metabox-js', $this->get_dist_url('admin/js/metabox.js'), array('jquery', 'media-upload', 'thickbox'), PAPERDORK_METABOX_VERSION);
    }

    protected function set_loaded_from() {
        if (strpos(__FILE__, 'wp-content/plugins/') !== false) $this->loaded_from = 'plugin';
        else if (strpos(__FILE__, 'wp-content/themes/') !== false) $this->loaded_from = 'theme';
    }

    private function get_dist_url($file) {
        if ($this->loaded_from == 'theme') return get_stylesheet_directory_uri() . '/dist/' . $file;
        else return plugin_dir_url(__DIR__) . 'dist/' . $file;
    }

    public function save_metabox($post_id, $post) {
        // Add nonce for security and authentication.
        $nonce_name   = sanitize_text_field(isset($_POST['roefja_metabox_nonce']) ? $_POST['roefja_metabox_nonce'] : '');
        $nonce_action = 'roefja_metabox_nonce_action';

        if (!wp_verify_nonce($nonce_name, $nonce_action)) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (wp_is_post_autosave($post_id)) return;
        if (wp_is_post_revision($post_id)) return;
    }

    public function getWPEditor($name = '', $current = '', $settings = []) {
        if ($name == '') return;
        if (!array_key_exists('textarea_name', $settings)) $settings['textarea_name'] = $name;
?>
        <div class="text-center">
            <?php wp_editor($current, $name . "_editor", $settings); ?>
        </div>
<?php
    }

    public function get_post_meta($post, $key, $single = true) {
        return get_post_meta($post->ID, $this->meta_key . '_' . $key, $single);
    }

    public function update_post_meta($post_id, $key, $value = '') {
        update_post_meta($post_id, $this->meta_key . '_' . $key, $value);
    }

    public function get_input_field($name, $key, $value = '', $placeholder = '', $type = 'text') {
        $field = new PaperdorkMetaboxField($name, $key, $value, $placeholder, $type);
        return $field->get_input_field();
    }

    public function get_input_field_full($name, $key, $value = '', $placeholder = '', $type = 'text', $min = '', $max = '') {
        $field = new PaperdorkMetaboxField($name, $key, $value, $placeholder, $type);
        if ($min != '' || $max != '') $field->set_min_max($min, $max);
        return $field->get_input_field_full();
    }

    public function get_input_group($name, $key,  $value = '', $placeholder = '',  $type = 'text', $class = 'setting') {
        $field = new PaperdorkMetaboxField($name, $key, $value, $placeholder, $type);
        $field->set_class($class);
        return $field->get_input_group();
    }

    public function get_id() {
        return $this->id;
    }
}

class PaperdorkMetaboxField {
    protected $name = '';
    protected $key = '';
    protected $value = '';
    protected $placeholder = '';
    protected $type = 'text';
    protected $class = '';
    protected $min = null;
    protected $max = null;
    protected $label_new_line = false;
    protected $options = [];
    protected $required = false;
    protected $accept = '';

    public function __construct($name, $key, $value = '', $placeholder = '', $type = 'text', $label_new_line = false) {

        $this->name = $name;
        $this->key = $key;
        $this->set_value($value);
        $this->placeholder = $placeholder;

        if ($type == 'image') {
            $this->accept = 'image/*';
            $type = 'file';
        }

        $this->set_type($type);
        $this->set_label_new_line($label_new_line);
    }

    public function get_key() {
        return $this->key;
    }

    public function set_min_max($min = null, $max = null) {
        $this->min = $min;
        $this->max = $max;
    }

    public function set_class($class) {
        $this->class = $class;
    }

    public function set_value($value) {
        $this->value = $value;
    }

    public function set_type($type) {
        if ($type != '') $this->type = $type;
    }

    public function set_label_new_line($label_new_line) {
        $this->label_new_line = $label_new_line;
    }

    public function set_required($required) {
        $this->required = $required;
    }

    public function get_required() {
        return $this->required;
    }

    public function get_input_field($widefat = false) {
        return '<p><label style="min-width:100%;display:inline-block"><b>' . esc_html($this->name) . ':</b></label> <input class="' . ($widefat ? 'widefat ' : '') . esc_attr($this->class) . '" ' . ($this->min !== null ? 'min=' . esc_attr($this->min) : '') . ' ' . ($this->max !== null ? 'max=' . esc_attr($this->max) : '') . ' name="' . esc_attr($this->key) . '" type="' . esc_attr($this->type) . '" placeholder="' . esc_attr($this->placeholder) . '" value="' . esc_attr($this->value) . '"></p>';
    }

    public function get_input_field_full() {
        return $this->get_input_field(true);
    }

    protected function get_required_label() {
        $html = '';

        if ($this->get_required()) return '<span class="required-label">*</span>';

        return $html;
    }

    public function get_input_group() {

        $label = '<label><b>' . $this->name . $this->get_required_label() . '</b></label>';
        if ($this->label_new_line) $label .= '<br>';

        $input_group = $label . $this->get_input_group_field();

        return '<div id="' . esc_attr($this->key) . '_group" class="input-group ' . ($this->get_required() ? 'required-group' : '') . '">' . esc_html($input_group) . '</div>';
    }

    protected function get_input_group_field() {

        $class = $this->class;

        if ($this->label_new_line) $class .= ' widefat';

        $input_group = '';
        if ($this->type == 'checkbox') {
            $input_group = '<input ' . ($this->get_required() ? 'required' : '') . ' type="' . esc_attr($this->type) . '" ' . ($this->key != '' ? 'name="' . esc_attr($this->key) . '" ' : '') . ' class="' . esc_attr($class) . '" value="1" ' . ($this->value == 1 ? 'checked' : '') . '>';
        } else if ($this->type == 'selector') {

            $input_group = '<option value="">' . esc_html__('Nothing selected') . '</option>';

            asort($this->options);
            foreach ($this->options as $option) {
                $input_group .= '<option ' . ($option == $this->value ? 'selected' : '') . ' value="' . esc_attr($option) . '">' . esc_html($option) . '</option>';
            }

            $input_group = '<select ' . ($this->get_required() ? 'required' : '') . ' ' . ($this->key != '' ? 'name="' . esc_attr($this->key) . '" ' : '') . ' class="' . esc_attr($class) . '">' . esc_html($input_group) . '</select>';
        } else if ($this->type == 'radio' || $this->type == 'checkboxes') {

            $or_type = $this->type;

            if ($this->type == 'radio') $type = 'radio';
            else $type = 'checkbox';

            foreach ($this->options as $o_key => $o_label) {
                $input_group .= '
                    <div class="' . $type . '-group">
                        <input ' . ($this->get_required() && $type != 'checkbox' ? 'required' : '') . ' id="' . esc_attr($this->key) . '_' . esc_attr($o_key) . '" type="' . esc_attr($type) . '" ' . ($this->key != '' ? 'name="' . esc_attr($this->key) . ($or_type == 'checkboxes' ? '[]' : '') . '" ' : '') . ' ' . ($this->value == $o_key ? 'checked' : '') . ' class="' . esc_attr($class) . '" value="' . esc_attr($o_key) . '">
                        <label>' . $o_label . '</label>
                    </div>
                    ';
            }
        } else if ($this->type == 'textarea') {
            $input_group = '<textarea ' . ($this->get_required() ? 'required' : '') . ' ' . ($this->key != '' ? 'name="' . esc_attr($this->key) . '" ' : '') . ' class="' . esc_attr($class) . '" >' . esc_textarea($this->value) . '</textarea>';
        } else {
            $input_group = '<input ' . ($this->get_required() ? 'required' : '') . ' type="' . esc_attr($this->type) . '" ' . ($this->key != '' ? 'name="' . esc_attr($this->key) . '" ' : '') . ' ' . ($this->accept != '' ? 'accept="' . esc_attr($this->accept) . '" ' : '') . ' class="' . esc_attr($class) . '" value="' . esc_attr($this->value) . '">';
        }

        return $input_group;
    }

    public function set_selector_options($options = []) {
        $this->set_type('selector');
        $this->set_options($options);
    }

    public function set_options($options = []) {
        $this->options = $options;
    }
}

?>