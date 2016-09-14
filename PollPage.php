<?php

class PollPage extends Article {

	public $title = null;

	/**
	 * Constructor and clear the article
	 * @param $title Object: reference to a Title object.
	 */
	public function __construct( Title $title ) {
		parent::__construct( $title );
	}

	/**
	 * Called on every poll page view.
	 */
	public function view() {
		global $wgUser, $wgOut, $wgRequest, $wgExtensionAssetsPath, $wgUploadPath;
		global $wgSupressPageTitle, $wgNameSpacesWithEditMenu;

		// Perform no custom handling if the poll in question has been deleted
		if ( !$this->getID() ) {
			parent::view();
		}

		$wgSupressPageTitle = true;

		// WHAT DOES MARSELLUS WALLACE LOOK LIKE?
		$what = $this->getContext();

		$title = $this->getTitle();
		$lang = $what->getLanguage();

		$wgOut->setHTMLTitle( $title->getText() );
		$wgOut->setPageTitle( $title->getText() );

		$wgNameSpacesWithEditMenu[] = NS_POLL;

		$createPollObj = SpecialPage::getTitleFor( 'CreatePoll' );

		// Get total polls count so we can tell the user how many they have
		// voted for out of total
		$dbr = wfGetDB( DB_MASTER );
		$total_polls = 0;
		$s = $dbr->selectRow(
			'poll_question',
			array( 'COUNT(*) AS count' ),
			array(),
			__METHOD__
		);
		if ( $s !== false ) {
			$total_polls = $lang->formatNum( $s->count );
		}

		$stats = new UserStats( $wgUser->getID(), $wgUser->getName() );
		$stats_current_user = $stats->getUserStats();

		$p = new Poll();
		$poll_info = $p->getPoll( $title->getArticleID() );

		if( !isset( $poll_info['id'] ) ) {
			return '';
		}

		$imgPath = $wgExtensionAssetsPath . '/SocialProfile/images';

		// Set up submitter data
		$user_title = Title::makeTitle( NS_USER, $poll_info['user_name'] );
		$avatar = new wAvatar( $poll_info['user_id'], 'ml' );
		// $avatarID = $avatar->getAvatarImage();
		$mAvatarAnchor = $avatar->getAvatarAnchor();
		// $stats = new UserStats( $poll_info['user_id'], $poll_info['user_name'] );
		// $stats_data = $stats->getUserStats();
		$user_name_short = $lang->truncate( $poll_info['user_name'], 27 );

		$profile = new UserProfile( $poll_info['user_name'] );
		$profileData = $profile->getProfile();

		$poll_embed_name = htmlspecialchars( $title->getText(), ENT_QUOTES );
		$poll_embed_link = "<pollembed id=\"{$poll_info['id']}\" title=\"{$poll_embed_name}\" />";

		$output = '<div class="row">';

		$output .= '<div class="col-md-8">';

		$output .= $this->getContext()->getOutput()->parse($poll_embed_link);

		$output .= '</div>';

		$output .= '<div class="col-md-4">';
		// Show the "create a poll" link to registered users
		// if( $wgUser->isLoggedIn() ) {
		// 	$output .= '<div class="create-link">
		// 		<a href="' . htmlspecialchars( $createPollObj->getFullURL() ) . '">
		// 			<img src="' . $imgPath . '/addIcon.gif" alt="" />'
		// 			. wfMessage( 'poll-create' )->text() .
		// 		'</a>
		// 	</div>';
		// }
		// $formattedVoteCount = $lang->formatNum( $stats_data['poll_votes'] );
		// $formattedEditCount = $lang->formatNum( $stats_data['edits'] );
		// $formattedCommentCount = $lang->formatNum( $stats_data['comments'] );
		// <li>
		// 	<img src=\"{$imgPath}/voteIcon.gif\" alt=\"\" />
		// 	{$formattedVoteCount}
		// </li>
		// <li>
		// 	<img src=\"{$imgPath}/editIcon.gif\" alt=\"\" />
		// 	{$formattedEditCount}
		// </li>
		// <li>
		// 	<img src=\"{$imgPath}/commentsIcon.gif\" alt=\"\" />
		// 	{$formattedCommentCount}
		// </li>
		$output .= '<div class="well author-container">
					<div class="author-info">
						'.$mAvatarAnchor."
					</div>
					<div class=\"author-title\">
						".Linker::userLink($poll_info['user_id'], $poll_info['user_name'])."
					</div>";
		if ( $profileData['about'] ) {
			$output .= $profileData['about'];
		}
		$output .= '</div>'; // end of author-container

		//$output .= '<div>' // more poll by the same author
		// $output .= '<div class="well poll-stats">';

		// if( $wgUser->isLoggedIn() ) {
		// 	$output .= wfMessage(
		// 		'poll-voted-for',
		// 		'<b>' . $stats_current_user['poll_votes'] . '</b>',
		// 		$total_polls,
		// 		$lang->formatNum( $stats_current_user['poll_votes'] )
		// 	)->parse();
		// } else {
		// 	$register_title = SpecialPage::getTitleFor( 'Userlogin', 'signup' );
		// 	$output .= '<div class="alert alert-warning" roll="alert">' . wfMessage(
		// 			'poll-nologin-message',
		// 			htmlspecialchars( $register_title->getFullURL() )
		// 		)->text() . '<a id="vote-login" data-toggle="modal" data-target=".user-login">登录</a>。</div>' . "\n";
		// }

		// $output .= '</div>' . "\n";

		$toggle_flag_label = ( ( $poll_info['status'] == 1 ) ? wfMessage( 'poll-flag-poll' )->text() : wfMessage( 'poll-unflag-poll' )->text() );
		$toggle_flag_status = ( ( $poll_info['status'] == 1 ) ? 2 : 1 );

		if( $poll_info['status'] == 1 ) {
			// Creator and admins can change the status of a poll
			$toggle_label = ( ( $poll_info['status'] == 1 ) ? wfMessage( 'poll-close-poll' )->text() : wfMessage( 'poll-open-poll' )->text() );
			$toggle_status = ( ( $poll_info['status'] == 1 ) ? 0 : 1 );
		}

		$output .= '<div class="well link-container">' . "\n";
		// "Embed this on a wiki page" feature
		
		$output .= '<b>' . wfMessage( 'poll-embed' )->plain() . "</b>
						<form name=\"embed_poll\">
							<input name='embed_code' style='width:300px;font-size:10px;' type='text' value='$poll_embed_link' onclick='javascript:document.embed_poll.embed_code.focus();document.embed_poll.embed_code.select();' readonly='readonly' />
						</form>\n";

		$adminLinks = array();
		// Poll administrators can access the poll admin panel
		if( $wgUser->isAllowed( 'polladmin' ) ) {
			$adminLinks[] = Linker::link(
				SpecialPage::getTitleFor( 'AdminPoll' ),
				wfMessage( 'poll-admin-panel' )->text()
			);
		}
		// if( $poll_info['status'] == 1 && ( $poll_info['user_id'] == $wgUser->getID() || $wgUser->isAllowed( 'polladmin' ) ) ) {
		if( $poll_info['status'] == 1 && $wgUser->isAllowed( 'polladmin' ) ) {
			$adminLinks[] = "<a class=\"poll-status-toggle-link\" href=\"javascript:void(0)\" data-status=\"{$toggle_status}\">{$toggle_label}</a>";
		}
		// if( $poll_info['status'] == 1 || $wgUser->isAllowed( 'polladmin' ) ) {$poll_info['user_id'] == $wgUser->getID()
		if( $poll_info['user_id'] == $wgUser->getID() || $wgUser->isAllowed( 'polladmin' ) ) {
			$adminLinks[] = "<a class=\"poll-status-toggle-link\" href=\"javascript:void(0)\" data-status=\"{$toggle_flag_status}\">{$toggle_flag_label}</a>";
		}
		if ( !empty( $adminLinks ) ) {
			$output .= $lang->pipeList( $adminLinks );
		}

		$output .= "\n" . '</div>' . "\n"; // .poll-links
		// Safelinks
		$random_poll_link = SpecialPage::getTitleFor( 'RandomPoll' );
		$output .= "
		<div class=\"well more-container\">"."<ul><li><a href=\"" 
			. htmlspecialchars( SpecialPage::getTitleFor( 'ViewPoll' )->getFullURL( 'user=' . $poll_info['user_name'] ) ) . '">'
			. wfMessage( 'poll-view-all-by', $user_name_short, $poll_info['user_name'] )->parse() . '</a></li><li>'.'
			<a href="' . htmlspecialchars( $random_poll_link->getFullURL() ) . '">' .
				wfmessage( 'poll-take-button' )->text() .
			'</a></li></ul>
		</div>';

		// $output .= $this->getOtherPolls();

		$output .= '</div>' . "\n"; // end of col-md-4

		$wgOut->addHtml($output);
		$wgOut->addWikiText( '<div class="clearfix"></div><comments/>' );
		
	}
	public static function getFollowingUserPolls($choice_user){
		global $wgUser;
		$output = '';
		$hjUser = HuijiUser::newFromName( $wgUser->getName() );
		if (!$hjUser->isLoggedIn()){
			return '';
		}
		$following = $hjUser->getFollowingUsers();
		$f_all_user = array();
		foreach ($following as $key => $value) {
			$f_all_user[] = $value['user_name'];
		}
		if ( count( $choice_user ) > 0 ) {
			$following_voter = array();
			foreach ( $choice_user as $key => $value) {
				// echo $value['pv_user_name'];die();
				if( in_array( $value['pv_user_name'], $f_all_user ) && !in_array( $value['pv_user_name'], $following_voter ) ){
					$following_voter[] = $value['pv_user_name'];
				}
			}
			if ( count($following_voter) > 0 ) {
				$follow_vote_user = '';
				if ( count($following_voter) > 4 ) {
					// Linker::link( $userPage, $following_voter[0], array(), array() );
					$follow_vote_user = Linker::link( User::newFromName($following_voter[0])->getUserPage(), $following_voter[0], array(), array() )."&nbsp;".
										Linker::link( User::newFromName($following_voter[1])->getUserPage(), $following_voter[1], array(), array() )."&nbsp;".
										Linker::link( User::newFromName($following_voter[2])->getUserPage(), $following_voter[2], array(), array() )."和".
										Linker::link( User::newFromName($following_voter[3])->getUserPage(), $following_voter[3], array(), array() )."等";
				}else{
					for ($i=0; $i < count($following_voter); $i++) { 
						$follow_vote_user .= Linker::link( User::newFromName($following_voter[$i])->getUserPage(), $following_voter[$i], array(), array() );
						if ($i == count($following_voter)-2 ){
							$follow_vote_user .= '和';
						}else {
							$follow_vote_user .= '&nbsp;';
						}
					}
				}
				$output .= "<span class=\"poll-user\">".wfMessage('poll-user')->params($follow_vote_user)->text()."</span>";
			}

		}
		return $output;
	}
}
