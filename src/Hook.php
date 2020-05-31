<?php

namespace Shohel\Pluggable;

/**
 * Plugin API: Hook class
 *
 */

/**
 * Core class used to implement action and filter hook functionality.
 *
 * @see Iterator
 * @see ArrayAccess
 */
final class Hook implements \Iterator, \ArrayAccess {

	/**
	 * Hook callbacks.
	 *
	 * @var array
	 */
	public $callbacks = array();

	/**
	 * The priority keys of actively running iterations of a hook.
	 *
	 * @var array
	 */
	private $iterations = array();

	/**
	 * The current priority of actively running iterations of a hook.
	 *
	 * @var array
	 */
	private $current_priority = array();

	/**
	 * Number of levels this hook can be recursively called.
	 *
	 * @var int
	 */
	private $nesting_level = 0;

	/**
	 * Flag for if we're current doing an action, rather than a filter.
	 *
	 * @var bool
	 */
	private $doing_action = false;


    /**
     * @param $tag
     * @param $function_to_add
     * @param $priority
     * @param $accepted_args
     */
	public function add_filter( $tag, $function_to_add, $priority, $accepted_args ) {
		$idx              = _plugable_filter_build_unique_id( $tag, $function_to_add, $priority );
		$priority_existed = isset( $this->callbacks[ $priority ] );

		$this->callbacks[ $priority ][ $idx ] = array(
			'function'      => $function_to_add,
			'accepted_args' => $accepted_args,
		);

		// if we're adding a new priority to the list, put them back in sorted order
		if ( ! $priority_existed && count( $this->callbacks ) > 1 ) {
			ksort( $this->callbacks, SORT_NUMERIC );
		}

		if ( $this->nesting_level > 0 ) {
			$this->resort_active_iterations( $priority, $priority_existed );
		}
	}

    /**
     * @param bool $new_priority
     * @param bool $priority_existed
     */
	private function resort_active_iterations( $new_priority = false, $priority_existed = false ) {
		$new_priorities = array_keys( $this->callbacks );

		// If there are no remaining hooks, clear out all running iterations.
		if ( ! $new_priorities ) {
			foreach ( $this->iterations as $index => $iteration ) {
				$this->iterations[ $index ] = $new_priorities;
			}
			return;
		}

		$min = min( $new_priorities );
		foreach ( $this->iterations as $index => &$iteration ) {
			$current = current( $iteration );
			// If we're already at the end of this iteration, just leave the array pointer where it is.
			if ( false === $current ) {
				continue;
			}

			$iteration = $new_priorities;

			if ( $current < $min ) {
				array_unshift( $iteration, $current );
				continue;
			}

			while ( current( $iteration ) < $current ) {
				if ( false === next( $iteration ) ) {
					break;
				}
			}

			// If we have a new priority that didn't exist, but ::apply_filters() or ::do_action() thinks it's the current priority...
			if ( $new_priority === $this->current_priority[ $index ] && ! $priority_existed ) {
				/*
				 * ... and the new priority is the same as what $this->iterations thinks is the previous
				 * priority, we need to move back to it.
				 */

				if ( false === current( $iteration ) ) {
					// If we've already moved off the end of the array, go back to the last element.
					$prev = end( $iteration );
				} else {
					// Otherwise, just go back to the previous element.
					$prev = prev( $iteration );
				}
				if ( false === $prev ) {
					// Start of the array. Reset, and go about our day.
					reset( $iteration );
				} elseif ( $new_priority !== $prev ) {
					// Previous wasn't the same. Move forward again.
					next( $iteration );
				}
			}
		}
		unset( $iteration );
	}

    /**
     * @param $tag
     * @param $function_to_remove
     * @param $priority
     * @return bool
     */
	public function remove_filter( $tag, $function_to_remove, $priority ) {
		$function_key = _plugable_filter_build_unique_id( $tag, $function_to_remove, $priority );

		$exists = isset( $this->callbacks[ $priority ][ $function_key ] );
		if ( $exists ) {
			unset( $this->callbacks[ $priority ][ $function_key ] );
			if ( ! $this->callbacks[ $priority ] ) {
				unset( $this->callbacks[ $priority ] );
				if ( $this->nesting_level > 0 ) {
					$this->resort_active_iterations();
				}
			}
		}
		return $exists;
	}

    /**
     * @param string $tag
     * @param bool $function_to_check
     * @return bool|int|string
     */
	public function has_filter( $tag = '', $function_to_check = false ) {
		if ( false === $function_to_check ) {
			return $this->has_filters();
		}

		$function_key = _plugable_filter_build_unique_id( $tag, $function_to_check, false );
		if ( ! $function_key ) {
			return false;
		}

		foreach ( $this->callbacks as $priority => $callbacks ) {
			if ( isset( $callbacks[ $function_key ] ) ) {
				return $priority;
			}
		}

		return false;
	}

    /**
     * @return bool
     */
	public function has_filters() {
		foreach ( $this->callbacks as $callbacks ) {
			if ( $callbacks ) {
				return true;
			}
		}
		return false;
	}

    /**
     * @param bool $priority
     */
	public function remove_all_filters( $priority = false ) {
		if ( ! $this->callbacks ) {
			return;
		}

		if ( false === $priority ) {
			$this->callbacks = array();
		} elseif ( isset( $this->callbacks[ $priority ] ) ) {
			unset( $this->callbacks[ $priority ] );
		}

		if ( $this->nesting_level > 0 ) {
			$this->resort_active_iterations();
		}
	}


    /**
     * @param $value
     * @param $args
     * @return mixed
     */
	public function apply_filters( $value, $args ) {
		if ( ! $this->callbacks ) {
			return $value;
		}

		$nesting_level = $this->nesting_level++;

		$this->iterations[ $nesting_level ] = array_keys( $this->callbacks );
		$num_args                           = count( $args );

		do {
			$this->current_priority[ $nesting_level ] = current( $this->iterations[ $nesting_level ] );
			$priority                                 = $this->current_priority[ $nesting_level ];

			foreach ( $this->callbacks[ $priority ] as $the_ ) {
				if ( ! $this->doing_action ) {
					$args[0] = $value;
				}

				// Avoid the array_slice if possible.
				if ( $the_['accepted_args'] == 0 ) {
					$value = call_user_func( $the_['function'] );
				} elseif ( $the_['accepted_args'] >= $num_args ) {
					$value = call_user_func_array( $the_['function'], $args );
				} else {
					$value = call_user_func_array( $the_['function'], array_slice( $args, 0, (int) $the_['accepted_args'] ) );
				}
			}
		} while ( false !== next( $this->iterations[ $nesting_level ] ) );

		unset( $this->iterations[ $nesting_level ] );
		unset( $this->current_priority[ $nesting_level ] );

		$this->nesting_level--;

		return $value;
	}

    /**
     * @param $args
     */
	public function do_action( $args ) {
		$this->doing_action = true;
		$this->apply_filters( '', $args );

		// If there are recursive calls to the current action, we haven't finished it until we get to the last one.
		if ( ! $this->nesting_level ) {
			$this->doing_action = false;
		}
	}

    /**
     * @param $args
     */
	public function do_all_hook( &$args ) {
		$nesting_level                      = $this->nesting_level++;
		$this->iterations[ $nesting_level ] = array_keys( $this->callbacks );

		do {
			$priority = current( $this->iterations[ $nesting_level ] );
			foreach ( $this->callbacks[ $priority ] as $the_ ) {
				call_user_func_array( $the_['function'], $args );
			}
		} while ( false !== next( $this->iterations[ $nesting_level ] ) );

		unset( $this->iterations[ $nesting_level ] );
		$this->nesting_level--;
	}

    /**
     * @return bool|mixed
     */
	public function current_priority() {
		if ( false === current( $this->iterations ) ) {
			return false;
		}

		return current( current( $this->iterations ) );
	}

    /**
     * @param $filters
     * @return Hook[]
     */
	public static function build_preinitialized_hooks( $filters ) {
		/** @var Hook[] $normalized */
		$normalized = array();

		foreach ( $filters as $tag => $callback_groups ) {
			if ( is_object( $callback_groups ) && $callback_groups instanceof Hook ) {
				$normalized[ $tag ] = $callback_groups;
				continue;
			}
			$hook = new Hook();

			// Loop through callback groups.
			foreach ( $callback_groups as $priority => $callbacks ) {

				// Loop through callbacks.
				foreach ( $callbacks as $cb ) {
					$hook->add_filter( $tag, $cb['function'], $priority, $cb['accepted_args'] );
				}
			}
			$normalized[ $tag ] = $hook;
		}
		return $normalized;
	}

    /**
     * @param mixed $offset
     * @return bool
     */
	public function offsetExists( $offset ) {
		return isset( $this->callbacks[ $offset ] );
	}

    /**
     * @param mixed $offset
     * @return mixed|null
     */
	public function offsetGet( $offset ) {
		return isset( $this->callbacks[ $offset ] ) ? $this->callbacks[ $offset ] : null;
	}

    /**
     * @param mixed $offset
     * @param mixed $value
     */
	public function offsetSet( $offset, $value ) {
		if ( is_null( $offset ) ) {
			$this->callbacks[] = $value;
		} else {
			$this->callbacks[ $offset ] = $value;
		}
	}

    /**
     * @param mixed $offset
     */
	public function offsetUnset( $offset ) {
		unset( $this->callbacks[ $offset ] );
	}

    /**
     * @return mixed
     */
	public function current() {
		return current( $this->callbacks );
	}

    /**
     * @return mixed|void
     */
	public function next() {
		return next( $this->callbacks );
	}

    /**
     * @return bool|float|int|string|null
     */
	public function key() {
		return key( $this->callbacks );
	}

    /**
     * @return bool
     */
	public function valid() {
		return key( $this->callbacks ) !== null;
	}


	public function rewind() {
		reset( $this->callbacks );
	}

}
