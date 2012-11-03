<?php

class SimpleBlacklist extends Plugin
{
	public function configure()
	{
		$ui = new FormUI( strtolower( get_class( $this ) ) );
		$blacklist = $ui->append( 'textarea', 'blacklist', 'option:simpleblacklist__blacklist', _t( 'Items to blacklist (words, IP addresses, URLs, etc):' ) );
		$blacklist->rows = 8;
		$blacklist->class[] = 'resizable';
		$frequency = $ui->append('checkbox', 'frequency', 'option:simpleblacklist__frequency', _t( 'Bypass blacklist for frequent commenters:' ) );
		$keep = $ui->append('checkbox', 'keepcomments', 'option:simpleblacklist__keepcomments', _t( 'Keep comments (only mark them as spam):' ) );
		$ui->append('fieldset', 'learning', _t('Learning from spam', __CLASS__));
		$ui->learning->append('checkbox', 'blacklistauthor', 'option:simpleblacklist__blacklistauthor', _t( 'Auto-blacklist author' ) );
		$ui->learning->append('checkbox', 'blacklistmail', 'option:simpleblacklist__blacklistmail', _t( 'Auto-blacklist mail' ) );
		$ui->learning->append('checkbox', 'blacklisturl', 'option:simpleblacklist__blacklisturl', _t( 'Auto-blacklist url' ) );
		$ui->learning->append('checkbox', 'blacklistip', 'option:simpleblacklist__blacklistip', _t( 'Auto-blacklist ip' ) );
		$ui->on_success( array( $this, 'updated_config' ) );
		$ui->append( 'submit', 'save', _t( 'Save' ) );
		return $ui;
	}

	public function updated_config( FormUI $ui )
	{
		$blacklist = explode( "\n", $ui->blacklist->value );
		$blacklist = array_unique( $blacklist );
		natsort( $blacklist );
		$_POST[$ui->blacklist->field] =  implode( "\n", $blacklist );

		Session::notice( _t( 'Blacklist saved.' , 'simpleblacklist' ) );
		$ui->save();
	}

	public function filter_comment_insert_allow( $allow, $comment )
	{
		// Don't discard comments at all when the user disabled that (action_comment_insert_before will mark them as spam then)
		if(Options::get( 'simpleblacklist__keepcomments' )) { return true; }
		
		return $this->check_comment( $comment );
	}
	
	public function action_comment_insert_before ( $comment )
	{
		if( $comment->type == Comment::COMMENT && $comment->status != Comment::STATUS_SPAM)
		{
			if( $this->check_comment( $comment ) === false )
			{
				$comment->status = Comment::STATUS_SPAM;
				EventLog::log( "Comment by " . $comment->name . " automatically marked as spam", 'info', 'Simple Blacklist', 'plugin' );
			}
		}
		return $comment;
	}
	
	function check_comment( $comment )
	{
		// don't blacklist logged-in users: they can speak freely
		if ( User::identify()->loggedin ) { return true; }

		// and if the person has more than 5 comments approved,
		// they're likely not a spammer, so don't blacklist them
		$bypass = Options::get( 'simpleblacklist__frequency', false );
		if ( $bypass ) {
			$comments = Comments::get( array( 'email' => $comment->email,
			'name' => $comment->name, 
			'url' => $comment->url,
			'status' => Comment::STATUS_APPROVED )
			);
			if ( $comments->count >= 5 ) {
				return true;
			}
		}

		$allow = true;
		$reason = "";
		$blacklist = explode( "\n", Options::get( 'simpleblacklist__blacklist' ) );
		foreach ( $blacklist as $item ) {
			$item = trim( strtolower( $item ) );
			if ( '' == $item ) { continue; }
			// check against the commenter name
			if ( false !== strpos( strtolower( $comment->name ), $item ) ) {
				$allow = false;
			}
			// check against the commenter email
			if ( false !== strpos( strtolower( $comment->email ), $item ) ) {
				$allow = false;
			}
			// check against the commenter URL
			if ( false !== strpos( strtolower( $comment->url ), $item ) ) {
				$allow = false;
			}
			// check against the commenter IP address
			if ( (strpos($comment->ip, '.') > 0 ? $comment->ip : long2ip( $comment->ip )) == $item ) {
				$allow = false;
			}
			// now check the body of the comment
			if ( false !== strpos( strtolower( $comment->content ), $item ) ) {
				$allow = false;
			}
			if( $allow === false ) {
				 break;
			}
		}
		
		return $allow;
	}
	
	function action_comment_update_status ($comment, $status, $value)
	{
		// This is semi-optimal because field changes are always invoked so this applies to every comment and not only to comments marked as spam manually. Doesn't matter but costs resources. There's no other way, so do it.
		
		if( $value == Comment::STATUS_SPAM )
		{
			$blacklistauthor = Options::get( 'simpleblacklist__blacklistauthor', false );
			$blacklistmail = Options::get( 'simpleblacklist__blacklistmail', false );
			$blacklisturl = Options::get( 'simpleblacklist__blacklisturl', false );
			$blacklistip = Options::get( 'simpleblacklist__blacklistip', false );
			$blacklist = Options::get( 'simpleblacklist__blacklist', "");
			$newblacklist = explode( "\n", $blacklist );
			if ( $blacklistauthor ) {
				$newblacklist[] = $comment->author;
			}
			if ( $blacklistmail ) {
				$newblacklist[] = $comment->email;
			}
			if ( $blacklisturl ) {
				$newblacklist[] = $comment->url;
			}
			if ( $blacklistip ) {
				$newblacklist[] = (strpos($comment->ip, '.') > 0 ? $comment->ip : long2ip( $comment->ip ));
			}
			$newblacklist = array_unique( $newblacklist );
			natsort( $newblacklist );
			Options::set( 'simpleblacklist__blacklist', implode( "\n", $newblacklist ));
		}
	}
}
?>
