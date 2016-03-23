<?php
/**
 * A special page for creating new polls.
 * @file
 * @ingroup Extensions
 */
class CreatePoll extends SpecialPage {

	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct() {
		parent::__construct( 'CreatePoll' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Show the special page
	 *
	 * @param $par Mixed: parameter passed to the page or null
	 */
	public function execute( $par ) {
		global $wgMemc, $wgContLang, $wgSupressPageTitle;

		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		$wgSupressPageTitle = true;

		// Blocked users cannot create polls
		if( $user->isBlocked() ) {
			throw new UserBlockedError( $user->getBlock() );
		}

		// Check that the DB isn't locked
		if( wfReadOnly() ) {
			$out->readOnlyPage();
			return;
		}

		/**
		 * Redirect anonymous users to login page
		 * It will automatically return them to the CreatePoll page
		 */
		if( $user->getID() == 0 ) {
			$out->setPageTitle( $this->msg( 'poll-woops' )->plain() );
			$login = SpecialPage::getTitleFor( 'Userlogin' );
			$out->redirect( $login->getLocalURL( 'returnto=Special:CreatePoll' ) );
			return false;
		}

		/**
		 * Create Poll Thresholds based on User Stats
		 */
		global $wgCreatePollThresholds;
		if( is_array( $wgCreatePollThresholds ) && count( $wgCreatePollThresholds ) > 0 ) {
			$canCreate = 1;

			$stats = new UserStats( $user->getID(), $user->getName() );
			$stats_data = $stats->getUserStats();

			$threshold_reason = '';
			foreach( $wgCreatePollThresholds as $field => $threshold ) {
				if ( (int)str_replace(',','',$stats_data[$field]) < $threshold ) {
					$canCreate = 2;
					$threshold_reason = $threshold."次编辑";
				}
			}
			
			if( $canCreate == 2 ) {
				$wgSupressPageTitle = false;
				$out->setPageTitle( $this->msg( 'poll-create-threshold-title' )->plain() );
				$out->addWikiMsg( 'poll-create-threshold-reason', $threshold_reason );
				return '';
			}
		}

		// Add CSS & JS
		$out->addModuleStyles( 'ext.pollNY.css' );
		$out->addModules( 'ext.pollNY' );

		// If the request was POSTed, try creating the poll
		if( $request->wasPosted() && $_SESSION['alreadysubmitted'] == false ) {
			$_SESSION['alreadysubmitted'] = true;

			// Add poll
			$poll_title = Title::makeTitleSafe( NS_POLL, $request->getVal( 'poll_question' ) );
			if( is_null( $poll_title ) && !$poll_title instanceof Title ) {
				$wgSupressPageTitle = false;
				$out->setPageTitle( $this->msg( 'poll-create-threshold-title' )->plain() );
				$out->addWikiMsg( 'poll-create-threshold-reason', $threshold_reason );
				return '';
			}

			// Put choices in wikitext (so we can track changes)
			$choices = '';
			for( $x = 1; $x <= 10; $x++ ) {
				if( $request->getVal( "answer_{$x}" ) ) {
					$choices .= $request->getVal( "answer_{$x}" ) . "\n";
				}
			}

			// Create poll wiki page
			$localizedCategoryNS = $wgContLang->getNsText( NS_CATEGORY );
			$article = new Article( $poll_title );
			$article->doEdit(
				"<userpoll>\n$choices</userpoll>\n\n[[" .
					$localizedCategoryNS . ':' .
					$this->msg( 'poll-category' )->inContentLanguage()->plain() . "]]\n" .
				'[[' . $localizedCategoryNS . ':' .
					$this->msg( 'poll-category-user', $user->getName() )->inContentLanguage()->text()  . "]]\n" .
				'[[' . $localizedCategoryNS . ":{{subst:CURRENTMONTHNAME}} {{subst:CURRENTDAY}}, {{subst:CURRENTYEAR}}]]\n\n__NOEDITSECTION__",
				$this->msg( 'poll-edit-desc' )->inContentLanguage()->plain()
			);

			$newPageId = $article->getID();

			$p = new Poll();
			// print_r($request);
			// echo $request->getVal( 'poll_question' );die();
			$poll_id = $p->addPollQuestion(
				$request->getVal( 'poll_question' ),
				$request->getVal( 'poll_image_name' ),
				$newPageId
			);

			// Add choices
			for( $x = 1; $x <= 10; $x++ ) {
				if( $request->getVal( "answer_{$x}" ) ) {
					$p->addPollChoice(
						$poll_id,
						$request->getVal( "answer_{$x}" ),
						$x
					);
				}
			}

			// Clear poll cache
			$key = wfMemcKey( 'user', 'profile', 'polls', $user->getID() );
			$wgMemc->delete( $key );

			// Redirect to new poll page
			$out->redirect( $poll_title->getFullURL() );
		} else {
			$_SESSION['alreadysubmitted'] = false;
			// Load the GUI template class
			include( 'create-poll.tmpl.php' );
			$template = new CreatePollTemplate;
			// Expose _this_ class to the GUI template
			$template->setRef( 'parentClass', $this );
			// And output the template!
			$out->addTemplate( $template );
		}
	}

	protected function getGroupName() {
		return 'poll';
	}
}