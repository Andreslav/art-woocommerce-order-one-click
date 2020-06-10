<?php
/**
 * Файл обработки данных на фронте
 *
 * @see     https://wpruse.ru/my-plugins/art-woocommerce-order-one-click/
 * @package art-woocommerce-order-one-click/includes
 * @version 1.8.0
 */

/**
 * Class AWOOC_Front_End
 *
 * @author Artem Abramovich
 * @since  1.8.0
 */
class AWOOC_Front_End {

	/**
	 * Constructor.
	 *
	 * @since 1.8.0
	 */
	public function __construct() {

		/**
		 * Base hooks
		 */
		add_action( 'wp_footer', array( $this, 'popup_window_html' ), 30 );

		/**
		 * WooCommerce hooks
		 */
		add_filter( 'woocommerce_is_purchasable', array( $this, 'disable_add_to_cart_no_price' ), 10, 2 );
		add_filter( 'woocommerce_product_is_in_stock', array( $this, 'disable_add_to_cart_out_stock' ), 10, 2 );
		add_filter( 'woocommerce_hide_invisible_variations', array( $this, 'hide_variable_add_to_cart' ), 10, 3 );
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'add_custom_button' ), 15 );

	}


	/**
	 * Вывод кнопки Заказать в зависимости от настроек
	 *
	 * @since 1.8.0
	 * @since 2.3.6
	 */
	public function add_custom_button() {

		wp_enqueue_script( 'awooc-scripts' );
		wp_enqueue_style( 'awooc-styles' );

		$product = wc_get_product();

		$show_add_to_card = get_option( 'woocommerce_awooc_mode_catalog' );

		$visible_button = $product->get_meta( '_awooc_button', true );

		if ( ! isset( $visible_button ) || 'yes' !== $visible_button ) {
			switch ( $show_add_to_card ) {
				case 'dont_show_add_to_card':
					add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'disable_text_add_to_cart_to_related' ) );
					add_filter( 'woocommerce_product_add_to_cart_url', array( $this, 'disable_url_add_to_cart_to_related' ) );
					add_filter( 'woocommerce_loop_add_to_cart_args', array( $this, 'disable_ajax_add_to_cart_to_related' ), 10, 2 );

					$this->hide_button_add_to_card();
					awooc_html_custom_add_to_cart();
					break;
				case 'no_stock_no_price':
				case 'show_add_to_card':
					awooc_html_custom_add_to_cart();
					break;
				case 'in_stock_add_to_card':
					if ( $product->is_on_backorder() || 0 === $product->get_price() || empty( $product->get_price() ) || ! $product->is_in_stock() ) {
						$this->hide_button_add_to_card();
						awooc_html_custom_add_to_cart();
					}
					break;
			}
		}

	}


	/**
	 * Вывод всплывающего окна
	 *
	 * @since 1.8.0
	 */
	public function popup_window_html() {

		$elements = get_option( 'woocommerce_awooc_select_item' );
		if ( ! is_array( $elements ) ) {
			return;
		}

		include AWOOC_PLUGIN_DIR . '/includes/view/html-popup-window.php';
	}


	/**
	 * Включение кнопки Заказать в если нет цены в простых товарах
	 *
	 * @param  bool       $bool    входящее булево значение.
	 * @param  WC_Product $product объект продукта.
	 *
	 * @return bool
	 *
	 * @since 2.2.0
	 */
	public function disable_add_to_cart_no_price( $bool, $product ) {

		if ( 'variation' === $product->get_type() ) {
			return $bool;
		}

		if ( 'yes' === $product->get_meta( '_awooc_button', true ) ) {
			return $bool;
		}

		$mode_catalog = get_option( 'woocommerce_awooc_mode_catalog' );

		if ( 'dont_show_add_to_card' === $mode_catalog ) {

			$bool = is_product() ? true : false;
		}

		if ( 'no_stock_no_price' === $mode_catalog && false === $bool ) {

			$bool = is_product() ? true : false;

			add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'hide_button_add_to_card' ) );
			add_filter( 'awooc_button_label', array( $this, 'custom_button_label' ) );
		}

		return $bool;
	}


	/**
	 * Включение кнопки Заказать в если нет в наличии в простых товарах
	 *
	 * @param  bool       $status  входящее булево значение.
	 * @param  WC_Product $product объект продукта.
	 *
	 * @return bool
	 *
	 * @since 2.2.0
	 */
	public function disable_add_to_cart_out_stock( $status, $product ) {

		if ( 'variation' === $product->get_type() ) {
			return $status;
		}

		if ( 'yes' === $product->get_meta( '_awooc_button', true ) ) {
			return $status;
		}

		$mode_catalog = get_option( 'woocommerce_awooc_mode_catalog' );

		if ( 'dont_show_add_to_card' === $mode_catalog && false === $status ) {

			$status = is_product() ? true : false;
		}

		if ( 'no_stock_no_price' === $mode_catalog && false === $status ) {
			$status = is_product() ? true : false;
			add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'hide_button_add_to_card' ) );
			add_filter( 'awooc_button_label', array( $this, 'custom_button_label' ) );
		}

		return $status;
	}


	/**
	 * Изменение надписи на кнопке при включении режима реагирования на отсутствие цены и наличия
	 *
	 * @param  string $label входящая строка из опций.
	 *
	 * @return string
	 *
	 * @since 2.2.0
	 */
	public function custom_button_label( $label ) {

		$label = get_option( 'woocommerce_awooc_title_custom' ) ? esc_html( get_option( 'woocommerce_awooc_title_custom' ) ) :
			esc_html( get_option( 'woocommerce_awooc_title_button' ) );

		return $label;
	}


	/**
	 * Включение кнопки Заказать в если нет цены или наличия в вариаиях
	 *
	 * @param  bool                $bool       входящее булево значение.
	 * @param  int                 $product_id ID родительского товара.
	 * @param  WC_Product_Variable $variation  объект вариации.
	 *
	 * @return bool
	 *
	 * @since 2.0.0
	 */
	public function hide_variable_add_to_cart( $bool, $product_id, $variation ) {

		$product = wc_get_product( $product_id );

		$mode = get_option( 'woocommerce_awooc_mode_catalog' );

		if ( 'yes' === $product->get_meta( '_awooc_button', true ) ) {
			return $bool;
		}

		if ( 'no_stock_no_price' === $mode || 'dont_show_add_to_card' === $mode ) {

			if ( ! $product->get_price() ) {
				$bool = false;
				add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'hide_button_add_to_card' ) );
				remove_filter( 'woocommerce_single_variation', 'woocommerce_single_variation', 10 );

				if ( 'no_stock_no_price' === $mode ) {
					add_filter( 'awooc_button_label', array( $this, 'custom_button_label' ) );
				}
			}
		}

		return $bool;

	}


	/**
	 * Скрытие кнопки купить
	 *
	 * @return mixed|void
	 * @since 1.8.3
	 *
	 * @since 1.8.0
	 */
	public function hide_button_add_to_card() {

		ob_start();
		?>
		<style>
			.woocommerce button.btn,
			.woocommerce button.button.alt,
			.woocommerce-page button.button.alt,
			.woocommerce-variation-add-to-cart .quantity,
			.woocommerce-variation-add-to-cart .single_add_to_cart_button,
			.single_add_to_cart_button,
			.quantity {
				display: none !important;
			}

			.blockUI.blockOverlay {
				background: rgba(0, 0, 0, 1) !important;
			}
		</style>
		<?php

		$disable_add_to_card = apply_filters( 'awooc_disable_add_to_card_style', ob_get_clean() );
		echo wp_kses( $disable_add_to_card, array( 'style' => array() ) );
	}


	/**
	 * Показ кнопки В корзину
	 *
	 * @return mixed|void
	 * @since 1.8.0
	 *
	 */
	public function show_button_add_to_card() {

		ob_start();
		?>
		<style>
			.woocommerce-variation-add-to-cart,
			.single_add_to_cart_button,
			.qty {
				display: inline-block !important;
			}

			.blockUI.blockOverlay {
				background: rgba(0, 0, 0, 1) !important;
			}
		</style>
		<?php

		$enable_add_to_card = apply_filters( 'awooc_enable_add_to_card_style', ob_get_clean() );

		echo wp_kses( $enable_add_to_card, array( 'style' => array() ) );
	}


	/**
	 *  Замена урл на кнопках в похожих товарах на страницах товарах
	 *
	 * @param  string $url входящий урл.
	 *
	 * @return string
	 * @since 1.8.0
	 */
	public function disable_url_add_to_cart_to_related( $url ) {

		$product = wc_get_product();
		if ( is_product() ) {
			$url = get_permalink();
		}

		return $url;
	}


	/**
	 * Замена текста на кнопках в похожих товарах на страницах товарах
	 *
	 * @param  string $text входящий текст на кнопке.
	 *
	 * @return string
	 * @since 1.8.0
	 */
	public function disable_text_add_to_cart_to_related( $text ) {

		if ( is_product() ) {
			$text = __( 'Read more', 'woocommerce' );
		}

		return $text;
	}


	/**
	 * Удаление класса вызова ajax в режиме каталога для похожих товаров
	 *
	 * @param  array      $args    массив аргументов.
	 * @param  WC_Product $product объект продукта.
	 *
	 * @return mixed
	 * @since 2.2.5
	 */
	public function disable_ajax_add_to_cart_to_related( $args, $product ) {

		if ( 'simple' === $product->get_type() && is_product() ) {
			$search = 'ajax_add_to_cart';
			$pos    = strrpos( $args['class'], $search );

			if ( false !== $pos ) {
				$args['class'] = substr_replace( $args['class'], '', $pos, strlen( $search ) );
			}
		}

		return $args;
	}
}
