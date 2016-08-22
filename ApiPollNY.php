<?php
/**
 * PollNY API module
 *
 * @file
 * @ingroup API
 * @date 21 July 2013
 * @see http://www.mediawiki.org/wiki/API:Extensions#ApiSampleApiExtension.php
 */
class ApiPollNY extends ApiBase {

	/**
	 * @var Poll Instance of the Poll class, set in execute() below
	 */
	private $poll;

	/**
	 * Main entry point.
	 */
	public function execute() {
		$user = $this->getUser();

		// Get the request parameters
		$params = $this->extractRequestParams();

		$action = $params['what'];

		// If the "what" param isn't present, we don't know what to do!
		if ( !$action || $action === null ) {
			$this->dieUsageMsg( 'missingparam' );
		}

		$pollID = $params['pollID'];
		// Ensure that the pollID parameter is present for actions that require
		// it and that it really is numeric
		if (
			in_array( $action, array( 'delete', 'updateStatus', 'vote' ) ) &&
			( !$pollID || $pollID === null || !is_numeric( $pollID ) )
		)
		{
			$this->dieUsageMsg( 'missingparam' );
		}

		// Action-specific parameter validation stuff
		if ( $action == 'getPollResults' ) {
			$pageID = $params['pageID'];
			if ( !$pageID || $pageID === null || !is_numeric( $pageID ) ) {
				$this->dieUsageMsg( 'missingparam' );
			}
		} elseif ( $action == 'updateStatus' ) {
			$status = $params['status'];
			if ( $status === null || !is_numeric( $status ) ) {
				$this->dieUsageMsg( 'missingparam' );
			}
		} elseif ( $action == 'titleExists' ) {
			if ( !$params['pageName'] || $params['pageName'] === null ) {
				$this->dieUsageMsg( 'missingparam' );
			}
		} elseif ( $action == 'vote' ) {
			$choiceID = $params['choiceID'];
			$pageId = $params['pageID'];
			if ( !$choiceID || $choiceID === null || !is_numeric( $choiceID ) || !$pageId || $pageId === null || !is_numeric( $pageId ) ) {
				$this->dieUsageMsg( 'missingparam' );
			}
		}

		// Set the private class member variable
		$this->poll = new Poll();

		// Decide what function to call
		switch ( $action ) {
			case 'delete':
				$output = $this->delete( $pollID );
				break;
			case 'getPollResults':
				$output = $this->getPollResults( $pageID );
				break;
			case 'getRandom':
				$output = $this->poll->getRandomPollURL( $user->getName() );
				break;
			case 'updateStatus':
				$output = $this->updateStatus( $pollID, $params['status'] );
				break;
			case 'titleExists':
				$output = $this->titleExists( $params['pageName'] );
				break;
			case 'vote':
				$output = $this->vote( $pageId, $pollID, (int) $params['choiceID'] );
				break;
			case 'render':
				$output = $this->render($params['pollName']);
			default:
				break;
		}

		// Top level
		$this->getResult()->addValue( null, $this->getModuleName(),
			array( 'result' => $output )
		);

		return true;
	}

	function delete( $pollID ) {
		if ( !$this->getUser()->isAllowed( 'polladmin' ) ) {
			return '';
		}

		if ( $pollID > 0 ) {
			$dbw = wfGetDB( DB_MASTER );
			$s = $dbw->selectRow(
				'poll_question',
				array( 'poll_page_id' ),
				array( 'poll_id' => intval( $pollID ) ),
				__METHOD__
			);

			if ( $s !== false ) {
				$dbw->delete(
					'poll_user_vote',
					array( 'pv_poll_id' => intval( $pollID ) ),
					__METHOD__
				);

				$dbw->delete(
					'poll_choice',
					array( 'pc_poll_id' => intval( $pollID ) ),
					__METHOD__
				);

				$dbw->delete(
					'poll_question',
					array( 'poll_page_id' => $s->poll_page_id ),
					__METHOD__
				);

				$pollTitle = Title::newFromId( $s->poll_page_id );
				$article = new Article( $pollTitle );
				$article->doDeleteArticle( 'delete poll' );
			}
		}

		return 'OK';
	}

	function getPollResults( $pageID ) {
		global $wgExtensionAssetsPath;

		$poll_info = $this->poll->getPoll( $pageID );
		$x = 1;

		$output = '';
		foreach ( $poll_info['choices'] as $choice ) {
			//$percent = round( $choice['votes'] / $poll_info['votes'] * 100 );
			if ( $poll_info['votes'] > 0 ) {
				$bar_width = floor( 480 * ( $choice['votes'] / $poll_info['votes'] ) );
			}
			$bar_img = "<img src=\"{$wgExtensionAssetsPath}/SocialProfile/images/vote-bar-{$x}.gif\" border=\"0\" class=\"image-choice-{$x}\" style=\"width:{$choice['percent']}%;height:12px;\"/>";

			$output .= "<div class=\"poll-choice\">
		<div class=\"poll-choice-left\">{$choice['choice']} ({$choice['percent']}%)</div>";

			$output .= "<div class=\"poll-choice-right primary\" style=\"width:{$choice['percent']}%\"> <span class=\"poll-choice-votes\">" .
				wfMessage( 'poll-votes', $choice['votes'] )->parse() . PollPage::getFollowingUserPolls($choice['vote_users']).
				'</span></div>';
			$output .= '</div>';

			$x++;
		}

		return $output;
	}

	function titleExists( $pageName ) {
		// Construct page title object to convert to database key
		$pageTitle = Title::makeTitle( NS_MAIN, urldecode( $pageName ) );
		$dbKey = $pageTitle->getDBKey();

		// Database key would be in page_title if the page already exists
		$dbr = wfGetDB( DB_MASTER );
		$s = $dbr->selectRow(
			'page',
			array( 'page_id' ),
			array( 'page_title' => $dbKey, 'page_namespace' => NS_POLL ),
			__METHOD__
		);
		if ( $s !== false ) {
			return 'Page exists';
		} else {
			return 'OK';
		}
	}

	function updateStatus( $pollID, $status ) {
		// return $status;
		if(
			$status > 0 &&
			( $this->poll->doesUserOwnPoll( $this->getUser()->getID(), $pollID ) ||
			$this->getUser()->isAllowed( 'polladmin' ) )
		) {
			$this->poll->updatePollStatus( $pollID, $status );
			return 'Status successfully changed';
		} elseif (
			$status == 0 &&
			$this->getUser()->isAllowed( 'polladmin' )
		) {
			$this->poll->updatePollStatus( $pollID, $status );
			return 'Status successfully changed';
		} else {
			return 'error';
		}
	}

	function vote( $pageId, $pollID, $choiceID ) {
		$user = $this->getUser();
		if ( !$user->isAllowed( 'pollny-vote' ) ) {
			return 'error';
		}
		if (
			!$this->poll->userVoted( $user->getName(), $pollID ) &&
			$user->isAllowed( 'pollny-vote' )
		)
		{
			$this->poll->addPollVote( $pageId, $pollID, $choiceID );
		}

		return 'OK';
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getDescription() {
		return 'PollNY API - includes both user and admin functions';
	}

	/**
	 * @return Array
	 */
	public function getAllowedParams() {
		return array(
			'what' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'choiceID' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
			'pageName' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'pollID' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
			'pageID' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
			'status' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
			'pollName' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
		);
	}
	public function render( $poll_name ) {
					// Disable caching; this is important so that we don't cause subtle
			// bugs that are a bitch to fix.
			// $wgOut->enableClientCache( false );
			// $parser->disableCache();
		global $wgOut, $wgUser, $wgExtensionAssetsPath, $wgPollDisplay;

		$poll_title = Title::newFromText( $poll_name, NS_POLL );
		$poll_title = PollNYHooks::followPollID( $poll_title );
		$poll_page_id = $poll_title->getArticleID();
		// if ( $wgUser->getID() == 0 ) {
		// 	$output = '<h1>no login</h1>';
		// 	return $output;
		// }
		if( $poll_page_id > 0 ) {
			$p = new Poll();
			$poll_info = $p->getPoll( $poll_page_id );

			$output = "\t\t" . '<div class="poll-embed-title">' .wfMessage('poll-embed-title-prefix')->text() .
				$poll_info['question'] .
			'</div>' . "\n";


			// If the user hasn't voted for this poll yet, they're allowed
			// to do so and the poll is open for votes, display the question
			// and let the user vote
			if (
				$wgUser->isAllowed( 'pollny-vote' ) &&
				!$p->userVoted( $wgUser->getName(), $poll_info['id'] ) &&
				$poll_info['status'] == 1
			)
			{
				$output .= '<div class="poll-wrap">';
				if ( !$wgUser->isLoggedIn() ) {
					$register_title = SpecialPage::getTitleFor( 'Userlogin', 'signup' );
					$output .= '<div class="c-form-message">' . wfMessage(
							'poll-nologin-message',
							htmlspecialchars( $register_title->getFullURL() )
						)->text() . '<a href="/wiki/Special:UserLogin">登录</a>。</div>' . "\n";
				}else{
					$wgOut->addModules( 'ext.pollNY' );
					$output .= "<div id=\"loading-poll_{$poll_info['id']}\" class=\"poll-loading-msg\"></div>";
					$output .= "<div id=\"poll-display_{$poll_info['id']}\" style=\"display;\">";
					$output .= "<form name=\"poll_{$poll_info['id']}\"><input type=\"hidden\" id=\"poll_id_{$poll_info['id']}\" name=\"poll_id_{$poll_info['id']}\" value=\"{$poll_info['id']}\"/>";

					foreach( $poll_info['choices'] as $choice ) {
						$output .= "<div class=\"poll-choice\">
						<input type=\"radio\" name=\"poll_choice\" data-poll-id=\"{$poll_info['id']}\" data-poll-page-id=\"{$poll_page_id}\" id=\"poll_choice\" value=\"{$choice['id']}\">{$choice['choice']}
						</div>";
					}

					$output .= '</form>';
				}
				$output .= '</div>';
			} else {
				// Display message if poll has been closed for voting
				if( $poll_info['status'] == 0 ) {
					$output .= '<div class="poll-closed">' .
						wfMessage( 'poll-closed' )->text() . '</div>';
				}

				$x = 1;
				$output .= '<div class="poll-wrap">';
				foreach( $poll_info['choices'] as $choice ) {
					//$percent = round( $choice['votes'] / $poll_info['votes'] * 100 );
					if( $poll_info['votes'] > 0 ) {
						$bar_width = floor( 480 * ( $choice['votes'] / $poll_info['votes'] ) );
					}
					$output .= "<div class=\"poll-choice\">
					<div class=\"poll-choice-left\">{$choice['choice']} ({$choice['percent']}%) <span class=\"poll-choice-votes\">" .
						wfMessage( 'poll-votes', $choice['votes'] )->parse() . PollPage::getFollowingUserPolls($choice['vote_users'])."</span></div>";

					// If the amount of votes is not set, set it to 0
					// This fixes an odd bug where "votes" would be shown
					// instead of "0 votes" when using the pollembed tag.
					if ( empty( $choice['votes'] ) ) {
						$choice['votes'] = 0;
					}

					$output .= "<div class=\"poll-choice-right primary\" style=\"width:{$choice['percent']}%\"></div>";
					$output .= '</div>';

					$x++;
				}

				$output .= '<div class="poll-total-votes">(' .
					wfMessage(
						'poll-based-on-votes',
						$poll_info['votes']
					)->parse() . ')</div>';
				if ( isset( $wgPollDisplay['comments'] ) && $wgPollDisplay['comments'] ) {
					$output .= '<div><a href="' . htmlspecialchars( $poll_title->getFullURL() ) . '">' .
						wfMessage( 'poll-discuss' )->text() . '</a></div>';
				}
				$output .= '<div class="poll-timestamp">' .
					wfMessage( 'poll-createdago', Poll::getTimeAgo( $poll_info['timestamp'] ) )->parse() .
				'</div></div>';
			}
			$output .= '</div>';

			return $output;
		} else {
			// Poll doesn't exist or is unavailable for some other reason
			$output = '<div class="poll-embed-title">' .
				wfMessage( 'poll-unavailable' )->text() . '</div>';
			return $output;
		}
	
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getParamDescription() {
		return array_merge( parent::getParamDescription(), array(
			'what' => 'What to do?',
			'choiceID' => 'Same as clicking the <choiceID>th choice via the GUI; only used when what=vote',
			'pageName' => 'Title to check for (only used when what=titleExists); should be URL-encoded',
			'pollID' => 'Poll ID of the poll that is being deleted/updated/voted for',
			'pageID' => 'Page ID (only used when what=getPollResults)',
			'status' => 'New status of the poll (when what=updateStatus); possible values are 0 (=closed), 1 and 2 (=flagged)',
		) );
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getExamples() {
		return array(
			'api.php?action=pollny&what=delete&pollID=66' => 'Deletes the poll #66',
			'api.php?action=pollny&what=getPollResults&pollID=666' => 'Gets the results of the poll #666',
			'api.php?action=pollny&what=getRandom' => 'Gets a random poll to which the current user hasn\'t answered yet',
			'api.php?action=pollny&what=titleExists&pageName=Is%20PollNY%20awesome%3F' => 'Checks if there is already a poll with the title "Is PollNY awesome?"',
			'api.php?action=pollny&what=updateStatus&pollID=47&status=1' => 'Sets the status of the poll #47 to 1 (=open); possible status values are 0 (=closed), 1 and 2 (=flagged)',
			'api.php?action=pollny&what=vote&pollID=33&choiceID=4' => 'Votes (answers) the poll #33 with the 4th choice',
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return array(
			'action=pollny&what=delete&pollID=66'
				=> 'apihelp-pollny-example-1',
			'action=pollny&what=getPollResults&pollID=666'
				=> 'apihelp-pollny-example-2',
			'action=pollny&what=getRandom'
				=> 'apihelp-pollny-example-3',
			'action=pollny&what=titleExists&pageName=Is%20PollNY%20awesome%3F'
				=> 'apihelp-pollny-example-4',
			'action=pollny&what=updateStatus&pollID=47&status=1'
				=> 'apihelp-pollny-example-5',
			'action=pollny&what=vote&pollID=33&choiceID=4'
				=> 'apihelp-pollny-example-6'
		);
	}
}
