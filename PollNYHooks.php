<?php
/**
 * Class containing PollNY's hooked functions.
 * All functions are public and static.
 *
 * @file
 * @ingroup Extensions
 */
class PollNYHooks {

	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) { 
		// Add required CSS & JS via ResourceLoader
		$out->addModules( array('ext.pollNY.css', 'ext.pollNY' ));
	}

	/**
	 * Updates the poll_question table to point to the new title when a page in
	 * the NS_POLL namespace is moved.
	 *
	 * @param $title Object: Title object referring to the old title
	 * @param $newTitle Object: Title object referring to the new (current)
	 *                          title
	 * @param $user Object: User object performing the move [unused]
	 * @param $oldid Integer: old ID of the page
	 * @param $newid Integer: new ID of the page [unused]
	 * @return Boolean: true
	 */
	public static function updatePollQuestion( &$title, &$newTitle, &$user, $oldid, $newid ) {
		if( $title->getNamespace() == NS_POLL ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->update(
				'poll_question',
				array( 'poll_text' => $newTitle->getText() ),
				array( 'poll_page_id' => intval( $oldid ) ),
				__METHOD__
			);
		}
		return true;
	}

	/**
	 * Called when deleting a poll page to make sure that the appropriate poll
	 * database tables will be updated accordingly & memcached will be purged.
	 *
	 * @param $article Object: instance of Article class
	 * @param $user Unused
	 * @param $reason Mixed: deletion reason (unused)
	 * @return Boolean: true
	 */
	public static function deletePollQuestion( &$article, &$user, $reason ) {
		global $wgSupressPageTitle;

		if( $article->getTitle()->getNamespace() == NS_POLL ) {
			$wgSupressPageTitle = true;

			$dbw = wfGetDB( DB_MASTER );

			$s = $dbw->selectRow(
				'poll_question',
				array( 'poll_user_id', 'poll_id' ),
				array( 'poll_page_id' => $article->getID() ),
				__METHOD__
			);
			if ( $s !== false ) {
				// Clear profile cache for user id that created poll
				// global $wgMemc;
				// $key = wfMemcKey( 'user', 'profile', 'polls', $s->poll_user_id );
				// $wgMemc->delete( $key );
				Poll::clearPollCache( $article->getID(), $s->poll_user_id );

				// Delete poll record
				$dbw->delete(
					'poll_user_vote',
					array( 'pv_poll_id' => $s->poll_id ),
					__METHOD__
				);
				$dbw->delete(
					'poll_choice',
					array( 'pc_poll_id' => $s->poll_id ),
					__METHOD__
				);
				$dbw->delete(
					'poll_question',
					array( 'poll_page_id' => $article->getID() ),
					__METHOD__
				);
			}
		}

		return true;
	}

	/**
	 * Rendering for the <userpoll> tag.
	 *
	 * @param $parser Object: instace of Parser class
	 * @return Boolean: true
	 */
	public static function registerUserPollHook( &$parser ) {
		$parser->setHook( 'userpoll', array( 'PollNYHooks', 'renderPollNY' ) );
		return true;
	}

	public static function renderPollNY( $input, $args, $parser ) {
		return '';
	}

	/**
	 * Handles the viewing of pages in the poll namespace.
	 *
	 * @param $title Object: instance of Title class
	 * @param $article Object: instance of Article class
	 * @return Boolean: true
	 */
	public static function pollFromTitle( &$title, &$article ) {
		if ( $title->getNamespace() == NS_POLL ) {
			global $wgRequest, $wgOut;
			global $wgPollScripts, $wgSupressSubTitle, $wgSupressPageCategories;

			// We don't want caching here, it'll only cause problems...
			// $wgOut->enableClientCache( false );
			// $wgHooks['ParserLimitReport'][] = 'PollNYHooks::markUncacheable';

			// Prevents editing of polls
			if( $wgRequest->getVal( 'action' ) == 'edit' ) {
				if( $title->getArticleID() == 0 ) {
					$create = SpecialPage::getTitleFor( 'CreatePoll' );
					$wgOut->redirect(
						$create->getFullURL( 'wpDestName=' . $title->getText() )
					);
				} else {
					$update = SpecialPage::getTitleFor( 'UpdatePoll' );
					$wgOut->redirect(
						$update->getFullURL( 'id=' . $title->getArticleID() )
					);
				}
			}

			$wgSupressSubTitle = true;
			$wgSupressPageCategories = true;

			// Add required JS & CSS
			$wgOut->addModules( 'ext.pollNY' );
			$wgOut->addModuleStyles( 'ext.pollNY.css' );

			$article = new PollPage( $title );
		}

		return true;
	}

	/**
	 * Mark page as uncacheable
	 *
	 * @param $parser Parser object
	 * @param $limitReport String: unused
	 * @return Boolean: true
	 */
	public static function markUncacheable( $parser, &$limitReport ) {
		$parser->disableCache();
		return true;
	}

	/**
	 * Set up the <pollembed> tag for embedding polls on wiki pages.
	 *
	 * @param $parser Object: instance of Parser class
	 * @return Boolean: true
	 */
	public static function registerPollEmbedHook( &$parser ) {
		$parser->setHook( 'pollembed', array( 'PollNYHooks', 'renderEmbedPoll' ) );
		return true;
	}

	public static function followPollID( $pollTitle ) {
		$pollArticle = new Article( $pollTitle );
		$pollWikiContent = $pollArticle->getContent();

		if( $pollArticle->isRedirect( $pollWikiContent ) ) {
			$pollTitle = $pollArticle->followRedirect();
			return PollNYHooks::followPollID( $pollTitle );
		} else {
			return $pollTitle;
		}
	}

	/**
	 * Callback function for the <pollembed> tag.
	 *
	 * @param $input Mixed: user input
	 * @param $args Array: arguments supplied to the pollembed tag
	 * @param $parser Object: instance of Parser class
	 * @return HTML or nothing
	 */
	public static function renderEmbedPoll( $input, $args, $parser ) {
		$poll_name = $args['title'];
		if( $poll_name ) {
			global $wgOut, $wgUser, $wgExtensionAssetsPath, $wgPollDisplay;

			// Load CSS for non-Monaco skins - Monaco's ny.css already contains
			// PollNY's styles (and more)
			if ( get_class( $wgOut->getSkin() ) !== 'SkinMonaco' ) {
				$wgOut->addModuleStyles( 'ext.pollNY.css' );
			}

			// Disable caching; this is important so that we don't cause subtle
			// bugs that are a bitch to fix.
			// $wgOut->enableClientCache( false );
			// $parser->disableCache();

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

				$output = "\t\t" . '<div><div class="poll-embed-title">投票:' .
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
							)->text() . '<a id="vote-login" data-toggle="modal" data-target=".user-login">登录</a>。</div>' . "\n";
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
					$output .= '</div></div>';
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

						$output .= "<div class=\"poll-choice-right\" style=\"width:{$choice['percent']}%\"></div>";
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

		return '';
	}

	/**
	 * Adds the three new tables to the database when the user runs
	 * maintenance/update.php.
	 *
	 * @param $updater DatabaseUpdater
	 * @return Boolean: true
	 */
	public static function addTables( $updater ) {
		$dir = dirname( __FILE__ );
		$file = "$dir/poll.sql";

		$updater->addExtensionUpdate( array( 'addTable', 'poll_choice', $file, true ) );
		$updater->addExtensionUpdate( array( 'addTable', 'poll_question', $file, true ) );
		$updater->addExtensionUpdate( array( 'addTable', 'poll_user_vote', $file, true ) );

		return true;
	}

	/**
	 * For the Renameuser extension
	 *
	 * @param $renameUserSQL
	 * @return Boolean: true
	 */
	public static function onUserRename( $renameUserSQL ) {
		// poll_choice table has no information related to the user
		$renameUserSQL->tables['poll_question'] = array( 'poll_user_name', 'poll_user_id' );
		$renameUserSQL->tables['poll_user_vote'] = array( 'pv_user_name', 'pv_user_id' );
		return true;
	}

	/**
	 * Register the canonical names for our namespace and its talkspace.
	 *
	 * @param $list Array: array of namespace numbers with corresponding
	 *                     canonical names
	 * @return Boolean: true
	 */
	public static function onCanonicalNamespaces( &$list ) {
		$list[NS_POLL] = 'Poll';
		$list[NS_POLL_TALK] = 'Poll_talk';
		return true;
	}
	public static function onSkinTemplateToolboxEnd( &$skinTemplate ){
		$title = SpecialPage::getTitleFor('CreatePoll');
		$line = Linker::LinkKnown($title, '<i class="icon-pie-chart "></i> 创建投票', array('class'=>'poll-page') );
		echo Html::rawElement( 'li', array(), $line );
		return true;
	}
}
