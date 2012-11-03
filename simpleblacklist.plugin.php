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
				EventLog::log( "Comment by " . $comment->name . " automatically marked as spam because of the $reason.", 'info', 'Simple Blacklist', 'plugin' );
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
			if ( false !== strpos( strpos($comment->ip, '.') > 0 ? $comment->ip : long2ip( $comment->ip ), $item ) ) {
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
}
?>
