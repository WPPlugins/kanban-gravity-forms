<?php
/*
Contributors:		gelform
Plugin Name:		Kanban + Gravity Forms
Plugin URI:			https://kanbanwp.com/addons/gravityforms/
Description:		Use Gravity Forms forms to interact with your Kanban boards.
Requires at least:	4.0
Tested up to:		4.8.1
Version:			0.0.4
Release Date:		June 9, 2017
Author:				Gelform Inc
Author URI:			http://gelwp.com
License:			GPLv2 or later
License URI:		http://www.gnu.org/licenses/gpl-2.0.html
Text Domain:		kanban
Domain Path: 		/languages/
*/



// Kanban + Gravity Forms is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 2 of the License, or
// any later version.
//
// Kanban + Gravity Forms is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Kanban Shortcodes. If not, see {URI to Plugin License}.



// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



class Kanban_Gravity_Forms {
	static $slug = '';
	static $friendlyname = '';
	static $plugin_basename = '';
	static $plugin_data;

	static $task_fields = array(
		'title'            => 'Title',
		'user_id_author'   => 'Task author',
		'user_id_assigned' => 'Assigned to user',
		'status_id'        => 'Status',
		'estimate_id'      => 'Estimate',
		'project_id'       => 'Project'
	);


//	static $options = array(
//		'gravityforms' => array()
//	);



	static function init() {
		self::$slug = basename( __FILE__, '.php' );
		self::$plugin_basename = plugin_basename( __FILE__ );
		self::$friendlyname = trim( str_replace( array( 'Kanban', '_' ), ' ', __CLASS__ ) );



		if ( !function_exists( 'get_plugin_data' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		self::$plugin_data = get_plugin_data( __FILE__ );


		$is_core = self::check_for_core();
		if ( ! $is_core ) {
			return false;
		}

		add_action(
			sprintf(
				'%s_updates',
				self::$slug
			),
			array( __CLASS__, 'do_updates' )
		);

		self::check_for_updates();



//		add_filter(
//			'kanban_option_get_defaults_return',
//			array(__CLASS__, 'add_options_defaults')
//		);


		add_action( 'init', array( __CLASS__, 'save_settings' ) );

		// add tab to settings page
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 10 );

		add_action( 'gform_post_submission', array( __CLASS__, 'on_post_submission' ), 10, 2 );



		add_filter( 'gform_pre_render', array( __CLASS__, 'populate_form_selects' ) );
		add_filter( 'gform_pre_validation', array( __CLASS__, 'populate_form_selects' ) );
		add_filter( 'gform_pre_submission_filter', array( __CLASS__, 'populate_form_selects' ) );
		add_filter( 'gform_admin_pre_render', array( __CLASS__, 'populate_form_selects' ) );
	}



	static function admin_menu() {
		add_submenu_page(
			Kanban::get_instance()->settings->basename,
			'Kanban Gravity Forms',
			'Gravity Forms',
			'manage_options',
			'kanban_gravityforms',
			array( __CLASS__, 'add_admin_page' )
		);
	}



	static function add_admin_page() {

		$forms = array();

		if ( class_exists( 'GFAPI' ) ) {
			$forms = GFAPI::get_forms();
		}

		$boards = Kanban_Board::get_all();

		foreach ( $boards as $board_id => &$board ) {
			$board->projects  = Kanban_Project::get_all( $board_id );
			$board->statuses  = Kanban_Status::get_all( $board_id );
			$board->estimates = Kanban_Estimate::get_all( $board_id );
			$board->users     = Kanban_User::get_allowed_users( $board_id );
		}



		$table_columns = self::$task_fields;


		// Previously saved data.
		$saved = array();
		if ( ! empty( $forms ) ) {
			foreach ( $forms as $form ) {

				$saved[ $form['id'] ] = Kanban_Option::get_option( sprintf(
						'%s-%d',
						self::$slug,
						$form['id']
					)
				);
			}
		}

		include plugin_dir_path( __FILE__ ) . 'templates/admin-page.php';
	}



	static function save_settings() {

		if ( ! is_admin() || $_SERVER[ 'REQUEST_METHOD' ] != 'POST' || ! isset( $_POST[ self::$slug . '-nonce' ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST[ self::$slug . '-nonce' ], self::$slug ) ) {
			return;
		}


		foreach ( $_POST[ 'forms' ] as $form_id => $form ) {

			$form_data = array();

			foreach ( $form as $field_id => $field_data ) {
				if ( $field_id == 'board' ) {
					$form_data['board'] = intval( $field_data );
					continue;
				}

				if ( !isset($field_data['table_column']) || !isset(self::$task_fields[$field_data['table_column']]) ) continue;

				$form_data[$field_id] = $field_data;
			}

			Kanban_Option::update_option(
				sprintf(
					'%s-%d',
					self::$slug,
					$form_id
				),
				$form_data
			);
		}



		wp_redirect(
			add_query_arg(
				array(
					'message' => __( 'Saved!', 'kanban' )
				),
				sanitize_text_field( wp_unslash( $_POST[ '_wp_http_referer' ] ) )
			)
		);
		exit;
	}



	static function on_post_submission( $entry, $form ) {


		$form_id = $entry[ 'form_id' ];

		$saved = Kanban_Option::get_option(
			sprintf(
				'%s-%d',
				self::$slug,
				$form_id
			)
		);

		if ( empty($saved) ) {
			return false;
		}

		$table_columns = Kanban_Task::table_columns();
		$task_data     = array_fill_keys( array_keys( $table_columns ), '' );


		$board_id = $saved[ 'board' ];

		$task_data[ 'created_dt_gmt' ]   = Kanban_Utils::mysql_now_gmt();
		$task_data[ 'modified_dt_gmt' ]  = Kanban_Utils::mysql_now_gmt();
		$task_data[ 'modified_user_id' ] = 0; // get_current_user_id();
		$task_data[ 'user_id_author' ]   = get_current_user_id();
		$task_data[ 'is_active' ]        = 1;
		$task_data[ 'board_id' ]         = $board_id;



		foreach ( $saved as $field_id => $task_field ) {

			// get the board id and move on
			if ( $field_id == 'board' ) {
				continue;
			}

			$task_data[ $task_field[ 'table_column' ] ] = $entry[ $field_id ];
		}

		//Set to the first status if empty.
		if ( empty( $task_data[ 'status_id' ] ) ) {
			$statuses = Kanban_Status::get_all( $board_id );

			$status = reset( $statuses );

			$task_data[ 'status_id' ] = $status->id;
		}

		Kanban_Task::replace( $task_data );
	}



	/**
	 *
	 * @link https://www.gravityhelp.com/documentation/article/dynamically-populating-drop-down-fields/
	 *
	 * @param $form
	 *
	 * @return object
	 */
	static function populate_form_selects( $form ) {


		$form_id = $form['id'];

		$saved = Kanban_Option::get_option( sprintf(
			'%s-%d',
			self::$slug,
			$form_id
		) );


		if ( empty($saved) ) {
			return $form;
		}

		$board_id = $saved[ 'board' ];

		$estimates = array();
		$statuses  = array();
		$users     = array();

		foreach ( $saved as $field_id => $task_field ) {

			if ( $field_id == 'board' ) {
				continue;
			}

			if ( ! isset( $task_field[ 'defaultValue' ] ) ) {
				$task_field[ 'defaultValue' ] = null;
			}

			switch ( $task_field[ 'table_column' ] ) {
				case 'estimate_id':

					if ( empty( $estimates ) ) {
						$estimates = Kanban_Estimate::get_all( $board_id );
					}

					foreach ( $form[ 'fields' ] as &$field ) {
						if ( $field->id != $field_id ) {
							continue;
						}

						switch ( $field->type ) {
							case 'hidden':
								$field->defaultValue = $task_field[ 'defaultValue' ];

								break;

							case 'select':

								$choices = array();
								foreach ( $estimates as $estimate ) {
									$choices[] = array( 'text' => $estimate->title, 'value' => $estimate->id );
								}

								$field->choices = $choices;

								break;

						}
					}

					break;

				case 'status_id':

					if ( empty( $statuses ) ) {
						$statuses = Kanban_Status::get_all( $board_id );
					}

					foreach ( $form[ 'fields' ] as &$field ) {
						if ( $field->id != $field_id ) {
							continue;
						}

						switch ( $field->type ) {
							case 'hidden':
								$field->defaultValue = $task_field[ 'defaultValue' ];

								break;

							case 'select':

								$choices = array();
								foreach ( $statuses as $status ) {
									$choices[] = array( 'text' => $status->title, 'value' => $status->id );
								}

								$field->choices = $choices;

								break;

						}
					}

					break;

				case 'project_id':

					if ( empty( $projects ) ) {
						$projects = Kanban_Project::get_all( $board_id );
					}

					foreach ( $form[ 'fields' ] as &$field ) {
						if ( $field->id != $field_id ) {
							continue;
						}

						switch ( $field->type ) {
							case 'hidden':
								$field->defaultValue = $task_field[ 'defaultValue' ];

								break;

							case 'select':

								$choices = array();
								foreach ( $projects as $project ) {
									$choices[] = array( 'text' => $project->title, 'value' => $project->id );
								}

								$field->choices = $choices;

								break;

						}
					}

					break;

				case 'user_id_author':
				case 'user_id_assigned':

					if ( empty( $users ) ) {
						$users = Kanban_User::get_allowed_users( $board_id );
					}

					foreach ( $form[ 'fields' ] as &$field ) {
						if ( $field->id != $field_id ) {
							continue;
						}

						switch ( $field->type ) {
							case 'hidden':
								$field->defaultValue = $task_field[ 'defaultValue' ];

								break;

							case 'select':

								$choices = array();
								foreach ( $users as $user ) {
									$choices[] = array( 'text' => $user->long_name_email, 'value' => $user->ID );
								}

								$field->choices = $choices;

								break;

						}
					}

					break;
			}
		}

		return $form;
	}



	/**
	 * Functions to do on single blog activation, like remove db option.
	 */
//	static function on_deactivation() {
//	}



//	static function add_options_defaults( $defaults ) {
//		return array_merge( $defaults, self::$options );
//	}



	static function do_updates () {
		$saved = Kanban_Option::get_option(self::$slug);

		if ( is_array($saved) && !empty($saved) ) {

			foreach ( $saved as $form_id => $form_data ) {

				if ( !isset($form_data['board']) || empty($form_data['board'] ) ) continue;

				foreach ( $form_data as $field_id => $field_data ) {
					if ( $field_id == 'board' ) continue;

					if ( !isset($field_data['table_column']) || !isset(self::$task_fields[$field_data['table_column']]) ) {
						unset($form_data[$field_id]);
					}
				}

				Kanban_Option::update_option(
					sprintf(
						'%s-%d',
						self::$slug,
						$form_id
					),
					$form_data
				);
			}

			if ( method_exists('Kanban_Option', 'delete_option') ) {
				Kanban_Option::delete_option( self::$slug );
			}
		}
	}



	/**
	 * Triggered on plugins_loaded priority 30
	 * @link http://mac-blog.org.ua/wordpress-custom-database-table-example-full/
	 */
	static function check_for_updates() {

		$prev_version = get_option( __CLASS__ . '_version' );

		// See if we're out of sync.
		if ( version_compare( self::$plugin_data[ 'Version' ], $prev_version ) === 0 ) {
			return false;
		}

		do_action(
			sprintf(
				'%s_updates',
				self::$slug
			)
		);

		// Save version to avoid updates.
		update_option( __CLASS__ . '_version', self::$plugin_data[ 'Version' ], true );
	}




	static function check_for_core() {
		if ( class_exists( 'Kanban' ) ) {
			return TRUE;
		}

		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}

		if ( is_plugin_active_for_network( self::$plugin_basename ) ) {
			add_action( 'network_admin_notices',  array( __CLASS__, 'admin_deactivate_notice' ) );
		}
		else {
			add_action( 'admin_notices', array( __CLASS__, 'admin_deactivate_notice' ) );
		}



		deactivate_plugins( self::$plugin_basename );

		return FALSE;
	}



	static function admin_deactivate_notice() {
		if ( !is_admin() ) {
			return;
		}
		?>
		<div class="error below-h2">
			<p>
				<?php
				echo sprintf(
					__('Whoops! This plugin %s requires the <a href="https://wordpress.org/plugins/kanban/" target="_blank">Kanban for WordPress</a> plugin.
	            		Please make sure it\'s installed and activated.'
					),
					self::$friendlyname
				);
				?>
			</p>
		</div>
		<?php
	}

}



function Kanban_Gravity_Forms() {
	Kanban_Gravity_Forms::init();
}



add_action( 'plugins_loaded', 'Kanban_Gravity_Forms', 20, 0 );