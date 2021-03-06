<?php

if ( ! class_exists( 'DFM_Transient_Hook' ) ) {

	/**
	 * Class DFM_Transient_Hook
	 * Processes data on specified hook when registering transient
	 */
	class DFM_Transient_Hook {

		/**
		 * Name of transient
		 *
		 * @var string
		 * @access private
		 */
		private $transient;

		/**
		 * Name of hook we want to hook into
		 *
		 * @var string
		 * @access private
		 */
		private $hook;

		/**
		 * Callback to decide if we want to re-generate transient data in this hook
		 *
		 * @var string
		 * @access private
		 */
		private $callback;

		/**
		 * DFM_Transient_Hook constructor.
		 *
		 * @param string $transient
		 * @param string $hook
		 * @param bool $async_update
		 * @param string $callback
		 */
		public function __construct( $transient, $hook, $async_update, $callback = '' ) {

			$this->transient = $transient;
			$this->hook      = $hook;
			$this->callback  = $callback;
			$this->async     = $async_update;

			$this->add_hook( $hook );

		}

		/**
		 * Dynamically add the hook for regenerating the transient
		 *
		 * @param string $hook
		 * @return void
		 * @access private
		 */
		private function add_hook( $hook ) {
			// Pass an arbitrarily high arg count to avoid errors.
			add_action( $hook, array( $this, 'spawn' ), 10, 20 );
		}

		/**
		 * Run an update in real time for the transient.
		 *
		 * @param string $modifier Name of the modifier
		 * @return void
		 * @access private
		 */
		private function run_update( $modifier ) {

			$transient_obj = new DFM_Transients( $this->transient, $modifier );

			// Bail if another process is already trying to update this transient.
			if ( $transient_obj->is_locked() && ! $transient_obj->owns_lock( '' ) ) {
				return;
			}

			if ( ! $transient_obj->is_locked() ) {
				$transient_obj->lock_update();
			}

			$data = call_user_func( $transient_obj->transient_object->callback, $modifier );
			$transient_obj->set( $data );

			$transient_obj->unlock_update();

		}

		/**
		 * Spawn a process to regenerate the transient data
		 *
		 * @return void
		 * @access public
		 */
		public function spawn() {

			$modifiers = '';

			if ( ! empty( $this->callback ) ) {

				// Grab the args from the hook we are using
				$hook_args = func_get_args();

				// Call the callback for this hook, and pass the args to it.
				// This callback decides if we should actually run the process to update the transient data based on the
				// args passed to it. The callback should return false if we don't want to run the action, and should
				// return the transient modifier if we do want to run it. You can also optionally return an array of
				// modifiers if you want to update multiple transients at once.
				$modifiers = call_user_func( $this->callback, $hook_args );
				if ( false === $modifiers ) {
					return;
				}
			}

			// If we are running an async task, instantiate a new async handler.
			if ( $this->async ) {

				// If we have an array of modifiers, update each of them.
				if ( is_array( $modifiers ) ) {
					foreach ( $modifiers as $modifier ) {
						new DFM_Async_Handler( $this->transient, $modifier );
					}
				} else {
					new DFM_Async_Handler( $this->transient, $modifiers );
				}

			// Run the update in real time if we aren't using an async process
			} else {

				// If we have an array of modifiers, update each of them.
				if ( is_array( $modifiers ) ) {
					foreach ( $modifiers as $modifier ) {
						$this->run_update( $modifier );
					}
				} else {
					$this->run_update( $modifiers );
				}

			}

		}

	}

}
