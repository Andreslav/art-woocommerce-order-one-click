<?php
/**
 * Class AWOOC_Ajax
 *
 * @author Artem Abramovich
 * @since  1.8.0
 */
class AWOOC_Ajax {

	/**
	 * Переменная для сверки с настройками
	 *
	 * @since 1.8.0
	 *
	 * @var mixed|void
	 */
	public $elements;


	/**
	 * Constructor.
	 *
	 * @since 1.8.0
	 */
	public function __construct() {

		$this->elements = get_option( 'woocommerce_awooc_select_item' );

		if ( ! $this->elements ) {
			$this->elements = awooc_default_elements_item();
		}

		add_action( 'wp_ajax_nopriv_awooc_ajax_product_form', array( $this, 'ajax_scripts_callback' ) );
		add_action( 'wp_ajax_awooc_ajax_product_form', array( $this, 'ajax_scripts_callback' ) );
	}


	/**
	 * Возвратна функция дл загрузки данных во всплывающем окне
	 *
	 *
	 */
	public function ajax_scripts_callback() {

		if ( ! wp_verify_nonce( $_POST['nonce'], 'awooc-nonce' ) ) {
			wp_die( esc_html__( 'Oops ... Data sent from unknown address', 'art-woocommerce-order-one-click' ) );
		}

		if ( ! isset( $_POST['id'] ) || empty( $_POST['id'] ) ) {
			wp_die(
				esc_html__(
					'Something is wrong with sending data. Unable to get product ID. Disable the output in the popup window or contact the developers of the plugin',
					'art-woocommerce-order-one-click'
				)
			);
		}

		$product = wc_get_product( esc_attr( $_POST['id'] ) );

		$data = array(
			'elements'    => 'full',
			'title'       => $this->product_title( $product ),
			'image'       => $this->product_image( $product ),
			'link'        => esc_url( get_permalink( $this->product_id( $product ) ) ),
			'sku'         => $this->product_sku( $product ),
			'attr'        => $this->product_attr( $product ),
			'price'       => $this->product_price( $product ),
			'pricenumber' => $product->get_price(),
			'qty'         => '<div class="awooc-form-custom-order-qty"></div>',
			'form'        => $this->select_form(),
		);

		// проверяем на включенный режим, если включен режим любой кроме шатного, то удаляем количество
		if ( 'dont_show_add_to_card' === get_option( 'woocommerce_awooc_mode_catalog' ) || 'in_stock_add_to_card' === get_option( 'woocommerce_awooc_mode_catalog' ) ) {
			unset( $data['qty'] );
		}

		if ( ! $product->get_price() ) {
			unset( $data['qty'] );
			unset( $data['price'] );
		}

		if ( empty( $this->elements ) || ! isset( $this->elements ) ) {
			$data['elements'] = 'empty';
		}

		wp_send_json( $data );

		wp_die();
	}


	/**
	 * Получение заголовка товара
	 *
	 * @param WC_Product $product
	 *
	 * @since 1.8.0
	 *
	 * @return bool|string
	 */
	public function product_title( $product ) {

		if ( ! in_array( 'title', $this->elements, true ) ) {
			return false;
		}

		return $product->get_title();
	}


	/**
	 * Вспомогательная функция для проверки типа товара
	 *
	 * @since 1.8.0
	 *
	 * @param WC_Product $product
	 *
	 * @return mixed
	 */
	public function product_id( $product ) {

		if ( 'simple' === $product->get_type() ) {
			$product_id = $product->get_id();
		} else {
			$product_id = $product->get_parent_id();
		}

		return $product_id;
	}


	/**
	 * Получаем изображение товара
	 *
	 * @since 1.8.0
	 *
	 * @param WC_Product $product
	 *
	 * @return bool|mixed|string
	 */
	public function product_image( $product ) {

		if ( ! in_array( 'image', $this->elements, true ) ) {
			return false;
		}

		$image = '';

		$post_thumbnail_id = get_post_thumbnail_id( $product->get_id() );

		if ( ! $post_thumbnail_id ) {
			$post_thumbnail_id = get_post_thumbnail_id( $product->get_parent_id() );
		}

		$full_size_image = wp_get_attachment_image_src( $post_thumbnail_id, apply_filters( 'awooc_thumbnail_name', 'shop_single' ) );

		if ( $full_size_image ) {
			$image = apply_filters(
				'awooc_popup_image_html',
				sprintf(
					'<img src="%s" alt="%s" class="%s" width="%s" height="%s">',
					esc_url( $full_size_image[0] ),
					apply_filters( 'awooc_popup_image_alt', '' ),
					apply_filters( 'awooc_popup_image_classes', esc_attr( 'awooc-form-custom-order-img' ) ),
					esc_attr( $full_size_image[1] ),
					esc_attr( $full_size_image[2] )
				),
				$product
			);
		}

		return $image;
	}


	/**
	 * Получаем артикул товара
	 *
	 * @since 1.8.0
	 *
	 * @param WC_Product $product
	 *
	 * @return bool|mixed
	 */
	public function product_sku( $product ) {

		if ( ! in_array( 'sku', $this->elements, true ) ) {
			return false;
		}

		if ( ! wc_product_sku_enabled() && ( ! $product->get_sku() || ! $product->is_type( 'variable' ) ) ) {
			return false;
		}

		$sku = $product->get_sku() ? $product->get_sku() : 'N/A';

		return wp_kses_post(
			apply_filters(
				'awooc_popup_sku_html',
				sprintf(
					'<span class="awooc-sku-wrapper">%s</span><span class="awooc-sku">%s</span>',
					apply_filters( 'awooc_popup_sku_label', __( 'SKU: ', 'art-woocommerce-order-one-click' ) ),
					$sku
				),
				$product
			)
		);
	}


	/**
	 * Получение атрибутов вариативного товара
	 *
	 * @since 1.8.0
	 *
	 * @param WC_Product $product
	 *
	 * @return bool|string
	 */
	public function product_attr( $product ) {

		if ( ! in_array( 'attr', $this->elements, true ) ) {
			return false;
		}

		if ( $product->is_type( 'simple' ) ) {
			return false;
		}

		$attributes       = $product->get_attributes();
		$product_variable = new WC_Product_Variable( $product->get_parent_id() );
		$variations       = $product_variable->get_variation_attributes();
		$attr_name        = array();

		foreach ( $attributes as $attr => $value ) {

			$attr_label = wc_attribute_label( $attr );
			$meta       = get_post_meta( $product->get_id(), wc_variation_attribute_name( $attr ), true );
			$term       = get_term_by( 'slug', $meta, $attr );

			if ( false !== $term ) {
				$attr_name[] = $attr_label . ': ' . $term->name;
			}
		}

		if ( empty( $attr_name ) && isset( $variations ) ) {
			foreach ( $variations as $key => $item ) {

				$attr_name[] = wc_attribute_label( $key ) . ': ' . implode( array_intersect( $item, $attributes ) );
			}
		}

		$allowed_html = array(
			'br'   => array(),
			'span' => array(),
		);

		$product_var_attr = wp_kses( implode( '; </span><span>', $attr_name ), $allowed_html );

		if ( ! isset( $variations ) ) {
			return false;
		}

		$attr_json = sprintf(
			'%s</br><span class="awooc-attr-wrapper"><span>%s</span></span>',
			apply_filters( 'awooc_popup_attr_label', esc_html__( 'Attributes: ', 'art-woocommerce-order-one-click' ) ),
			$product_var_attr
		);

		return $attr_json;

	}


	/**
	 * Получаем цену товара
	 *
	 * @since 1.8.0
	 *
	 * @param WC_Product $product
	 *
	 * @return bool|mixed
	 */
	public function product_price( $product ) {

		if ( ! in_array( 'price', $this->elements, true ) ) {
			return false;
		}

		return apply_filters(
			'awooc_popup_price_html',
			sprintf(
				'%s<span class="awooc-price-wrapper">%s</span></div>',
				apply_filters( 'awooc_popup_price_label', __( 'Price: ', 'art-woocommerce-order-one-click' ) ),
				wc_price( $product->get_price() )
			),
			$product
		);

	}


	/**
	 * Output form in a popup window
	 *
	 * @since 1.8.1
	 * @return bool|string
	 */
	public function select_form() {

		$select_form = get_option( 'woocommerce_awooc_select_form' );
		if ( ! $select_form ) {
			return false;
		}

		return do_shortcode( '[contact-form-7 id="' . esc_attr( $select_form ) . '"]' );
	}


	/**
	 * Получаем ссылку на товар
	 *
	 * @since 1.8.0
	 *
	 * @param WC_Product $product
	 *
	 * @return string
	 */
	public function product_link( $product ) {

		return sprintf(
			'<span class="awooc-form-custom-order-link awooc-hide">Ссылка на товар: %s</span>',
			esc_url( get_permalink( $this->product_id( $product ) ) )
		);

	}

}
