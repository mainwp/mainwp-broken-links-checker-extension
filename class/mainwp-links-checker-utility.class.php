<?php
class MainWP_Links_Checker_Utility {
	public static function format_timestamp( $timestamp ) {
		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	static function ctype_digit( $str ) {
		return ( is_string( $str ) || is_int( $str ) || is_float( $str ) ) && preg_match( '/^\d+\z/', $str );
	}

	public static function map_site( &$website, $keys ) {
		$outputSite = array();
		foreach ( $keys as $key ) {
			$outputSite[ $key ] = $website->$key;
		}
		return $outputSite;
	}

	/*
    PluginName: Broken Link Checker
    PluginURI: http://w-shadow.com/blog/2007/08/05/broken-link-checker-for-wordpress/
    Description: Checks your blog for broken links and missing images and notifies you on the dashboard if any are found.
    Version: 1.9.2
    Author: Janis Elsts
    AuthorURI: http://w-shadow.com/
    TextDomain: broken-link-checker
    */

	static function fuzzy_delta( $delta, $template = 'default' ) {
		$ONE_MINUTE = 60;
		$ONE_HOUR = 60 * $ONE_MINUTE;
		$ONE_DAY = 24 * $ONE_HOUR;
		$ONE_MONTH = $ONE_DAY * 3652425 / 120000;
		$ONE_YEAR = $ONE_DAY * 3652425 / 10000;

		$templates = array(
			'seconds' => array(
				'default' => _n_noop( '%d second', '%d seconds' ),
				'ago' 		=> _n_noop( '%d second ago', '%d seconds ago' ),
			),
			'minutes' => array(
				'default' => _n_noop( '%d minute', '%d minutes' ),
				'ago' 		=> _n_noop( '%d minute ago', '%d minutes ago' ),
			),
			'hours' => array(
				'default' => _n_noop( '%d hour', '%d hours' ),
				'ago' 		=> _n_noop( '%d hour ago', '%d hours ago' ),
			),
			'days' => array(
				'default' => _n_noop( '%d day', '%d days' ),
				'ago' 		=> _n_noop( '%d day ago', '%d days ago' ),
			),
			'months' => array(
				'default' => _n_noop( '%d month', '%d months' ),
				'ago' 		=> _n_noop( '%d month ago', '%d months ago' ),
			),
		);

		if ( $delta < 1 ) {
			$delta = 1;
		}

		if ( $delta < $ONE_MINUTE ) {
			$units = 'seconds';
		} elseif ( $delta < $ONE_HOUR ) {
			$delta = intval( $delta / $ONE_MINUTE );
			$units = 'minutes';
		} elseif ( $delta < $ONE_DAY ) {
			$delta = intval( $delta / $ONE_HOUR );
			$units = 'hours';
		} elseif ( $delta < $ONE_MONTH ) {
			$delta = intval( $delta / $ONE_DAY );
			$units = 'days';
		} else {
			$delta = intval( $delta / $ONE_MONTH );
			$units = 'months';
		}

		return sprintf(
			_n(
				$templates[ $units ][ $template ][0],
				$templates[ $units ][ $template ][1],
				$delta
			),
			$delta
		);
	}
}
