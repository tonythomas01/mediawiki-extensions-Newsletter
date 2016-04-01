<?php

/**
 * @covers SpecialNewsletterCreate
 *
 * @group SpecialPage
 * @group Database
 *
 * @author Addshore
 */
class SpecialNewsletterCreateTest extends SpecialPageTestBase{

	protected function newSpecialPage() {
		return new SpecialNewsletterCreate();
	}

	public function testSpecialPageDoesNotFatal() {
		$user = new TestUser( __METHOD__, __METHOD__, 'foo@bar.com', [ 'autoconfirmed' ] );
		$this->executeSpecialPage( '', null, null, $user->getUser() );
		$this->assertTrue( true );
	}
}
