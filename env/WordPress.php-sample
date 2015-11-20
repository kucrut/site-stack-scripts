<?php

/**
 * .env file creator for {@link https://wordpress.org/ WordPress}
 *
 * @author Dzikri Aziz <kvcrvt@gmail.com>
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2
 */

namespace WP_Stack;

class Env extends \kucrut\env\Creator {

	protected static $env_vars = array(
		'WP_ENV' => array(
			'type'          => 'askAndValidate',
			'args'          => array(
				'question'  => '* Environment',
				'validator' => array( __CLASS__, 'validate' ),
				'attempts'  => false,
				'default'   => 'development',
			),
		),
		'DOMAIN_CURRENT_SITE' => array(
			'type'       => 'askAndValidate',
			'args'       => array(
				'question'  => '* Main site domain name',
				'validator' => array( __CLASS__, 'validate' ),
				'attempts'  => false,
			),
			'default_cb' => array( __CLASS__, 'get_dir_name' ),
		),
		'_HTTPS' => array(
			'type'     => 'askConfirmation',
			'args'     => array(
				'question'  => '* Use HTTPS?',
				'default'   => false,
			),
			'after_cb' => array( __CLASS__, 'set_urls' ),
		),
		'DOMAIN_NAMES' => array(
			'type'            => 'askAndValidate',
			'args'            => array(
				'question'  => '* Additional domain names (for multisite, separate with spaces)',
				'validator' => array( __CLASS__, 'validate' ),
				'attempts'  => false,
			),
			'default_cb'      => array( __CLASS__, 'get_env_value' ),
			'default_cb_args' => 'DOMAIN_CURRENT_SITE',
			'after_cb'        => array( __CLASS__, 'set_domain_names' ),
		),
		'DB_NAME' => array(
			'type'            => 'askAndValidate',
			'args'            => array(
				'question'  => '* Database Name',
				'validator' => array( __CLASS__, 'validate' ),
				'attempts'  => false,
			),
			'default_cb'      => array( __CLASS__, 'get_default_db_name' ),
		),
		'DB_HOST' => array(
			'type'            => 'askAndValidate',
			'args'            => array(
				'question'  => '* Database Host',
				'validator' => array( __CLASS__, 'validate' ),
				'attempts'  => false,
				'default'   => 'localhost',
			),
		),
		'DB_USER' => array(
			'type'          => 'askAndValidate',
			'args'          => array(
				'question'  => '* Database User',
				'validator' => array( __CLASS__, 'validate' ),
				'attempts'  => false,
				'default'   => 'wp',
			),
		),
		'DB_PASSWORD' => array(
			'type'          => 'ask',
			'args'          => array(
				'question'  => '* Database Password',
				'default'   => 'wp',
			),
		),
		'TABLE_PREFIX' => array(
			'type'          => 'askAndValidate',
			'args'          => array(
				'question'  => '* Database Table Prefix',
				'validator' => array( __CLASS__, 'validate' ),
				'attempts'  => false,
				'default'   => 'wp_',
			),
		),
		'DISABLED_PLUGINS' => array(
			'type'          => 'ask',
			'args'          => array(
				'question'  => '* Disabled plugins
    You can list plugins you want to disable for this environment here.
    For example: w3-total-cache/w3-total-cache.php
    Separate plugins with commas.
  ',
			),
		),
	);


	protected static $salt_keys = array(
		'AUTH_KEY',
		'SECURE_AUTH_KEY',
		'LOGGED_IN_KEY',
		'NONCE_KEY',
		'AUTH_SALT',
		'SECURE_AUTH_SALT',
		'LOGGED_IN_SALT',
		'NONCE_SALT',
	);

	protected static $salt_length = 24;


	public static function get_default_db_name() {
		return parent::strip_non_alpha_numerics( static::$env_vars['DOMAIN_CURRENT_SITE'] );
	}


	public static function set_domain_names() {
		$domains   = explode( ' ', static::$env_vars['DOMAIN_NAMES'] );
		$domains   = array_map( 'trim', $domains );
		$domains[] = static::$env_vars['DOMAIN_CURRENT_SITE'];

		static::$env_vars['DOMAIN_NAMES'] = implode( ' ', array_unique( $domains ) );
	}


	public static function set_urls() {
		$protocol                       = static::$env_vars['_HTTPS'] ? 'https://' : 'http://';
		static::$env_vars['WP_HOME']    = $protocol . static::$env_vars['DOMAIN_CURRENT_SITE'];
		static::$env_vars['WP_SITEURL'] = static::$env_vars['WP_HOME'] . '/wp';

		unset( static::$env_vars['_HTTPS'] );
	}


	public static function validate_url( $value ) {
		if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			throw new \RunTimeException( 'Invalid URL' );
		}

		return $value;
	}
}
