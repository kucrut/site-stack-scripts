<?php

/**
 * .env creator
 *
 * Based on {@link https://github.com/roots/bedrock Bedrock's installer} script.
 *
 * This script intended to be used to create '.env' file for sites that use
 * Composer and {@link https://github.com/vlucas/phpdotenv PHPDotEnv}.
 *
 * @see readme.md FIXME
 *
 * @author Dzikri Aziz <dzikri.aziz@gmail.com>
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2
 */

namespace kucrut\env;

use Composer\Script\Event;

abstract class Creator {

	/**
	 * Holds stack's base directory path
	 *
	 * @var string
	 * @access protected
	 */
	protected static $base_dir = '';

	/**
	 * Holds default salt length
	 *
	 * @var int
	 * @access protected
	 */
	protected static $salt_length = 64;

	/**
	 * Holds environment variables to be created
	 *
	 * @var array
	 * @access protected
	 */
	protected static $env_vars = array();

	/**
	 * Holds salt-related keys
	 *
	 * @var array
	 * @access protected
	 */
	protected static $salt_keys = array();

	/**
	 * Holds allowed question types
	 *
	 * @var array
	 * @access private
	 */
	private static $_question_types = array(
		'ask',
		'askConfirmation',
		'askAndValidate',
		'askAndHideAnswer',
	 );

	/**
	 * Holds current env var properties in loop
	 *
	 * @var array
	 * @access protected
	 */
	private static $_current_props;


	/**
	 * Create .env file
	 *
	 * @access public
	 * @param Event $event Composer event object
	 */
	public static function create( Event $event ) {
		self::$base_dir   = dirname( dirname( dirname( __DIR__ ) ) );
		$default_filename = '.env';
		$filename         = getenv( 'ENV_FILE' );
		$io               = $event->getIO();

		if ( ! $io->isInteractive() ) {
			if ( empty( $filename ) ) {
				$filename = $default_filename;
			}

			foreach ( static::$env_vars as $key => $props ) {
				if ( empty( $props['section_mark'] ) ) {
					static::$env_vars[ $key ] = self::get_default( $props, true );
				} else {
					unset( static::$env_vars[ $key ] );
				}

				if ( isset( $props['after_cb'] ) && is_callable( $props['after_cb'] ) ) {
					call_user_func( $props['after_cb'] );
				}
			}
		} else {
			if ( empty( $filename ) ) {
				$filename = $io->askAndValidate(
					sprintf( 'Filename to write environment variables to [<comment>%s</comment>]: ', $default_filename ),
					function( $string, $x = 0 ) {
						if ( ! preg_match( '#^[\w\._-]+$#i', $string ) ) {
							throw new \RunTimeException( 'The filename can only contains alphanumerics, dots, and underscores' );
						}

						return $string;
					},
					null,
					$default_filename
				);
			}

			if ( 0 === strpos( $filename, '/' ) ) {
				$env_file = $filename;
			} else {
				$env_file = sprintf( '%s/%s', self::$base_dir, $filename );
			}

			if ( file_exists( $env_file ) ) {
				$replace_old = $io->askConfirmation(
					sprintf( '<info>%s</info> already exists. Do you want to override it? [<comment>y,N</comment>] ', $filename ),
					false
				);

				if ( false === $replace_old ) {
					self::create( $event );
					exit( 0 );
				}
			}

			$io->write( sprintf( '<info>Generating <comment>"%s"</comment> file</info>', $filename ) );
			$to_remove = array();
			foreach ( static::$env_vars as $key => $props ) {
				self::$_current_props = $props;
				if ( ! in_array( $props['type'], self::$_question_types ) ) {
					throw new \RunTimeException( "Question type `{$props['type']}` is not recognized." );
				}

				$default = self::get_default( $props );
				if ( ! empty( $props['requires'] )
					&& static::$env_vars[ $props['requires']['key'] ] !== $props['requires']['value']
				 ) {
					$value = $default;
				}

				if ( ! isset( $value ) ) {
					if ( 'askConfirmation' === $props['type'] ) {
						$comment_default = true === $default ? 'Y,n' : 'y,N';
					} else {
						$comment_default = $default;
						$props['args']['attempts'] = 3;
					}

					$props['args']['question'] = sprintf(
						'%s [<comment>%s</comment>]: ',
						$props['args']['question'],
						$comment_default
					);
					$props['args']['default']  = $default;
					$value = call_user_func_array( array( $io, $props['type'] ), $props['args'] );
				}

				static::$env_vars[ $key ] = $value;
				if ( isset( $props['after_cb'] ) && is_callable( $props['after_cb'] ) ) {
					call_user_func( $props['after_cb'] );
				}
				if ( ! empty( $props['section_mark'] ) ) {
					$to_remove[] = $key;
				}
				unset( $value );
			}

			foreach ( $to_remove as $key ) {
				unset( static::$env_vars[ $key ] );
			}
		}

		foreach ( (array) static::$salt_keys as $key ) {
			static::$env_vars[ $key ] = self::generate_salt( static::$salt_length );
		}

		$env_vars = array();
		foreach ( static::$env_vars as $key => $value ) {
			$env_vars[] = sprintf( "%s='%s'", $key, $value );
		}
		$env_vars = implode( "\n", $env_vars ) . "\n";

		try {
			file_put_contents( $env_file, $env_vars, LOCK_EX );
			$io->write( sprintf( '<info><comment>%s</comment> successfully created.</info>', $filename ) );
		} catch ( \Exception $e ) {
			$io->write( '<error>An error occured while creating your .env file. Error message:</error>' );
			$io->write( sprintf( '<error>%s</error>%s', $e->getMessage(), "\n" ) );
			$io->write( '<info>Below is the environment variables generated:</info>' );
			$io->write( $env_vars );
		}
	}


	/**
	 * Get current projects directory name
	 *
	 * @access public
	 * @return string Directory name
	 */
	public static function get_dir_name() {
		return basename( self::$base_dir );
	}


	/**
	 * Get default env value
	 *
	 * @access public
	 * @param array env properties
	 * @return mixed
	 */
	public static function get_default( Array $props ) {
		if ( ! isset( $props['args']['default'] ) ) {
			$props['args']['default'] = false;
		}
		if ( ! empty( $props['default_cb'] ) && is_callable( $props['default_cb'] ) ) {
			if ( empty( $props['default_cb_args'] ) ) {
				$props['default_cb_args'] = array();
			}
			$props['args']['default'] = call_user_func_array(
				$props['default_cb'],
				(array) $props['default_cb_args']
			);
		}

		return $props['args']['default'];
	}


	/**
	 * Get env value
	 *
	 * @access public
	 * @param string $key Env key
	 * @return mixed
	 */
	public static function get_env_value( $key ) {
		if ( isset( static::$env_vars[ $key ] ) ) {
			return static::$env_vars[ $key ];
		}

		return false;
	}


	/**
	 * Validate env value
	 *
	 * @access public
	 * @param mixed $value
	 * @return mixed $value
	 */
	public static function validate( $value ) {
		$value = trim( $value );

		if ( empty( $value ) ) {
			if ( ! empty( self::$_current_props['error_message'] ) ) {
				$message = self::$_current_props['error_message'];
			} else {
				$message = 'Value can not be empty.';
			}

			throw new \RunTimeException( $message );
		}

		return $value;
	}


	/**
	 * Generate salt
	 *
	 * Slightly modified/simpler version of wp_generate_password
	 * https://github.com/WordPress/WordPress/blob/cd8cedc40d768e9e1d5a5f5a08f1bd677c804cb9/wp-includes/pluggable.php#L1575
	 *
	 * @access public
	 * @param  int $length Salt length
	 * @return string
	 */
	public static function generate_salt( $length = 64 ) {
		$chars  = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$chars .= '! @#$%^&*()';
		$chars .= '-_ []{}<>~`+=,.;:/?|';

		$salt = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$salt .= substr( $chars, rand( 0, strlen( $chars ) - 1 ), 1 );
		}

		return $salt;
	}


	/**
	 * Strip non-alphanumerics-underscores from string
	 *
	 * @access public
	 * @param string $string String to strip
	 * @return string
	 */
	public static function strip_non_alpha_numerics( $string ) {
		return preg_replace( '/[^a-zA-Z0-9_]+/', '_', $string );
	}
}
