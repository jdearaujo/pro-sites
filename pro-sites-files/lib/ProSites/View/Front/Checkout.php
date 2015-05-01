<?php

if ( ! class_exists( 'ProSites_View_Front_Checkout' ) ) {
	class ProSites_View_Front_Checkout {

		public static $new_signup = false;
		public static $default_period = 'price_1';
		public static $selected_level = 0;

		public static function render_checkout_page( $content, $blog_id, $domain = false, $selected_period = 'price_1', $selected_level = false ) {
			global $psts, $current_prosite_blog;

			$session_data = ProSites_Helper_Session::session( 'new_blog_details' );
			// If its in session, get it
			if( isset( $session_data['new_blog_details'] ) && isset( $session_data['new_blog_details']['level'] ) ) {
				$selected_period = 'price_' . ( (int) $session_data['new_blog_details']['period'] );
				$selected_level = (int) $session_data['new_blog_details']['level'];
			}

			// User is not logged in and this is not a new registration.
			// Get them to sign up! (or login)
			if( empty( $blog_id ) && ! isset( $session_data['new_blog_details'] ) && empty( $current_prosite_blog ) ) {
				self::$new_signup = true;
			}
			// Get blog_id from the session...
			if( isset( $session_data['new_blog_details'] ) && isset( $session_data['new_blog_details']['blog_id'] ) ) {
				$blog_id = $session_data['new_blog_details']['blog_id'];
			}
			// Or if we're at checkout and already have a blog (1 blog only!)
			$blog_id = empty( $blog_id ) && ! empty( $current_prosite_blog ) ? $current_prosite_blog : $blog_id;

			self::$default_period = apply_filters( 'prosites_render_checkout_page_period', $selected_period, $blog_id );
			self::$selected_level = apply_filters( 'prosites_render_checkout_page_level', $selected_level, $blog_id );

			// Are the tables enabled?
			$plans_table_enabled = $psts->get_setting('plans_table_enabled');
			$plans_table_enabled = 'enabled' === $plans_table_enabled ? true : false;
			$features_table_enabled = $psts->get_setting( 'comparison_table_enabled' );
			$features_table_enabled = 'enabled' === $features_table_enabled ? true : false;

			// $columns = self::get_pricing_columns( $plans_table_enabled, $features_table_enabled );
			$columns = self::get_pricing_columns( true, $features_table_enabled );

			$content .= self::render_tables_wrapper( 'pre' );
			if( $plans_table_enabled ) {
				$content .= self::render_pricing_columns( $columns, $blog_id );
			} else {
				$content .= self::render_pricing_grid( $columns, $blog_id );
			}
			$content .= self::render_tables_wrapper( 'post' );

			if( self::$new_signup && ! is_user_logged_in() ) {
				$content .= self::render_login();
			}
//			$expire = $psts->get_expire( $blog_id );

			// Signup registration
			$content .= ProSites_View_Front_Registration::render_signup_form();

			// Hook for the gateways
//			$content = apply_filters( 'psts_checkout_output', $content, $blog_id, $domain );
			$content .= ProSites_View_Front_Gateway::render_checkout( array(), $blog_id, $domain );

			return apply_filters( 'prosites_render_checkout_page', $content, $blog_id, $domain );

			// Reset
			self::$new_signup = false;
		}

		private static function render_pricing_columns( $columns, $blog_id = false, $echo = false ) {
			global $psts;

			$content = '';
			$total_columns = count( $columns );
			$total_width = 100.0;
			$total_width -= 6.0; // account for extra space around featured plan
			$column_width = $total_width / $total_columns;
			$feature_width = $column_width + 6.0;
			$normal_style = 'width: ' . $column_width . '%; ';
			$feature_style = 'width: ' . $feature_width . '%; ';

			$column_keys = array_keys( $columns[0] );
			$show_pricing_table = in_array( 'title', $column_keys );
			$show_feature_table = in_array( 'sub_title', $column_keys );
			$show_buy_buttons = in_array( 'button', $column_keys );
			$add_coupon = in_array( 'coupon', $column_keys );
//			$show_buy_buttons = false;

			foreach( $columns as $key => $column ) {
				$style = true === $column['featured'] ? $feature_style : $normal_style;
				$col_class = true === $column['featured'] ? ' featured' : '';
				$level_id = isset( $column['level_id'] ) ? $column['level_id'] : 0;

				// Has a chosen plan been given? Note: Period should already be set.
				if( ! empty( self::$selected_level ) && 0 != $level_id ) {
					$col_class = $level_id == (int) self::$selected_level ? $col_class . ' chosen-plan' : $col_class;
				}

				$content .= '<ul class="pricing-column psts-level-' . esc_attr( $level_id ) . ' ' . esc_attr( $col_class ) . '" style="' . esc_attr( $style ) . '">';

				if( $show_pricing_table ) {
					if( empty( $column['title'] ) ) {
						$content .= '<li class="title no-title"></li>';
					} else {
						$content .= '<li class="title">' . ProSites::filter_html( $column['title'] ) . '</li>';
					}

					$content .= '<li class="summary">' . ProSites::filter_html( $column['summary'] ) . '</li>';
				}

				if( $show_feature_table ) {
					$features_class = $show_pricing_table ? '' : 'no-header';
					if( empty( $column['sub_title'] ) ) {
						$content .= '<li class="sub-title no-title ' . $features_class . '"></li>';
					} else {
						$content .= '<li class="sub-title ' . $features_class . '">' . ProSites::filter_html( $column['sub_title'] ) . '</li>';
					}

					$content .= '<li><ul class="feature-section">';

					foreach( $column['features'] as $index => $feature ) {
						$alt = isset( $feature['alt'] ) && true == $feature['alt'] ? 'alternate' : '';

						$content .= '<li class="feature feature-' . $index . ' ' . $alt . '">';

						if( isset( $feature['name'] ) && ! empty( $feature['name'] ) ) {
							$content .= '<div class="feature-name">' . ProSites::filter_html( $feature['name'] ) . '</div>';
						}
						if( isset( $feature['indicator'] ) && ! empty( $feature['indicator'] ) ) {
							$content .= '<div class="feature-indicator">' . ProSites::filter_html( $feature['indicator'] ) . '</div>';
						}
						if( isset( $feature['text'] ) && ! empty( $feature['text'] ) ) {
							$content .= '<div class="feature-text">' . ProSites::filter_html( $feature['text'] ) . '</div>';
						}

						$content .= '</li>';
					}

					$content .= '</ul></li>';

				}

				if( $show_buy_buttons ) {
					if( empty( $column['button'] ) ) {
						if( $add_coupon ) {
							$content .= '<li class="coupon">';
							$content .= '<div class="coupon-box">';
							$content .= '<input type="text" name="apply-coupon" placeholder="' . __( 'Enter coupon', 'psts' ) . '" />';
							$content .= '<a name="apply-coupon-link" class="apply-coupon-link">' . $column['coupon'] . '</a>';
							$content .= '</div>';
							$content .= '</li>';
						} else {
							$content .= '<li class="button-box no-button"></li>';
						}
					} else {
						$content .= '<li class="button-box">' . $column['button'] . '</li>';
					}
				}

				$content .= '</ul>';

			}

			$allow_free = $psts->get_setting('free_signup');
			if( $allow_free ) {
				$style = 'margin-left: ' . $column_width . '%; ';
				$style .= 'width: ' . ( 100 - $column_width ) . '%; ';
				$content .= self::render_free( $style, $blog_id );
			}

			if( $echo ) {
				echo $content;
			}

			return $content;
		}

		public static function get_pricing_columns( $show_header, $show_features, $show_buy_button = true ) {
			global $psts;

			$columns = array();

			$level_list = get_site_option( 'psts_levels' );
			$total_plans = count( $level_list );

			$default_order = array();
			for( $i = 1; $i <= $total_plans; $i++ ) {
				$default_order[] = $i;
			}
			$default_order = implode( ',', $default_order );
			$pricing_levels_order = $psts->get_setting( 'pricing_levels_order', $default_order );
			$pricing_levels_order = explode( ',', $pricing_levels_order );

			/**
			 * @todo Add a setting to disable
			 */
			$featured_level = $psts->get_setting( 'featured_level' );

			// Initialize all columns
			for( $i = 0; $i <= $total_plans; $i++ ) {
				$columns[] = array();
			}

			$col_count = 0;
			if( $show_header ) {
				$columns[ $col_count ]['title'] = '';
				$columns[ $col_count ]['summary'] = self::get_header_details();
				$columns[ $col_count ]['featured'] = false;
				$col_count += 1;

				foreach( $pricing_levels_order as $level ) {
					$columns[ $col_count ] = self::get_header_details( $level );
					$columns[ $col_count ]['level_id'] = $level;
					$col_count += 1;
				}
			}

			if( $show_features ) {

				// Set first row
				$col_count = 0;
				$row_count = 0;
				$columns[ $col_count ]['alt'] = $row_count %2 != 0;
				$columns[ $col_count ]['sub_title'] = __( 'Compare Features', 'psts' );
				$columns[ $col_count ]['features'] = array();
				$col_count += 1;
				foreach( $pricing_levels_order as $level ) {
					$columns[ $col_count ]['alt'] = $row_count %2 != 0;
					$columns[ $col_count ]['sub_title'] = '';
					$columns[ $col_count ]['features'] = array();
					$col_count += 1;
				}
				$row_count += 1;

				$feature_table = ProSites_Model_Pricing::load_feature_settings();
				$feature_order = $feature_table['feature_order'];
				$feature_order = explode( ',', $feature_order );
				$feature_order = array_filter( $feature_order );
				$enabled_modules = $psts->get_setting( 'modules_enabled', array() );

				foreach( $feature_order as $index => $feature_key ) {

					if( empty( $feature_table[ $feature_key ]['visible'] ) ) {
						continue;
					}

					if( isset( $feature_table[ $feature_key ]['module'] ) && ! in_array( $feature_table[ $feature_key ]['module'], $enabled_modules ) )  {
						continue;
					}

					$col_count = 0;
					$columns[ $col_count ]['features'][ $index ]['name'] = $feature_table[ $feature_key ]['description'];
					$columns[ $col_count ]['features'][ $index ]['alt'] = $row_count %2 != 0;
					$col_count += 1;

					foreach( $pricing_levels_order as $level ) {
						$columns[ $col_count ]['features'][ $index ]['indicator'] = self::get_feature_indicator( $feature_table[ $feature_key ], $level );
						$columns[ $col_count ]['features'][ $index ]['text'] = $feature_table[ $feature_key ]['levels'][ $level ]['text'];
						$columns[ $col_count ]['features'][ $index ]['alt'] = $row_count %2 != 0;
						$col_count += 1;
					}

					$row_count += 1;
				}

			}

			if( $show_buy_button ) {

				$col_count = 0;
				if( $show_header ) {
					$columns[ $col_count ]['button'] = '';
					$col_count += 1;

					foreach( $pricing_levels_order as $level ) {
						if( ! self::$new_signup ) {
							$columns[ $col_count ]['button'] = '<button class="choose-plan-button">' . __( 'Choose Plan', 'psts' ) . '</button>';
						} else {
							$columns[ $col_count ]['button'] = '<button class="choose-plan-button register-new">' . __( 'Sign Up', 'psts' ) . '</button>';
//							$args = array( 'level' => $level, 'period' => '1' );
//							$class = 'price_1' == self::$default_period ? '' : 'hide';
//							$buttons = '<button data-link="' . add_query_arg( $args, site_url('wp-signup.php') )  . '" class="choose-plan-button register-new price_1 '. $class .'">' . __( 'Sign Up', 'psts' ) . '</button>';
//							$args = array( 'level' => $level, 'period' => '3' );
//							$class = 'price_3' == self::$default_period ? '' : 'hide';
//							$buttons .= '<button data-link="' . add_query_arg( $args, site_url('wp-signup.php') )  . '" class="choose-plan-button register-new price_3 '. $class .'">' . __( 'Sign Up', 'psts' ) . '</button>';
//							$args = array( 'level' => $level, 'period' => '12' );
//							$class = 'price_12' == self::$default_period ? '' : 'hide';
//							$buttons .= '<button data-link="' . add_query_arg( $args, site_url('wp-signup.php') )  . '" class="choose-plan-button register-new price_12 '. $class .'">' . __( 'Sign Up', 'psts' ) . '</button>';
//							$columns[ $col_count ]['button'] = $buttons;
						}

						$col_count += 1;
					}
				}

			}

			$coupons_enabled = $psts->get_setting('coupons_enabled');
			$coupons_enabled = 'enabled' === $coupons_enabled ? true : false;

			if( $coupons_enabled ) {
				$col_count = 0;
				$columns[ $col_count ]['coupon'] = __( 'Apply coupon', 'psts' );
			}


			return $columns;
		}

		private static function get_header_details( $level = false ) {
			global $psts;

			$recurring = $psts->get_setting( 'recurring_subscriptions', 1 );

			$periods = array(
				'price_1' => __('every month', 'psts' ),
				'price_3' => __('every 3 months', 'psts' ),
				'price_12' => __('every 12 months', 'psts' ),
			);

			$periods_non_recurring = array(
				'price_1' => __('for 1 month', 'psts' ),
				'price_3' => __('for 3 months', 'psts' ),
				'price_12' => __('for 12 months', 'psts' ),
			);

			$payment_type = array(
				'price_1' => __('Monthly', 'psts' ),
				'price_3' => __('Quarterly', 'psts' ),
				'price_12' => __('Annually', 'psts' ),
			);

			$plan_text = array(
				'payment_type' => __( 'Payment period', 'psts' ),
				'setup' => __( 'Plus a One Time %s Setup Fee', 'psts' ),
				'summary' => __( 'That\'s equivalent to <strong>only %s Monthly</strong>, ', 'psts' ),
				'saving' => __( 'saving you <strong>%s</strong> by paying for %d months in advanced.', 'psts' ),
				'monthly' => __( 'Take advantage of <strong>extra savings</strong> by paying in advance.', 'psts' ),
				'monthly_alt' => __( '<em>Try it out!</em><br /><span>You can easily upgrade to a better value plan at any time.</span>', 'psts' ),
			);

			if( empty( $level ) ) {

				$content = '<div class="period-selector"><div class="heading">' . esc_html( $plan_text['payment_type'] ) . '</div>
					<select class="chosen">
					<option value="price_1" ' . selected( self::$default_period, 'price_1', false ) . '>' . esc_html( $payment_type['price_1'] ) . '</option>
					<option value="price_3" ' . selected( self::$default_period, 'price_3', false ) . '>' . esc_html( $payment_type['price_3'] ) . '</option>
					<option value="price_12" ' . selected( self::$default_period, 'price_12', false ) . '>' . esc_html( $payment_type['price_12'] ) . '</option>
				</select></div>';

				return $content;
			} else {
				global $psts;

				$content = '';

				if( 'enabled' == $psts->get_setting('psts_checkout_show_featured') ){
					$featured_level = $psts->get_setting( 'featured_level' );
				} else {
					$featured_level = -1;
				}

				$level_list = get_site_option( 'psts_levels' );
				$setup_fee_amount = $psts->get_setting( 'setup_fee', 0 );

				$level_details = array();

				$level_details['title'] = $level_list[ $level ]['name'];

				// Is this the featured level?
				if( $featured_level == $level ) {
					$level_details['featured'] = true;
				} else {
					$level_details['featured'] = false;
				}

				if( ! empty( $setup_fee_amount ) ) {
					$setup_fee = ProSites_Helper_UI::rich_currency_format( $setup_fee_amount );
				}
				$setup_msg = '';
				if( ! empty( $setup_fee_amount ) ) {
					$setup_msg = '<div class="setup-fee">' . sprintf( $plan_text['setup'], $setup_fee ) . '</div>';
				}

				$level_details['breakdown'] = array();
				$level_details['savings_msg'] = array();
				foreach( $periods as $period_key => $period ) {

					switch( $period_key ) {
						case 'price_1':
							$months = 1;
							break;
						case 'price_3':
							$months = 3;
							break;
						case 'price_12':
							$months = 12;
							break;
					}

					$display_style = self::$default_period != $period_key ? ' hide' : '';

					if( ! $recurring ) {
						$period = $periods_non_recurring[ $period_key ];
					}

					// Get level price and format it
					$price = ProSites_Helper_UI::rich_currency_format( $level_list[ $level ][ $period_key ] );
					$period_content = '<div class="price ' . esc_attr( $period_key ) . esc_attr( $display_style ) . '">';
					$period_content .= '<div class="plan-price original-amount">' . $price . '</div>';
					$period_content .= '<div class="period original-period">' . esc_html( $period ) . '</div>';
					$period_content .= ! empty( $setup_msg ) ? $setup_msg : '';
					$period_content .= '</div>';
					$level_details['breakdown'][ $period_key ] = str_replace( 'hide', '', $period_content );
					$content .= $period_content;

					$monthly_price = $level_list[ $level ]['price_1'];

					$monthly_calculated = $level_list[ $level ][ $period_key ] / $months * 1.0;
					$difference = ( $monthly_price - $monthly_calculated ) * $months;

					$formatted_calculated = '<div class="monthly-price original-amount">' . ProSites_Helper_UI::rich_currency_format( $monthly_calculated ) . '</div>';
					$formatted_savings = '<div class="savings-price original-amount">' . ProSites_Helper_UI::rich_currency_format( $difference ) . '</div>';

					$summary_msg = $plan_text['monthly'];

					if( $months > 1 ) {
						$summary_msg = sprintf( $plan_text['summary'], $formatted_calculated );
						if( $difference > 0.0 ) {
							$summary_msg .= sprintf( $plan_text['saving'], $formatted_savings, $months );
						}
						$level_details['savings_msg'][ $period_key ] = '<div class="level-summary ' . esc_attr( $period_key ) . '">' . $summary_msg . '</div>';
					} else {
						$level_details['savings_msg'][ $period_key ] = '<div class="level-summary ' . esc_attr( $period_key ) . '">' . $plan_text['monthly_alt'] . '</div>';
					}

					$content .= '<div class="level-summary ' . esc_attr( $period_key ) . esc_attr( $display_style ) . '">' . $summary_msg . '</div>';
				}

				$level_details['summary'] = $content;

				return $level_details;
			}


		}

		private static function get_feature_indicator( $feature, $level ) {

			$status = isset( $feature['levels'][ $level ]['status'] ) ? $feature['levels'][ $level ]['status'] : 'none';
			$easy_status = is_array( $status );
			$status = is_array( $status ) ? $status['display'] : $status;

			$active_status = isset( $feature['active'] ) ? $feature['active'] : '';

			$status_array = array(
				'tick' => '&#x2713',
				'cross' => '&#x2718',
			);

			// Across levels
			if( ! empty( $active_status ) ) {

				if( 'module' == $active_status ) {
					$module    = $feature['module'];
					$is_active = true;
					if ( method_exists( $module, 'is_active' ) ) {
						$is_active = call_user_func( $module . '::is_active' );
					}

					if( $is_active ) {
						$status = 'tick';
					} else {
						$status = 'cross';
					}

				} else {
					$status = 'none';
				}
			}


			if( $easy_status ) {
				// Status is given
				return '<span class="text">' .$status . '</span>';
			} else {

				// Calculate status
				switch( $status ) {
					case 'module':
						$module = $feature['module'];
						if( method_exists( $module, 'get_level_status' ) ) {
							$status = call_user_func( $module . '::get_level_status', $level );
						} else {
							$status = 'none';
						}
						break;
					case 'inverse':
						$module = $feature['module'];
						if( method_exists( $module, 'get_level_status' ) ) {
							$status = call_user_func( $module . '::get_level_status', $level );
							$status = 'cross' == $status ? 'tick' : 'cross';
						} else {
							$status = 'none';
						}
						break;
				}

				switch( $status ) {
					case 'tick':
					case 'cross':
						return '<span class="icon-' . $status . '"></span>';
						break;
					case 'none':
						return '';
						break;
				}

				return '';
			}

		}

		public static function render_tables_wrapper ( $section,  $echo = false ) {
			$content = '';
			$period = str_replace( 'price_', '', self::$default_period );
			$level = self::$selected_level;
			switch( $section ) {

				case 'pre':
					$content .= '<div id="prosites-checkout-table" data-period="' . $period . '" data-level="' . $level . '">';
					break;

				case 'post':
					$content .= '</div>';
					break;

			}

			if( $echo ) {
				echo $content;
			}

			return $content;
		}

		public static function render_login() {
			$content = sprintf( '<div class="login-existing">
					%s <a class="login-toggle" href="%s" title="%s">%s</a>
					<!-- Login Form -->
					%s
				</div>',
				esc_html__( 'Already have a site?', 'psts' ), // Catchphrase
				esc_url( wp_login_url( get_permalink() ) ), // Login URL
				esc_attr__( 'Login', 'psts' ), // Anchor Title
				esc_html__( 'Login now.', 'psts' ), // Anchor Text
				wp_login_form( array( 'echo' => false ) ) // Login Form
			);
			return $content;
		}

		public static function render_free( $style, $blog_id ) {
			global $psts;

			$session_data = ProSites_Helper_Session::session( 'new_blog_details' );

			$free_text = $psts->get_setting('free_msg');
			$content = '';
			if ( ! isset( $_GET['bid'] ) && empty( $blog_id ) && ! isset( $session_data['new_blog_details']['blogname'] ) ) {
				$content = '<div class="free-plan-link" style="' . esc_attr( $style ) . '"><a>' . esc_html( $free_text ) . '</a></div>';
			} else {
				if( empty( $blog_id ) && ! empty( $_GET['bid'] ) ) {
					$blog_id = (int) $_GET['bid'];
				}
				if( ! is_pro_site( $blog_id ) ) {
					$free_link = '<a class="pblg-checkout-opt" style="width:100%" id="psts-free-option" href="' . get_admin_url( (int) $_GET['bid'], 'index.php?psts_dismiss=1', 'http' ) . '" title="' . __( 'Dismiss', 'psts' ) . '">' . $psts->get_setting( 'free_msg', __( 'No thank you, I will continue with a basic site for now', 'psts' ) ) . '</a>';
					$content = '<div class="free-plan-link-logged-in" style="' . esc_attr( $style ) . '"><p>' . esc_html__( 'Your current site is a basic site with no extra features. Upgrade now by selecting a plan above.', 'psts' ) . '</p><p>' . $free_link . '</p></div>';
				}
			}
			return $content;
		}

		private static function render_pricing_grid( $columns, $blog_id = false, $echo = false ) {
			global $psts;

			$levels    = (array) get_site_option( 'psts_levels' );
			$periods = (array) $psts->get_setting( 'enabled_periods' );
			$recurring = $psts->get_setting( 'recurring_subscriptions', 1 );

			//remove levels that are hidden
			foreach ( $levels as $level_id => $level ) {
				$is_visible = isset( $level['is_visible'] ) ? (bool) $level['is_visible'] : true;
				if ( $is_visible ) {
					continue;
				}
				unset( $columns[ $level_id ] );
			}

			$sel_level = self::$selected_level;
			$sel_period = self::$default_period;

			if ( count( $periods ) >= 3 ) {
				$width      = '23%';
				$free_width = '95%';
			} else if ( count( $periods ) == 2 ) {
				$width      = '30%';
				$free_width = '92.5%';
			} else {
				$width      = '40%';
				$free_width = '85%';
			}

			$content = '';

			// TODO: Add coupon filter, apply_filters( 'psts_before_checkout_grid', $content, $blog_id );

			$content = apply_filters( 'psts_before_checkout_grid', $content, $blog_id );
			$content .= '<table id="psts_checkout_grid" width="100%">';

			if ( $recurring ) {
				$content .= '<tr class="psts_level_head">
					<th>' . __( 'Level', 'psts' ) . '</th>';
				if ( in_array( 1, $periods ) ) {
					$content .= '<th>' . __( 'Monthly', 'psts' ) . '</th>';
				}
				if ( in_array( 3, $periods ) ) {
					$content .= '<th>' . __( 'Every 3 Months', 'psts' ) . '</th>';
				}
				if ( in_array( 12, $periods ) ) {
					$content .= '<th>' . __( 'Every 12 Months', 'psts' ) . '</th>';
				}
				$content .= '</tr>';
			} else {
				$content .= '<tr class="psts_level_head">
					<th>' . __( 'Level', 'psts' ) . '</th>';
				if ( in_array( 1, $periods ) ) {
					$content .= '<th>' . __( '1 Month', 'psts' ) . '</th>';
				}
				if ( in_array( 3, $periods ) ) {
					$content .= '<th>' . __( '3 Months', 'psts' ) . '</th>';
				}
				if ( in_array( 12, $periods ) ) {
					$content .= '<th>' . __( '12 Months', 'psts' ) . '</th>';
				}
				$content .= '</tr>';
			}

			foreach ( $columns as $level_id => $column ) {
				if ( 0 == $level_id ) {
					continue;
				}
				$content .= '<tr class="psts_level level-' . $level_id . '">
				<td valign="middle" class="level-name">';
				$content .= apply_filters( 'psts_checkout_grid_levelname', '<h3>' . $column['title'] . '</h3>', $level, $blog_id );
				$content .= '</td>';

				if ( in_array( 1, $periods ) ) {
					$content .= '<td class="level-option" style="width: ' . $width . '"><div class="pblg-checkout-opt">';
					$content .= $columns[$level_id]['breakdown']['price_1'];
					$content .= $columns[$level_id]['savings_msg']['price_1'];
					$content .= '</div></td>';
				}

				if ( in_array( 3, $periods ) ) {
					$content .= '<td class="level-option" style="width: ' . $width . '"><div class="pblg-checkout-opt">';
					$content .= $columns[$level_id]['breakdown']['price_3'];
					$content .= $columns[$level_id]['savings_msg']['price_3'];
					$content .= '</div></td>';
				}

				if ( in_array( 12, $periods ) ) {
					$content .= '<td class="level-option" style="width: ' . $width . '"><div class="pblg-checkout-opt">';
					$content .= $columns[$level_id]['breakdown']['price_12'];
					$content .= $columns[$level_id]['savings_msg']['price_12'];
					$content .= '</div></td>';
				}

				$content .= '</tr>';
			}

			$column_keys = array_keys( $columns[0] );
			$add_coupon = in_array( 'coupon', $column_keys );
			if( $add_coupon ) {
				$content .= '<tr>';
				$content .= '<td colspan="' . (count( $periods )+1) . '">';
				$content .= '<div class="pricing-column grid-checkout"><div class="coupon">';
				$content .= '<div class="coupon-box">';
				$content .= '<input type="text" name="apply-coupon" placeholder="' . __( 'Enter coupon', 'psts' ) . '" />';
				$content .= '<a name="apply-coupon-link" class="apply-coupon-link">' . esc_html__( 'Apply Coupon', 'psts' ) . '</a>';
				$content .= '</div></div></div>';
				$content .= '</td>';
				$content .= '</tr>';
			}

			$content .= '</table>';

			$allow_free = $psts->get_setting('free_signup');
			$content = apply_filters( 'psts_checkout_grid_before_free', $content, $blog_id, $periods, $free_width );
			if( $allow_free ) {
				$style = 'width: ' . $free_width . '; ';
				$content .= self::render_free( $style, $blog_id );
			}
			$content = apply_filters( 'psts_checkout_grid_after_free', $content, $blog_id, $periods, $free_width );

			return $content;
		}

	}
}