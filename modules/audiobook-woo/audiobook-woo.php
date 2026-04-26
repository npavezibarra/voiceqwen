<?php
/**
 * Module: Audiobook WooCommerce Integration
 * Description: Connects Audiobooks to WooCommerce products.
 */

namespace VoiceQwen\AudiobookWoo;

if ( ! defined( 'ABSPATH' ) ) exit;

class AudiobookWoo {

    public static function init() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        add_action( 'add_meta_boxes', [ __CLASS__, 'add_metabox' ] );
        add_action( 'save_post_product', [ __CLASS__, 'save_product_meta' ] );
        
        // AJAX for variation creation
        add_action( 'wp_ajax_vq_woo_create_variations', [ __CLASS__, 'ajax_create_variations' ] );
        
        // AJAX for Store: Add to cart
        add_action( 'wp_ajax_vq_woo_add_to_cart', [ __CLASS__, 'ajax_add_to_cart' ] );
        add_action( 'wp_ajax_nopriv_vq_woo_add_to_cart', [ __CLASS__, 'ajax_add_to_cart' ] );
    }

    public static function ajax_add_to_cart() {
        check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
        
        $product_id = intval( $_POST['product_id'] );
        if ( ! $product_id ) wp_send_json_error( 'Invalid product' );

        if ( ! class_exists( 'WooCommerce' ) ) wp_send_json_error( 'WooCommerce not active' );

        $result = WC()->cart->add_to_cart( $product_id );
        
        if ( $result ) {
            wp_send_json_success([
                'checkout_url' => wc_get_checkout_url()
            ]);
        }
        
        wp_send_json_error( 'Could not add to cart' );
    }

    public static function add_metabox() {
        add_meta_box(
            'vq_audiobook_selection',
            'LOCUTOR: Audiobook Integration',
            [ __CLASS__, 'render_metabox' ],
            'product',
            'side',
            'default'
        );
    }

    public static function render_metabox( $post ) {
        $selected_audiobook = get_post_meta( $post->ID, '_vq_linked_audiobook', true );
        $audiobooks = get_posts([
            'post_type' => 'audiobook',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);

        wp_nonce_field( 'vq_woo_nonce', 'vq_woo_nonce' );
        ?>
        <div class="vq-woo-metabox">
            <p>
                <label for="vq_linked_audiobook">Select Audiobook:</label><br>
                <select name="vq_linked_audiobook" id="vq_linked_audiobook" style="width:100%;">
                    <option value="">-- None --</option>
                    <?php foreach ( $audiobooks as $book ) : ?>
                        <option value="<?php echo $book->ID; ?>" <?php selected( $selected_audiobook, $book->ID ); ?>>
                            <?php echo esc_html( $book->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            
            <?php if ( $selected_audiobook ) : ?>
                <hr>
                <p>
                    <button type="button" class="button button-secondary" id="vq-create-woo-variations">
                        Setup Variations
                    </button>
                </p>
                <p class="description">This will convert the product to Variable and add "Físico" and "Audiobook" variations.</p>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#vq-create-woo-variations').on('click', function() {
                if (!confirm('This will convert the product to a Variable Product. Continue?')) return;
                
                const btn = $(this);
                btn.prop('disabled', true).text('Creating...');

                $.post(ajaxurl, {
                    action: 'vq_woo_create_variations',
                    nonce: '<?php echo wp_create_nonce("vq_woo_variation_nonce"); ?>',
                    product_id: <?php echo $post->ID; ?>,
                    audiobook_id: $('#vq_linked_audiobook').val()
                }, function(res) {
                    if (res.success) {
                        alert('Variations created! Reloading...');
                        location.reload();
                    } else {
                        alert('Error: ' + res.data);
                        btn.prop('disabled', false).text('Setup Variations');
                    }
                });
            });
        });
        </script>
        <?php
    }

    public static function save_product_meta( $post_id ) {
        if ( ! isset( $_POST['vq_woo_nonce'] ) || ! wp_verify_nonce( $_POST['vq_woo_nonce'], 'vq_woo_nonce' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( isset( $_POST['vq_linked_audiobook'] ) ) {
            update_post_meta( $post_id, '_vq_linked_audiobook', sanitize_text_field( $_POST['vq_linked_audiobook'] ) );
        }
    }

    public static function ajax_create_variations() {
        check_ajax_referer( 'vq_woo_variation_nonce', 'nonce' );
        
        $product_id = intval( $_POST['product_id'] );
        $audiobook_id = intval( $_POST['audiobook_id'] );

        if ( ! $product_id || ! $audiobook_id ) {
            wp_send_json_error( 'Invalid ID' );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( 'Product not found' );
        }

        // 1. Convert to Variable if needed
        if ( ! $product->is_type( 'variable' ) ) {
            wp_set_object_terms( $product_id, 'variable', 'product_type' );
            $product = new \WC_Product_Variable( $product_id );
        }

        // 2. Set Attributes
        $attribute_name = 'Formato';
        $options = [ 'Libro Físico', 'Audiobook', 'Físico + Audiobook' ];

        $attributes = $product->get_attributes();
        $attr_found = false;
        
        foreach ( $attributes as &$attr ) {
            if ( $attr->get_name() === $attribute_name ) {
                $attr->set_options( $options );
                $attr_found = true;
                break;
            }
        }

        if ( ! $attr_found ) {
            $attribute = new \WC_Product_Attribute();
            $attribute->set_id( 0 );
            $attribute->set_name( $attribute_name );
            $attribute->set_options( $options );
            $attribute->set_position( 0 );
            $attribute->set_visible( true );
            $attribute->set_variation( true );
            $attributes[] = $attribute;
        }
        
        $product->set_attributes( $attributes );
        $product->save();

        // 3. Create Variations
        $data_store = $product->get_data_store();
        
        foreach ( $options as $option ) {
            $attribute_key = 'attribute_' . sanitize_title( $attribute_name );
            // Check if variation already exists
            $existing_variation_id = $data_store->find_matching_product_variation( $product, [ $attribute_key => $option ] );
            
            if ( ! $existing_variation_id ) {
                $variation = new \WC_Product_Variation();
                $variation->set_parent_id( $product_id );
                $variation->set_attributes( [ $attribute_key => $option ] );
                $variation->set_status( 'publish' );
                $variation->set_manage_stock( false );
                $variation->set_price( $product->get_price() ?: 10 );
                $variation->set_regular_price( $product->get_regular_price() ?: 10 );
                
                if ( $option === 'Audiobook' ) {
                    $variation->set_virtual( true );
                    $variation->set_downloadable( true );
                    $variation->update_meta_data( '_vq_linked_audiobook', $audiobook_id );
                } elseif ( $option === 'Físico + Audiobook' ) {
                    $variation->set_virtual( false ); // It's physical too
                    $variation->set_downloadable( true ); // But includes digital download
                    $variation->update_meta_data( '_vq_linked_audiobook', $audiobook_id );
                }
                
                $variation->save();
            }
        }

        wp_send_json_success();
    }
}

add_action( 'plugins_loaded', [ 'VoiceQwen\AudiobookWoo\AudiobookWoo', 'init' ] );
