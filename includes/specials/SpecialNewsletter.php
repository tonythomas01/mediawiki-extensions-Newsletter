<?php
/**
 * Special page to handle actions related to specific newsletters
 *
 * @author Glaisher
 * @license GNU GPL v2+
 */
class SpecialNewsletter extends SpecialPage {

	/** Subpage actions */
	const NEWSLETTER_MANAGE = 'manage';
	const NEWSLETTER_ANNOUNCE = 'announce';
	const NEWSLETTER_SUBSCRIBE = 'subscribe';
	const NEWSLETTER_UNSUBSCRIBE = 'unsubscribe';

	/**
	 * @var Newsletter|null
	 */
	protected $newsletter;

	public function __construct() {
		parent::__construct( 'Newsletter' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * @param string|null $par subpage parameter
	 */
	public function execute( $par ) {

		if ( $par == '' ) {
			// If no subpage was specified - only [[Special:Newsletter]] - redirect to Special:Newsletters
			$this->getOutput()->redirect(
					SpecialPage::getTitleFor( 'Newsletters' )->getFullURL(),
					'303'
			);
			return;
		}

		$this->setHeaders();

		// Separate out newsletter id and action from subpage
		$params = explode( '/', $par );
		$params[1] = isset( $params[1] ) ? $params[1] : null;
		list( $id, $action ) = $params;

		$out = $this->getOutput();
		$this->newsletter = Newsletter::newFromID( (int)$id );

		if ( $this->newsletter ) {
			// Newsletter exists for the given subpage id - let's check what they want to do
			switch ( $action ) {
				case self::NEWSLETTER_SUBSCRIBE:
				case self::NEWSLETTER_UNSUBSCRIBE:
					$this->doSubscribeExecute();
					break;
				case self::NEWSLETTER_ANNOUNCE:
					$this->doAnnounceExecute();
					break;
				default:
					$this->getOutput()->redirect(
						Title::makeTitleSafe( NS_NEWSLETTER, $this->newsletter->getName() )->getFullURL() );
					return;
			}

			$out->addSubtitle( $this->getNavigationLinks( $action ) );

		} else {
			// Show an error message (with delete log entry) if we couldn't find a newsletter
			$out->showErrorPage( 'newsletter-notfound', 'newsletter-not-found-id' );
			$num = LogEventsList::showLogExtract(
				$out,
				'newsletter',
				$this->getPageTitle( (int)$id ),
				'',
				array(
					'showIfEmpty' => false,
					'conds' => array( 'log_action' => 'newsletter-removed' ),
					'msgKey' => 'newsletter-deleted-log'
				)
			);
		}

	}

	/**
	 * Get the navigation links shown in the subtitle
	 *
	 * @param string|null $current subpage currently being shown, null if default "view" page
	 */
	protected function getNavigationLinks( $current ) {
		$linkRenderer = $this->getLinkRenderer();
		$listLink = $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor( 'Newsletters' ),
			$this->msg( 'backlinksubtitle',
				$this->msg( 'newsletter-subtitlelinks-list' )->text()
			)->text()
		);
		if ( $current === null ) {
			// We've the fancy buttons on the default "view" page so don't
			// add redundant navigation links and fast return here
			return $listLink;
		}

		// Build the links taking the current user's access levels into account
		$user = $this->getUser();
		$actions = array();
		if ( $user->isLoggedIn() ) {
			$actions[] = $this->newsletter->isSubscribed( $user )
				? self::NEWSLETTER_UNSUBSCRIBE
				: self::NEWSLETTER_SUBSCRIBE;
		}
		if ( $this->newsletter->isPublisher( $user ) ) {
			$actions[] = self::NEWSLETTER_ANNOUNCE;
		}
		if ( $this->newsletter->canManage( $user ) ) {
			$actions[] = self::NEWSLETTER_MANAGE;
		}

		$links = array();
		foreach ( $actions as $action ) {
			$title = $this->getPageTitle( $this->newsletter->getId() . '/' . $action );
			// Messages used here: 'newsletter-subtitlelinks-announce',
			// 'newsletter-subtitlelinks-subscribe', 'newsletter-subtitlelinks-unsubscribe'
			// 'newsletter-subtitlelinks-manage'
			$msg = $this->msg( 'newsletter-subtitlelinks-' . $action )->text();
			$link = $linkRenderer->makeKnownLink( $title, $msg );
			if ( $action == self::NEWSLETTER_MANAGE ) {
				$title = Title::makeTitleSafe( NS_NEWSLETTER, $this->newsletter->getName() );
				$msg = $this->msg( 'newsletter-subtitlelinks-' . $action )->text();
				$link = $linkRenderer->makeKnownLink( $title, $msg, [], ['action'=>'edit'] );
			}
			if ( $current === $action ) {
				$links[] = Linker::makeSelfLinkObj( $title, $msg );
			} else {

				$links[] = $link;
			}
		}

		$newsletterLinks = $linkRenderer->makeKnownLink(
			Title::makeTitleSafe( NS_NEWSLETTER, $this->newsletter->getName() ),
			$this->getName()
		) . ' ' . $this->msg( 'parentheses' )
			->rawParams( $this->getLanguage()->pipeList( $links ) )
			->text();

		return $this->getLanguage()->pipeList( array( $listLink, $newsletterLinks ) );
	}

	/**
	 * Create a common HTMLForm which can be used by specific page actions
	 *
	 * @param array $fields array of form fields
	 * @param callback $submit submit callback
	 *
	 * @return HTMLForm
	 */
	private function getHTMLForm( array $fields, /* callable */ $submit ) {
		$form = HTMLForm::factory(
			'ooui',
			$fields,
			$this->getContext()
		);
		$form->setSubmitCallback( $submit );

		return $form;
	}

	/**
	 * We need the escaped newsletter name several times so
	 * extract the method here.
	 *
	 * @return string
	 */
	protected function getEscapedName() {
		return htmlspecialchars( $this->newsletter->getName() );
	}

	/**
	 * Build a group of buttons: Manage, Subscribe|Unsubscribe
	 * Buttons will be showed to the user only if they are relevant to the current user.
	 *
	 * @return string HTML for the button group
	 */
	protected function getNewsletterActionButtons() {
		$user = $this->getUser();
		$id = $this->newsletter->getId();
		$buttons = array();
		$this->getOutput()->enableOOUI();

		if ( $this->newsletter->canManage( $user ) ) {
			$buttons[] = new OOUI\ButtonWidget(
				array(
					'label' => $this->msg( 'newsletter-manage-button' )->escaped(),
					'icon' => 'settings',
					'href' => Title::makeTitleSafe( NS_NEWSLETTER, $this->newsletter->getName() )->getEditURL(),
				)
			);
		}

		if ( $this->newsletter->isPublisher( $user ) ) {
			$buttons[] = new OOUI\ButtonWidget(
				array(
					'label' => $this->msg( 'newsletter-announce-button' )->escaped(),
					'icon' => 'comment',
					'href' => $this->getPageTitle( $id . '/' . self::NEWSLETTER_ANNOUNCE )->getFullURL()
				)
			);
		}

		if ( $this->newsletter->isSubscribed( $user ) ) {
			$buttons[] = new OOUI\ButtonWidget(
				array(
					'label' => $this->msg( 'newsletter-unsubscribe-button' )->escaped(),
					'flags' => array( 'destructive' ),
					'href' => $this->getPageTitle( $id . '/' . self::NEWSLETTER_UNSUBSCRIBE )->getFullURL()
				)
			);
		} else {
			$buttons[] = new OOUI\ButtonWidget(
				array(
					'label' => $this->msg( 'newsletter-subscribe-button' )->escaped(),
					'flags' => array( 'constructive' ),
					'href' => $this->getPageTitle( $id . '/' . self::NEWSLETTER_SUBSCRIBE )->getFullURL()
				)
			);
		}

		$widget = new OOUI\ButtonGroupWidget( array( 'items' =>  $buttons ) );
		return $widget->toString();
	}

	/**
	 * Build the (un)subscribe form for Special:Newsletter/$id/(un)subscribe
	 * The actual form showed will be switched depending on whether the current
	 * user is subscribed or not.
	 */
	protected function doSubscribeExecute() {
		// IPs shouldn't be able to subscribe to newsletters
		$this->requireLogin( 'newsletter-subscribe-loginrequired' );
		$this->checkReadOnly();
		$this->getOutput()->setPageTitle( $this->msg( 'newsletter-subscribe' ) );

		if ( $this->newsletter->isSubscribed( $this->getUser() ) ) {
			// User is subscribed so show the unsubscribe form
			$txt = $this->msg( 'newsletter-subscribe-text' )
				->rawParams( $this->getEscapedName() )->parse();
			$button = array(
				'unsubscribe' => array(
					'type' => 'submit',
					'name' => 'unsubscribe',
					'default' => $this->msg( 'newsletter-do-unsubscribe' )->escaped(),
					'id' => 'mw-newsletter-unsubscribe',
					'flags' => array( 'primary', 'destructive' ),
				)
			);
		} else {
			// Show the subscribe form if the user is not subscribed currently
			$txt = $this->msg( 'newsletter-subscribe-text' )
				->rawParams( $this->getEscapedName() )->parse();
			$button = array(
				'subscribe' => array(
					'type' => 'submit',
					'name' => 'subscribe',
					'default' => $this->msg( 'newsletter-do-subscribe' )->escaped(),
					'id' => 'mw-newsletter-subscribe',
					'flags' => array( 'primary', 'constructive' ),
				)
			);
		}

		$form = $this->getHTMLForm( $button, array( $this, 'submitSubscribeForm' ) );
		$form->addHeaderText( $txt );
		$form->suppressDefaultSubmit();
		$form->show();
		$this->getOutput()->addReturnTo( Title::makeTitleSafe( NS_NEWSLETTER, $this->newsletter->getName() ) );
	}

	/**
	 * Submit callback for subscribe form.
	 * @throws Exception
	 * @return Status
	 */
	public function submitSubscribeForm() {
		$request = $this->getRequest();
		$user = $this->getUser();

		if ( $request->getCheck( 'subscribe' ) ) {
			$status = $this->newsletter->subscribe( $user );
			$action = 'subscribe';
		} elseif ( $request->getCheck( 'unsubscribe' ) ) {
			$status = $this->newsletter->unsubscribe( $user );
			$action = 'unsubscribe';
		} else {
			throw new Exception( 'POST data corrupted or required parameter missing from request' );
		}

		if ( $status->isGood() ) {
			// @todo We could probably do this in a better way
			// Add the success message if the action was successful
			// Messages used: 'newsletter-subscribe-success', 'newsletter-unsubscribe-success'
			$this->getOutput()->addHTML(
				$this->msg( "newsletter-$action-success" )
					->rawParams( $this->getEscapedName() )->parse()
			);
		}

		return $status;
	}

	/**
	 * Build the announce form for Special:Newsletter/$id/announce. This does
	 * permissions and read-only check as well and handles showing error and
	 * success pages.
	 *
	 * @throws UserBlockedError
	 */
	protected function doAnnounceExecute() {
		$user = $this->getUser();
		$out = $this->getOutput();

		// Echo handles read-only mode on their own but we'll now let the user know
		// that wiki is currently in read-only mode and stop from here.
		$this->checkReadOnly();

		if ( $user->isBlocked() ) {
			// Blocked users should just stay blocked.
			throw new UserBlockedError( $user->getBlock() );
		}

		if ( !$this->newsletter->isPublisher( $user ) ) {
			$out->showPermissionsErrorPage(
				array( array( 'newsletter-announce-nopermission' ) )
			);
			return;
		}

		$out->setPageTitle(
			$this->msg( 'newsletter-announce' )
				->rawParams( $this->getEscapedName() )
		);

		$fields = array(
			'issuepage' => array(
				'type' => 'title',
				'name' => 'issuepage',
				'creatable' => true,
				'required' => true,
				'label-message' => 'newsletter-announce-issuetitle',
				'default' => '',
			),
			'summary' => array(
				// @todo add a help message explaining what this does
				'type' => 'text',
				'name' => 'summary',
				'label-message' => 'newsletter-announce-summary',
				'maxlength' => '160',
				'autofocus' => true,
			),
		);

		$form = $this->getHTMLForm(
			$fields,
			array( $this, 'submitAnnounceForm' )
		);
		$form->setSubmitTextMsg( 'newsletter-announce-submit' );

		$status = $form->show();
		if ( $status === true ) {
			// Success!
			$out->addHTML(
				$this->msg( 'newsletter-announce-success' )
					->rawParams( $this->getEscapedName() )
					->numParams( $this->newsletter->getSubscriberCount() )
					->parseAsBlock()
			);
			$out->addReturnTo( Title::makeTitleSafe( NS_NEWSLETTER, $this->newsletter->getName() ) );
		}
	}

	/**
	 * Submit callback for the announce form (validate, add to issues table and create
	 * Echo event). This assumes that permissions check etc has been done already.
	 *
	 * @param array $data
	 *
	 * @return Status|bool true on success, Status fatal otherwise
	 * @throws Exception if Echo is not installed
	 */
	public function submitAnnounceForm( array $data ) {
		$title = Title::newFromText( $data['issuepage'] );

		// Do some basic validation on the issue page
		if ( !$title ) {
			return Status::newFatal( 'newsletter-announce-invalid-page' );
		}


		if ( !$title->exists() ) {
			return Status::newFatal( 'newsletter-announce-nonexistent-page' );
		}

		if ( $title->inNamespace( NS_FILE ) ) {
			// Eh..
			return Status::newFatal( 'newsletter-announce-invalid-page' );
		}

		// Validate summary
		$reasonSpamMatch = EditPage::matchSummarySpamRegex( $data['summary'] );
		if ( $reasonSpamMatch ) {
			return Status::newFatal( 'spamprotectionmatch', $reasonSpamMatch );
		}

		if ( !class_exists( 'EchoEvent' ) ) {
			throw new Exception( 'Echo extension is not installed.' );
		}

		$user = $this->getUser();
		if ( $user->pingLimiter( 'newsletter-announce' ) ) {
			// Prevent people from spamming
			throw new ThrottledError;
		}

		$summary = trim( $data['summary'] );

		// Everything seems okay. Let's try to do it for real now.
		$store = NewsletterStore::getDefaultInstance();
		$success = $store->addNewsletterIssue( $this->newsletter, $title, $user, $summary );

		if ( !$success ) {
			// DB insert failed. :( so don't create an Echo event and stop from here
			return Status::newFatal( 'newsletter-announce-failure' );
		}

		EchoEvent::create(
			array(
				'type' => 'newsletter-announce',
				'title' => $title,
				'extra' => array(
					'newsletter-name' => $this->newsletter->getName(),
					'newsletter-id' => $this->newsletter->getId(),
					'section-text' => $summary,
					'notifyAgent' => true,
				),
				'agent' => $user,
			)
		);

		// Yay!
		return true;
	}

	/**
	 * Don't list this page in Special:SpecialPages as we just redirect to
	 * Special:Newsletters if no ID was provided.
	 *
	 * @return bool
	 */
	public function isListed() {
		return false;
	}

}
