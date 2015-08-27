<?php

/**
 * Special page for creating newsletters
 *
 */
class SpecialNewsletterCreate extends FormSpecialPage {


	public function __construct() {
		parent::__construct( 'NewsletterCreate' );
	}

	public function execute( $par ) {
		$this->requireLogin();
		parent::execute( $par );
	}

	/**
	 * @param HTMLForm $form
	 */
	protected function alterForm( HTMLForm $form ) {
		$form->setSubmitTextMsg( 'newsletter-create-submit' );
		$form->setWrapperLegendMsg( 'newsletter-create-section' );
	}

	/**
	 * Function to generate Create Newsletter Form
	 *
	 * @return array
	 */
	protected function getFormFields() {
		return array(
			'name' => array(
				'type' => 'text',
				'required' => true,
				'label-message' => 'newsletter-name',
			),
			'description' => array(
				'type' => 'textarea',
				'required' => true,
				'label-message' => 'newsletter-desc',
				'rows' => 15,
				'cols' => 50,
			),
			'mainpage' => array(
				'required' => true,
				'type' => 'text',
				'label-message' => 'newsletter-title',
			),
			'frequency' => array(
				'required' => true,
				'type' => 'selectorother',
				'label-message' => 'newsletter-frequency',
				'options' => array(
					'weekly' => $this->msg( 'newsletter-option-weekly' ),
					'monthly' => $this->msg( 'newsletter-option-monthly' ),
					'quarterly' => $this->msg( 'newsletter-option-quarterly' ),
				),
				'size' => 18, # size of 'other' field
				'maxlength' => 50,
			),
			// @todo FIXME: this shouldn't be a form field
			'publisher' => array(
				'type' => 'hidden',
				'default' => $this->getUser()->getId(),
			),
		);
	}

	/**
	 * Perform insert query on newsletter table with data retrieved from HTML
	 * form for creating newsletters
	 *
	 * @param array $formData The data entered by user in the form
	 *
	 * @return bool|array true on success, array on error
	 */
	public function onSubmit( array $formData ) {
		if ( isset( $formData['mainpage'] ) ) {
			$page = Title::newFromText( $formData['mainpage'] );
			$pageId = $page->getArticleId();
		} else {
			return array( 'newsletter-create-mainpage-error' );
		}
		if ( isset( $formData['name'] ) &&
			isset( $formData['description'] ) &&
			( $pageId !== 0 ) &&
			isset( $formData['mainpage'] ) &&
			isset( $formData['frequency'] ) &&
			isset( $formData['publisher'] )
		) {
			// inserting into database
			$dbw = wfGetDB( DB_MASTER );
			$rowData = array(
				'nl_name' => $formData['name'],
				'nl_desc' => $formData['description'],
				'nl_main_page_id' => $pageId,
				'nl_frequency' => $formData['frequency'],
				'nl_owner_id' => $formData['publisher'],
			);

			try {
				$dbw->insert( 'nl_newsletters', $rowData, __METHOD__ );
			}
			catch ( DBQueryError $e ) {
				return array( 'newsletter-exist-error' );
			}
			$this->getOutput()->addWikiMsg( 'newsletter-create-confirmation' );
			// Add newsletter creator as publisher
			$dbr = wfGetDB( DB_SLAVE );
			$res = $dbr->select(
				'nl_newsletters',
				array( 'nl_id' ),
				array(
					'nl_name' => $formData['name'],
				),
				__METHOD__
			);

			$newsletterId = array();
			foreach ( $res as $row ) {
				$newsletterId = $row->nl_id;
			}
			$this->autoSubscribe( $newsletterId, $formData['publisher'] );


			return true;
		}

		return array( 'newsletter-mainpage-not-found-error' );
	}

	/**
	 * Automatically subscribe and add owner as publisher of the newsletter
	 *
	 * @param int $newsletterId Id of the newsletter
	 * @param int $ownerId User Id of the owner
	 * @return bool
	 */
	private function autoSubscribe( $newsletterId, $ownerId ) {
		$dbw = wfGetDB( DB_MASTER );
		// add owner as a publisher
		$pubRowData = array(
			'newsletter_id' => $newsletterId,
			'publisher_id' => $ownerId,
		);
		$dbw->insert( 'nl_publishers', $pubRowData, __METHOD__ );

		$table = SubscriptionsTable::newFromGlobalState();
		$table->addSubscription( $ownerId, $newsletterId );

		return true;
	}

}
