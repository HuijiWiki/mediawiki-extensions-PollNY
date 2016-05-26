<?php 
/**
 * First we clear memcached  object cache. 
 * Second we clear page cache
 * third we clear squid cache
 * TODO: clear CDN cache as an option.
 *
 */
class InvalidatePollCacheJob extends Job {
	public function __construct( $title, $params ) {
		// Replace synchroniseThreadArticleData with an identifier for your job.
		parent::__construct( 'invalidatePollCacheJob', $title, $params );
	}

	/**
	 * Execute the job
	 *
	 * @return bool
	 */
	public function run() {
		global $wgMemc;
		$key = $this->params['key'];
		// Kill internal cache
		$wgMemc->delete( $key );

		// Purge squid
		$pageTitle = $this->title;
		if ( is_object( $pageTitle ) ) {
			$pageTitle->invalidateCache();
			$pageTitle->purgeSquid();

			// Kill parser cache
			// $article = new Article( $pageTitle, /* oldid */0 );
			// $parserCache = ParserCache::singleton();
			// $parserKey = $parserCache->getKey( $article, User::newFromId($this->params['userid']) );
			// $wgMemc->delete( $parserKey );
		}
	}
}