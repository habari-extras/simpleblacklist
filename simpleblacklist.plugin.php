<?php

class SimpleBlacklist extends Plugin
{
	public function filter_plugin_config( $actions, $plugin_id )
	{
		if ( $plugin_id == $this->plugin_id() ) {
			$actions[] = _t( 'Configure' );
		}
		
		return $actions;
	}

	public function action_plugin_ui( $plugin_id, $action )
	{
		if ( $plugin_id == $this->plugin_id() ) {
			switch ( $action ) {
				case _t('Configure') :
					$ui = new FormUI( strtolower( get_class( $this ) ) );
					$blacklist = $ui->append( 'textarea', 'blacklist', 'option:simpleblacklist__blacklist', _t( 'Items to blacklist (words, IP addresses, URLs, etc):' ) );
					$blacklist->rows = 8;
					$blacklist->class[] = 'resizable';
					$frequency = $ui->append('checkbox', 'frequency', 'option:simpleblacklist__frequency', _t( 'Bypass blacklist for frequent commenters:' ) );
					$ui->on_success( array( $this, 'updated_config' ) );
					$ui->append( 'submit', 'save', _t( 'Save' ) );
					$ui->out();
				break;
			}
		}
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
		// don't blacklist logged-in users: they can speak freely
		if ( User::identify()->loggedin ) { return true; }

		// and if the person has more than 5 comments approved,
		// they're likely not a spammer, so don't blacklist them
		$bypass = Options::get( 'simpleblacklist__frequency' );
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
			if ( false !== strpos( long2ip( $comment->ip ), $item ) ) {
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

	public function action_update_check()
	{
	 	Update::add( 'Simple Blacklist', '81648298-ecf8-4a0e-b8b7-7a33bab23b46', $this->info->version );
	}
}
?>
