<?php

use Behat\Behat\Context\Context;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context {

	protected $semaphore = 100;
	protected $segment   = 200;
	protected $processes = 23;
	protected $path;

	/**
	 * Initializes context.
	 *
	 * Every scenario gets its own context instance.
	 * You can also pass arbitrary arguments to the
	 * context constructor through behat.yml.
	 */
	public function __construct() {
	}

	/**
	 * @BeforeScenario
	 */
	public function addDataToPathAndSetSemaphore() {
		$this->path = getenv( 'PATH' );
		putenv( 'PATH=' . dirname( __DIR__ ) . '/data:' . $this->path );

		$this->setupSemaphore();
	}

	protected function setupSemaphore() {
		// get a handle to the semaphore associated with the shared memory
		// segment we want
		$sem = sem_get( $this->semaphore, 1, 0600 );

		// ensure exclusive access to the semaphore
		$acquired = sem_acquire( $sem );
		if ( ! $acquired ) {
			throw new RuntimeException( 'Could not acquire semaphore' );
		}

		// get a handle to our shared memory segment
		$shm = shm_attach( $this->segment, 16384, 0600 );

		// store the value back in the shared memory segment
		shm_put_var( $shm, $this->processes, array() );

		// release the handle to the shared memory segment
		shm_detach( $shm );

		// release the semaphore so other processes can acquire it
		sem_release( $sem );
	}

	/**
	 * @AfterScenario
	 */
	public function restorePath() {
		putenv( 'PATH=' . $this->path );
	}

	/**
	 * @When I run :command
	 */
	public function iRun( $command ) {
		exec( dirname( dirname( __DIR__ ) ) . '/' . $command );
	}

	/**
	 * @Then :command should have been called
	 */
	public function shouldHaveBeenCalled( $command ) {
		PHPUnit_Framework_Assert::assertContains(
			$command, $this->getRanCommands(), 'Calls where: ' . print_r( $this->getRanCommands(), true )
		);
	}

	protected function getRanCommands() {
		$sem      = sem_get( $this->semaphore, 1, 0600 );
		$acquired = sem_acquire( $sem );

		if ( ! $acquired ) {
			throw new RuntimeException( 'Could not acquire semaphore' );
		}

		$shm = shm_attach( $this->segment, 16384, 0600 );

		$processes = shm_get_var( $shm, $this->processes );
		$processes = is_array( $processes ) ? $processes : array();

		shm_detach( $shm );
		sem_release( $sem );

		return $processes;
	}

	/**
	 * @Then :command should not have been called
	 */
	public function shouldNotHaveBeenCalled( $command ) {
		PHPUnit_Framework_Assert::assertNotContains(
			$command, $this->getRanCommands(), 'Calls where: ' . print_r( $this->getRanCommands(), true )
		);
	}
}
