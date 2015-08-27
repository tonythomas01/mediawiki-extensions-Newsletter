<?php

/**
 * @license GNU GPL v2+
 * @author Tina Johnson
 */
class NewsletterTablePager extends TablePager {

	/**
	 * @var null|string[]
	 */
	private $fieldNames = null;

	public function getFieldNames() {
		if ( $this->fieldNames === null ) {
			$this->fieldNames = array(
				'nl_name' => $this->msg( 'newsletter-header-name' )->text(),
				'nl_desc' => $this->msg( 'newsletter-header-description' )->text(),
				'subscriber_count' => $this->msg( 'newsletter-header-subscriber_count' )->text(),
				'action' => $this->msg( 'newsletter-header-action' )->text(),
			);
		}
		return $this->fieldNames;
	}

	public function getQueryInfo() {
		$userId = $this->getUser()->getId();
		//TODO we could probably just retrieve all subscribers IDs as a string here.
		$info = array(
			'tables' => array( 'nl_newsletters' ),
			'fields' => array(
				'nl_name',
				'nl_desc',
				'nl_id',
				'subscribers' => ( '( SELECT COUNT(*) FROM nl_subscriptions WHERE newsletter_id = nl_id )' ),
				'current_user_subscribed' => "$userId IN (SELECT subscriber_id FROM nl_subscriptions WHERE newsletter_id = nl_id )" ,
			),
			'options' => array( 'DISTINCT nl_id' ),
		);

		return $info;
	}

	public function formatValue( $field, $value ) {
		switch ( $field ) {
			case 'nl_name':
				$dbr = wfGetDB( DB_SLAVE );
				$res = $dbr->select(
					'nl_newsletters',
					array( 'nl_main_page_id' ),
					array( 'nl_name' => $value ),
					__METHOD__
				);

				$mainPageId = '';
				foreach ( $res as $row ) {
					$mainPageId = $row->nl_main_page_id;
				}

				$url = $mainPageId ? Title::newFromID( $mainPageId )->getFullURL() : "#";

				return '<a href="' . $url . '">' . $value . '</a>';
			case 'nl_desc':
				return $value;
			case 'subscriber_count':
				return HTML::element(
					'input',
					array(
						'type' => 'textbox',
						'readonly' => 'true',
						'id' => 'newsletter-' . $this->mCurrentRow->nl_id,
						'value' => $this->mCurrentRow->subscribers,

					)
				);
			case 'action' :
				$radioSubscribe = Html::element(
						'input',
						array(
							'type' => 'radio',
							'name' => 'nl_id-' . $this->mCurrentRow->nl_id,
							'value' => 'subscribe',
							'checked' => $this->mCurrentRow->current_user_subscribed,
						)
					) . $this->msg( 'newsletter-subscribe-button-label' );
				$radioUnSubscribe = Html::element(
						'input',
						array(
							'type' => 'radio',
							'name' => 'nl_id-' . $this->mCurrentRow->nl_id,
							'value' => 'unsubscribe',
							'checked' => !$this->mCurrentRow->current_user_subscribed,
						)
					) . $this->msg( 'newsletter-unsubscribe-button-label' );

				return $radioSubscribe . $radioUnSubscribe;
		}
	}

	public function getDefaultSort() {
		return 'nl_name';
	}

	public function isFieldSortable( $field ) {
		return false;
	}

}