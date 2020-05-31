<?php
// Initialize the filter globals.
require( dirname( __FILE__ ) . '/Hook.php' );

/** @var \Shohel\Pluggable\Hook[] $plugable_filter */
global $plugable_filter, $plugable_actions, $plugable_current_filter;

if ( $plugable_filter ) {
	$plugable_filter = \Shohel\Pluggable\Hook::build_preinitialized_hooks( $plugable_filter );
} else {
	$plugable_filter = array();
}

if ( ! isset( $plugable_actions ) ) {
	$plugable_actions = array();
}

if ( ! isset( $plugable_current_filter ) ) {
	$plugable_current_filter = array();
}

/**
 * @param $tag
 * @param $function_to_add
 * @param int $priority
 * @param int $accepted_args
 * @return bool
 */
function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
	global $plugable_filter;
	if ( ! isset( $plugable_filter[ $tag ] ) ) {
		$plugable_filter[ $tag ] = new \Shohel\Pluggable\Hook();
	}
	$plugable_filter[ $tag ]->add_filter( $tag, $function_to_add, $priority, $accepted_args );
	return true;
}

/**
 * @param $tag
 * @param bool $function_to_check
 * @return bool|int
 */
function has_filter( $tag, $function_to_check = false ) {
	global $plugable_filter;

	if ( ! isset( $plugable_filter[ $tag ] ) ) {
		return false;
	}

	return $plugable_filter[ $tag ]->has_filter( $tag, $function_to_check );
}

/**
 * @param $tag
 * @param $value
 * @return mixed
 */
function apply_filters( $tag, $value ) {
	global $plugable_filter, $plugable_current_filter;

	$args = func_get_args();

	// Do 'all' actions first.
	if ( isset( $plugable_filter['all'] ) ) {
		$plugable_current_filter[] = $tag;
		_plugable_call_all_hook( $args );
	}

	if ( ! isset( $plugable_filter[ $tag ] ) ) {
		if ( isset( $plugable_filter['all'] ) ) {
			array_pop( $plugable_current_filter );
		}
		return $value;
	}

	if ( ! isset( $plugable_filter['all'] ) ) {
		$plugable_current_filter[] = $tag;
	}

	// Don't pass the tag name to Hook.
	array_shift( $args );

	$filtered = $plugable_filter[ $tag ]->apply_filters( $value, $args );

	array_pop( $plugable_current_filter );

	return $filtered;
}

/**
 * @param $tag
 * @param $args
 * @return mixed
 */
function apply_filters_ref_array( $tag, $args ) {
	global $plugable_filter, $plugable_current_filter;

	// Do 'all' actions first
	if ( isset( $plugable_filter['all'] ) ) {
		$plugable_current_filter[] = $tag;
		$all_args            = func_get_args();
		_plugable_call_all_hook( $all_args );
	}

	if ( ! isset( $plugable_filter[ $tag ] ) ) {
		if ( isset( $plugable_filter['all'] ) ) {
			array_pop( $plugable_current_filter );
		}
		return $args[0];
	}

	if ( ! isset( $plugable_filter['all'] ) ) {
		$plugable_current_filter[] = $tag;
	}

	$filtered = $plugable_filter[ $tag ]->apply_filters( $args[0], $args );

	array_pop( $plugable_current_filter );

	return $filtered;
}

/**
 * @param $tag
 * @param $function_to_remove
 * @param int $priority
 * @return bool
 */
function remove_filter( $tag, $function_to_remove, $priority = 10 ) {
	global $plugable_filter;

	$r = false;
	if ( isset( $plugable_filter[ $tag ] ) ) {
		$r = $plugable_filter[ $tag ]->remove_filter( $tag, $function_to_remove, $priority );
		if ( ! $plugable_filter[ $tag ]->callbacks ) {
			unset( $plugable_filter[ $tag ] );
		}
	}

	return $r;
}

/**
 * @param $tag
 * @param bool $priority
 * @return bool
 */
function remove_all_filters( $tag, $priority = false ) {
	global $plugable_filter;

	if ( isset( $plugable_filter[ $tag ] ) ) {
		$plugable_filter[ $tag ]->remove_all_filters( $priority );
		if ( ! $plugable_filter[ $tag ]->has_filters() ) {
			unset( $plugable_filter[ $tag ] );
		}
	}

	return true;
}

/**
 * @return mixed
 */
function current_filter() {
	global $plugable_current_filter;
	return end( $plugable_current_filter );
}

/**
 * @return string
 */
function current_action() {
	return current_filter();
}

/**
 * @param null $filter
 * @return bool
 */
function doing_filter( $filter = null ) {
	global $plugable_current_filter;

	if ( null === $filter ) {
		return ! empty( $plugable_current_filter );
	}

	return in_array( $filter, $plugable_current_filter );
}

/**
 * @param null $action
 * @return bool
 */
function doing_action( $action = null ) {
	return doing_filter( $action );
}

/**
 * @param $tag
 * @param $function_to_add
 * @param int $priority
 * @param int $accepted_args
 * @return true
 */
function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
	return add_filter( $tag, $function_to_add, $priority, $accepted_args );
}

/**
 * @param $tag
 * @param mixed ...$arg
 */
function do_action( $tag, ...$arg ) {
	global $plugable_filter, $plugable_actions, $plugable_current_filter;

	if ( ! isset( $plugable_actions[ $tag ] ) ) {
		$plugable_actions[ $tag ] = 1;
	} else {
		++$plugable_actions[ $tag ];
	}

	// Do 'all' actions first
	if ( isset( $plugable_filter['all'] ) ) {
		$plugable_current_filter[] = $tag;
		$all_args            = func_get_args();
		_plugable_call_all_hook( $all_args );
	}

	if ( ! isset( $plugable_filter[ $tag ] ) ) {
		if ( isset( $plugable_filter['all'] ) ) {
			array_pop( $plugable_current_filter );
		}
		return;
	}

	if ( ! isset( $plugable_filter['all'] ) ) {
		$plugable_current_filter[] = $tag;
	}

	if ( empty( $arg ) ) {
		$arg[] = '';
	} elseif ( is_array( $arg[0] ) && 1 === count( $arg[0] ) && isset( $arg[0][0] ) && is_object( $arg[0][0] ) ) {
		// Backward compatibility for PHP4-style passing of `array( &$this )` as action `$arg`.
		$arg[0] = $arg[0][0];
	}

	$plugable_filter[ $tag ]->do_action( $arg );

	array_pop( $plugable_current_filter );
}

/**
 * @param $tag
 * @return int
 */
function did_action( $tag ) {
	global $plugable_actions;

	if ( ! isset( $plugable_actions[ $tag ] ) ) {
		return 0;
	}

	return $plugable_actions[ $tag ];
}

/**
 * @param $tag
 * @param $args
 */
function do_action_ref_array( $tag, $args ) {
	global $plugable_filter, $plugable_actions, $plugable_current_filter;

	if ( ! isset( $plugable_actions[ $tag ] ) ) {
		$plugable_actions[ $tag ] = 1;
	} else {
		++$plugable_actions[ $tag ];
	}

	// Do 'all' actions first
	if ( isset( $plugable_filter['all'] ) ) {
		$plugable_current_filter[] = $tag;
		$all_args            = func_get_args();
		_plugable_call_all_hook( $all_args );
	}

	if ( ! isset( $plugable_filter[ $tag ] ) ) {
		if ( isset( $plugable_filter['all'] ) ) {
			array_pop( $plugable_current_filter );
		}
		return;
	}

	if ( ! isset( $plugable_filter['all'] ) ) {
		$plugable_current_filter[] = $tag;
	}

	$plugable_filter[ $tag ]->do_action( $args );

	array_pop( $plugable_current_filter );
}

/**
 * @param $tag
 * @param bool $function_to_check
 * @return false|int
 */
function has_action( $tag, $function_to_check = false ) {
	return has_filter( $tag, $function_to_check );
}

/**
 * @param $tag
 * @param $function_to_remove
 * @param int $priority
 * @return bool
 */
function remove_action( $tag, $function_to_remove, $priority = 10 ) {
	return remove_filter( $tag, $function_to_remove, $priority );
}

/**
 * @param $tag
 * @param bool $priority
 * @return true
 */
function remove_all_actions( $tag, $priority = false ) {
	return remove_all_filters( $tag, $priority );
}

/**
 * @param $args
 */
function _plugable_call_all_hook( $args ) {
	global $plugable_filter;

	$plugable_filter['all']->do_all_hook( $args );
}

function _plugable_filter_build_unique_id( $tag, $function, $priority ) {
	global $plugable_filter;
	static $filter_id_count = 0;

	if ( is_string( $function ) ) {
		return $function;
	}

	if ( is_object( $function ) ) {
		// Closures are currently implemented as objects
		$function = array( $function, '' );
	} else {
		$function = (array) $function;
	}

	if ( is_object( $function[0] ) ) {
		// Object Class Calling
		return spl_object_hash( $function[0] ) . $function[1];
	} elseif ( is_string( $function[0] ) ) {
		// Static Calling
		return $function[0] . '::' . $function[1];
	}
}
