<?php
/**
 * Unit test to test Api module - ApiNewsletter
 *
 * @group API
 * @group medium
 * @group Database
 * @covers ApiNewsletter
 * @author Tina Johnson
 */

class ApiNewsletterTest extends ApiTestCase {
	protected  function setUp() {
		parent::setUp();
		$dbw = wfGetDB( DB_MASTER );

		$user = User::newFromName( "Owner" );
		$user->addToDatabase();

		$rowData = array(
			'nl_name' => 'MyNewsletter',
			'nl_desc' => 'This is a newsletter',
			'nl_main_page_id' => 1,
			'nl_frequency' => 'monthly',
			'nl_owner_id' => $user->getId()
		);
		$dbw->insert( 'nl_newsletters', $rowData, __METHOD__ );
		$this->tablesUsed = array( 'nl_newsletters' );
            }

	protected function getNewsletterId() {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'nl_newsletters',
			array( 'nl_id' ),
			array(
			'nl_name' => 'MyNewsletter'
			),
			__METHOD__
		);
		$newsletterId = null;
		foreach ( $res as $row ) {
			$newsletterId = $row->nl_id;
		}

		return $newsletterId;
	}

	function testApiNewsletterForSubscribingNewsletter() {
		$this->doApiRequest( array(
			'action' => 'newsletterapi',
			'newsletterId' => $this->getNewsletterId(),
			'todo' => 'subscribe'
		) );

		$dbr = wfGetDB( DB_SLAVE );
		$result = $dbr->selectRowCount(
			'nl_subscriptions',
			array( 'subscriber_id' ),
			array(
			'newsletter_id' => $this->getNewsletterId()
			),
			__METHOD__
		);

		$this->assertEquals( $result, 1 );
	}

	function testApiNewsletterForUnsubscribingNewsletter() {
		$this->doApiRequest( array(
			'action' => 'newsletterapi',
			'newsletterId' => $this->getNewsletterId(),
			'todo' => 'unsubscribe'
		) );

		$dbr = wfGetDB( DB_SLAVE );
		$result = $dbr->selectRowCount(
			'nl_subscriptions',
			array( 'subscriber_id' ),
			array(
			'newsletter_id' => $this->getNewsletterId()
			),
			__METHOD__
		);

		$this->assertEquals( $result, 0 );
	}
}