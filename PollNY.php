<?php
/**
 * PollNY extension
 * Defines a new namespace for polls (NS_POLL, the namespace number is 300 by
 * default) and 6 new special pages for poll creation/administration.
 *
 * @file
 * @ingroup Extensions
 * @author Aaron Wright <aaron.wright@gmail.com>
 * @author David Pean <david.pean@gmail.com>
 * @author Jack Phoenix <jack@countervandalism.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link https://www.mediawiki.org/wiki/Extension:PollNY Documentation
 */

// Extension credits that will show up on Special:Version
$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'PollNY',
	'version' => '3.3.3',
	'author' => array( 'Aaron Wright', 'David Pean', 'Jack Phoenix' ),
	'descriptionmsg' => 'poll-desc',
	'url' => 'https://www.mediawiki.org/wiki/Extension:PollNY'
);

// Global poll namespace reference
// If you change this, you'll need to edit Poll.js (the onload handlers section)
define( 'NS_POLL', 300 );
define( 'NS_POLL_TALK', 301 );

# Configuration section
// Display comments on poll pages? Requires the Comments extension.
$wgPollDisplay['comments'] = false;

// For example: 'edits' => 5 if you want to require users to have at least 5
// edits before they can create new polls.
$wgCreatePollThresholds = array('edits' => 5);
# End configuration values

// New user right for voting in polls
$wgAvailableRights[] = 'pollny-vote';
$wgGroupPermissions['*']['pollny-vote'] = true;

// New user right for administering polls
$wgAvailableRights[] = 'polladmin';
$wgGroupPermissions['sysop']['polladmin'] = true;
$wgGroupPermissions['staff']['polladmin'] = true;

// Set up the new special pages
$wgMessagesDirs['PollNY'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['PollNYAlias'] = __DIR__ . '/Poll.alias.php';
// Namespace translations
$wgExtensionMessagesFiles['PollNYNamespaces'] = __DIR__ . '/Poll.namespaces.php';

$wgAutoloadClasses['AdminPoll'] = __DIR__ . '/SpecialAdminPoll.php';
$wgAutoloadClasses['CreatePoll'] = __DIR__ . '/SpecialCreatePoll.php';
$wgAutoloadClasses['Poll'] = __DIR__ . '/PollClass.php';
$wgAutoloadClasses['PollPage'] = __DIR__ . '/PollPage.php';
$wgAutoloadClasses['RandomPoll'] = __DIR__ . '/SpecialRandomPoll.php';
$wgAutoloadClasses['UpdatePoll'] = __DIR__ . '/SpecialUpdatePoll.php';
$wgAutoloadClasses['ViewPoll'] = __DIR__ . '/SpecialViewPoll.php';

$wgSpecialPages['AdminPoll'] = 'AdminPoll';
$wgSpecialPages['CreatePoll'] = 'CreatePoll';
$wgSpecialPages['RandomPoll'] = 'RandomPoll';
$wgSpecialPages['UpdatePoll'] = 'UpdatePoll';
$wgSpecialPages['ViewPoll'] = 'ViewPoll';

// Upload form
$wgAutoloadClasses['SpecialPollAjaxUpload'] = __DIR__ . '/MiniAjaxUpload.php';
$wgAutoloadClasses['PollAjaxUploadForm'] = __DIR__ . '/MiniAjaxUpload.php';
$wgAutoloadClasses['PollUpload'] = __DIR__ . '/MiniAjaxUpload.php';
$wgSpecialPages['PollAjaxUpload'] = 'SpecialPollAjaxUpload';

// Load the API module
$wgAutoloadClasses['ApiPollNY'] = __DIR__ . '/ApiPollNY.php';
$wgAPIModules['pollny'] = 'ApiPollNY';

// Hooked functions
$wgAutoloadClasses['PollNYHooks'] = __DIR__ . '/PollNYHooks.php';

$wgHooks['TitleMoveComplete'][] = 'PollNYHooks::updatePollQuestion';
$wgHooks['ArticleDelete'][] = 'PollNYHooks::deletePollQuestion';
$wgHooks['ParserFirstCallInit'][] = 'PollNYHooks::registerUserPollHook';
$wgHooks['ParserFirstCallInit'][] = 'PollNYHooks::registerPollEmbedHook';
$wgHooks['ArticleFromTitle'][] = 'PollNYHooks::pollFromTitle';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'PollNYHooks::addTables';
$wgHooks['RenameUserSQL'][] = 'PollNYHooks::onUserRename'; // For the Renameuser extension
$wgHooks['CanonicalNamespaces'][] = 'PollNYHooks::onCanonicalNamespaces';
$wgHooks['BeforeCreateEchoEvent'][] = 'Poll::onBeforeCreateEchoEvent';
$wgHooks['EchoGetDefaultNotifiedUsers'][] = 'Poll::onEchoGetDefaultNotifiedUsers';

// ResourceLoader support for MediaWiki 1.17+
$wgResourceModules['ext.pollNY'] = array(
	'scripts' => 'Poll.js',
	'messages' => array(
		// PollPage.php
		'poll-open-message', 'poll-close-message', 'poll-flagged-message',
		'poll-finished',
		// SpecialAdminPoll.php
		'poll-open-message', 'poll-close-message', 'poll-flagged-message',
		'poll-delete-message', 'poll-js-action-complete',
		// SpecialCreatePoll.php / create-poll.tmpl.php
		'poll-createpoll-error-nomore', 'poll-upload-new-image',
		'poll-atleast', 'poll-enterquestion', 'poll-hash',
		'poll-pleasechoose',
	),
	'dependencies' => array(
		'ext.socialprofile.flash',
		'ext.socialprofile.LightBox'
	),
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'PollNY',
	'position' => 'bottom'
);

$wgResourceModules['ext.pollNY.css'] = array(
	'styles' => 'Poll.css',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'PollNY',
	'position' => 'top' // available since r85616
);